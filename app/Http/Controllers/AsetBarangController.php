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
            ->when($pemegangId, function ($query, $pemegangId) {
                $query->whereHas('pemegangSaatIni', fn ($p) => $p->where('pemegang_aset.id_pegawai', $pemegangId));
            })
            ->orderByDesc('id_aset')
            ->paginate(10)
            ->withQueryString();

        // Dropdown filter pemegang: hanya pegawai yang sedang memegang aset.
        $pemegangOptions = Pegawai::whereHas('pemegangAset', fn ($q) => $q->where('status', 'aktif'))
            ->orderBy('nama_pegawai')
            ->get(['id_pegawai', 'nama_pegawai']);

        // Dropdown pemegang (Tambah/Edit): semua pegawai.
        $allPegawai = Pegawai::orderBy('nama_pegawai')->get(['id_pegawai', 'nama_pegawai']);
        $kondisiList = self::KONDISI;

        return view('aset-barang.admin-index', compact(
            'asetList',
            'pemegangOptions',
            'allPegawai',
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

        $this->createAsetFor($request, $pemegangId);

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil ditambahkan.',
        ]);
    }

    /**
     * Tambah aset dari tabel flat (tanpa pegawai di route).
     */
    public function storeFlat(Request $request)
    {
        $this->createAsetFor($request, $request->input('id_pegawai'));

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil ditambahkan.',
        ]);
    }

    private function createAsetFor(Request $request, int $pegawaiId): void
    {
        $data = $this->validateData($request);
        $data['id_barang'] = $this->resolveBarang($request->input('nama_barang'));

        $aset = Aset::create($data);

        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawaiId,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        $this->placeAtPegawaiRoom($aset, $pegawaiId);
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
     * Perbarui aset. Jika pemegang berubah -> catat mutasi & pindah ruangan otomatis.
     */
    public function update(Request $request, Aset $aset)
    {
        $data = $this->validateUpdateData($request, $aset);
        $data['id_barang'] = $this->resolveBarang($request->input('nama_barang'));

        $pemegangBaru = $request->input('id_pegawai');
        $pemegangLama = $aset->pemegangSaatIni?->pegawai?->id_pegawai;

        $aset->update($data);

        // Ganti pemegang = mutasi
        if ($pemegangBaru && $pemegangBaru != $pemegangLama) {
            $this->mutatePemegang($aset, $pemegangLama, $pemegangBaru, $request->input('keterangan'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil diperbarui.',
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
        $ruanganId = $pegawai?->id_ruangan;

        if (!$ruanganId) {
            return null;
        }

        // Tutup penempatan aktif lama
        PenempatanAset::where('id_aset', $aset->id_aset)
            ->where('status', 'aktif')
            ->update(['status' => 'tidak aktif', 'tanggal_selesai' => now()->toDateString()]);

        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruanganId,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        return $ruanganId;
    }

    private function validateData(Request $request, ?Aset $aset = null): array
    {
        $asetId = $aset?->id_aset;

        return $request->validate([
            'id_pegawai' => ['required', 'exists:pegawai,id_pegawai'],
            'nama_barang' => ['required', 'string', 'max:100'],
            'nomor_kartu_barang' => ['required', 'string', 'max:50', Rule::unique('aset', 'nomor_kartu_barang')->ignore($asetId, 'id_aset')],
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
        return $this->validateData($request, $aset);
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
