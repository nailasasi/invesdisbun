<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\InventarisRuangan;
use App\Models\RiwayatPenghapusan;
use App\Models\UsulanPenghapusan;
use App\Rules\FotoKameraValid;
use App\Services\AsetScope;
use App\Services\BuktiOtorisasi;
use App\Services\UsulanPenghapusanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PenghapusanAsetController extends Controller
{
    public function __construct(
        private readonly ?BuktiOtorisasi $bukti = null,
        private readonly ?AsetScope $scope = null,
        private readonly ?UsulanPenghapusanService $usulanService = null,
    ) {
    }

    private function scope(): AsetScope
    {
        return $this->scope ?? app(AsetScope::class);
    }

    private function usulan(): UsulanPenghapusanService
    {
        return $this->usulanService ?? app(UsulanPenghapusanService::class);
    }

    private function buktiService(): BuktiOtorisasi
    {
        return $this->bukti ?? app(BuktiOtorisasi::class);
    }

    /**
     * Daftar antrean usulan penghapusan (status aset: diusulkan_hapus).
     * Dibatasi oleh AsetScope: Admin Aset melihat global, Admin UPT hanya lokasi fisiknya.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $scope = $this->scope();

        $usulanList = UsulanPenghapusan::with([
                'aset.barang.kategori',
                'aset.kendaraan',
                'aset.penempatanAktif.ruangan',
                'aset.pemegangSaatIni.pegawai',
                'aset.skpd',
                'aset.lokasi',
                'inventaris.ruangan.lokasi',
                'inventaris.ruangan.skpd',
            ])
            ->where('status_usulan', 'diajukan')
            ->when(! $scope->cakupanPenuh(), function ($q) use ($scope) {
                $idLokasi = $scope->idLokasi() ?: [];
                $q->where(function ($sub) use ($scope, $idLokasi) {
                    $sub->whereHas('aset', fn ($a) => $scope->terapkan($a))
                        ->orWhereHas('inventaris.ruangan', function ($r) use ($scope, $idLokasi) {
                            if ($scope->isAdminUpt()) {
                                if (empty($idLokasi)) {
                                    $r->whereRaw('1 = 0');
                                } else {
                                    $r->whereIn('ruangan.id_lokasi', $idLokasi);
                                }
                            } elseif ($scope->isAdminBidang()) {
                                $r->where('ruangan.id_lokasi', 1);
                            }
                        });
                });
            })
            ->when($search !== '', fn ($q) => $q->where(function ($query) use ($search) {
                $query->whereHas('aset.barang', fn ($b) => $b->where('nama_barang', 'like', "%{$search}%"))
                    ->orWhereHas('aset.kendaraan', fn ($k) => $k->where('nama_kendaraan', 'like', "%{$search}%"))
                    ->orWhereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', 'like', "%{$search}%"))
                    ->orWhereHas('inventaris', fn ($inv) => $inv->where('nama_barang', 'like', "%{$search}%")
                        ->orWhere('kode_inventaris', 'like', "%{$search}%")
                        ->orWhere('merk', 'like', "%{$search}%"));
            }))
            ->orderByDesc('id_usulan_hapus')
            ->paginate(10)
            ->withQueryString();

        // Aset berstatus aktif yang belum punya usulan aktif, difilter sesuai scope untuk dropdown modal.
        $asetAktifList = Aset::with(['barang:id_barang,nama_barang', 'kendaraan:id_kendaraan,id_aset,nama_kendaraan,merk,tipe'])
            ->where('status_aset', 'aktif')
            ->when(! $scope->cakupanPenuh(), fn ($q) => $scope->terapkan($q))
            ->whereNotIn('id_aset', UsulanPenghapusan::where('status_usulan', 'diajukan')->whereNotNull('id_aset')->select('id_aset'))
            ->orderBy('id_aset')
            ->get(['id_aset', 'id_barang', 'id_lokasi', 'nomor_kartu_barang', 'is_kendaraan']);

        $totalRiwayat = Schema::hasTable('riwayat_penghapusan')
            ? RiwayatPenghapusan::when(! $scope->cakupanPenuh(), function ($q) use ($scope) {
                $idLokasi = $scope->idLokasi() ?: [];
                $q->where(function ($sub) use ($scope, $idLokasi) {
                    $sub->whereHas('aset', fn ($a) => $scope->terapkan($a))
                        ->orWhereHas('inventaris.ruangan', function ($r) use ($scope, $idLokasi) {
                            if ($scope->isAdminUpt()) {
                                if (empty($idLokasi)) {
                                    $r->whereRaw('1 = 0');
                                } else {
                                    $r->whereIn('ruangan.id_lokasi', $idLokasi);
                                }
                            } elseif ($scope->isAdminBidang()) {
                                $r->where('ruangan.id_lokasi', 1);
                            }
                        });
                });
            })->count()
            : 0;

        return view('penghapusan.index', compact('usulanList', 'asetAktifList', 'totalRiwayat'));
    }

    /**
     * Usulan penghapusan: aset masuk status "diusulkan_hapus" (keluar dari
     * daftar aktif), tanpa menghapus baris dari tabel aset.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'id_aset' => ['required', 'exists:aset,id_aset'],
            'alasan_penghapusan' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $aset = Aset::findOrFail($data['id_aset']);

        // Validasi otoritas scope lokasi pada backend (mencegah manipulasi parameter / IDOR)
        abort_unless(
            $this->scope()->bolehAksesAset($aset),
            403,
            'Anda tidak memiliki kewenangan untuk mengusulkan penghapusan aset di luar unit kerja Anda.'
        );

        if ($aset->status_aset !== 'aktif') {
            return back()->with('error', 'Hanya aset berstatus aktif yang dapat diusulkan penghapusan.');
        }

        if (UsulanPenghapusan::where('id_aset', $aset->id_aset)->where('status_usulan', 'diajukan')->exists()) {
            return back()->with('error', 'Aset sudah memiliki usulan penghapusan yang aktif.');
        }

        // Delegasikan pembuatan usulan ke UsulanPenghapusanService (atomik dengan concurrency lock)
        $jadikanRusakBerat = $data['alasan_penghapusan'] === UsulanPenghapusanService::KONDISI_RUSAK_BERAT;
        $sukses = $this->usulan()->usulkan(
            $aset,
            $data['alasan_penghapusan'],
            $data['keterangan'] ?? null,
            $jadikanRusakBerat
        );

        if (! $sukses) {
            return back()->with('error', 'Aset tidak dapat diusulkan atau sudah memiliki usulan aktif.');
        }

        return back()->with('success', 'Aset berhasil diusulkan penghapusan.');
    }

    /**
     * Batalkan usulan (klik salah oleh staf): aset/inventaris kembali aktif, baris usulan dihapus.
     */
    public function reaktifkan(UsulanPenghapusan $usulan)
    {
        $aset = $usulan->aset;
        $inventaris = $usulan->inventaris;

        DB::transaction(function () use ($aset, $inventaris, $usulan) {
            if ($aset) {
                $aset->update(['status_aset' => 'aktif']);
            } elseif ($inventaris) {
                $inventaris->update(['status' => InventarisRuangan::STATUS_AKTIF]);
            }

            $usulan->delete();
        });

        if ($inventaris) {
            return back()->with('success', 'Usulan dibatalkan dan inventaris lokal dikembalikan ke status aktif.');
        }

        if ($aset && $aset->kondisi === UsulanPenghapusanService::KONDISI_RUSAK_BERAT) {
            return back()->with(
                'warning',
                'Usulan dibatalkan dan aset kembali aktif. Kondisi aset masih "Rusak Berat" — perbarui kondisinya bila sudah diperbaiki, karena kondisi Rusak Berat akan mengusulkan penghapusan lagi.'
            );
        }

        return back()->with('success', 'Usulan dibatalkan dan aset dikembalikan ke status aktif.');
    }

    /**
     * Eksekusi Hapus Permanen dengan otorisasi kamera foto + password akun.
     * Mengubah status aset/inventaris ke "dihapuskan", menyetujui usulan dengan nomor & tanggal SK resmi,
     * dan mencatat riwayat penghapusan permanen secara atomik.
     */
    public function eksekusi(Request $request, UsulanPenghapusan $usulan)
    {
        // 1. Verifikasi kelayakan usulan dan aset/inventaris
        if ($usulan->status_usulan !== 'diajukan') {
            return back()->with('error', 'Usulan penghapusan ini sudah pernah diproses atau tidak lagi berstatus diajukan.');
        }

        $aset = $usulan->aset;
        $inventaris = $usulan->inventaris;

        if (! $aset && ! $inventaris) {
            return back()->with('error', 'Target aset atau inventaris yang diusulkan tidak ditemukan.');
        }

        if ($aset && $aset->status_aset !== 'diusulkan_hapus') {
            return back()->with('error', 'Status aset tidak valid untuk dieksekusi penghapusan permanen.');
        }

        if ($inventaris && $inventaris->status !== InventarisRuangan::STATUS_DIUSULKAN_HAPUS) {
            return back()->with('error', 'Status inventaris lokal tidak valid untuk dieksekusi penghapusan permanen.');
        }

        // 2. Validasi dokumen resmi SK + step-up otorisasi (password akun + foto kamera)
        $validated = $request->validate([
            'no_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date', 'before_or_equal:today'],
            'password_otorisasi' => ['required', 'current_password:web'],
            'foto_bukti_otentikasi' => ['required', 'string', new FotoKameraValid($this->buktiService())],
        ], [
            'no_sk.required' => 'Nomor SK penghapusan resmi wajib diisi.',
            'no_sk.max' => 'Nomor SK maksimal 100 karakter.',
            'tanggal_sk.required' => 'Tanggal SK penghapusan resmi wajib diisi.',
            'tanggal_sk.date' => 'Format tanggal SK tidak valid.',
            'tanggal_sk.before_or_equal' => 'Tanggal SK tidak boleh melebihi tanggal eksekusi hari ini.',
            'password_otorisasi.required' => 'Password otorisasi wajib diisi untuk konfirmasi.',
            'password_otorisasi.current_password' => 'Password otorisasi tidak cocok dengan akun Anda.',
            'foto_bukti_otentikasi.required' => 'Foto otorisasi melalui kamera wajib diambil.',
        ]);

        // 3. Verifikasi tabel riwayat harus tersedia; jangan mengubah aset tanpa catatan riwayat
        if (! Schema::hasTable('riwayat_penghapusan')) {
            return back()
                ->withInput($request->except(['password_otorisasi', 'foto_bukti_otentikasi']))
                ->with('error', 'Tabel riwayat_penghapusan belum tersedia di database. Jalankan migrasi terlebih dahulu sebelum melakukan penghapusan permanen.');
        }

        // 4. Simpan foto ke disk privat lebih dulu
        try {
            $fotoBukti = $this->buktiService()->simpan($request->input('foto_bukti_otentikasi'));
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->except(['password_otorisasi', 'foto_bukti_otentikasi']))
                ->withErrors(['foto_bukti_otentikasi' => 'Foto otorisasi gagal disimpan: '.$e->getMessage().'. Silakan ambil foto ulang.']);
        }

        // 5. Siapkan snapshot identitas aset atau inventaris lengkap saat eksekusi
        if ($aset) {
            $aset->loadMissing([
                'barang.kategori',
                'kendaraan',
                'penempatanAktif.ruangan',
                'pemegangSaatIni.pegawai',
                'skpd',
                'lokasi',
            ]);

            $snapshot = [
                'id_aset' => $aset->id_aset,
                'nomor_kartu_barang' => $aset->nomor_kartu_barang,
                'nama_aset' => $aset->kendaraan?->namaTampil() ?? $aset->barang?->nama_barang ?? 'Aset #'.$aset->id_aset,
                'kode_barang' => $aset->barang?->kode_barang ?? null,
                'kategori' => $aset->barang?->kategori?->nama_kategori ?? ($aset->is_kendaraan ? 'Kendaraan' : 'Barang'),
                'is_kendaraan' => (bool) $aset->is_kendaraan,
                'is_inventaris' => false,
                'merk' => $aset->merk,
                'nilai_perolehan' => (float) ($aset->nilai_perolehan ?? 0),
                'kondisi' => $aset->kondisi,
                'tanggal_pengadaan' => $aset->tanggal_pengadaan?->toDateString(),
                'skpd' => $aset->skpd?->nama_skpd,
                'lokasi' => $aset->lokasi?->nama_lokasi,
                'ruangan' => $aset->penempatanAktif?->ruangan?->nama_ruangan,
                'pemegang' => $aset->pemegangSaatIni?->pegawai?->nama_pegawai,
                'no_sk' => $validated['no_sk'],
                'tanggal_sk' => $validated['tanggal_sk'],
            ];
        } else {
            $inventaris->loadMissing(['ruangan.lokasi', 'ruangan.skpd']);

            $snapshot = [
                'id_inventaris' => $inventaris->id_inventaris,
                'nomor_kartu_barang' => $inventaris->kode_inventaris,
                'nama_aset' => $inventaris->nama_barang,
                'kode_barang' => $inventaris->kode_inventaris,
                'kategori' => 'Inventaris Lokal',
                'is_kendaraan' => false,
                'is_inventaris' => true,
                'merk' => $inventaris->merk ?: '—',
                'nilai_perolehan' => 0,
                'kondisi' => $inventaris->kondisi ?? 'Rusak Berat',
                'tanggal_pengadaan' => $inventaris->created_at?->toDateString(),
                'skpd' => $inventaris->ruangan?->skpd?->nama_skpd ?? '—',
                'lokasi' => $inventaris->ruangan?->lokasi?->nama_lokasi ?? '—',
                'ruangan' => $inventaris->ruangan?->nama_ruangan ?? '—',
                'pemegang' => 'Ruangan (Inventaris Lokal)',
                'no_sk' => $validated['no_sk'],
                'tanggal_sk' => $validated['tanggal_sk'],
            ];
        }

        $user = auth()->user();
        $namaAktor = $user?->pegawai?->nama_pegawai ?? $user?->username ?? 'Admin Aset';
        $waktuEksekusi = now();

        // 6. DB Transaction atomik dengan row lock untuk mencegah eksekusi ganda / race condition
        try {
            DB::transaction(function () use ($usulan, $aset, $inventaris, $snapshot, $fotoBukti, $user, $namaAktor, $validated, $waktuEksekusi) {
                $lockedUsulan = UsulanPenghapusan::whereKey($usulan->getKey())->lockForUpdate()->first();
                if (! $lockedUsulan || $lockedUsulan->status_usulan !== 'diajukan') {
                    throw new \RuntimeException('Usulan penghapusan ini sudah pernah diproses.');
                }

                // Ubah status aset/inventaris menjadi dihapuskan (tidak hard delete dari tabel)
                if ($aset) {
                    $aset->update(['status_aset' => 'dihapuskan']);
                } elseif ($inventaris) {
                    $inventaris->update(['status' => InventarisRuangan::STATUS_DIHAPUS]);
                }

                // Update usulan penghapusan menjadi disetujui dengan nomor & tanggal SK resmi
                $lockedUsulan->update([
                    'status_usulan' => 'disetujui',
                    'no_sk' => $validated['no_sk'],
                    'tanggal_sk' => $validated['tanggal_sk'],
                ]);

                // Catat riwayat penghapusan permanen
                RiwayatPenghapusan::create([
                    'id_usulan_hapus' => $lockedUsulan->id_usulan_hapus,
                    'id_aset' => $aset?->id_aset,
                    'id_inventaris' => $inventaris?->id_inventaris,
                    'id_user' => $user?->id_user,
                    'no_sk' => $validated['no_sk'],
                    'tanggal_sk' => $validated['tanggal_sk'],
                    'nama_pelaksana' => $namaAktor,
                    'waktu_penghapusan' => $waktuEksekusi,
                    'alasan_penghapusan' => $lockedUsulan->alasan_penghapusan ?? 'Penghapusan Permanen',
                    'keterangan' => $lockedUsulan->keterangan,
                    'foto_bukti' => $fotoBukti,
                    'snapshot_aset' => $snapshot,
                ]);
            });
        } catch (\Throwable $e) {
            // Kompensasi penghapusan file foto jika database gagal
            $this->buktiService()->hapus($fotoBukti);

            return back()
                ->withInput($request->except(['password_otorisasi', 'foto_bukti_otentikasi']))
                ->with('error', 'Gagal memproses penghapusan permanen: '.$e->getMessage());
        }

        $namaTarget = $snapshot['nama_aset'] ?? 'Aset/Inventaris';

        return redirect()
            ->route('penghapusan.index')
            ->with('success', "{$namaTarget} berhasil dihapus permanen dari inventaris aktif dengan SK No. {$validated['no_sk']} dan dicatat ke Riwayat Penghapusan.");
    }

    /**
     * Halaman daftar riwayat aset yang telah dihapus permanen.
     */
    public function riwayat(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $scope = $this->scope();

        $riwayatList = Schema::hasTable('riwayat_penghapusan')
            ? RiwayatPenghapusan::with(['aset', 'inventaris.ruangan.lokasi', 'user.pegawai'])
                ->when(! $scope->cakupanPenuh(), function ($q) use ($scope) {
                    $idLokasi = $scope->idLokasi() ?: [];
                    $q->where(function ($sub) use ($scope, $idLokasi) {
                        $sub->whereHas('aset', fn ($a) => $scope->terapkan($a))
                            ->orWhereHas('inventaris.ruangan', function ($r) use ($scope, $idLokasi) {
                                if ($scope->isAdminUpt()) {
                                    if (empty($idLokasi)) {
                                        $r->whereRaw('1 = 0');
                                    } else {
                                        $r->whereIn('ruangan.id_lokasi', $idLokasi);
                                    }
                                } elseif ($scope->isAdminBidang()) {
                                    $r->where('ruangan.id_lokasi', 1);
                                }
                            });
                    });
                })
                ->when($search !== '', function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('nama_pelaksana', 'like', "%{$search}%")
                            ->orWhere('no_sk', 'like', "%{$search}%")
                            ->orWhere('alasan_penghapusan', 'like', "%{$search}%")
                            ->orWhere('snapshot_aset', 'like', "%{$search}%")
                            ->orWhereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', 'like', "%{$search}%"))
                            ->orWhereHas('inventaris', fn ($inv) => $inv->where('kode_inventaris', 'like', "%{$search}%")
                                ->orWhere('nama_barang', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('waktu_penghapusan')
                ->paginate(10)
                ->withQueryString()
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);

        $totalAntrean = UsulanPenghapusan::where('status_usulan', 'diajukan')
            ->when(! $scope->cakupanPenuh(), function ($q) use ($scope) {
                $idLokasi = $scope->idLokasi() ?: [];
                $q->where(function ($sub) use ($scope, $idLokasi) {
                    $sub->whereHas('aset', fn ($a) => $scope->terapkan($a))
                        ->orWhereHas('inventaris.ruangan', function ($r) use ($scope, $idLokasi) {
                            if ($scope->isAdminUpt()) {
                                if (empty($idLokasi)) {
                                    $r->whereRaw('1 = 0');
                                } else {
                                    $r->whereIn('ruangan.id_lokasi', $idLokasi);
                                }
                            } elseif ($scope->isAdminBidang()) {
                                $r->where('ruangan.id_lokasi', 1);
                            }
                        });
                });
            })
            ->count();

        return view('penghapusan.riwayat', compact('riwayatList', 'totalAntrean'));
    }

    /**
     * Sajikan file foto otorisasi dari disk privat secara aman.
     */
    public function showBukti(RiwayatPenghapusan $riwayat)
    {
        $path = $riwayat->foto_bukti;

        abort_if(blank($path), 404, 'Bukti otorisasi tidak ditemukan.');

        $disk = Storage::disk(BuktiOtorisasi::DISK);

        abort_unless($disk->exists($path), 404, 'File bukti foto tidak ditemukan pada server.');

        return $disk->response($path);
    }
}
