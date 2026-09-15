<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\KategoriAset;
use App\Models\Kendaraan;
use App\Models\MasterBarang;
use App\Models\PajakKendaraan;
use App\Models\Pegawai;
use App\Models\RiwayatPlat;
use App\Models\UsulanPenghapusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class KendaraanController extends Controller
{
    const KONDISI = ['Baik', 'Rusak Ringan', 'Rusak Berat'];
    const JENIS_KENDARAAN = ['Roda Dua (Sepeda Motor)', 'Roda Empat (Mobil)', 'Truk', 'Pick Up', 'Bus', 'Lainnya'];
    const STATUS_PLAT = ['Aktif', 'Tidak Aktif'];
    const KATEGORI_KENDARAAN = 'Kendaraan';

    /**
     * Daftar seluruh kendaraan beserta pemegang/penempatannya.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status'); // 'aktif' | 'nonaktif' | null

        $kendaraanList = Kendaraan::with([
            'aset.barang', 'aset.pemegangSaatIni.pegawai', 'aset.penempatanAktif.ruangan',
            'platAktif', 'pajakAktif',
        ])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('pemegang', 'like', "%{$search}%")
                        ->orWhere('nomor_rangka', 'like', "%{$search}%")
                        ->orWhere('nomor_mesin', 'like', "%{$search}%")
                        ->orWhere('jenis_kendaraan', 'like', "%{$search}%")
                        ->orWhereHas('aset', function ($a) use ($search) {
                            $a->where('nomor_kartu_barang', 'like', "%{$search}%")
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
     * Detail satu kendaraan: info + plat + pajak + riwayat izin.
     */
    public function show(Kendaraan $kendaraan)
    {
        $kendaraan->load([
            'aset.barang.kategori',
            'aset.pemegangSaatIni.pegawai',
            'aset.penempatanAktif.ruangan',
            'riwayatPlat' => fn ($q) => $q->orderByRaw("CASE WHEN status = 'Aktif' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->limit(3),
            'pajak' => fn ($q) => $q->orderByDesc('id_pajak'),
            'izin' => fn ($q) => $q->orderByDesc('id_izin'),
            'izin.pengaju',
            'izin.pengemudi',
        ]);

        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';
        $kondisiList = self::KONDISI;
        $jenisList = self::JENIS_KENDARAAN;
        $statusPlatList = self::STATUS_PLAT;
        $jenisPajakList = ['PKB', 'SWDKLLJ', 'Pajak Bumi', 'Lainnya'];
        $pengajuOptions = \App\Models\Pegawai::orderBy('nama_pegawai')->get(['id_pegawai', 'nama_pegawai']);
        $pengemudiOptions = \App\Models\Pegawai::orderBy('nama_pegawai')->get(['id_pegawai', 'nama_pegawai']);
        $pegawais = Pegawai::orderBy('nama_pegawai', 'asc')->get();

        return view('kendaraan.show', compact(
            'kendaraan', 'isAdminAset', 'kondisiList', 'jenisList',
            'statusPlatList', 'jenisPajakList', 'pengajuOptions', 'pengemudiOptions', 'pegawais'
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
            'jenis_kendaraan' => $this->cell($row, $colMap['jenis'] ?? '') ?: null,
            'nomor_rangka' => $this->cell($row, $colMap['rangka'] ?? '') ?: null,
            'nomor_mesin' => $this->cell($row, $colMap['mesin'] ?? '') ?: null,
            'merk' => $this->cell($row, $colMap['merk'] ?? '') ?: null,
            'tipe' => $this->cell($row, $colMap['tipe'] ?? '') ?: null,
            'pemegang' => $this->cell($row, $colMap['pemegang'] ?? '') ?: null,
            'keterangan' => $this->cell($row, $colMap['keterangan'] ?? '') ?: null,
        ]);

        $this->syncPlat($kendaraan, $request);
        $this->syncPajak($kendaraan, $request);

        return response()->json(['success' => true, 'message' => 'Kendaraan berhasil ditambahkan.']);
    }

    public function edit(Kendaraan $kendaraan)
    {
        $kendaraan->load('aset', 'platAktif', 'pajakAktif');

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
            // plat aktif
            'plat_aktif' => $kendaraan->platAktif?->id_plat ?? null,
            'plat_nomor' => $kendaraan->platAktif?->nomor_plat ?? '',
            'plat_tanggal' => $kendaraan->platAktif?->tanggal_berlaku?->format('Y-m-d'),
            'plat_ganti' => (bool) $kendaraan->platAktif?->ganti_plat,
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
            'pemegang' => $data['pemegang'] ?? null,
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        if ($request->hasFile('foto')) {
            if ($kendaraan->foto) {
                Storage::disk('public')->delete($kendaraan->foto);
            }
            $kendaraan->update(['foto' => $this->storeFoto($request)]);
        }

        $this->syncPlat($kendaraan, $request, $data);
        $this->syncPajak($kendaraan, $request, $data);

        return response()->json(['success' => true, 'message' => 'Kendaraan berhasil diperbarui.']);
    }

    public function destroy(Kendaraan $kendaraan)
    {
        $aset = $kendaraan->aset;

        if (!$aset) {
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

    // ---------- Riwayat Plat ----------

    public function storePlat(Request $request, Kendaraan $kendaraan)
    {
        $data = $request->validate([
            'nomor_plat' => ['required', 'string', 'max:20'],
            'tanggal_berlaku' => ['nullable', 'date'],
            'ganti_plat' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(self::STATUS_PLAT)],
        ]);

        if ($data['status'] === 'Aktif') {
            RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)
                ->where('status', 'Aktif')
                ->update(['status' => 'Tidak Aktif']);
        }

        RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => $data['nomor_plat'],
            'tanggal_berlaku' => $data['tanggal_berlaku'] ?? null,
            'ganti_plat' => $data['ganti_plat'] ?? false,
            'status' => $data['status'],
        ]);

        return response()->json(['success' => true, 'message' => 'Riwayat plat berhasil ditambahkan.']);
    }

    // ---------- Import ----------

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls', 'max:5120'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $errors = [];
        $created = 0;
        $photoWarnings = 0;

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $headerRow = (int) ($request->input('header_row', 2));
        if ($headerRow < 1) {
            $headerRow = 2;
        }

        // Petakan nama header -> indeks kolom (huruf), toleran terhadap urutan kolom.
        $colMap = $this->buildHeaderMap($rows[$headerRow] ?? []);

        // Kumpulkan foto dari objek gambar tersisip, kelompokkan per baris data.
        $photosByRow = $this->collectPhotos($sheet);

        foreach ($rows as $index => $row) {
            if ($index <= $headerRow) {
                continue;
            }
            $nama = $this->cell($row, $colMap['nama'] ?? '');
            if (!$nama) {
                continue;
            }

            try {
                $aset = Aset::create([
                    'id_barang' => $this->resolveBarang(trim($nama)),
                    'nomor_kartu_barang' => $this->nomorKartuFallback($this->cell($row, $colMap['kartu'] ?? '')),
                    'kondisi' => 'Baik',
                    'status_aset' => 'aktif',
                    'is_kendaraan' => true,
                ]);

                $kendaraan = Kendaraan::create([
                    'id_aset' => $aset->id_aset,
                    'jenis_kendaraan' => $this->cell($row, $colMap['jenis'] ?? '') ?: null,
                    'nomor_rangka' => $this->cell($row, $colMap['rangka'] ?? '') ?: null,
                    'nomor_mesin' => $this->cell($row, $colMap['mesin'] ?? '') ?: null,
                    'pemegang' => $this->cell($row, $colMap['pemegang'] ?? '') ?: null,
                    'keterangan' => $this->cell($row, $colMap['keterangan'] ?? '') ?: null,
                ]);

                // Foto
                $photoPath = $photosByRow[$index] ?? null;
                if ($photoPath) {
                    $kendaraan->update(['foto' => $photoPath]);
                }

                // Pajak
                $pajakDate = $this->cell($row, $colMap['pajak'] ?? '');
                $nominal = $this->cell($row, $colMap['nominal'] ?? '');
                $total = $this->cell($row, $colMap['total'] ?? '');
                if ($pajakDate || $nominal || $total) {
                    PajakKendaraan::create([
                        'id_kendaraan' => $kendaraan->id_kendaraan,
                        'jenis_pajak' => 'PKB',
                        'tanggal_berakhir' => $this->parseDate($pajakDate),
                        'nominal' => $this->toNumber($nominal),
                        'total_pajak' => $this->toNumber($total),
                        'pajak_5_tahunan' => $this->parseYaTidak($this->cell($row, $colMap['tahunan'] ?? '')),
                        'status' => 'Aktif',
                    ]);
                }

                // Plat: pertama = aktif, sisanya = riwayat tidak aktif
                $platRaw = $this->cell($row, $colMap['plat'] ?? '');
                $masaAktif = $this->parseDate($this->cell($row, $colMap['masa_aktif'] ?? ''));
                $gantiPlat = $this->parseYaTidak($this->cell($row, $colMap['ganti_plat'] ?? ''));
                if ($platRaw) {
                    $nomorList = $this->splitNomorPolisi($platRaw);
                    foreach ($nomorList as $i => $nomor) {
                        RiwayatPlat::create([
                            'id_kendaraan' => $kendaraan->id_kendaraan,
                            'nomor_plat' => $nomor,
                            'tanggal_berlaku' => $masaAktif,
                            'ganti_plat' => $i === 0 ? $gantiPlat : false,
                            'status' => $i === 0 ? 'Aktif' : 'Tidak Aktif',
                        ]);
                    }
                }

                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Baris {$index}: {$e->getMessage()}";
            }
        }

        $message = "Berhasil mengimpor {$created} kendaraan.";
        if ($photosByRow && count($photosByRow) > $created) {
            $photoWarnings = count($photosByRow) - $created;
            $message .= " {$photoWarnings} foto tidak dapat dipetakan ke baris data.";
        }
        if ($errors) {
            $message .= ' ' . count($errors) . ' baris gagal.';
        }

        return back()->with(
            $errors ? 'error' : 'success',
            $errors ? $message . ' -> ' . implode(' | ', $errors) : $message
        );
    }

    /**
     * Petakan nama header kolom -> huruf kolom, toleran terhadap urutan.
     */
    private function buildHeaderMap(array $headerRow): array
    {
      $aliases = [
           'nama' => [ 'jenis kendaraan','nama kendaraan','nama barang','kendaraan',],
            'merk' => ['merk', 'merek', 'brand'],
            'tipe' => ['tipe', 'type', 'model'],
            'jenis' => ['jenis', 'jenis kendaraan', 'jenis kend', 'roda', 'tipe kendaraan'],
            'kartu' => ['nomor kartu', 'no kartu', 'kartu barang', 'no. kartu', 'nomor kartu barang'],
            'pemegang' => ['pemegang', 'pemegang kendaraan', 'pemegang kend'],
            'rangka' => ['no rangka', 'nomor rangka', 'no. rangka', 'norangka'],
            'mesin' => ['no mesin', 'nomor mesin', 'no. mesin', 'nomesin'],
            'pajak' => ['pajak bulan', 'pajak jatuh tempo', 'jatuh tempo', 'pajak', 'pajak berakhir'],
            'nominal' => ['nominal pajak', 'nominal', 'nominal pkb'],
            'total' => ['total pajak', 'total', 'total pkb'],
            'tahunan' => ['pajak 5 tahunan', '5 tahunan', 'pajak lima tahunan'],
            'ganti_plat' => ['ganti plat', 'ganti plat iya tidak', 'ganti plat ya tidak'],
            'plat' => ['nomor polisi', 'no polisi', 'nopol', 'nomor plat', 'no. polisi', 'plat nomor'],
            'masa_aktif' => ['masa aktif', 'masa aktif nomor polisi', 'masa aktif nopol'],
            'keterangan' => ['keterangan', 'catatan', 'ket'],
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
                if (!$image) {
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

            if (!$bytes) {
                return null;
            }

            $name = 'kendaraan/' . Str::uuid()->toString() . '.' . $ext;
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

    private function syncPlat(Kendaraan $kendaraan, Request $request, ?array $data = null): void
    {
        $d = $data ?: $request->only(['plat_nomor', 'plat_tanggal', 'plat_ganti']);
        $nomor = $request->input('plat_nomor', $d['plat_nomor'] ?? '');
        if ($nomor !== '') {
            $platAktif = $kendaraan->platAktif;
            if ($platAktif) {
                $platAktif->update([
                    'nomor_plat' => $nomor,
                    'tanggal_berlaku' => $request->input('plat_tanggal', $d['plat_tanggal'] ?? null) ?: $platAktif->tanggal_berlaku,
                    'ganti_plat' => (bool) $request->input('plat_ganti', $d['plat_ganti'] ?? false),
                ]);
            } else {
                RiwayatPlat::create([
                    'id_kendaraan' => $kendaraan->id_kendaraan,
                    'nomor_plat' => $nomor,
                    'tanggal_berlaku' => $request->input('plat_tanggal', $d['plat_tanggal'] ?? null),
                    'ganti_plat' => (bool) $request->input('plat_ganti', $d['plat_ganti'] ?? false),
                    'status' => 'Aktif',
                ]);
            }
        }
    }

    private function syncPajak(Kendaraan $kendaraan, Request $request, ?array $data = null): void
    {
        $berakhir = $request->input('pajak_tanggal_berakhir', $data['pajak_tanggal_berakhir'] ?? null);
        if (!$berakhir) {
            return;
        }

        $pajakAktif = $kendaraan->pajakAktif;
        $payload = [
            'tanggal_berakhir' => $berakhir,
            'pajak_5_tahunan' => (bool) $request->input('pajak_max_tahunan', $data['pajak_max_tahunan'] ?? false),
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
        if (!$request->hasFile('foto')) {
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
            $candidate = 'KND-' . date('YmdHis') . random_int(10, 99);
        } while (\App\Models\Aset::where('nomor_kartu_barang', $candidate)->exists());
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
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'nomor_kartu_barang' => ['nullable', 'string', 'max:255'],
            'tanggal_pengadaan' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'tanggal_habis_pakai' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric', 'min:0'],
            'kondisi' => ['nullable', Rule::in(self::KONDISI)],
            'status_aset' => ['nullable', 'string', 'max:50'],
            'plat_nomor' => ['nullable', 'string', 'max:20'],
            'plat_tanggal' => ['nullable', 'date'],
            'plat_ganti' => ['nullable', 'boolean'],
            'pajak_tanggal_berakhir' => ['nullable', 'date'],
            'pajak_max_tahunan' => ['nullable', 'boolean'],
            'pajak_nominal' => ['nullable', 'numeric', 'min:0'],
            'pajak_total' => ['nullable', 'numeric', 'min:0'],
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

    private function cleanPlat(string $plat): string
    {
        // Ambil bagian pertama jika ada pemisah (> /)
        $first = preg_split('/[>\/]/', $plat)[0] ?? $plat;
        return trim(preg_replace('/\s+/', ' ', $first));
    }

    private function parseDate($value): ?string
    {
        if (!$value) {
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
            return \Illuminate\Support\Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function toNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = trim((string) $value);
        $s = str_replace(['Rp', ' '], '', $s);
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        if (!is_numeric($s)) {
            return null;
        }
        return (float) $s;
    }
}
