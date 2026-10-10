<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\KategoriAset;
use App\Models\Kendaraan;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\PajakKendaraan;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\RiwayatPlat;
use App\Models\UsulanPenghapusan;
use App\Services\AsetPenempatan;
use App\Services\AsetScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KendaraanController extends Controller
{
    public function __construct(private readonly AsetScope $scope)
    {
    }

    const KONDISI = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

    const JENIS_KENDARAAN = ['Roda Dua (Sepeda Motor)', 'Roda Empat (Mobil)', 'Truk', 'Pick Up', 'Bus', 'Lainnya'];

    const STATUS_PLAT = ['Aktif', 'Tidak Aktif'];

    const KATEGORI_KENDARAAN = 'Kendaraan';

    /**
     * Object-level check: kendaraan (via aset-nya) harus dalam cakupan
     * user. Route CUD/kelola sudah `role:Admin Aset`, tetapi dicek ulang
     * di sini agar tetap aman bila role diperluas atau route dipanggil
     * tanpa middleware — akses lintas unit via URL/id selalu 403.
     */
    private function authorizeKendaraan(Kendaraan $kendaraan, string $aksi = 'mengelola kendaraan'): void
    {
        abort_if(
            ! $kendaraan->aset || ! $this->scope->bolehAksesAset($kendaraan->aset),
            403,
            $this->scope->pesanAkses($aksi)
        );
    }

    /**
     * Daftar seluruh kendaraan beserta pemegang/penempatannya.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status'); // 'aktif' | 'nonaktif' | null

        $kendaraanList = Kendaraan::with([
            'aset.barang', 'aset.pemegangSaatIni.pegawai', 'aset.penempatanAktif.ruangan',
            'aset.skpd', 'aset.lokasi',
            'platAktif', 'pajakAktif',
        ])
            // Kendaraan adalah aset, jadi ikut cakupan lokasi fisik.
            ->whereHas('aset', fn ($a) => $a->tap(fn ($q) => $this->scope->terapkan($q)))
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('pemegang', 'like', "%{$search}%")
                        ->orWhere('nomor_rangka', 'like', "%{$search}%")
                        ->orWhere('nomor_mesin', 'like', "%{$search}%")
                        ->orWhere('jenis_kendaraan', 'like', "%{$search}%")
                        ->orWhere('merk', 'like', "%{$search}%")
                        ->orWhere('tipe', 'like', "%{$search}%")
                        // Nomor plat ada di riwayat_plat. Dicari ke seluruh riwayat
                        // (plat lama ikut ketemu) dan dinormalkan spasinya supaya
                        // "B1234ABC" tetap cocok dengan "B 1234 ABC".
                        ->orWhereHas('riwayatPlat', function ($p) use ($search) {
                            $tanpaSpasi = str_replace(' ', '', $search);
                            $p->where(function ($q) use ($search, $tanpaSpasi) {
                                $q->where('nomor_plat', 'like', "%{$search}%")
                                    ->orWhereRaw("REPLACE(nomor_plat, ' ', '') LIKE ?", ["%{$tanpaSpasi}%"]);
                            });
                        })
                        ->orWhereHas('aset', function ($a) use ($search) {
                            $a->where('nomor_kartu_barang', 'like', "%{$search}%")
                                ->orWhere('merk', 'like', "%{$search}%")
                                ->orWhereHas('barang', fn ($b) => $b->where('nama_barang', 'like', "%{$search}%"));
                        });
                });
            })
            ->when($status === 'aktif', fn ($q) => $q->whereHas('aset', fn ($a) => $a->where('status_aset', 'aktif')))
            ->when($status === 'nonaktif', fn ($q) => $q->whereHas('aset', fn ($a) => $a->where('status_aset', '!=', 'aktif')))
            ->orderByDesc('id_kendaraan')
            ->paginate(10)
            ->withQueryString();

        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';

        $pegawais = Pegawai::orderBy('nama_pegawai', 'asc')->get();

        return view('kendaraan.index', compact('kendaraanList', 'isAdminAset', 'pegawais'));
    }

    /**
     * Detail satu kendaraan: info aset + riwayat plat + pajak kendaraan.
     */
    public function show(Kendaraan $kendaraan)
    {
        $aset = $kendaraan->aset;

        abort_if(
            ! $aset || ! $this->scope->bolehAksesAset($aset),
            403,
            $this->scope->pesanAkses('melihat detail kendaraan')
        );

        $kendaraan->load([
            'aset.barang.kategori',
            'aset.pemegangSaatIni.pegawai',
            'aset.penempatanAktif.ruangan',
            'aset.skpd',
            'aset.lokasi',
            'riwayatPlat' => fn ($q) => $q->orderByRaw("CASE WHEN status = 'Aktif' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->limit(3),
            'pajak' => fn ($q) => $q->orderBy('tanggal_berakhir')->orderBy('id_pajak'),
        ]);

        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';
        $kondisiList = self::KONDISI;
        $jenisList = self::JENIS_KENDARAAN;
        $statusPlatList = self::STATUS_PLAT;
        $jenisPajakList = ['PKB', 'SWDKLLJ', 'Pajak Bumi', 'Lainnya'];
        $pegawais = Pegawai::orderBy('nama_pegawai', 'asc')->get();

        // Riwayat mutasi/ganti pemegang kendaraan ini (untuk tabel Riwayat Pemegang).
        $riwayatMutasi = DetailMutasiAset::with([
            'mutasi.userPenginput.pegawai', 'pegawaiLama', 'pegawaiBaru',
        ])
            ->where('id_aset', $kendaraan->id_aset)
            ->orderByDesc('id_detail')
            ->get();

        return view('kendaraan.show', compact(
            'kendaraan', 'isAdminAset', 'kondisiList', 'jenisList',
            'statusPlatList', 'jenisPajakList', 'pegawais',
            'riwayatMutasi'
        ));
    }

    public function create()
    {
        return response()->json([
            'jenisList' => self::JENIS_KENDARAAN,
            'kondisiList' => self::KONDISI,
        ]);
    }

    /**
     * Simpan kendaraan baru -> membuat row aset (is_kendaraan=1) + row kendaraan.
     */
    public function store(Request $request)
    {
        $data = $this->validateKendaraan($request);

        // Pemegang AWAL kendaraan baru (hanya saat tambah; edit tidak memakainya).
        // Bila nama cocok dengan master pegawai dipakai data pegawai tsb
        // (relasi PemegangAset) — sama seperti alur import — dan bila tidak
        // cocok, nama bebas tetap disimpan sebagai teks (tanpa membuat Pegawai).
        $pemegangAwal = trim((string) ($data['pemegang_awal'] ?? $data['pemegang'] ?? ''));
        $pegawaiPemegang = null;
        if ($pemegangAwal !== '') {
            $pegawaiPemegang = Pegawai::whereRaw('LOWER(nama_pegawai) = ?', [mb_strtolower($pemegangAwal)])->first();
        }

        $aset = Aset::create([
            'id_barang' => $this->resolveBarang($data['nama_kendaraan'] ?? ''),
            'nomor_kartu_barang' => $this->nomorKartuFallback($data['nomor_kartu_barang'] ?? null),
            'merk' => $data['merk'] ?? null,
            'tanggal_pengadaan' => $data['tanggal_pengadaan'] ?? null,
            'tanggal_perolehan' => $data['tanggal_perolehan'] ?? null,
            'tanggal_habis_pakai' => $data['tanggal_habis_pakai'] ?? null,
            'nilai_perolehan' => $data['nilai_perolehan'] ?? null,
            'kondisi' => $data['kondisi'] ?? null,
            'status_aset' => $data['status_aset'] ?? 'aktif',
            'is_kendaraan' => true,
        ]);

        $kendaraan = Kendaraan::create([
            'id_aset' => $aset->id_aset,
            'jenis_kendaraan' => $data['jenis_kendaraan'] ?? null,
            'nomor_rangka' => $data['nomor_rangka'] ?? null,
            'nomor_mesin' => $data['nomor_mesin'] ?? null,
            'merk' => $data['merk'] ?? null,
            'tipe' => $data['tipe'] ?? null,
            'pemegang' => $pegawaiPemegang?->nama_pegawai ?? ($pemegangAwal !== '' ? $pemegangAwal : null),
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        if (! empty($data['nomor_polisi'])) {
            RiwayatPlat::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'nomor_plat' => $data['nomor_polisi'],
                'tanggal_berlaku' => null,
                'ganti_plat' => false,
                'status' => 'Aktif',
            ]);
        }

        // Penetapan pemegang pertama BUKAN mutasi: tulis PemegangAset aktif dan
        // sinkronkan penempatan aset sesuai pola yang berlaku (lih. import &
        // AsetBarangController). Tidak membuat MutasiAset / DetailMutasiAset.
        if ($pegawaiPemegang) {
            PemegangAset::create([
                'id_aset' => $aset->id_aset,
                'id_pegawai' => $pegawaiPemegang->id_pegawai,
                'tanggal_mulai' => now()->toDateString(),
                'status' => 'aktif',
            ]);

            AsetPenempatan::sinkronAset($aset, $pegawaiPemegang->id_pegawai, $pegawaiPemegang->id_ruangan);
        }

        $this->syncPajak($kendaraan, $request);

        return response()->json(['success' => true, 'message' => 'Kendaraan berhasil ditambahkan.']);
    }

    public function edit(Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'mengubah kendaraan');

        $kendaraan->load('aset', 'pajakAktif', 'platAktif');

        return response()->json([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'id_aset' => $kendaraan->id_aset,
            'nama_kendaraan' => $kendaraan->aset?->barang?->nama_barang ?? '',
            'nomor_kartu_barang' => $kendaraan->aset?->nomor_kartu_barang,
            'jenis_kendaraan' => $kendaraan->jenis_kendaraan,
            'nomor_rangka' => $kendaraan->nomor_rangka,
            'nomor_mesin' => $kendaraan->nomor_mesin,
            'merk' => $kendaraan->aset?->merk,
            'tipe' => $kendaraan->tipe,
            'pemegang' => $kendaraan->pemegang,
            'keterangan' => $kendaraan->keterangan,
            'foto' => $kendaraan->foto,
            'tanggal_pengadaan' => $kendaraan->aset?->tanggal_pengadaan?->format('Y-m-d'),
            'tanggal_perolehan' => $kendaraan->aset?->tanggal_perolehan?->format('Y-m-d'),
            'tanggal_habis_pakai' => $kendaraan->aset?->tanggal_habis_pakai?->format('Y-m-d'),
            'nilai_perolehan' => $kendaraan->aset?->nilai_perolehan,
            'kondisi' => $kendaraan->aset?->kondisi,
            'status_aset' => $kendaraan->aset?->status_aset,
            'nomor_plat' => $kendaraan->platAktif?->nomor_plat ?? '',
            // pajak aktif
            'pajak_aktif' => $kendaraan->pajakAktif?->id_pajak ?? null,
            'pajak_tanggal_berakhir' => $kendaraan->pajakAktif?->tanggal_berakhir?->format('Y-m-d'),
            'pajak_max_tahunan' => (bool) $kendaraan->pajakAktif?->pajak_5_tahunan,
            'pajak_nominal' => $kendaraan->pajakAktif?->nominal,
            'pajak_total' => $kendaraan->pajakAktif?->total_pajak,
        ]);
    }

    public function update(Request $request, Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'mengubah kendaraan');

        $data = $this->validateKendaraan($request, $kendaraan);

        $aset = $kendaraan->aset;
        $aset->update([
            'id_barang' => $this->resolveBarang($data['nama_kendaraan'] ?? ''),
            'nomor_kartu_barang' => $data['nomor_kartu_barang'] ?? $aset->nomor_kartu_barang,
            'merk' => $data['merk'] ?? null,
            'tanggal_pengadaan' => $data['tanggal_pengadaan'] ?? null,
            'tanggal_perolehan' => $data['tanggal_perolehan'] ?? null,
            'tanggal_habis_pakai' => $data['tanggal_habis_pakai'] ?? null,
            'nilai_perolehan' => $data['nilai_perolehan'] ?? null,
            'kondisi' => $data['kondisi'] ?? null,
            'status_aset' => $data['status_aset'] ?? 'aktif',
            'is_kendaraan' => true,
        ]);

        $kendaraan->update([
            'jenis_kendaraan' => $data['jenis_kendaraan'] ?? null,
            'nomor_rangka' => $data['nomor_rangka'] ?? null,
            'nomor_mesin' => $data['nomor_mesin'] ?? null,
            'merk' => $data['merk'] ?? null,
            'tipe' => $data['tipe'] ?? null,
            // Pemegang sengaja TIDAK ikut diupdate dari request: pergantian
            // pemegang hanya sah melalui flow Mutasi (mutasiPemegang).
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        if ($request->hasFile('foto')) {
            if ($kendaraan->foto) {
                Storage::disk('public')->delete($kendaraan->foto);
            }
            $kendaraan->update(['foto' => $this->storeFoto($request)]);
        }

        // Nomor polisi: bila diubah dari plat aktif, tercatat sebagai pergantian
        // pelat di RiwayatPlat (plat lama dinonaktifkan, riwayat tetap tersimpan).
        if (! empty($data['nomor_polisi'])) {
            $platTerbaru = trim(mb_strtoupper($data['nomor_polisi']));
            $platAktif = $kendaraan->platAktif;
            if (! $platAktif || trim(mb_strtoupper($platAktif->nomor_plat)) !== $platTerbaru) {
                if ($platAktif) {
                    $platAktif->update(['status' => 'Tidak Aktif']);
                }
                RiwayatPlat::create([
                    'id_kendaraan' => $kendaraan->id_kendaraan,
                    'nomor_plat' => $data['nomor_polisi'],
                    'tanggal_berlaku' => null,
                    'ganti_plat' => false,
                    'status' => 'Aktif',
                ]);
            }
        }

        $this->syncPajak($kendaraan, $request, $data);

        return response()->json(['success' => true, 'message' => 'Kendaraan berhasil diperbarui.']);
    }

    public function destroy(Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'mengusulkan penghapusan kendaraan');

        $aset = $kendaraan->aset;

        if (! $aset) {
            return response()->json([
                'success' => false,
                'message' => 'Data aset kendaraan tidak ditemukan.',
            ], 422);
        }

        if ($aset->status_aset !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya kendaraan berstatus aktif yang dapat diusulkan penghapusan.',
            ], 422);
        }

        if (UsulanPenghapusan::where('id_aset', $aset->id_aset)->where('status_usulan', 'diajukan')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Kendaraan sudah memiliki usulan penghapusan yang aktif.',
            ], 422);
        }

        // Bukan hard delete (SOP BMD): kendaraan & aset tetap tersimpan,
        // hanya status aset berubah sehingga hilang dari daftar aktif.
        $aset->update(['status_aset' => 'diusulkan_hapus']);

        UsulanPenghapusan::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai_penghapus' => auth()->user()?->pegawai?->id_pegawai,
            'tanggal_usulan' => now()->toDateString(),
            'alasan_penghapusan' => 'Lainnya',
            'status_usulan' => 'diajukan',
            'keterangan' => 'Diusulkan melalui tombol hapus pada daftar kendaraan.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kendaraan diusulkan penghapusan dan dikeluarkan dari daftar aktif.',
        ]);
    }

    /**
     * Mutasi Pemegang Kendaraan: ganti pemegang kendaraan dinas ke pegawai lain.
     * Perbarui kolom `kendaraan.pemegang`, catat riwayat ke tabel
     * mutasi_aset + detail_mutasi_aset, lalu siapkan unduhan SPPKD & BAST.
     */
    public function mutasiPemegang(Request $request, Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'memutasi pemegang kendaraan');

        $data = $request->validate([
            'id_pegawai_baru' => ['required', 'exists:pegawai,id_pegawai'],
            'tanggal_mutasi' => ['nullable', 'date'],
            'nomor_surat' => ['nullable', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $aset = $kendaraan->aset;

        if (! $aset || $aset->status_aset !== 'aktif') {
            return back()->with('error', 'Mutasi pemegang hanya dapat dilakukan pada kendaraan berstatus aktif.')->withInput();
        }

        $pegawaiBaru = Pegawai::findOrFail($data['id_pegawai_baru']);

        // Pemegang lama disimpan sebagai teks bebas di kendaraan.pemegang;
        // petakan ke pegawai bila namanya cocok agar terlihat pada riwayat & BAST.
        $pegawaiLama = null;
        if ($kendaraan->pemegang) {
            $pegawaiLama = Pegawai::where('nama_pegawai', $kendaraan->pemegang)->first();
        }

        if ($pegawaiLama && $pegawaiLama->id_pegawai === $pegawaiBaru->id_pegawai) {
            return back()->with('error', 'Pemegang baru tidak boleh sama dengan pemegang lama.')->withInput();
        }

        $nomorSurat = trim($data['nomor_surat'] ?? '');
        $catatan = trim($data['keterangan'] ?? '');
        $keterangan = 'Serah terima dari '.($kendaraan->pemegang ?: 'Pemegang lama');
        if ($nomorSurat !== '') {
            $keterangan .= '. Nomor Surat: '.$nomorSurat;
        }
        if ($catatan !== '') {
            $keterangan .= '. '.$catatan;
        }

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => $data['tanggal_mutasi'] ?? now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => $keterangan,
            'id_user_penginput' => auth()->id(),
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $pegawaiLama?->id_pegawai,
            'pegawai_baru' => $pegawaiBaru->id_pegawai,
            'ruangan_lama' => null,
            'ruangan_baru' => null,
        ]);

        $kendaraan->update(['pemegang' => $pegawaiBaru->nama_pegawai]);

        // Simpan id mutasi untuk modal unduh dokumen di halaman index.
        session()->flash('mutasi_kendaraan_id', $mutasi->id_mutasi);

        return back()->with('success', 'Pemegang kendaraan berhasil diganti ke '.$pegawaiBaru->nama_pegawai.'.');
    }

    // ---------- Import ----------

    /**
     * Impor data kendaraan secara massal dari Excel.
     *
     * Aturan final (lihat handoff):
     * - Duplicate identity: nomor_rangka / nomor_mesin (bukan Nomor Polisi).
     * - Mode SKIP  -> tidak mengubah apa pun.
     * - Mode UPDATE -> hanya field master kendaraan; TIDAK boleh menyentuh
     *   PajakKendaraan dan RiwayatPlat. Pajak & plat kendaraan existing hanya
     *   berubah lewat flow Pajak Kendaraan (Pajak 5 Tahunan).
     * - Kendaraan BARU -> boleh membuat PajakKendaraan + Riwayat Plat awal.
     * - Satu row = satu DB::transaction; row gagal tidak meninggalkan sampah.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls', 'max:5120'],
            'mode' => ['required', 'in:skip,update'],
        ]);

        $mode = $request->input('mode');

        $errors = [];
        $warnings = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // Baris header = baris pertama yang dikenali minimal 3 header sah.
        // Mendeteksi otomatis (bukan input manual) agar template tahan
        // terhadap baris judul opsional di atas header.
        $headerRow = null;
        $colMap = [];
        foreach ($rows as $index => $row) {
            if ($index > 10) {
                break;
            }
            $map = $this->buildHeaderMap($row);
            if (count($map) >= 3) {
                $headerRow = $index;
                $colMap = $map;
                break;
            }
        }

        if ($headerRow === null) {
            return back()->with('error', 'Header kolom tidak ditemukan pada 10 baris pertama. Pastikan file sesuai template import kendaraan.');
        }

        // Kumpulkan foto dari objek gambar tersisip, kelompokkan per baris data.
        $photosByRow = $this->collectPhotos($sheet);

        foreach ($rows as $index => $row) {
            if ($index <= $headerRow) {
                continue;
            }

            // Lewati baris contoh bawaan template & baris kosong.
            if ($this->isContohRow($row) || ! $this->rowBerisi($row, $colMap)) {
                continue;
            }

            $nama = $this->cell($row, $colMap['nama'] ?? '');
            $rangka = $this->cell($row, $colMap['rangka'] ?? '');
            $mesin = $this->cell($row, $colMap['mesin'] ?? '');
            $platRaw = $this->cell($row, $colMap['plat'] ?? '');
            $pemegang = $this->cell($row, $colMap['pemegang'] ?? '');
            $jt1 = $this->cell($row, $colMap['pajak1'] ?? '');
            $jt5 = $this->cell($row, $colMap['pajak5'] ?? '');
            $nominal = $this->cell($row, $colMap['nominal'] ?? '');
            $total = $this->cell($row, $colMap['total'] ?? '');
            $flag5 = $this->parseYaTidak($this->cell($row, $colMap['tahunan'] ?? ''));

            // Kompatibilitas file template lama (satu kolom tanggal + flag):
            // tanggal tunggal diarahkan ke JT5 bila flag "Pajak 5 Tahunan" = Iya,
            // selainnya ke JT1.
            $legacyPajak = $this->cell($row, $colMap['pajak'] ?? '');
            if ($legacyPajak) {
                if ($flag5 && ! $jt5) {
                    $jt5 = $legacyPajak;
                } elseif (! $flag5 && ! $jt1) {
                    $jt1 = $legacyPajak;
                }
            }

            // Duplicate identity wajib: tanpa identitas, row tidak bisa
            // dideteksi sebagai duplikat pada import berikutnya.
            if (! $rangka && ! $mesin) {
                $errors[] = "Baris {$index}: No. Rangka dan No. Mesin keduanya kosong. Isi minimal salah satu agar duplikat dapat dideteksi.";
                continue;
            }

            // Pemegang adalah field master BEBAS (free text) — disimpan persis
            // seperti tertulis di Excel. Tidak pernah membuat Pegawai baru,
            // tidak pernah menebak scope. Jika teks tidak terpetakan ke master
            // pegawai, kendaraan TETAP diimpor; hanya penempatan otomatis yang
            // dilewati (aset fail-closed, id_lokasi NULL) dan admin diberi
            // peringatan per baris.
            $pegawai = null;
            if ($pemegang) {
                $pegawai = Pegawai::whereRaw('LOWER(nama_pegawai) = ?', [mb_strtolower(trim($pemegang))])->first();

                if (! $pegawai) {
                    $warnings[] = "Baris {$index}: Pemegang \"{$pemegang}\" tidak terdaftar di master pegawai. Disimpan sebagai teks; penempatan otomatis dilewati.";
                }
            }

            $identityQuery = Kendaraan::query()->where(function ($q) use ($rangka, $mesin) {
                if ($rangka) {
                    $q->orWhere('nomor_rangka', $rangka);
                }
                if ($mesin) {
                    $q->orWhere('nomor_mesin', $mesin);
                }
            });

            $existing = (clone $identityQuery)->first();

            // Mode UPDATE pada kendaraan existing: hanya field master.
            // Pajak & Riwayat Plat DILARANG disentuh di sini.
            if ($existing) {
                if ($mode === 'skip') {
                    $skipped++;
                    continue;
                }

                // Identitas konflik (rangka &/atau mesin mengarah ke >1 record):
                // jangan pilih record secara sembarang — baris ditolak.
                if ((clone $identityQuery)->count() > 1) {
                    $errors[] = "Baris {$index}: Identitas kendaraan konflik — No. Rangka dan/atau No. Mesin cocok dengan lebih dari satu kendaraan terdaftar. Periksa data master kendaraan.";
                    continue;
                }

                // Pemegang pada Excel wajib sama dengan pemegang terdaftar
                // (dibandingkan tanpa peka huruf & spasi luar). Beda = tolak:
                // pergantian pemegang hanya sah lewat menu Mutasi. Kolom kosong
                // tidak dianggap permintaan ganti/hapus (tetap mempertahankan
                // pemegang terdaftar; kontrak import lama).
                if ($pemegang !== null && ! $this->pemegangSama($pemegang, $existing->pemegang)) {
                    $errors[] = "Baris {$index}: Pemegang kendaraan berbeda dengan data yang terdaftar. Pergantian pemegang harus dilakukan melalui menu Mutasi.";
                    continue;
                }

                try {
                    DB::transaction(function () use ($existing, $row, $colMap, $photosByRow, $index, $rangka, $mesin, $pemegang, $nama) {
                        $existing->update([
                            'jenis_kendaraan' => $nama ?: $existing->jenis_kendaraan,
                            'nomor_rangka' => $rangka ?? $existing->nomor_rangka,
                            'nomor_mesin' => $mesin ?? $existing->nomor_mesin,
                            // Pemegang adalah field master bebas; sinkronisasi
                            // penempatan TIDAK dilakukan pada mode update karena
                            // pergantian pemegang sah lewat flow Mutasi.
                            'pemegang' => $pemegang ?? $existing->pemegang,
                        ]);

                        $photoPath = $photosByRow[$index] ?? null;
                        if ($photoPath && ! $existing->foto) {
                            $existing->update(['foto' => $photoPath]);
                        }
                    });
                    $updated++;
                } catch (\Throwable $e) {
                    $errors[] = "Baris {$index}: {$e->getMessage()}";
                }
                continue;
            }

            // ---------- Kendaraan BARU ----------
            if (! $nama) {
                $errors[] = "Baris {$index}: Jenis Kendaraan wajib diisi untuk kendaraan baru (dipakai sebagai nama master barang).";
                continue;
            }

            // Validasi pajak/plat awal kendaraan baru.
            // "Pajak 5 Tahunan = Iya" berarti sedang terjadi ganti plat, sehingga
            // JT5 dan Nomor Polisi wajib ada. Kedua kondisi tidak lengkap =
            // row ditolak (dilaporkan sebagai error baris).
            $jt1Date = $this->parseDate($jt1);
            $jt5Date = $this->parseDate($jt5);
            if ($flag5 && ! $jt5Date) {
                $errors[] = "Baris {$index}: Pajak 5 Tahunan = Iya tetapi Jatuh Tempo Pajak 5 Tahun kosong.";
                continue;
            }
            if ($flag5 && ! $platRaw) {
                $errors[] = "Baris {$index}: Pajak 5 Tahunan = Iya tetapi Nomor Polisi kosong (Riwayat Plat tidak dapat dibuat).";
                continue;
            }

            try {
                DB::transaction(function () use (
                    $nama, $rangka, $mesin, $platRaw, $pemegang, $pegawai,
                    $jt1Date, $jt5Date, $nominal, $total, $flag5,
                    $photosByRow, $index
                ) {
                    $aset = Aset::create([
                        'id_barang' => $this->resolveBarang(trim($nama)),
                        'nomor_kartu_barang' => $this->nomorKartuFallback(null),
                        'kondisi' => 'Baik',
                        'status_aset' => 'aktif',
                        'is_kendaraan' => true,
                    ]);

                    $kendaraan = Kendaraan::create([
                        'id_aset' => $aset->id_aset,
                        'jenis_kendaraan' => $nama,
                        'nomor_rangka' => $rangka,
                        'nomor_mesin' => $mesin,
                        'pemegang' => $pemegang,
                    ]);

                    $photoPath = $photosByRow[$index] ?? null;
                    if ($photoPath) {
                        $kendaraan->update(['foto' => $photoPath]);
                    }

                    // Penempatan diturunkan dari Pemegang -> Pegawai -> Ruangan.
                    // Hanya jika teks pemegang cocok dengan master pegawai.
                    // Jika tidak cocok, pemegang tetap disimpan apa adanya dan
                    // aset tetap fail-closed (id_lokasi NULL).
                    if ($pegawai) {
                        PemegangAset::create([
                            'id_aset' => $aset->id_aset,
                            'id_pegawai' => $pegawai->id_pegawai,
                            'tanggal_mulai' => now()->toDateString(),
                            'status' => 'aktif',
                        ]);

                        AsetPenempatan::sinkronAset($aset, $pegawai->id_pegawai, $pegawai->id_ruangan);
                    }

                    // Pajak awal kendaraan baru dari data STNK/Excel.
                    // JT1 dan JT5 direpresentasikan sebagai DUA baris pajak
                    // (schema existing mendukung multi-baris). Nominal & total
                    // hanya disimpan SEKALI, pada JT1 bila ada; bila hanya JT5
                    // yang terisi, JT5 menjadi baris aktif dan menerima nominal.
                    // Tanggal disimpan persis dari Excel (tanpa hitung otomatis).
                    $nominalVal = $this->toNumber($nominal);
                    $totalVal = $this->toNumber($total);

                    if ($jt1Date) {
                        PajakKendaraan::create([
                            'id_kendaraan' => $kendaraan->id_kendaraan,
                            'jenis_pajak' => 'PKB',
                            'tahun' => Carbon::parse($jt1Date)->year,
                            'tanggal_berakhir' => $jt1Date,
                            'nominal' => $nominalVal,
                            'total_pajak' => $totalVal,
                            'pajak_5_tahunan' => false,
                            'status' => 'Aktif',
                        ]);
                    }

                    if ($jt5Date) {
                        PajakKendaraan::create([
                            'id_kendaraan' => $kendaraan->id_kendaraan,
                            'jenis_pajak' => 'PKB',
                            'tahun' => Carbon::parse($jt5Date)->year,
                            'tanggal_berakhir' => $jt5Date,
                            'nominal' => $jt1Date ? null : $nominalVal,
                            'total_pajak' => $jt1Date ? null : $totalVal,
                            'pajak_5_tahunan' => true,
                            // Bila JT1 ada, JT1 yang Aktif (acuan dashboard);
                            // JT5 hanya informasi jatuh tempo 5 tahunan.
                            'status' => $jt1Date ? 'Tidak Aktif' : 'Aktif',
                        ]);
                    }

                    // Riwayat plat awal dibuat bila Nomor Polisi tersedia, agar
                    // kolom "Plat Aktif" & "Jatuh Tempo Plat (5 Tahunan)"
                    // (yang membaca platAktif->tanggal_berlaku) terisi.
                    // Nomor terakhir = plat Aktif, sebelumnya = Tidak Aktif;
                    // tanggal_berlaku mengikuti JT5 (jatuh tempo plat 5 tahunan).
                    // ganti_plat HANYA true bila "Pajak 5 Tahunan = Iya": berarti
                    // kejadian pergantian plat, sehingga menghapus pajak 5 tahunan
                    // merevert plat ini. Bila flag "Tidak", plat hanya data awal
                    // (bukan hasil pergantian) sehingga tidak direvert.
                    if ($platRaw) {
                        $nomorList = $this->splitNomorPolisi($platRaw);
                        $terakhir = array_key_last($nomorList);
                        foreach ($nomorList as $i => $nomor) {
                            RiwayatPlat::create([
                                'id_kendaraan' => $kendaraan->id_kendaraan,
                                'nomor_plat' => $nomor,
                                'tanggal_berlaku' => $jt5Date,
                                'ganti_plat' => $flag5 && $i === $terakhir,
                                'status' => $i === $terakhir ? 'Aktif' : 'Tidak Aktif',
                            ]);
                        }
                    }
                });
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Baris {$index}: {$e->getMessage()}";
            }
        }

        $photoWarnings = 0;
        if ($photosByRow && count($photosByRow) > ($created + $updated)) {
            $photoWarnings = count($photosByRow) - ($created + $updated);
        }

        $message = "Berhasil mengimpor {$created} kendaraan baru";
        if ($updated) {
            $message .= ", {$updated} diperbarui";
        }
        if ($skipped) {
            $message .= ", {$skipped} duplikat dilewati";
        }
        if ($photoWarnings) {
            $message .= ". {$photoWarnings} foto tidak dapat dipetakan ke baris data";
        }
        if ($errors) {
            $message .= '. '.count($errors).' baris gagal.';
        }
        if ($warnings) {
            $message .= '. Peringatan: '.implode(' | ', $warnings);
        }

        return back()->with(
            $errors ? 'error' : 'success',
            $errors ? $message.' -> '.implode(' | ', $errors) : $message
        );
    }

    /**
     * Unduh template Excel baku isian import kendaraan.
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import Kendaraan');

        $sheet->fromArray(self::KOLOM_TEMPLATE, null, 'A1');

        // Baris contoh (dilewati otomatis saat import).
        $sheet->fromArray([
            'CONTOH - Toyota Fortuner',
            'CONTOH - B 1234 ABC > B 5678 DEF',
            'CONTOH - MHKMJ1A200012345',
            'CONTOH - 1GRFE1234567',
            'Budi Santoso',
            '02/07/2027',
            '02/07/2031',
            2443500,
            2443500,
            'Tidak',
            null, // dokumentasi: gambar disisip, bukan teks
        ], null, 'A2');

        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static fn () => $writer->save('php://output'),
            'Template-Import-Kendaraan.xlsx'
        );
    }

    /**
     * Daftar kolom resmi template import kendaraan (berurutan).
     */
    private const KOLOM_TEMPLATE = [
        'Jenis Kendaraan',
        'Nomor Polisi',
        'No. Rangka',
        'No. Mesin',
        'Pemegang Kendaraan',
        'Jatuh Tempo Pajak 1 Tahun',
        'Jatuh Tempo Pajak 5 Tahun',
        'Nominal Pajak',
        'Total Pajak',
        'Pajak 5 Tahunan',
        'Dokumentasi',
    ];

    /**
     * Petakan nama header kolom -> huruf kolom, toleran terhadap urutan.
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $aliases = [
            'nama' => ['jenis kendaraan', 'nama kendaraan', 'nama barang', 'kendaraan', 'merk kendaraan', 'merk'],
            'plat' => ['nomor polisi', 'no polisi', 'nopol', 'nomor plat', 'no. polisi', 'plat nomor', 'plat'],
            'rangka' => ['no rangka', 'nomor rangka', 'no. rangka', 'norangka', 'nomor rangka kendaraan'],
            'mesin' => ['no mesin', 'nomor mesin', 'no. mesin', 'nomesin', 'nomor mesin kendaraan'],
            'pemegang' => ['pemegang kendaraan', 'pemegang', 'pemegang kend'],
            'pajak1' => ['jatuh tempo pajak 1 tahun', 'jatuh tempo 1 tahun', 'pajak 1 tahun', 'jatuh tempo pajak tahunan', 'jatuh tempo tahunan'],
            'pajak5' => ['jatuh tempo pajak 5 tahun', 'jatuh tempo 5 tahun', 'pajak 5 tahun'],
            // Alias template lama (satu kolom tanggal) — tetap dibaca demi
            // kompatibilitas file lama; diarahkan ke JT1/JT5 oleh import().
            'pajak' => ['tanggal jatuh tempo pajak', 'tanggal jatuh tempo', 'jatuh tempo pajak', 'jatuh tempo', 'pajak jatuh tempo', 'pajak berakhir', 'pajak'],
            'nominal' => ['nominal pajak', 'nominal', 'nominal pkb'],
            'total' => ['total pajak', 'total', 'total pkb'],
            'tahunan' => ['pajak 5 tahunan', '5 tahunan', 'pajak lima tahunan'],
        ];

        $map = [];
        foreach ($headerRow as $colLetter => $value) {
            $label = $this->normalizeHeader($value);
            if ($label === '') {
                continue;
            }
            foreach ($aliases as $field => $candidates) {
                foreach ($candidates as $alias) {
                    if ($this->normalizeHeader($alias) === $label) {
                        $map[$field] = $colLetter;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Baris contoh bawaan template (mengandung penanda CONTOH) dilewati.
     */
    private function isContohRow(array $row): bool
    {
        foreach ($row as $value) {
            if (str_contains(mb_strtolower((string) ($value ?? '')), 'contoh')) {
                return true;
            }
        }

        return false;
    }

    /**
     * True bila row memuat set satu nilai data yang dikenali (bukan kosong).
     */
    private function rowBerisi(array $row, array $colMap): bool
    {
        foreach ($colMap as $field => $letter) {
            if ($this->cell($row, $letter) !== null) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader($value): string
    {
        $s = trim((string) ($value ?? ''));
        $s = mb_strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim($s);
    }

    /**
     * Kumpulkan gambar tersisip, petakan ke nomor baris data berdasarkan posisi.
     * Mengembalikan [nomorBaris => relativePathFoto].
     */
    private function collectPhotos($sheet): array
    {
        $result = [];
        foreach ($sheet->getDrawingCollection() as $drawing) {
            $coordinates = $drawing->getCoordinates(); // mis. 'B3'
            $row = (int) preg_replace('/[^0-9]/', '', $coordinates);
            if ($row < 1) {
                continue;
            }
            $path = $this->saveDrawing($drawing);
            if ($path) {
                $result[$row] = $path;
            }
        }

        return $result;
    }

    private function saveDrawing($drawing): ?string
    {
        try {
            $ext = 'png';
            $bytes = null;

            if ($drawing instanceof MemoryDrawing) {
                $image = $drawing->getImageResource();
                if (! $image) {
                    return null;
                }
                $ext = strtolower($drawing->getMimeType()) === 'image/png' ? 'png' : 'jpg';
                ob_start();
                if ($ext === 'png') {
                    imagepng($image);
                } else {
                    imagejpeg($image, null, 90);
                }
                $bytes = ob_get_clean();
            } elseif ($drawing instanceof Drawing && $drawing->getPath()) {
                $bytes = @file_get_contents($drawing->getPath());
                $ext = strtolower($drawing->getExtension()) ?: 'png';
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
            }

            if (! $bytes) {
                return null;
            }

            $name = 'kendaraan/'.Str::uuid()->toString().'.'.$ext;
            Storage::disk('public')->put($name, $bytes);

            return $name;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseYaTidak($value): bool
    {
        if ($value === null) {
            return false;
        }
        $s = mb_strtolower(trim((string) $value));

        return in_array($s, ['ya', 'y', 'iya', 'yes', 'true', '1'], true);
    }

    /**
     * Dua nama pemegang dianggap sama bila identik setelah spasi luar dibuang
     * dan huruf dinormalkan (lowercase). Perbedaan selain itu (kapitalisasi
     * internal, tanda baca, kata tambahan) dianggap BERBEDA.
     */
    private function pemegangSama(?string $a, ?string $b): bool
    {
        return mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
    }

    private function splitNomorPolisi(string $plat): array
    {
        $parts = preg_split('/[>\/|]+/', $plat);
        $list = [];
        foreach ($parts as $p) {
            $clean = trim(preg_replace('/\s+/', ' ', $p));
            if ($clean !== '') {
                $list[] = $clean;
            }
        }

        return $list;
    }

    // ---------- Helpers ----------

    private function syncPajak(Kendaraan $kendaraan, Request $request, ?array $data = null): void
    {
        $perpanjang = (bool) $request->input('pajak_perpanjang', $data['pajak_perpanjang'] ?? false);
        if (! $perpanjang) {
            return;
        }

        // Tanggal jatuh tempo SELALU input manual Admin sesuai bukti/STNK -
        // disimpan persis, tanpa hitung otomatis. Tanpa tanggal, perpanjangan dilewati.
        $tanggalInput = $request->input('pajak_tanggal_berakhir', $data['pajak_tanggal_berakhir'] ?? null);
        if (empty($tanggalInput)) {
            return;
        }

        $limaTahunan = (bool) $request->input('pajak_max_tahunan', $data['pajak_max_tahunan'] ?? false);
        $berakhir = \Carbon\Carbon::parse($tanggalInput);

        $pajakAktif = $kendaraan->pajakAktif;
        $payload = [
            'tanggal_berakhir' => $berakhir,
            'pajak_5_tahunan' => $limaTahunan,
            'nominal' => $request->input('pajak_nominal', $data['pajak_nominal'] ?? null),
            'total_pajak' => $request->input('pajak_total', $data['pajak_total'] ?? null),
        ];

        if ($pajakAktif) {
            $pajakAktif->update($payload);
        } else {
            PajakKendaraan::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'jenis_pajak' => 'PKB',
                ...$payload,
                'status' => 'Aktif',
            ]);
        }
    }

    private function storeFoto(Request $request): ?string
    {
        if (! $request->hasFile('foto')) {
            return null;
        }

        return $request->file('foto')->store('kendaraan', 'public');
    }

    private function nomorKartuFallback($value): string
    {
        $s = trim((string) ($value ?? ''));
        if ($s !== '') {
            return $s;
        }
        do {
            $candidate = 'KND-'.date('YmdHis').random_int(10, 99);
        } while (Aset::where('nomor_kartu_barang', $candidate)->exists());

        return $candidate;
    }

    private function validateKendaraan(Request $request, ?Kendaraan $kendaraan = null): array
    {
        return $request->validate([
            'nama_kendaraan' => ['required', 'string', 'max:100'],
            'jenis_kendaraan' => ['nullable', 'string', 'max:50'],
            'nomor_rangka' => ['nullable', 'string', 'max:100'],
            'nomor_mesin' => ['nullable', 'string', 'max:100'],
            'merk' => ['nullable', 'string', 'max:100'],
            'tipe' => ['nullable', 'string', 'max:50'],
            'pemegang' => ['nullable', 'string', 'max:150'],
            'pemegang_awal' => ['nullable', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'nomor_kartu_barang' => ['nullable', 'string', 'max:255'],
            'tanggal_pengadaan' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'tanggal_habis_pakai' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric', 'min:0'],
            'kondisi' => ['nullable', Rule::in(self::KONDISI)],
            'status_aset' => ['nullable', 'string', 'max:50'],
            'nomor_polisi' => ['nullable', 'string', 'max:20'],
        ]);
    }

    /**
     * Cari master_barang berdasarkan nama; jika belum ada, buat baru dengan kategori Kendaraan.
     */
    private function resolveBarang(string $namaBarang): ?int
    {
        $namaBarang = trim($namaBarang);
        if ($namaBarang === '') {
            return null;
        }

        $barang = MasterBarang::whereRaw('LOWER(nama_barang) = ?', [mb_strtolower($namaBarang)])->first();
        if ($barang) {
            return $barang->id_barang;
        }

        $kategori = KategoriAset::firstOrCreate(['nama_kategori' => self::KATEGORI_KENDARAAN]);

        return MasterBarang::create([
            'nama_barang' => $namaBarang,
            'id_kategori' => $kategori->id_kategori,
        ])->id_barang;
    }

    private function cell(array $row, string $key): ?string
    {
        $val = $row[$key] ?? null;
        if ($val === null) {
            return null;
        }
        $s = trim((string) $val);

        return $s === '' ? null : $s;
    }

    private function parseDate($value): ?string
    {
        if (! $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function toNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Normalisasi format angka dari Excel. Sel numerik yang diformat
        // (mis. "#,##0") dihasilkan PhpSpreadsheet sebagai teks, sehingga
        // "2,443,500" dan "2.443.500" harus sama-sama dikenali. Hanya digit,
        // pemisah, dan tanda minus yang dipertahankan (otomatis membuang
        // "Rp" dan spasi).
        $s = preg_replace('/[^0-9,.\-]/', '', (string) $value);

        $hasDot = strpos($s, '.') !== false;
        $hasComma = strpos($s, ',') !== false;

        if ($hasDot && $hasComma) {
            // Pemisah terakhir = desimal; sisanya pemisah ribuan.
            if (strrpos($s, '.') > strrpos($s, ',')) {
                $s = str_replace(',', '', $s);
            } else {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            }
        } elseif ($hasComma) {
            $s = preg_match('/^-?\d{1,3}(,\d{3})+$/', $s)
                ? str_replace(',', '', $s)
                : str_replace(',', '.', $s);
        } elseif ($hasDot && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }
}
