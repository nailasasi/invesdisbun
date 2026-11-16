<?php

namespace App\Http\Controllers;

use App\Models\MutasiAset;
use App\Models\Kendaraan;
use App\Models\DetailMutasiAset;
use App\Models\Pegawai;
use App\Models\TemplateDokumen;
use App\Services\AsetScope;
use App\Support\Word\WordTemplateFiller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use PhpOffice\PhpWord\TemplateProcessor;

class MutasiAsetController extends Controller
{
    public function __construct(
        private readonly ?AsetScope $scope = null,
    ) {
    }

    private function scope(): AsetScope
    {
        return $this->scope ?? app(AsetScope::class);
    }

    /**
     * Riwayat mutasi aset (read-only) untuk Admin Aset dan Admin UPT.
     * Admin Aset memiliki akses global; Admin UPT dibatasi mutasi historis yang melibatkan lokasi UPT miliknya.
     */
    public function index(Request $request)
    {
        $scope = $this->scope();

        // Kategori: Admin Aset & Admin UPT dapat mengakses tab barang maupun kendaraan (dengan batasan scope UPT).
        $kategori = in_array($request->query('kategori'), ['barang', 'kendaraan'], true)
            ? $request->query('kategori')
            : 'barang';

        // Validasi input filter & pencarian
        $validator = Validator::make($request->all(), [
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['nullable', 'date_format:Y-m-d'],
            'jenis_mutasi' => ['nullable', 'string', 'in:Pindah Ruangan,Ganti Pemegang'],
        ], [
            'tanggal_mulai.date_format' => 'Format tanggal mulai tidak valid (harus YYYY-MM-DD).',
            'tanggal_akhir.date_format' => 'Format tanggal akhir tidak valid (harus YYYY-MM-DD).',
            'jenis_mutasi.in' => 'Pilihan jenis mutasi tidak valid.',
        ]);

        $validator->after(function ($v) use ($request) {
            $mulai = $request->query('tanggal_mulai');
            $akhir = $request->query('tanggal_akhir');
            if ($mulai && $akhir && $mulai > $akhir) {
                $v->errors()->add('tanggal_akhir', 'Tanggal akhir tidak boleh lebih awal daripada tanggal mulai.');
            }
        });

        $hasDateErrors = false;
        if ($validator->fails()) {
            $errs = $validator->errors();
            view()->share('errors', (new ViewErrorBag)->put('default', $errs));
            session()->flash('errors', (new ViewErrorBag)->put('default', $errs));
            if ($errs->has('tanggal_mulai') || $errs->has('tanggal_akhir')) {
                $hasDateErrors = true;
            }
        }

        $search = trim((string) $request->query('search'));
        $jenisMutasi = in_array($request->query('jenis_mutasi'), ['Pindah Ruangan', 'Ganti Pemegang'], true)
            ? $request->query('jenis_mutasi')
            : null;

        $query = MutasiAset::with([
            'details.aset.barang',
            'details.aset.kendaraan.platAktif',
            'details.inventaris',
            'details.pegawaiLama.ruangan',
            'details.pegawaiBaru.ruangan',
            'details.ruanganLama.lokasi',
            'details.ruanganBaru.lokasi',
            'userPenginput.pegawai',
        ]);

        // Tab kategori: seluruh mutasi dinas (aset barang & kendaraan) terpusat di sini.
        // Inventaris lokal ruangan masuk dalam kategori aset barang (non-kendaraan).
        if ($kategori === 'kendaraan') {
            $query->whereHas('details.aset', fn ($a) => $a->where('is_kendaraan', true));
        } else {
            $query->whereHas('details', function ($dq) {
                $dq->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
                   ->orWhereNotNull('id_inventaris');
            });
        }

        // Pembatasan cakupan Admin UPT:
        // Untuk kendaraan: mutasi masuk, mutasi keluar, internal UPT, serta kepemilikan aset UPT.
        // Untuk barang: tetap menggunakan mekanisme yang sudah berjalan (ruanganLama atau ruanganBaru).
        $idLokasiUpt = $scope->isAdminUpt() ? ($scope->idLokasi() ?: []) : null;
        if ($scope->isAdminUpt()) {
            $skpdId = $scope->skpdId();

            if ($kategori === 'kendaraan') {
                if (! $skpdId && empty($idLokasiUpt)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('details', function ($dq) use ($skpdId, $idLokasiUpt) {
                        $dq->where(function ($sub) use ($skpdId, $idLokasiUpt) {
                            $sub->whereHas('aset', function ($aq) use ($skpdId, $idLokasiUpt) {
                                $aq->where(function ($a) use ($skpdId, $idLokasiUpt) {
                                    if ($skpdId) {
                                        $a->where('id_skpd', $skpdId);
                                    }
                                    if (! empty($idLokasiUpt)) {
                                        if ($skpdId) {
                                            $a->orWhereIn('id_lokasi', $idLokasiUpt);
                                        } else {
                                            $a->whereIn('id_lokasi', $idLokasiUpt);
                                        }
                                    }
                                });
                            })
                            ->orWhereHas('pegawaiLama', function ($pq) use ($skpdId, $idLokasiUpt) {
                                $pq->where(function ($p) use ($skpdId, $idLokasiUpt) {
                                    if ($skpdId) {
                                        $p->where('id_skpd', $skpdId);
                                    }
                                    if (! empty($idLokasiUpt)) {
                                        $pCondition = fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt);
                                        if ($skpdId) {
                                            $p->orWhereHas('ruangan', $pCondition);
                                        } else {
                                            $p->whereHas('ruangan', $pCondition);
                                        }
                                    }
                                });
                            })
                            ->orWhereHas('pegawaiBaru', function ($pq) use ($skpdId, $idLokasiUpt) {
                                $pq->where(function ($p) use ($skpdId, $idLokasiUpt) {
                                    if ($skpdId) {
                                        $p->where('id_skpd', $skpdId);
                                    }
                                    if (! empty($idLokasiUpt)) {
                                        $pCondition = fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt);
                                        if ($skpdId) {
                                            $p->orWhereHas('ruangan', $pCondition);
                                        } else {
                                            $p->whereHas('ruangan', $pCondition);
                                        }
                                    }
                                });
                            });

                            if (! empty($idLokasiUpt)) {
                                $sub->orWhereHas('ruanganLama', fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt))
                                    ->orWhereHas('ruanganBaru', fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt));
                            }
                        });
                    });
                }
            } else {
                // Tab Barang: tetap menggunakan mekanisme yang sudah berjalan
                if (empty($idLokasiUpt)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('details', function ($dq) use ($idLokasiUpt) {
                        $dq->where(function ($w) use ($idLokasiUpt) {
                            $w->whereHas('ruanganLama', fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt))
                              ->orWhereHas('ruanganBaru', fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt));
                        });
                    });
                }
            }
        }

        // Filter Jenis Mutasi
        if ($jenisMutasi) {
            $query->where('jenis_mutasi', $jenisMutasi);
        }

        // Filter Rentang Tanggal (hanya diterapkan jika tanggal valid)
        if (! $hasDateErrors) {
            $tanggalMulai = $request->query('tanggal_mulai');
            $tanggalAkhir = $request->query('tanggal_akhir');

            if ($tanggalMulai && $tanggalAkhir) {
                $query->whereBetween('tanggal_mutasi', [$tanggalMulai, $tanggalAkhir]);
            } elseif ($tanggalMulai) {
                $query->where('tanggal_mutasi', '>=', $tanggalMulai);
            } elseif ($tanggalAkhir) {
                $query->where('tanggal_mutasi', '<=', $tanggalAkhir);
            }
        }

        // Filter Pencarian Teks Terpadu
        if ($search !== '') {
            $query->where(function ($q) use ($search, $scope, $idLokasiUpt, $kategori) {
                $cleanNumeric = ltrim($search, '#');
                if (is_numeric($cleanNumeric)) {
                    $q->where('id_mutasi', (int) $cleanNumeric)
                      ->orWhere('keterangan', 'like', "%{$search}%");
                } else {
                    $q->where('keterangan', 'like', "%{$search}%");
                }

                $q->orWhereHas('details', function ($dq) use ($search, $scope, $idLokasiUpt, $kategori) {
                    // Prioritas Keamanan: Admin UPT wajib terikat pada unit/lokasi UPT-nya pada detail yang sama
                    if ($scope->isAdminUpt()) {
                        $skpdId = $scope->skpdId();
                        $idLokasiUpt = $scope->idLokasi() ?: [];

                        if ($kategori === 'kendaraan') {
                            $dq->where(function ($scopeCheck) use ($skpdId, $idLokasiUpt) {
                                $scopeCheck->whereHas('aset', function ($aq) use ($skpdId, $idLokasiUpt) {
                                    $aq->where(function ($a) use ($skpdId, $idLokasiUpt) {
                                        if ($skpdId) {
                                            $a->where('id_skpd', $skpdId);
                                        }
                                        if (! empty($idLokasiUpt)) {
                                            if ($skpdId) {
                                                $a->orWhereIn('id_lokasi', $idLokasiUpt);
                                            } else {
                                                $a->whereIn('id_lokasi', $idLokasiUpt);
                                            }
                                        }
                                    });
                                })
                                ->orWhereHas('pegawaiLama', function ($pq) use ($skpdId, $idLokasiUpt) {
                                    $pq->where(function ($p) use ($skpdId, $idLokasiUpt) {
                                        if ($skpdId) {
                                            $p->where('id_skpd', $skpdId);
                                        }
                                        if (! empty($idLokasiUpt)) {
                                            $pCond = fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt);
                                            if ($skpdId) {
                                                $p->orWhereHas('ruangan', $pCond);
                                            } else {
                                                $p->whereHas('ruangan', $pCond);
                                            }
                                        }
                                    });
                                })
                                ->orWhereHas('pegawaiBaru', function ($pq) use ($skpdId, $idLokasiUpt) {
                                    $pq->where(function ($p) use ($skpdId, $idLokasiUpt) {
                                        if ($skpdId) {
                                            $p->where('id_skpd', $skpdId);
                                        }
                                        if (! empty($idLokasiUpt)) {
                                            $pCond = fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt);
                                            if ($skpdId) {
                                                $p->orWhereHas('ruangan', $pCond);
                                            } else {
                                                $p->whereHas('ruangan', $pCond);
                                            }
                                        }
                                    });
                                });

                                if (! empty($idLokasiUpt)) {
                                    $scopeCheck->orWhereHas('ruanganLama', fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt))
                                               ->orWhereHas('ruanganBaru', fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt));
                                }
                            });
                        } else {
                            $dq->where(function ($scopeCheck) use ($idLokasiUpt) {
                                $scopeCheck->whereHas('ruanganLama', fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt))
                                           ->orWhereHas('ruanganBaru', fn ($r) => $r->whereIn('id_lokasi', $idLokasiUpt));
                            });
                        }
                    }

                    $dq->where(function ($sub) use ($search, $kategori) {
                        if ($kategori === 'kendaraan') {
                            $sub->whereHas('aset.kendaraan', function ($k) use ($search) {
                                $k->where('pemegang', 'like', "%{$search}%")
                                  ->orWhere('jenis_kendaraan', 'like', "%{$search}%");
                            })->orWhereHas('pegawaiBaru', fn ($p) => $p->where('nama_pegawai', 'like', "%{$search}%"))
                              ->orWhereHas('pegawaiLama', fn ($p) => $p->where('nama_pegawai', 'like', "%{$search}%"));
                        } else {
                            $sub->whereHas('aset.barang', fn ($b) => $b->where('nama_barang', 'like', "%{$search}%"))
                                ->orWhereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', 'like', "%{$search}%"))
                                ->orWhereHas('inventaris', fn ($inv) => $inv->where('nama_barang', 'like', "%{$search}%")
                                    ->orWhere('kode_inventaris', 'like', "%{$search}%"))
                                ->orWhereHas('pegawaiLama', fn ($p) => $p->where('nama_pegawai', 'like', "%{$search}%"))
                                ->orWhereHas('pegawaiBaru', fn ($p) => $p->where('nama_pegawai', 'like', "%{$search}%"))
                                ->orWhereHas('ruanganLama', fn ($r) => $r->where('nama_ruangan', 'like', "%{$search}%"))
                                ->orWhereHas('ruanganBaru', fn ($r) => $r->where('nama_ruangan', 'like', "%{$search}%"));
                        }
                    });
                });
            });
        }

        $mutasiList = $query->orderByDesc('id_mutasi')
            ->paginate(10)
            ->withQueryString();

        // Siapkan detail yang diizinkan untuk setiap mutasi (Anti-Leakage untuk Admin UPT)
        // dan tentukan kelayakan unduh BAST historis tanpa N+1 query.
        $mutasiList->getCollection()->transform(function ($mutasi) use ($scope, $kategori) {
            if ($scope->isAdminUpt()) {
                $skpdId = $scope->skpdId();
                $idLokasiUpt = $scope->idLokasi() ?: [];

                if ($kategori === 'kendaraan') {
                    // Filter detail yang berkaitan dengan cakupan UPT
                    $visible = $mutasi->details->filter(function ($d) use ($skpdId, $idLokasiUpt) {
                        return $this->isDetailInUptScope($d, $skpdId, $idLokasiUpt);
                    })->values();

                    $bisaUnduh = true;
                    if ($mutasi->details->isEmpty()) {
                        $bisaUnduh = false;
                    } else {
                        foreach ($mutasi->details as $d) {
                            if (! $this->isDetailInUptScope($d, $skpdId, $idLokasiUpt)) {
                                $bisaUnduh = false;
                                break;
                            }
                        }
                    }

                    $mutasi->setRelation('visible_details', $visible);
                    $mutasi->bisa_unduh_bast = $bisaUnduh;
                } else {
                    // Filter detail barang yang melibatkan lokasi fisik UPT pengguna
                    $visible = $mutasi->details->filter(function ($d) use ($idLokasiUpt) {
                        $asal = $d->ruanganLama?->id_lokasi ? (int) $d->ruanganLama->id_lokasi : null;
                        $tujuan = $d->ruanganBaru?->id_lokasi ? (int) $d->ruanganBaru->id_lokasi : null;

                        $cocokAsal = $asal !== null && in_array($asal, $idLokasiUpt, true);
                        $cocokTujuan = $tujuan !== null && in_array($tujuan, $idLokasiUpt, true);

                        return $cocokAsal || $cocokTujuan;
                    })->values();

                    // Evaluasi hak unduh BAST: seluruh detail dalam transaksi mutasi
                    // wajib memenuhi syarat lokasi historis UPT (sama persis dengan downloadBAST).
                    $bisaUnduh = true;
                    if ($mutasi->details->isEmpty()) {
                        $bisaUnduh = false;
                    } else {
                        foreach ($mutasi->details as $d) {
                            if (! $d->aset) {
                                $bisaUnduh = false;
                                break;
                            }
                            $asal = $d->ruanganLama?->id_lokasi ? (int) $d->ruanganLama->id_lokasi : null;
                            $tujuan = $d->ruanganBaru?->id_lokasi ? (int) $d->ruanganBaru->id_lokasi : null;
                            $cocokAsal = $asal !== null && in_array($asal, $idLokasiUpt, true);
                            $cocokTujuan = $tujuan !== null && in_array($tujuan, $idLokasiUpt, true);
                            if (! $cocokAsal && ! $cocokTujuan) {
                                $bisaUnduh = false;
                                break;
                            }
                        }
                    }

                    $mutasi->setRelation('visible_details', $visible);
                    $mutasi->bisa_unduh_bast = $bisaUnduh;
                }
            } else {
                // Admin Aset memiliki akses global ke seluruh detail dan dokumen BAST
                $mutasi->setRelation('visible_details', $mutasi->details);
                $mutasi->bisa_unduh_bast = true;
            }

            return $mutasi;
        });

        return view('mutasi-aset.index', compact('mutasiList', 'kategori'));
    }

    /**
     * Unduh Berita Acara Serah Terima (BAST) Barang untuk mutasi Ganti Pemegang (.docx).
     * Dokumen digenerate on-demand dari template 'bast_barang' — tidak disimpan
     * ke storage agar tidak membebani hosting dan selalu sinkron dengan data terbaru.
     */
    public function downloadBAST($id_mutasi)
    {
        $scope = $this->scope();
        abort_unless(
            $scope->isAdminAset() || $scope->isAdminUpt(),
            403,
            'Anda tidak memiliki hak akses untuk mengunduh BAST.'
        );

        $mutasi = MutasiAset::with([
            'details.aset.barang',
            'details.pegawaiLama',
            'details.pegawaiBaru',
            'details.ruanganLama',
            'details.ruanganBaru',
        ])->findOrFail($id_mutasi);

        // BAST hanya untuk serah terima tanggung jawab perorangan.
        // Pindah ruangan merupakan penataan lokasi internal yang tercatat di KIR.
        if ($mutasi->jenis_mutasi !== 'Ganti Pemegang') {
            return back()->with('error', 'Dokumen BAST hanya tersedia untuk mutasi Ganti Pemegang.');
        }

        $details = $mutasi->details;
        if ($details->isEmpty()) {
            return back()->with('error', 'Detail mutasi tidak ditemukan.');
        }

        // Verifikasi cakupan hak akses aset historis (Opsi B)
        // Admin Aset memiliki akses global; Admin UPT diverifikasi berdasarkan keterkaitan lokasi fisik
        // asal (ruangan_lama) dan/atau tujuan (ruangan_baru) pada saat mutasi terjadi.
        if ($scope->isAdminUpt()) {
            $idLokasiUpt = $scope->idLokasi() ?: [];
            if (empty($idLokasiUpt)) {
                abort(403, 'Aset berada di luar cakupan kewenangan Anda.');
            }

            foreach ($details as $detail) {
                if (! $detail->aset) {
                    abort(403, 'Aset berada di luar cakupan kewenangan Anda.');
                }

                $lokasiAsal = $detail->ruanganLama?->id_lokasi ? (int) $detail->ruanganLama->id_lokasi : null;
                $lokasiTujuan = $detail->ruanganBaru?->id_lokasi ? (int) $detail->ruanganBaru->id_lokasi : null;

                $asalCocok = $lokasiAsal !== null && in_array($lokasiAsal, $idLokasiUpt, true);
                $tujuanCocok = $lokasiTujuan !== null && in_array($lokasiTujuan, $idLokasiUpt, true);

                // Fail-closed: Jika asal DAN tujuan tidak berada dalam cakupan UPT, tolak akses.
                // Seluruh aset dalam BAST harus berada dalam cakupan historis UPT.
                if (! $asalCocok && ! $tujuanCocok) {
                    abort(403, 'Aset berada di luar cakupan kewenangan Anda.');
                }
            }
        }

        $template = TemplateDokumen::where('kode_template', 'bast_barang')->first();
        if (! $template || ! $template->fileExists()) {
            return back()->with('error', 'Template BAST Barang belum diunggah di Pengaturan Dokumen.');
        }

        $phpWord = new TemplateProcessor($template->realPath());

        // Waktu dalam Bahasa Indonesia
        $tgl = \Carbon\Carbon::parse($mutasi->tanggal_mutasi ?? now())->locale('id');

        $firstDetail = $details->first();
        $p1 = $firstDetail->pegawaiLama;
        $p2 = $firstDetail->pegawaiBaru;
        $pihak1 = $this->resolvePihak($p1, $this->namaPemegangLamaDariKeterangan($mutasi->keterangan));
        $pihak2 = $this->resolvePihak($p2);

        // Susun daftar seluruh aset untuk tabel BAST multi-detail
        $items = [];
        $nomor = 1;
        foreach ($details as $d) {
            $asetModel = $d->aset;
            $items[] = [
                'nomor' => (string) $nomor++,
                'nama_barang' => (string) ($asetModel?->barang?->nama_barang ?? '-'),
                'merk_barang' => (string) ($asetModel?->merk ?? '-'),
                'tahun_pengadaan' => (string) ($asetModel?->tanggal_pengadaan ? \Carbon\Carbon::parse($asetModel->tanggal_pengadaan)->format('Y') : '-'),
                'kode_barang' => (string) ($asetModel?->nomor_kartu_barang ?? '-'),
            ];
        }

        // 1. Placeholder umum (header & para pihak) via PhpWord standar
        $phpWord->setValue('hari', $tgl->translatedFormat('l'));
        $phpWord->setValue('tanggal_terbilang', $this->terbilang((int) $tgl->format('d')));
        $phpWord->setValue('bulan', $tgl->translatedFormat('F'));
        $phpWord->setValue('tahun_terbilang', $this->terbilang((int) $tgl->format('Y')));

        $phpWord->setValue('nama_pihak_pertama', $pihak1['nama']);
        $phpWord->setValue('nip_pihak_pertama', $pihak1['nip']);
        $phpWord->setValue('jabatan_pihak_pertama', $pihak1['jabatan']);
        $phpWord->setValue('alamat_pihak_pertama', $pihak1['alamat']);

        $phpWord->setValue('nama_pihak_kedua', $pihak2['nama']);
        $phpWord->setValue('nip_pihak_kedua', $pihak2['nip']);
        $phpWord->setValue('jabatan_pihak_kedua', $pihak2['jabatan']);
        $phpWord->setValue('alamat_pihak_kedua', $pihak2['alamat']);

        // 2. Render baris tabel barang dinamis (multi-detail)
        $this->renderTableRows($phpWord, $items);

        // 3. Patch kurung kurawal tunggal {placeholder} langsung di XML
        $swap = [
            '{hari}'              => $tgl->translatedFormat('l'),
            '{tanggal_terbilang}' => $this->terbilang((int) $tgl->format('d')),
            '{bulan}'             => $tgl->translatedFormat('F'),
            '{tahun_terbilang}'   => $this->terbilang((int) $tgl->format('Y')),
            '{nama_pihak_pertama}'  => $pihak1['nama'],
            '{nip_pihak_pertama}'   => $pihak1['nip'],
            '{jabatan_pihak_pertama}' => $pihak1['jabatan'],
            '{alamat_pihak_pertama}' => $pihak1['alamat'],
            '{nama_pihak_kedua}'    => $pihak2['nama'],
            '{nip_pihak_kedua}'     => $pihak2['nip'],
            '{jabatan_pihak_kedua}' => $pihak2['jabatan'],
            '{alamat_pihak_kedua}' => $pihak2['alamat'],
            '{#barang}'         => '',
            '{/barang}'         => '',
        ];

        $this->applyReplacements($phpWord, $swap);

        $jumlahAset = count($items);
        if ($jumlahAset > 1) {
            $namaFile = 'BAST_' . $jumlahAset . '_Aset_' . $tgl->format('Ymd') . '.docx';
        } else {
            $namaBarangFirst = $items[0]['nama_barang'] ?? 'Aset';
            $namaFile = 'BAST_' . Str::slug($namaBarangFirst !== '-' ? $namaBarangFirst : 'Aset', '_') . '_' . $tgl->format('Ymd') . '.docx';
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'BAST_');
        $phpWord->saveAs($tempPath);

        return response()->download($tempPath, $namaFile)->deleteFileAfterSend(true);
    }

    /**
     * Unduh BAST Kendaraan (Berita Acara Serah Terima Kendaraan Dinas).
     * Template: 'bast_kendaraan' (unggah di Pengaturan Dokumen).
     */
    public function downloadBASTKendaraan(Kendaraan $kendaraan, $id_mutasi)
    {
        $scope = $this->scope();
        abort_unless(
            $scope->isAdminAset() || $scope->isAdminUpt(),
            403,
            'Anda tidak memiliki hak akses untuk mengunduh BAST kendaraan.'
        );

        $mutasi = MutasiAset::with([
            'details',
            'details.aset.barang',
            'details.pegawaiLama.ruangan',
            'details.pegawaiBaru.ruangan',
            'details.ruanganLama',
            'details.ruanganBaru',
        ])->findOrFail($id_mutasi);

        if ($mutasi->jenis_mutasi !== 'Ganti Pemegang') {
            return back()->with('error', 'Dokumen BAST hanya tersedia untuk mutasi Ganti Pemegang.');
        }

        $detail = $mutasi->details->first();
        if (!$detail || !$detail->aset || (int) $detail->id_aset !== (int) $kendaraan->id_aset) {
            return back()->with('error', 'Detail mutasi tidak ditemukan untuk kendaraan ini.');
        }

        // Verifikasi cakupan hak akses Admin UPT di backend (Fail-closed)
        if ($scope->isAdminUpt()) {
            $skpdId = $scope->skpdId();
            $idLokasiUpt = $scope->idLokasi() ?: [];

            if (! $skpdId && empty($idLokasiUpt)) {
                abort(403, 'Dokumen BAST kendaraan ini berada di luar cakupan kewenangan UPT Anda.');
            }

            if ($mutasi->details->isEmpty()) {
                abort(403, 'Dokumen BAST kendaraan ini berada di luar cakupan kewenangan UPT Anda.');
            }

            foreach ($mutasi->details as $d) {
                if (! $this->isDetailInUptScope($d, $skpdId, $idLokasiUpt)) {
                    abort(403, 'Dokumen BAST kendaraan ini berada di luar cakupan kewenangan UPT Anda.');
                }
            }
        }

        $template = TemplateDokumen::where('kode_template', 'bast_kendaraan')->first();
        if (!$template || !$template->fileExists()) {
            return back()->with('error', 'Template BAST Kendaraan belum diunggah di Pengaturan Dokumen.');
        }

        $phpWord = new TemplateProcessor($template->realPath());

        $tgl = \Carbon\Carbon::parse($mutasi->tanggal_mutasi ?? now())->locale('id');

        $p1 = $detail->pegawaiLama;
        $p2 = $detail->pegawaiBaru;
        $aset = $detail->aset;
        $knd = Kendaraan::where('id_aset', $aset->id_aset)->first();
        $platAktif = $knd?->platAktif;
        $namaKendaraan = $knd?->namaTampil() ?? '-';
        $pihak1 = $this->resolvePihak($p1, $this->namaPemegangLamaDariKeterangan($mutasi->keterangan));
        $pihak2 = $this->resolvePihak($p2, $knd?->pemegang);

        // Sesuaikan placeholder dengan template 'bast_kendaraan'.
        $values = [
            'hari' => $tgl->translatedFormat('l'),
            'tanggal_terbilang' => $this->terbilang((int) $tgl->format('d')),
            'bulan' => $tgl->translatedFormat('F'),
            'tahun_terbilang' => $this->terbilang((int) $tgl->format('Y')),
            'nama_pihak_pertama' => $pihak1['nama'],
            'nip_pihak_pertama' => $pihak1['nip'],
            'jabatan_pihak_pertama' => $pihak1['jabatan'],
            'alamat_pihak_pertama' => $pihak1['alamat'],
            'nama_pihak_kedua' => $pihak2['nama'],
            'nip_pihak_kedua' => $pihak2['nip'],
            'jabatan_pihak_kedua' => $pihak2['jabatan'],
            'alamat_pihak_kedua' => $pihak2['alamat'],
            'nomor' => '1',
            'no_plat' => $platAktif?->nomor_plat ?? '-',
            'jenis_kendaraan' => $namaKendaraan,
            'no_rangka' => $knd?->nomor_rangka ?? '-',
            'no_mesin' => $knd?->nomor_mesin ?? '-',
        ];

        foreach ($values as $key => $value) {
            $phpWord->setValue($key, $value);
        }

        $swap = [];
        foreach ($values as $key => $value) {
            $swap['{' . $key . '}'] = $value;
        }
        $swap['{#kendaraan}'] = '';
        $swap['{/kendaraan}'] = '';

        $this->applyReplacements($phpWord, $swap);

        $namaFile = 'BAST_Kendaraan_' . Str::slug($namaKendaraan !== '-' ? $namaKendaraan : 'Kendaraan', '_') . '_' . $tgl->format('Ymd') . '.docx';
        $tempPath = tempnam(sys_get_temp_dir(), 'BAST_');
        $phpWord->saveAs($tempPath);

        return response()->download($tempPath, $namaFile)->deleteFileAfterSend(true);
    }

    /**
     * Unduh SPPKD (Surat Penunjukan Pemakaian Kendaraan Dinas).
     * Template: 'sppkd' (unggah di Pengaturan Dokumen).
     */
    public function downloadSPPKD(Kendaraan $kendaraan, $id_mutasi)
    {
        abort_unless($this->scope()->isAdminAset(), 403, 'Hanya Admin Aset yang berwenang mengunduh dokumen mutasi kendaraan.');

        $mutasi = MutasiAset::with(['details', 'details.aset.barang', 'details.pegawaiBaru'])
            ->findOrFail($id_mutasi);

        if ($mutasi->jenis_mutasi !== 'Ganti Pemegang') {
            return back()->with('error', 'Dokumen SPPKD hanya tersedia untuk mutasi Ganti Pemegang.');
        }

        $detail = $mutasi->details->first();
        if (!$detail || !$detail->aset || (int) $detail->id_aset !== (int) $kendaraan->id_aset) {
            return back()->with('error', 'Detail mutasi tidak ditemukan untuk kendaraan ini.');
        }

        $template = TemplateDokumen::where('kode_template', 'sppkd')->first();
        if (!$template || !$template->fileExists()) {
            return back()->with('error', 'Template SPPKD belum diunggah di Pengaturan Dokumen.');
        }

        $phpWord = new TemplateProcessor($template->realPath());

        $tgl = \Carbon\Carbon::parse($mutasi->tanggal_mutasi ?? now())->locale('id');

        $p2 = $detail->pegawaiBaru;
        $aset = $detail->aset;
        $knd = Kendaraan::where('id_aset', $aset->id_aset)->first();
        $platAktif = $knd?->platAktif;
        $namaKendaraan = $knd?->namaTampil() ?? '-';
        $pihak2 = $this->resolvePihak($p2, $knd?->pemegang);

        // Ambil nomor surat dari keterangan jika dicatat saat mutasi.
        $nomorSurat = '';
        if ($mutasi->keterangan && preg_match('/Nomor Surat: ([^\.]+)/', $mutasi->keterangan, $m)) {
            $nomorSurat = trim($m[1]);
        }

        // Sesuaikan placeholder dengan template 'sppkd'.
        $values = [
            'nomor' => $nomorSurat ?: '1',
            'nama_asn' => $pihak2['nama'],
            'nip_asn' => $pihak2['nip'],
            'jabatan_asn' => $pihak2['jabatan'],
            'alamat_asn' => $pihak2['alamat'],
            'tanggal' => (int) $tgl->format('d'),
            'bulan' => $tgl->translatedFormat('F'),
            'tahun' => (int) $tgl->format('Y'),
            'no_plat' => $platAktif?->nomor_plat ?? '-',
            'jenis_kendaraan' => $namaKendaraan,
            'no_rangka' => $knd?->nomor_rangka ?? '-',
            'no_mesin' => $knd?->nomor_mesin ?? '-',
        ];

        foreach ($values as $key => $value) {
            $phpWord->setValue($key, $value);
        }

        $swap = [];
        foreach ($values as $key => $value) {
            $swap['{' . $key . '}'] = $value;
        }
        $swap['{#kendaraan}'] = '';
        $swap['{/kendaraan}'] = '';

        $this->applyReplacements($phpWord, $swap);

        $namaFile = 'SPPKD_' . ($pihak2['nama'] !== '-' ? Str::slug($pihak2['nama'], '_') : 'Kendaraan') . '_' . $tgl->format('Ymd') . '.docx';
        $tempPath = tempnam(sys_get_temp_dir(), 'SPPKD_');
        $phpWord->saveAs($tempPath);

        return response()->download($tempPath, $namaFile)->deleteFileAfterSend(true);
    }

    /**
     * Unduh SPPKD untuk pemegang yang sedang tercantum pada kendaraan
     * (kolom Pemegang tabel utama Kendaraan), tanpa record mutasi.
     *
     * Mengambil seluruh kendaraan aktif yang saat ini dipegang oleh pegawai
     * tersebut dan penempatannya sudah difinalisasi.
     */
    public function downloadSPPKDPemegang(Kendaraan $kendaraan)
    {
        abort_unless($this->scope()->isAdminAset(), 403, 'Hanya Admin Aset yang berwenang mengunduh dokumen mutasi kendaraan.');

        $aset = $kendaraan->aset;

        // 1. Identifikasi pemegang berdasarkan relasi resmi aset.pemegangSaatIni.pegawai.
        $pegawai = $aset?->pemegangSaatIni?->pegawai;

        // 2. Jika relasi pemegang aktif tidak tersedia pada data lama, gunakan fallback nama
        //    dari kendaraan.pemegang hanya apabila nama tersebut cocok dengan tepat satu data Pegawai.
        if (! $pegawai) {
            $namaPemegang = trim((string) $kendaraan->pemegang);
            if ($namaPemegang === '') {
                return back()->with('error', 'Kendaraan belum memiliki pemegang, SPPKD tidak dapat dibuat.');
            }

            $kandidat = Pegawai::where('nama_pegawai', $namaPemegang)->get();
            if ($kandidat->count() !== 1) {
                return back()->with('error', 'Identitas pemegang tidak ditemukan atau ambigu (nama tidak unik). SPPKD tidak dapat dibuat.');
            }

            $pegawai = $kandidat->first();
        }

        $pihak = $this->resolvePihak($pegawai);

        // 3. Ambil seluruh kendaraan yang saat ini dipegang oleh pegawai tersebut
        //    yang memenuhi seluruh syarat (aset aktif, penempatan aktif difinalisasi, pemegang saat ini sesuai id_pegawai).
        $kendaraanList = Kendaraan::with(['aset.penempatanAktif', 'platAktif'])
            ->whereHas('aset', function ($q) use ($pegawai) {
                $q->where('status_aset', 'aktif')
                    ->whereHas('pemegangSaatIni', function ($p) use ($pegawai) {
                        $p->where('id_pegawai', $pegawai->id_pegawai);
                    })
                    ->whereHas('penempatanAktif', function ($pen) {
                        $pen->where('is_finalized', 1);
                    });
            })
            ->orderBy('id_kendaraan')
            ->get()
            ->filter(function (Kendaraan $k) {
                return $k->aset && $this->scope()->bolehAksesAset($k->aset);
            })
            ->values();

        if ($kendaraanList->isEmpty()) {
            return back()->with('error', 'Tidak ada kendaraan aktif dengan status penempatan yang sudah difinalisasi untuk pemegang ini.');
        }

        $template = TemplateDokumen::where('kode_template', 'sppkd')->first();
        if (! $template || ! $template->fileExists()) {
            return back()->with('error', 'Template SPPKD belum diunggah di Pengaturan Dokumen.');
        }

        $tgl = Carbon::now()->locale('id');

        $values = [
            'nama_asn'    => (string) $pihak['nama'],
            'nip_asn'     => (string) $pihak['nip'],
            'jabatan_asn' => (string) $pihak['jabatan'],
            'alamat_asn'  => (string) $pihak['alamat'],
            'tanggal'     => (string) ((int) $tgl->format('d')),
            'bulan'       => (string) $tgl->translatedFormat('F'),
            'tahun'       => (string) $tgl->format('Y'),
        ];

        $rows = $kendaraanList->map(function (Kendaraan $k, int $index) {
            return [
                'nomor'           => (string) ($index + 1),
                'no_plat'         => (string) ($k->platAktif?->nomor_plat ?? '-'),
                'jenis_kendaraan' => (string) ($k->namaTampil() ?: '-'),
                'no_rangka'       => (string) ($k->nomor_rangka ?: '-'),
                'no_mesin'        => (string) ($k->nomor_mesin ?: '-'),
            ];
        })->all();

        $rowTokens = [
            'nomor'           => 'nomor',
            'no_plat'         => 'no_plat',
            'jenis_kendaraan' => 'jenis_kendaraan',
            'no_rangka'       => 'no_rangka',
            'no_mesin'        => 'no_mesin',
        ];

        $filePath = WordTemplateFiller::make($template->realPath())->render(
            values: $values,
            rows: $rows,
            options: [
                'row_macro'  => 'kendaraan',
                'row_tokens' => $rowTokens,
            ],
        );

        $namaFile = 'SPPKD_' . ($pihak['nama'] !== '-' ? Str::slug($pihak['nama'], '_') : 'Kendaraan') . '_' . $tgl->format('Ymd') . '.docx';

        return response()->download($filePath, $namaFile, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Unduh SPPKD kendaraan cukup dengan id_mutasi (tanpa mengirim id kendaraan).
     * Digunakan modal sukses mutasi di index kendaraan & menu Mutasi Aset.
     */
    public function downloadSPPKDById($id_mutasi)
    {
        abort_unless($this->scope()->isAdminAset(), 403, 'Hanya Admin Aset yang berwenang mengunduh dokumen mutasi kendaraan.');

        $detail = DetailMutasiAset::where('id_mutasi', $id_mutasi)->first();
        if (!$detail) {
            return back()->with('error', 'Detail mutasi tidak ditemukan.');
        }

        $kendaraan = Kendaraan::where('id_aset', $detail->id_aset)->first();
        if (!$kendaraan) {
            return back()->with('error', 'Kendaraan tidak ditemukan.');
        }

        return $this->downloadSPPKD($kendaraan, $id_mutasi);
    }

    /**
     * Unduh BAST kendaraan cukup dengan id_mutasi (tanpa mengirim id kendaraan).
     */
    public function downloadBASTKendaraanById($id_mutasi)
    {
        $scope = $this->scope();
        abort_unless(
            $scope->isAdminAset() || $scope->isAdminUpt(),
            403,
            'Anda tidak memiliki hak akses untuk mengunduh BAST kendaraan.'
        );

        $detail = DetailMutasiAset::where('id_mutasi', $id_mutasi)->first();
        if (!$detail) {
            return back()->with('error', 'Detail mutasi tidak ditemukan.');
        }

        $kendaraan = Kendaraan::where('id_aset', $detail->id_aset)->first();
        if (!$kendaraan) {
            return back()->with('error', 'Kendaraan tidak ditemukan.');
        }

        return $this->downloadBASTKendaraan($kendaraan, $id_mutasi);
    }

    /**
     * Memeriksa apakah detail mutasi kendaraan berkaitan dengan cakupan UPT.
     * Keterkaitan mencakup aset UPT, mutasi masuk/keluar pegawai UPT, dan ruangan UPT.
     */
    private function isDetailInUptScope(DetailMutasiAset $d, ?int $skpdId, array $idLokasiUpt): bool
    {
        if (! $skpdId && empty($idLokasiUpt)) {
            return false;
        }

        // 1. Keterkaitan Aset Kendaraan (SKPD penanggung jawab atau Lokasi fisik)
        if ($d->aset) {
            $asetSkpd = $d->aset->id_skpd ? (int) $d->aset->id_skpd : null;
            $asetLokasi = $d->aset->id_lokasi ? (int) $d->aset->id_lokasi : null;

            if ($skpdId && $asetSkpd === $skpdId) {
                return true;
            }
            if (! empty($idLokasiUpt) && $asetLokasi !== null && in_array($asetLokasi, $idLokasiUpt, true)) {
                return true;
            }
        }

        // 2. Keterkaitan Pegawai Lama (Mutasi Keluar / Internal UPT)
        if ($d->pegawaiLama) {
            $pLamaSkpd = $d->pegawaiLama->id_skpd ? (int) $d->pegawaiLama->id_skpd : null;
            $pLamaLokasi = $d->pegawaiLama->ruangan?->id_lokasi ? (int) $d->pegawaiLama->ruangan->id_lokasi : null;

            if ($skpdId && $pLamaSkpd === $skpdId) {
                return true;
            }
            if (! empty($idLokasiUpt) && $pLamaLokasi !== null && in_array($pLamaLokasi, $idLokasiUpt, true)) {
                return true;
            }
        }

        // 3. Keterkaitan Pegawai Baru (Mutasi Masuk / Internal UPT)
        if ($d->pegawaiBaru) {
            $pBaruSkpd = $d->pegawaiBaru->id_skpd ? (int) $d->pegawaiBaru->id_skpd : null;
            $pBaruLokasi = $d->pegawaiBaru->ruangan?->id_lokasi ? (int) $d->pegawaiBaru->ruangan->id_lokasi : null;

            if ($skpdId && $pBaruSkpd === $skpdId) {
                return true;
            }
            if (! empty($idLokasiUpt) && $pBaruLokasi !== null && in_array($pBaruLokasi, $idLokasiUpt, true)) {
                return true;
            }
        }

        // 4. Keterkaitan Ruangan Lama
        if ($d->ruanganLama && ! empty($idLokasiUpt)) {
            $rLamaLokasi = $d->ruanganLama->id_lokasi ? (int) $d->ruanganLama->id_lokasi : null;
            if ($rLamaLokasi !== null && in_array($rLamaLokasi, $idLokasiUpt, true)) {
                return true;
            }
        }

        // 5. Keterkaitan Ruangan Baru
        if ($d->ruanganBaru && ! empty($idLokasiUpt)) {
            $rBaruLokasi = $d->ruanganBaru->id_lokasi ? (int) $d->ruanganBaru->id_lokasi : null;
            if ($rBaruLokasi !== null && in_array($rBaruLokasi, $idLokasiUpt, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Duplikasi dan isi baris tabel barang pada dokumen BAST Word
     * untuk mendukung transaksi mutasi multi-detail.
     */
    private function renderTableRows(TemplateProcessor $phpWord, array $items): void
    {
        try {
            $reflection = new \ReflectionClass($phpWord);
            if (! $reflection->hasProperty('tempDocumentMainPart')) {
                return;
            }
            $property = $reflection->getProperty('tempDocumentMainPart');
            $property->setAccessible(true);
            $xml = $property->getValue($phpWord);

            if (! is_string($xml) || $xml === '') {
                return;
            }

            // Temukan baris tabel <w:tr> yang memuat kolom tabel barang
            $newXml = preg_replace_callback('/<w:tr\b[^>]*>.*?<\/w:tr>/s', function ($m) use ($items) {
                $rowXml = $m[0];
                if (str_contains($rowXml, 'nama_barang') || str_contains($rowXml, '{#barang}')) {
                    $out = '';
                    foreach ($items as $item) {
                        $row = $rowXml;
                        $swap = [
                            '{#barang}'         => '',
                            '{/barang}'         => '',
                            '{nomor}'           => (string) $item['nomor'],
                            '{nama_barang}'     => (string) $item['nama_barang'],
                            '{merk_barang}'     => (string) $item['merk_barang'],
                            '{tahun_pengadaan}' => (string) $item['tahun_pengadaan'],
                            '{kode_barang}'     => (string) $item['kode_barang'],
                        ];
                        $row = preg_replace_callback('/<w:p\b[^>]*>.*?<\/w:p>/s', function ($pm) use ($swap) {
                            return $this->replaceInParagraph($pm[0], $swap);
                        }, $row);
                        $out .= $row;
                    }

                    return $out;
                }

                return $rowXml;
            }, $xml);

            if (is_string($newXml) && $newXml !== '') {
                $property->setValue($phpWord, $newXml);
            }
        } catch (\Throwable $th) {
            // Abaikan kegagalan manipulasi low-level XML, lanjutkan dengan fallback
        }
    }

    /**
     * Terapkan penggantian placeholder lintas-run ({token}) di seluruh bagian
     * dokumen Word (main part, header, footer).
     */
    private function applyReplacements(TemplateProcessor $phpWord, array $swap): void
    {
        try {
            $reflection = new \ReflectionClass($phpWord);
            foreach (['tempDocumentMainPart', 'tempDocumentHeaders', 'tempDocumentFooters'] as $propName) {
                if (!$reflection->hasProperty($propName)) {
                    continue;
                }
                $property = $reflection->getProperty($propName);
                $property->setAccessible(true);
                $xml = $property->getValue($phpWord);

                if (!is_string($xml) || $xml === '') {
                    continue;
                }

                $property->setValue($phpWord, $this->replaceFragmentedPlaceholders($xml, $swap));
            }
        } catch (\Throwable $th) {
            // Abaikan; placeholder yang tersisa tetap tampil agar terlihat oleh pembuat template.
        }
    }

    /**
     * Bahan data satu pihak (penyerah/penerima) pada dokumen BAST/SPPKD.
     *
     * Relasi Pegawai (via ID pada detail_mutasi_aset) tetap prioritas utama.
     * Bila relasi tidak ada (pemegang disimpan sebagai teks), nama memakai
     * fallback teks; NIP/Jabatan/Alamat yang tidak tersedia menjadi '-'.
     * Tidak pernah menebak data pegawai lain berdasarkan nama/NIP.
     *
     * @return array{nama: string, nip: string, jabatan: string, alamat: string}
     */
    private function resolvePihak(?Pegawai $pegawai, ?string $namaFallback = null): array
    {
        return [
            'nama' => $pegawai?->nama_pegawai ?: ($namaFallback ?: '-'),
            'nip' => $pegawai?->nip ?: '-',
            'jabatan' => $pegawai?->jabatan ?: '-',
            'alamat' => $pegawai?->alamat ?: '-',
        ];
    }

    /**
     * Nama pemegang lama dari teks keterangan mutasi kendaraan, bila tidak ada
     * relasi Pegawai. Format keterangan dicatat `mutasiPemegang()` sebagai
     * "Serah terima dari {nama}...". Mengembalikan null bila tidak tersedia.
     */
    private function namaPemegangLamaDariKeterangan(?string $keterangan): ?string
    {
        if ($keterangan && preg_match('/Serah terima dari ([^\.]+)/', $keterangan, $m)) {
            $nama = trim($m[1]);

            return $nama !== '' ? $nama : null;
        }

        return null;
    }

    /**
     * Ganti placeholder lintas-run di XML dokumen Word.
     * Word memecah token placeholder ({hari}, {nama_barang}, dst.) ke beberapa
     * <w:r>/<w:t> (sering diselingi w:proofErr saat spell-check). str_replace
     * polos gagal karena teks tidak kontigu. Metode ini menyambung teks seluruh
     * run per paragraf, menemukan token, lalu mendistribusikan nilai pengganti
     * ke run-run yang menaungi token tanpa merusak format run asli.
     *
     * @return string XML hasil penggantian
     */
    private function replaceFragmentedPlaceholders(string $xml, array $swap): string
    {
        return preg_replace_callback(
            '/<w:p\b[^>]*>.*?<\/w:p>/s',
            function (array $m) use ($swap) {
                return $this->replaceInParagraph($m[0], $swap);
            },
            $xml
        ) ?? $xml;
    }

    /**
     * Proses satu paragraf /w:p: pecah menjadi segmen run + raw lalu replace token.
     */
    private function replaceInParagraph(string $para, array $swap): string
    {
        $segments = preg_split(
            '/(<w:r\b[^>]*>.*?<\/w:r>|<w:r\b[^>]*\/>)/s',
            $para,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if (!$segments || !Str::contains($para, '{')) {
            return $para;
        }

        $full = $this->segmentText($segments);

        foreach ($swap as $token => $value) {
            $pos = 0;
            while (($idx = mb_strpos($full, $token, $pos)) !== false) {
                $end = $idx + mb_strlen($token);

                $covered = [];
                $cursor = 0;
                foreach ($segments as $i => $xml2) {
                    if (!str_starts_with($xml2, '<w:r')) {
                        continue;
                    }
                    $len = mb_strlen($this->runText($xml2));
                    $rs = $cursor;
                    $re = $rs + $len;
                    if ($rs < $end && $re > $idx) {
                        $covered[] = ['i' => $i, 'a' => max($rs, $idx) - $rs, 'b' => min($re, $end) - $rs];
                    }
                    $cursor = $re;
                }

                if ($covered) {
                    $total = 0;
                    foreach ($covered as $c) {
                        $total += $c['b'] - $c['a'];
                    }

                    $slices = [];
                    $alloc = 0;
                    $vLen = mb_strlen($value);
                    foreach ($covered as $c) {
                        $take = (int) floor($vLen * ($c['b'] - $c['a']) / $total);
                        $take = min($take, $vLen - $alloc);
                        $slices[] = mb_substr($value, $alloc, $take);
                        $alloc += $take;
                    }
                    if ($alloc < $vLen && $covered) {
                        $slices[count($slices) - 1] .= mb_substr($value, $alloc);
                    }

                    foreach ($covered as $ci => $c) {
                        $i = $c['i'];
                        $t = $this->runText($segments[$i]);
                        $segments[$i] = $this->setRunText(
                            $segments[$i],
                            mb_substr($t, 0, $c['a']) . ($slices[$ci] ?? '') . mb_substr($t, $c['b'])
                        );
                    }
                }

                $full = $this->segmentText($segments);
                $pos = min($end, mb_strlen($full));
            }
        }

        return implode('', $segments);
    }

    /**
     * Ambil teks gabungan dari seluruh <w:t> sebuah <w:r>.
     */
    private function runText(string $runXml): string
    {
        if (!str_starts_with($runXml, '<w:r')) {
            return '';
        }
        if (preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/s', $runXml, $m)) {
            return implode('', $m[1]);
        }
        return '';
    }

    /**
     * Tulis ulang teks <w:t> (mempertahankan atribut & rPr run).
     */
    private function setRunText(string $runXml, string $newText): string
    {
        if (!preg_match('/^(<w:r\b[^>]*>)(.*)<\/w:r>$/s', $runXml, $m)) {
            return $runXml;
        }

        $inner = preg_replace_callback(
            '/<w:t\b[^>]*>.*?<\/w:t>|<w:t\b[^>]*\/>/s',
            function ($t) use ($newText) {
                return '<w:t xml:space="preserve">' . htmlspecialchars($newText, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t>';
            },
            $m[2],
            1
        );

        return $m[1] . $inner . '</w:r>';
    }

    /**
     * Gabungkan teks seluruh segmen (run memberikan teksnya, raw diabaikan).
     */
    private function segmentText(array $segments): string
    {
        $out = '';
        foreach ($segments as $xml2) {
            if (str_starts_with($xml2, '<w:r')) {
                $out .= $this->runText($xml2);
            }
        }
        return $out;
    }

    /**
     * Konversi bilangan 0-999999 menjadi terbilang Bahasa Indonesia.
     */
    private function terbilang(int $angka): string
    {
        $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if ($angka < 12) {
            return $satuan[$angka];
        }

        if ($angka < 20) {
            return $this->terbilang($angka - 10) . ' belas';
        }

        if ($angka < 100) {
            return $this->terbilang(intdiv($angka, 10)) . ' puluh' . ($angka % 10 ? ' ' . $this->terbilang($angka % 10) : '');
        }

        if ($angka < 200) {
            return 'seratus' . ($angka > 100 ? ' ' . $this->terbilang($angka - 100) : '');
        }

        if ($angka < 1000) {
            return $this->terbilang(intdiv($angka, 100)) . ' ratus' . ($angka % 100 ? ' ' . $this->terbilang($angka % 100) : '');
        }

        if ($angka < 2000) {
            return 'seribu' . ($angka > 1000 ? ' ' . $this->terbilang($angka - 1000) : '');
        }

        return $this->terbilang(intdiv($angka, 1000)) . ' ribu' . ($angka % 1000 ? ' ' . $this->terbilang($angka % 1000) : '');
    }
}
