<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\KategoriAset;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AsetBarangController extends Controller
{
    const KONDISI = ['Baik', 'Rusak Ringan', 'Rusak Berat'];
    const KATEGORI_MESIN = 'Peralatan dan Mesin';

    /**
     * Role-aware index:
     *  - Admin Aset  -> tabel flat seluruh aset (kolom Pemegang, aksi, filter).
     *  - Non-admin   -> daftar pegawai (grouped) lalu lihat aset per orang.
     */
    public function index(Request $request)
    {
        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';

        if ($isAdminAset) {
            return $this->adminIndex($request);
        }

        $search = $request->query('search');

        $pegawaiList = Pegawai::with('skpd')
            ->withCount(['pemegangAset as jumlah_aset' => fn ($q) => $q->where('status', 'aktif')])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_pegawai', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_pegawai')
            ->paginate(10)
            ->withQueryString();

        return view('aset-barang.index', compact('pegawaiList', 'isAdminAset'));
    }

    /**
     * Tabel flat seluruh aset untuk Admin Aset.
     */
    private function adminIndex(Request $request)
    {
        $search = $request->query('search');
        $penempatan = $request->query('penempatan'); // 'pemegang' | 'tanpa_pemegang' | null (semua)
        $pemegangId = $request->query('pemegang');

        $asetList = Aset::with(['barang', 'pemegangSaatIni.pegawai', 'penempatanAktif.ruangan'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nomor_kartu_barang', 'like', "%{$search}%")
                        ->orWhere('merk', 'like', "%{$search}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama_barang', 'like', "%{$search}%"))
                        ->orWhereHas('pemegangSaatIni.pegawai', fn ($p) => $p->where('nama_pegawai', 'like', "%{$search}%"));
                });
            })
            ->when($penempatan === 'pemegang', function ($query) {
                $query->whereHas('pemegangSaatIni');
            })
            ->when($penempatan === 'tanpa_pemegang', function ($query) {
                $query->whereDoesntHave('pemegangSaatIni');
            })
            ->when($pemegangId, function ($query, $pemegangId) {
                $query->whereHas('pemegangSaatIni', fn ($p) => $p->where('pemegang_aset.id_pegawai', $pemegangId));
            })
            ->orderByDesc('id_aset')
            ->paginate(10)
            ->withQueryString();

        // Dropdown filter pemegang: pegawai yang saat ini memegang aset.
        $pemegangOptions = Pegawai::whereHas('pemegangAset', fn ($q) => $q->where('status', 'aktif'))
            ->orderBy('nama_pegawai')
            ->get(['id_pegawai', 'nama_pegawai']);

        // Dropdown pemegang (Tambah/Edit): semua pegawai.
        $allPegawai = Pegawai::orderBy('nama_pegawai')->get(['id_pegawai', 'nama_pegawai', 'id_ruangan']);

        // Dropdown ruangan (Tambah/Edit Aset): semua ruangan (opsional).
        $ruanganOptions = Ruangan::orderBy('nama_ruangan')->get(['id_ruangan', 'nama_ruangan']);

        $kondisiList = self::KONDISI;

        return view('aset-barang.admin-index', compact(
            'asetList',
            'pemegangOptions',
            'allPegawai',
            'ruanganOptions',
            'kondisiList'
        ));
    }

    /**
     * Detail per orang: aset barang yang sedang dipegang (`pemegang_aset` aktif).
     */
    public function show(Pegawai $pegawai)
    {
        $pegawai->load('skpd');

        $asetList = PemegangAset::with('aset.barang.kategori')
            ->where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->orderByDesc('id_pemegang')
            ->get();

        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';
        $kondisiList = self::KONDISI;
        $allPegawai = Pegawai::orderBy('nama_pegawai')->get(['id_pegawai', 'nama_pegawai']);

        return view('aset-barang.show', compact('pegawai', 'asetList', 'kondisiList', 'allPegawai', 'isAdminAset'));
    }

    /**
     * Tambah aset baru. Pemegang diambil dari request `id_pegawai`
     * (dropdown pada tabel flat) atau default pegawai route.
     */
    public function store(Request $request, Pegawai $pegawai)
    {
        $pemegangId = $request->input('id_pegawai') ?: $pegawai->id_pegawai;

        $warning = $this->createAsetFor($request, $pemegangId);

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil ditambahkan.',
            'warning' => $warning,
        ]);
    }

    /**
     * Tambah aset dari tabel flat (tanpa pegawai di route).
     */
    public function storeFlat(Request $request)
    {
        $warning = $this->createAsetFor($request, $request->input('id_pegawai'));

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil ditambahkan.',
            'warning' => $warning,
        ]);
    }

    private function createAsetFor(Request $request, ?int $pegawaiId): ?string
    {
        $data = $this->validateData($request);
        $data['id_barang'] = $this->resolveBarang($request->input('nama_barang'));

        $aset = Aset::create($data);

        // Alur manual: aset melekat langsung ke ruangan, tanpa pemegang.
        if (!$pegawaiId) {
            $this->placeAtRoom($aset, $request->input('id_ruangan'));
            return null;
        }

        // Alur otomatis: aset dipegang pegawai, mengikuti ruangan kerja pegawai.
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawaiId,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        $this->placeAtPegawaiRoom($aset, $pegawaiId);

        $penempatanAktif = PenempatanAset::where('id_aset', $aset->id_aset)->where('status', 'aktif')->exists();
        if (!$penempatanAktif) {
            return 'Pegawai belum punya ruangan. Aset akan tampil tanpa ruangan sampai ruangan pegawai diisi.';
        }

        return null;
    }

    /**
     * Tempatkan aset secara manual ke ruangan tertentu (tanpa pemegang).
     */
    private function placeAtRoom(Aset $aset, ?int $ruanganId): ?int
    {
        return $aset->placeAtRoom($ruanganId);
    }

    /**
     * Data aset sebagai JSON untuk modal edit.
     */
    public function showAset(Aset $aset)
    {
        $pemegang = $aset->pemegangSaatIni;

        return response()->json([
            'id_aset' => $aset->id_aset,
            'id_barang' => $aset->id_barang,
            'nama_barang' => $aset->barang?->nama_barang ?? '',
            'id_pegawai' => $pemegang?->id_pegawai ?? null,
            'nama_pemegang' => $pemegang?->pegawai?->nama_pegawai ?? '',
            'id_ruangan' => $aset->penempatanAktif?->id_ruangan ?? null,
            'nomor_kartu_barang' => $aset->nomor_kartu_barang,
            'merk' => $aset->merk,
            'tanggal_pengadaan' => $aset->tanggal_pengadaan?->format('Y-m-d'),
            'tanggal_perolehan' => $aset->tanggal_perolehan?->format('Y-m-d'),
            'tanggal_habis_pakai' => $aset->tanggal_habis_pakai?->format('Y-m-d'),
            'nilai_perolehan' => $aset->nilai_perolehan,
            'kondisi' => $aset->kondisi,
            'status_aset' => $aset->status_aset,
        ]);
    }

    /**
     * Detail aset (read-only): informasi lengkap + riwayat mutasi per barang.
     */
    public function detailAset(Aset $aset)
    {
        $aset->load([
            'barang.kategori',
            'pemegangSaatIni.pegawai.skpd',
            'penempatanAktif.ruangan.skpd',
        ]);

        $riwayatMutasi = $aset->mutasiDetails()
            ->with([
                'mutasi.userPenginput.pegawai',
                'mutasi.userPenginput.role',
                'pegawaiLama',
                'pegawaiBaru',
                'ruanganLama',
                'ruanganBaru',
            ])
            ->orderByDesc('id_detail')
            ->get();

        return view('aset-barang.detail', compact('aset', 'riwayatMutasi'));
    }

    /**
     * Perbarui data aset (tanpa pemegang/ruangan).
     * Ganti pemegang / pindah ruangan dilakukan via aksi "Mutasi" terpisah.
     */
    public function update(Request $request, Aset $aset)
    {
        $data = $this->validateUpdateData($request, $aset);
        $data['id_barang'] = $this->resolveBarang($request->input('nama_barang'));

        $aset->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil diperbarui.',
        ]);
    }

    /**
     * Aksi mutasi terpisah: Ganti Pemegang (`tipe=pegawai`) atau
     * Pindah Ruangan (`tipe=ruangan`). Setiap perubahan tercatat di
     * tabel mutasi_aset + detail_mutasi_aset.
     */
    public function mutasi(Request $request, Aset $aset)
    {
        $data = $request->validate([
            'tipe' => ['required', Rule::in(['pegawai', 'ruangan'])],
            'id_pegawai' => ['nullable', 'exists:pegawai,id_pegawai'],
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['tipe'] === 'pegawai') {
            $pemegangBaru = $data['id_pegawai'] ?? null;
            if (!$pemegangBaru) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'id_pegawai' => 'Pilih pemegang baru untuk aset ini.',
                ]);
            }

            $pemegangLama = $aset->pemegangSaatIni?->pegawai?->id_pegawai;
            $this->mutatePemegang($aset, $pemegangLama, $pemegangBaru, $data['keterangan'] ?? null);

            $warning = null;
            $penempatanAktif = PenempatanAset::where('id_aset', $aset->id_aset)->where('status', 'aktif')->exists();
            if (!$penempatanAktif) {
                $warning = 'Pegawai belum punya ruangan. Aset akan tampil tanpa ruangan sampai ruangan pegawai diisi.';
            }

            return response()->json([
                'success' => true,
                'message' => 'Pemegang aset berhasil diganti.',
                'warning' => $warning,
            ]);
        }

        // tipe = ruangan (Pindah Ruangan)
        if ($aset->pemegangSaatIni) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tipe' => 'Aset ber-pemegang selalu mengikuti ruangan kerja pegawainya. Gunakan mode Ganti Pemegang.',
            ]);
        }

        $ruanganBaru = $data['id_ruangan'] ?? null;
        if (!$ruanganBaru) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id_ruangan' => 'Pilih ruangan tujuan.',
            ]);
        }

        $ruanganLama = $aset->penempatanAktif?->id_ruangan;
        $this->placeAtRoom($aset, $ruanganBaru);

        if ($ruanganLama == $ruanganBaru) {
            return response()->json([
                'success' => true,
                'message' => 'Aset sudah berada di ruangan tersebut.',
            ]);
        }

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => $data['keterangan'] ?? null,
            'id_user_penginput' => auth()->id(),
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => null,
            'pegawai_baru' => null,
            'ruangan_lama' => $ruanganLama,
            'ruangan_baru' => $ruanganBaru,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil dipindah ruangan.',
        ]);
    }

    /**
     * Hapus aset (pemegang_aset ikut terhapus via cascade).
     */
    public function destroy(Aset $aset)
    {
        $aset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil dihapus.',
        ]);
    }

    /**
     * Tutup pemegang lama, buka pemegang baru, pindah ruangan, catat riwayat mutasi.
     */
    private function mutatePemegang(Aset $aset, $pemegangLamaId, $pemegangBaruId, ?string $keterangan): void
    {
        $now = now()->toDateString();

        // Tutup pemegang lama
        PemegangAset::where('id_aset', $aset->id_aset)
            ->where('status', 'aktif')
            ->update(['status' => 'tidak aktif', 'tanggal_selesai' => $now]);

        // Buka pemegang baru
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pemegangBaruId,
            'tanggal_mulai' => $now,
            'status' => 'aktif',
        ]);

        // Ruangan lama -> baru (otomatis ikut ruangan kerja pemegang baru)
        $ruanganLama = $aset->penempatanAktif?->id_ruangan;
        $ruanganBaru = $this->placeAtPegawaiRoom($aset, $pemegangBaruId);
        $ruanganBaru = $ruanganBaru?: $ruanganLama;

        // Catat mutasi sebagai riwayat
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => $now,
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => $keterangan,
            'id_user_penginput' => auth()->id(),
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $pemegangLamaId,
            'pegawai_baru' => $pemegangBaruId,
            'ruangan_lama' => $ruanganLama,
            'ruangan_baru' => $ruanganBaru,
        ]);
    }

    /**
     * Tempatkan aset di ruangan kerja utama pegawai. Kembalikan id_ruangan (nullable).
     */
    private function placeAtPegawaiRoom(Aset $aset, int $pegawaiId): ?int
    {
        $pegawai = Pegawai::find($pegawaiId);
        return $aset->placeAtRoom($pegawai?->id_ruangan);
    }

    private function validateData(Request $request, ?Aset $aset = null): array
    {
        $asetId = $aset?->id_aset;

        return $request->validate([
            'id_pegawai' => ['nullable', 'exists:pegawai,id_pegawai'],
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
            'nama_barang' => ['required', 'string', 'max:100'],
            'nomor_kartu_barang' => ['required', 'string', 'max:255', Rule::unique('aset', 'nomor_kartu_barang')->ignore($asetId, 'id_aset')],
            'merk' => ['nullable', 'string', 'max:100'],
            'tanggal_pengadaan' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'tanggal_habis_pakai' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'status_aset' => ['required', 'string', 'max:50'],
        ]);
    }

    private function validateUpdateData(Request $request, ?Aset $aset = null): array
    {
        $asetId = $aset?->id_aset;

        $rules = [
            'nama_barang' => ['required', 'string', 'max:100'],
            'nomor_kartu_barang' => ['required', 'string', 'max:255', Rule::unique('aset', 'nomor_kartu_barang')->ignore($asetId, 'id_aset')],
            'merk' => ['nullable', 'string', 'max:100'],
            'tanggal_pengadaan' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'tanggal_habis_pakai' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'status_aset' => ['required', 'string', 'max:50'],
        ];

        $data = $request->validate($rules);

        return $data;
    }

    /**
     * Cari master_barang berdasarkan nama; jika belum ada, buat baru
     * dengan kategori "Peralatan dan Mesin" (hard-coded).
     */
    private function resolveBarang(string $namaBarang): ?int
    {
        $namaBarang = trim($namaBarang);

        $barang = MasterBarang::whereRaw('LOWER(nama_barang) = ?', [mb_strtolower($namaBarang)])->first();

        if ($barang) {
            return $barang->id_barang;
        }

        $kategori = KategoriAset::firstOrCreate(['nama_kategori' => self::KATEGORI_MESIN]);

        $barang = MasterBarang::create([
            'nama_barang' => $namaBarang,
            'id_kategori' => $kategori->id_kategori,
        ]);

        return $barang->id_barang;
    }
}
