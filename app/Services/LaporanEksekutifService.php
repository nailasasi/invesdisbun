<?php

namespace App\Services;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\Lokasi;
use App\Models\MutasiAset;
use App\Models\PajakKendaraan;
use App\Models\SensusAset;
use App\Models\Tanah;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service penyedia data dan kalkulasi analitis Modul Laporan Eksekutif.
 *
 * Menerapkan:
 * - Opsi A: Rekonstruksi saldo historis aset menggunakan tanggal_sk usulan penghapusan.
 * - Pemisahan tegas data flow (aktivitas periode) dan snapshot (posisi saldo per t_acuan).
 * - Multi-tenancy ketat via AsetScope dan TanahScope.
 * - Pemisahan register tanah dari perhitungan barang/unit.
 */
class LaporanEksekutifService
{
    public function __construct(
        private readonly AsetScope $scope,
        private readonly TanahScope $tanahScope,
        private readonly MasaPakaiAset $masaPakai
    ) {
    }

    /**
     * Hitung tanggal mulai, tanggal selesai, dan tanggal acuan laporan.
     *
     * @return array{
     *     jenis: string,
     *     tahun: int,
     *     bulan: int,
     *     start_date: CarbonInterface,
     *     end_date: CarbonInterface,
     *     acuan: CarbonInterface,
     *     judul_periode: string
     * }
     */
    public function resolvePeriode(string $jenis, int $tahun, int $bulan = 1): array
    {
        $jenis = strtolower(trim($jenis)) === 'tahunan' ? 'tahunan' : 'bulanan';
        $tahun = max(1990, min(2100, $tahun));
        $bulan = max(1, min(12, $bulan));

        $now = Carbon::now();

        if ($jenis === 'tahunan') {
            $startDate = Carbon::create($tahun, 1, 1)->startOfYear();
            $nominalEnd = Carbon::create($tahun, 12, 31)->endOfYear();
            $judulPeriode = "Tahun Anggaran {$tahun}";
        } else {
            $startDate = Carbon::create($tahun, $bulan, 1)->startOfMonth();
            $nominalEnd = Carbon::create($tahun, $bulan, 1)->endOfMonth();
            $namaBulan = match ($bulan) {
                1 => 'Januari',
                2 => 'Februari',
                3 => 'Maret',
                4 => 'April',
                5 => 'Mei',
                6 => 'Juni',
                7 => 'Juli',
                8 => 'Agustus',
                9 => 'September',
                10 => 'Oktober',
                11 => 'November',
                12 => 'Desember',
                default => 'Januari',
            };
            $judulPeriode = "Bulan {$namaBulan} {$tahun}";
        }

        // Tanggal acuan snapshot: jika periode melewati waktu saat ini, batasi ke hari ini (akhir hari ini)
        // agar laporan aktual tidak menghitung data dari masa depan.
        // Laporan tahun atau bulan sebelumnya yang sudah lampau tetap menggunakan akhir periode nominal secara utuh.
        if ($nominalEnd->isFuture()) {
            $endDate = $now->copy()->endOfDay();
            $acuan = $now->copy()->endOfDay();
        } else {
            $endDate = $nominalEnd;
            $acuan = $nominalEnd->copy()->endOfDay();
        }

        return [
            'jenis' => $jenis,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'acuan' => $acuan,
            'judul_periode' => $judulPeriode,
        ];
    }

    /**
     * Query dasar aset per tanggal acuan laporan dengan Rekonstruksi Saldo Opsi A.
     *
     * Menjamin setiap id_aset hanya dihitung tepat satu kali (tanpa fan-out join).
     */
    public function asetQueryAt(CarbonInterface $acuan, ?int $unitLokasiId = null): Builder
    {
        $query = Aset::query()
            ->whereNotNull('aset.tanggal_pengadaan')
            ->where('aset.tanggal_pengadaan', '<=', $acuan)
            ->where(function ($q) use ($acuan) {
                $q->where(function ($qNormal) {
                    $qNormal->whereNull('aset.status_aset')
                        ->orWhere('aset.status_aset', '!=', 'dihapuskan');
                })->orWhere(function ($qDihapuskan) use ($acuan) {
                    // Aset saat ini berstatus 'dihapuskan', tetapi tanggal SK-nya jatuh SETELAH tanggal acuan laporan
                    $qDihapuskan->where('aset.status_aset', 'dihapuskan')
                        ->whereExists(function ($sub) use ($acuan) {
                            $sub->select(DB::raw(1))
                                ->from('usulan_penghapusan')
                                ->whereColumn('usulan_penghapusan.id_aset', 'aset.id_aset')
                                ->where('usulan_penghapusan.status_usulan', 'disetujui')
                                ->where('usulan_penghapusan.tanggal_sk', '>', $acuan);
                        });
                });
            })
            // Pastikan tidak ada SK penghapusan disetujui yang sah pada atau sebelum tanggal acuan
            ->whereNotExists(function ($sub) use ($acuan) {
                $sub->select(DB::raw(1))
                    ->from('usulan_penghapusan')
                    ->whereColumn('usulan_penghapusan.id_aset', 'aset.id_aset')
                    ->where('usulan_penghapusan.status_usulan', 'disetujui')
                    ->whereNotNull('usulan_penghapusan.tanggal_sk')
                    ->where('usulan_penghapusan.tanggal_sk', '<=', $acuan);
            });

        // Filter unit kepemilikan aset
        $this->filterAsetByUnit($query, $unitLokasiId);

        return $query;
    }

    /**
     * Filter query aset berdasarkan unit kepemilikan/penempatan secara konsisten:
     * 1. Aset dengan penempatan aktif: dikelompokkan sesuai lokasi penempatan terbarunya (ruangan penempatan aktif),
     *    terlepas dari siapa yang menginput aset.
     * 2. Aset dengan pemegang aktif (tanpa penempatan ruangan, mis. kendaraan): mengikuti unit pegawai pemegang.
     * 3. Aset tanpa penempatan & tanpa pemegang: dikelompokkan sementara berdasarkan unit kerja (SKPD) / lokasi pembuat aset.
     * 4. Setelah mutasi, aset otomatis masuk ke unit sesuai penempatan terbarunya.
     * 5. null ($unitLokasiId === null): Konsolidasi seluruh unit.
     */
    public function filterAsetByUnit(Builder $query, ?int $unitLokasiId): Builder
    {
        if ($unitLokasiId === null) {
            if ($this->scope->isAdminUpt()) {
                $skpdId = (int) $this->scope->skpdId();
                if ($skpdId === 7) {
                    return $this->filterAsetByUnit($query, 3);
                }
                if ($skpdId === 6) {
                    return $this->filterAsetByUnit($query, 2);
                }
                $lokasiUpt = $this->scope->idLokasi();
                if (! empty($lokasiUpt)) {
                    return $this->filterAsetByUnit($query, (int) $lokasiUpt[0]);
                }
            }

            return $query;
        }

        if ($unitLokasiId === 3) {
            // UPT PSBP (Lokasi 3 / SKPD 7)
            return $query->where(function ($q) {
                // Skenario 1: Memiliki penempatan ruangan aktif di UPT PSBP
                $q->whereHas('penempatanAktif.ruangan', function ($r) {
                    $r->where('id_lokasi', 3)->orWhere('id_skpd', 7);
                })
                // Skenario 2: Tidak memiliki penempatan ruangan aktif, tetapi memiliki pemegang aktif di UPT PSBP
                ->orWhere(function ($qPemegang) {
                    $qPemegang->whereDoesntHave('penempatanAktif')
                        ->whereHas('pemegangSaatIni.pegawai', function ($p) {
                            $p->where('id_skpd', 7)
                              ->orWhereHas('ruangan', fn ($r) => $r->where('id_lokasi', 3)->orWhere('id_skpd', 7));
                        });
                })
                // Skenario 3: Tidak memiliki penempatan & tidak memiliki pemegang (unplaced), milik UPT PSBP
                ->orWhere(function ($qUnplaced) {
                    $qUnplaced->whereDoesntHave('penempatanAktif')
                        ->whereDoesntHave('pemegangSaatIni')
                        ->where(function ($sub) {
                            $sub->where('aset.id_lokasi', 3)
                                ->orWhere('aset.id_skpd', 7);
                        });
                });
            });
        }

        if ($unitLokasiId === 2) {
            // UPT P2BTP (Lokasi 2 / SKPD 6)
            return $query->where(function ($q) {
                // Skenario 1: Memiliki penempatan ruangan aktif di UPT P2BTP
                $q->whereHas('penempatanAktif.ruangan', function ($r) {
                    $r->where('id_lokasi', 2)->orWhere('id_skpd', 6);
                })
                // Skenario 2: Tidak memiliki penempatan ruangan aktif, tetapi memiliki pemegang aktif di UPT P2BTP
                ->orWhere(function ($qPemegang) {
                    $qPemegang->whereDoesntHave('penempatanAktif')
                        ->whereHas('pemegangSaatIni.pegawai', function ($p) {
                            $p->where('id_skpd', 6)
                              ->orWhereHas('ruangan', fn ($r) => $r->where('id_lokasi', 2)->orWhere('id_skpd', 6));
                        });
                })
                // Skenario 3: Tidak memiliki penempatan & tidak memiliki pemegang (unplaced), milik UPT P2BTP
                ->orWhere(function ($qUnplaced) {
                    $qUnplaced->whereDoesntHave('penempatanAktif')
                        ->whereDoesntHave('pemegangSaatIni')
                        ->where(function ($sub) {
                            $sub->where('aset.id_lokasi', 2)
                                ->orWhere('aset.id_skpd', 6);
                        });
                });
            });
        }

        if ($unitLokasiId === 1) {
            // Kantor Dinas Perkebunan (Lokasi 1 / SKPD 1..5 / Bukan UPT)
            return $query->where(function ($q) {
                // Skenario 1: Memiliki penempatan ruangan aktif di Dinas (ruangan bukan di UPT)
                $q->whereHas('penempatanAktif.ruangan', function ($r) {
                    $r->where(function ($sub) {
                        $sub->where('id_lokasi', 1)
                            ->orWhere(function ($s) {
                                $s->whereNotIn('id_lokasi', [2, 3])
                                  ->where(function ($sk) {
                                      $sk->whereNull('id_skpd')->orWhereNotIn('id_skpd', [6, 7]);
                                  });
                            });
                    });
                })
                // Skenario 2: Tidak memiliki penempatan ruangan aktif, tetapi memiliki pemegang aktif di Dinas (pegawai bukan UPT)
                ->orWhere(function ($qPemegang) {
                    $qPemegang->whereDoesntHave('penempatanAktif')
                        ->whereHas('pemegangSaatIni.pegawai', function ($p) {
                            $p->where(function ($sub) {
                                $sub->whereNull('id_skpd')->orWhereNotIn('id_skpd', [6, 7]);
                            })->whereDoesntHave('ruangan', fn ($r) => $r->whereIn('id_lokasi', [2, 3])->orWhereIn('id_skpd', [6, 7]));
                        });
                })
                // Skenario 3: Tidak memiliki penempatan & tidak memiliki pemegang (unplaced), aset Dinas (bukan UPT)
                ->orWhere(function ($qUnplaced) {
                    $qUnplaced->whereDoesntHave('penempatanAktif')
                        ->whereDoesntHave('pemegangSaatIni')
                        ->where(function ($sub) {
                            $sub->where(function ($s) {
                                $s->whereNull('aset.id_lokasi')
                                  ->orWhere('aset.id_lokasi', 1)
                                  ->orWhereNotIn('aset.id_lokasi', [2, 3]);
                            })->where(function ($s) {
                                $s->whereNull('aset.id_skpd')
                                  ->orWhereNotIn('aset.id_skpd', [6, 7]);
                            });
                        });
                });
            });
        }

        return $this->scope->terapkan($query);
    }

    /**
     * Hitung seluruh metrik eksekutif Phase 1.
     */
    public function kalkulasi(
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        CarbonInterface $acuan,
        ?int $unitLokasiId = null
    ): array {
        // 1. Ambil koleksi aset yang sah masuk saldo neraca per tanggal acuan
        $asetList = $this->asetQueryAt($acuan, $unitLokasiId)
            ->with([
                'barang.kategori',
                'pemegangSaatIni.pegawai.ruangan.lokasi',
                'penempatanAktif.ruangan.lokasi',
                'lokasi',
                'skpd',
            ])
            ->get();

        $totalAset = $asetList->count();
        $barangCount = 0;
        $kendaraanCount = 0;

        $totalNilaiPerolehanAset = 0.0;
        $totalNilaiBukuAset = 0.0;
        $totalAkumulasiPenyusutanAset = 0.0;

        $kondisiBaik = 0;
        $kondisiRusakRingan = 0;
        $kondisiRusakBerat = 0;
        $kondisiLainnya = 0;

        $masaHabis = 0;
        $masaKritis = 0;
        $masaNormal = 0;
        $masaNan = 0;

        $memilikiPenempatanAtauPemegang = 0;

        foreach ($asetList as $aset) {
            if ($aset->is_kendaraan) {
                $kendaraanCount++;
            } else {
                $barangCount++;
            }

            $nilaiPerolehan = $aset->nilai_perolehan !== null ? (float) $aset->nilai_perolehan : 0.0;
            $totalNilaiPerolehanAset += $nilaiPerolehan;

            // Evaluasi nilai buku dan masa manfaat terhadap TANGGAL ACUAN laporan
            $eval = $this->masaPakai->evaluasi($aset, $acuan);
            $nilaiBuku = $eval['nilai_buku'] !== null ? (float) $eval['nilai_buku'] : $nilaiPerolehan;
            $totalNilaiBukuAset += $nilaiBuku;

            $penyusutan = max(0.0, $nilaiPerolehan - $nilaiBuku);
            $totalAkumulasiPenyusutanAset += $penyusutan;

            match ($eval['masa_status']) {
                'habis' => $masaHabis++,
                'kritis' => $masaKritis++,
                'normal' => $masaNormal++,
                default => $masaNan++,
            };

            match ($aset->kondisi) {
                'Baik' => $kondisiBaik++,
                'Rusak Ringan' => $kondisiRusakRingan++,
                'Rusak Berat', 'Rusak', 'Rusak Berat / Tidak Layak' => $kondisiRusakBerat++,
                default => $kondisiLainnya++,
            };

            // Himpunan Union penempatan atau pemegang (tidak terhitung ganda)
            if ($aset->penempatanAktif !== null || $aset->pemegangSaatIni !== null) {
                $memilikiPenempatanAtauPemegang++;
            }
        }

        $persentasePenempatanPemegang = $totalAset > 0
            ? round(($memilikiPenempatanAtauPemegang / $totalAset) * 100, 2)
            : 0.0;

        // 2. Register Tanah KIB A (Terpisah dari Aset Bergerak)
        // Khusus Admin UPT PSBP dan Admin UPT P2BTP, nilai KIB A pada ekspor bernilai 0.
        // Admin Aset Dinas menampilkan nilai sesuai data nyata.
        $isDinas = ($unitLokasiId === 1 || ($unitLokasiId === null && $this->scope->isAdminAset()));
        $bisaAksesTanah = $isDinas;
        $totalBidangTanah = 0;
        $totalLuasTanah = 0.0;
        $totalNilaiPerolehanTanah = 0.0;
        $totalNilaiBukuTanah = 0.0;

        if ($isDinas) {
            $tanahQuery = Tanah::query()
                ->where(function ($q) use ($acuan) {
                    $q->whereNotNull('tanggal_perolehan')
                      ->where('tanggal_perolehan', '<=', $acuan)
                      ->orWhere(function ($qNull) use ($acuan) {
                          $qNull->whereNull('tanggal_perolehan')
                                ->where(function ($qb) use ($acuan) {
                                    $qb->whereNull('tanggal_buku')
                                       ->orWhere('tanggal_buku', '<=', $acuan);
                                });
                      });
                });

            $totalBidangTanah = (clone $tanahQuery)->count();
            $totalLuasTanah = (float) ((clone $tanahQuery)->sum('luas_tanah') ?? 0.0);
            $totalNilaiPerolehanTanah = (float) ((clone $tanahQuery)->sum('nilai_perolehan') ?? 0.0);
            $totalNilaiBukuTanah = $totalNilaiPerolehanTanah; // Tanah tidak disusutkan
        }

        // Nilai Finansial Gabungan
        $totalNilaiPerolehanGabungan = $totalNilaiPerolehanAset + $totalNilaiPerolehanTanah;
        $totalAkumulasiPenyusutanGabungan = $totalAkumulasiPenyusutanAset; // Tanah penyusutan = 0
        $totalNilaiBukuGabungan = $totalNilaiBukuAset + $totalNilaiBukuTanah;

        // 3. Progres Sensus Fisik Siklus 3 Tahunan
        $siklusSensusAktif = PeriodeSensus::periodeAktif($acuan);
        [$mulaiAktif] = PeriodeSensus::urai($siklusSensusAktif);
        $periodeCocok = [
            $siklusSensusAktif,
            PeriodeSensus::format($mulaiAktif, $mulaiAktif + PeriodeSensus::INTERVAL_TAHUN),
        ];
        $asetIdsInReport = $asetList->pluck('id_aset')->all();
        $sudahSensusCount = 0;
        $sensusBaik = 0;
        $sensusRusakRingan = 0;
        $sensusRusakBerat = 0;

        if (! empty($asetIdsInReport)) {
            $sensusRecords = SensusAset::whereIn('periode', $periodeCocok)
                ->whereIn('id_aset', $asetIdsInReport)
                ->get(['id_aset', 'hasil_sensus']);

            $sudahSensusCount = $sensusRecords->unique('id_aset')->count();
            foreach ($sensusRecords as $rec) {
                match ($rec->hasil_sensus) {
                    'Baik' => $sensusBaik++,
                    'Rusak Ringan' => $sensusRusakRingan++,
                    'Rusak Berat' => $sensusRusakBerat++,
                    default => null,
                };
            }
        }

        $wajibSensusCount = count($asetIdsInReport);
        $belumSensusCount = max(0, $wajibSensusCount - $sudahSensusCount);
        $persentaseSensus = $wajibSensusCount > 0
            ? round(($sudahSensusCount / $wajibSensusCount) * 100, 2)
            : 0.0;

        // 4. Pajak Kendaraan (Flow & Snapshot Kepatuhan)
        $kendaraanAsetIds = $asetList->where('is_kendaraan', true)->pluck('id_aset')->all();
        $kendaraanIds = Kendaraan::whereIn('id_aset', $kendaraanAsetIds)->pluck('id_kendaraan')->all();

        // 4A. Flow: Realisasi Pembayaran Selama Periode Laporan (Khusus Pajak Tahunan)
        $pajakBayarQuery = PajakKendaraan::whereIn('id_kendaraan', $kendaraanIds)
            ->where('pajak_5_tahunan', false)
            ->whereNotNull('tanggal_bayar')
            ->whereBetween('tanggal_bayar', [$startDate, $endDate]);

        $transaksiBayarCount = (clone $pajakBayarQuery)->count();
        $totalNominalBayar = (float) ((clone $pajakBayarQuery)->sum(DB::raw('COALESCE(total_pajak, nominal, 0)')) ?? 0.0);
        $kendaraanBayarCount = (clone $pajakBayarQuery)->distinct('id_kendaraan')->count('id_kendaraan');

        // 4B. Snapshot: Status Kepatuhan Masa Berlaku PKB Tahunan per Tanggal Acuan
        $pajakAmanCount = 0;
        $pajakKritisCount = 0;
        $pajakTerlambatCount = 0;
        $pajakTanpaDataCount = 0;

        if (! empty($kendaraanIds)) {
            $kendaraanModels = Kendaraan::whereIn('id_kendaraan', $kendaraanIds)
                ->with([
                    'pajakTahunanAktif',
                    'pajak' => fn ($q) => $q->where('pajak_5_tahunan', false)
                        ->orderByDesc('tanggal_berakhir')
                        ->orderByDesc('id_pajak'),
                ])
                ->get();

            foreach ($kendaraanModels as $k) {
                // Prioritaskan Pajak Tahunan Aktif; jika tidak ada, fallback ke Pajak Tahunan terbaru
                $pajak = $k->pajakTahunanAktif ?? $k->pajak->first();
                if (! $pajak || ! $pajak->tanggal_berakhir) {
                    $pajakTanpaDataCount++;
                    continue;
                }

                $diff = (int) $acuan->diffInDays($pajak->tanggal_berakhir, false);
                if ($diff < 0) {
                    $pajakTerlambatCount++;
                } elseif ($diff <= 30) {
                    $pajakKritisCount++;
                } else {
                    $pajakAmanCount++;
                }
            }
        }

        // 5. Data Flow Tambahan: Mutasi dan Pengadaan Baru Periode Ini
        $pengadaanBaruCount = $asetList->whereBetween('tanggal_pengadaan', [$startDate, $endDate])->count();
        $pengadaanBaruNilai = (float) $asetList->whereBetween('tanggal_pengadaan', [$startDate, $endDate])->sum('nilai_perolehan');

        $mutasiAsetCount = MutasiAset::whereBetween('tanggal_mutasi', [$startDate, $endDate])
            ->whereHas('details.aset', fn ($a) => $this->filterAsetByUnit($a, $unitLokasiId))
            ->count();

        // 6. Pemisahan Populasi: Pengadaan Tahun Laporan vs Seluruh Aset Aktif
        $tahunLaporan = (int) $acuan->year;
        $asetPengadaanTahun = $asetList->filter(function ($a) use ($tahunLaporan, $acuan) {
            return $a->tanggal_pengadaan !== null
                && (int) $a->tanggal_pengadaan->year === $tahunLaporan
                && $a->tanggal_pengadaan <= $acuan;
        });

        $jumlahAsetPengadaan = $asetPengadaanTahun->count();
        $pengadaanBarangCount = $asetPengadaanTahun->where('is_kendaraan', false)->count();
        $pengadaanKendaraanCount = $asetPengadaanTahun->where('is_kendaraan', true)->count();
        $nilaiPengadaanTahun = (float) $asetPengadaanTahun->sum('nilai_perolehan');
        $nilaiPengadaanBarang = (float) $asetPengadaanTahun->where('is_kendaraan', false)->sum('nilai_perolehan');
        $nilaiPengadaanKendaraan = (float) $asetPengadaanTahun->where('is_kendaraan', true)->sum('nilai_perolehan');

        $totalAsetAktif = $totalAset;
        $totalBarangAktif = $barangCount;
        $totalKendaraanAktif = $kendaraanCount;
        $keteranganKondisi = 'Kondisi seluruh aset aktif berdasarkan data kondisi terakhir.';
        $keteranganPengadaan = "Aset yang diperoleh pada Tahun {$tahunLaporan}.";

        return [
            // KPI Utama (Pengadaan Tahun Laporan Terpilih)
            'tahun_laporan' => $tahunLaporan,
            'jumlah_aset_pengadaan' => $jumlahAsetPengadaan,
            'pengadaan_barang_count' => $pengadaanBarangCount,
            'pengadaan_kendaraan_count' => $pengadaanKendaraanCount,
            'nilai_pengadaan_tahun' => $nilaiPengadaanTahun,
            'nilai_pengadaan_barang' => $nilaiPengadaanBarang,
            'nilai_pengadaan_kendaraan' => $nilaiPengadaanKendaraan,
            'keterangan_pengadaan' => $keteranganPengadaan,

            // Seluruh Aset Aktif (Portofolio Saldo & Rekapitulasi Kondisi)
            'total_aset_aktif' => $totalAsetAktif,
            'total_barang_aktif' => $totalBarangAktif,
            'total_kendaraan_aktif' => $totalKendaraanAktif,
            'total_kondisi_aset' => $totalAsetAktif,
            'keterangan_kondisi' => $keteranganKondisi,

            // Portofolio Aset Bergerak (Saldo Neraca)
            'total_barang_dan_kendaraan' => $totalAset,
            'total_barang' => $barangCount,
            'total_kendaraan' => $kendaraanCount,
            'total_nilai_perolehan_gabungan' => $totalNilaiPerolehanGabungan,
            'total_akumulasi_penyusutan_gabungan' => $totalAkumulasiPenyusutanGabungan,
            'total_nilai_buku_gabungan' => $totalNilaiBukuGabungan,
            'total_nilai_perolehan_aset' => $totalNilaiPerolehanAset,
            'total_nilai_buku_aset' => $totalNilaiBukuAset,
            'persentase_penempatan_pemegang' => $persentasePenempatanPemegang,
            'memiliki_penempatan_pemegang_count' => $memilikiPenempatanAtauPemegang,

            // Kondisi Fisik Master Aset
            'kondisi_baik' => $kondisiBaik,
            'kondisi_rusak_ringan' => $kondisiRusakRingan,
            'kondisi_rusak_berat' => $kondisiRusakBerat,
            'kondisi_lainnya' => $kondisiLainnya,

            // Masa Manfaat
            'masa_habis' => $masaHabis,
            'masa_kritis' => $masaKritis,
            'masa_normal' => $masaNormal,
            'masa_nan' => $masaNan,

            // Register Tanah
            'bisa_akses_tanah' => $bisaAksesTanah,
            'total_bidang_tanah' => $totalBidangTanah,
            'total_luas_tanah' => $totalLuasTanah,
            'total_nilai_perolehan_tanah' => $totalNilaiPerolehanTanah,
            'total_nilai_buku_tanah' => $totalNilaiBukuTanah,

            // Sensus Fisik Siklus 3-Tahunan
            'siklus_sensus_aktif' => $siklusSensusAktif,
            'sensus_wajib_count' => $wajibSensusCount,
            'sensus_sudah_count' => $sudahSensusCount,
            'sensus_belum_count' => $belumSensusCount,
            'sensus_persentase' => $persentaseSensus,
            'sensus_baik' => $sensusBaik,
            'sensus_rusak_ringan' => $sensusRusakRingan,
            'sensus_rusak_berat' => $sensusRusakBerat,

            // Pajak Kendaraan
            'pajak_flow_transaksi_count' => $transaksiBayarCount,
            'pajak_flow_total_nominal' => $totalNominalBayar,
            'pajak_flow_kendaraan_count' => $kendaraanBayarCount,
            'pajak_snapshot_aman' => $pajakAmanCount,
            'pajak_snapshot_kritis' => $pajakKritisCount,
            'pajak_snapshot_terlambat' => $pajakTerlambatCount,
            'pajak_snapshot_tanpa_data' => $pajakTanpaDataCount,

            // Aktivitas Flow Berjalan
            'pengadaan_baru_count' => $pengadaanBaruCount,
            'pengadaan_baru_nilai' => $pengadaanBaruNilai,
            'mutasi_aset_count' => $mutasiAsetCount,
        ];
    }

    /**
     * Kalkulasi seluruh dataset visualisasi grafik informasi eksekutif (Phase 2).
     *
     * Menerapkan:
     * - Opsi A: Rekonstruksi saldo historis aset per t_acuan.
     * - STRICTLY NO DRILLDOWN: murni visual informasi dengan hover tooltip.
     * - Pemisahan tegas KIB A Tanah (tidak disusutkan, tanpa atribut unit kerja).
     * - Barang dan Kendaraan saling lepas (tidak ada double-counting).
     * - Multi-tenancy mematuhi AsetScope dan TanahScope.
     *
     * @return array{
     *     kondisi: array{labels: array<int, string>, data: array<int, int>, persen: array<int, float>, total: int},
     *     kategori: array{labels: array<int, string>, data: array<int, int>, total: int},
     *     nilai: array{labels: array<int, string>, nilai_perolehan: array<int, float>, nilai_buku: array<int, float>, total_perolehan: float, total_buku: float},
     *     tren_pengadaan: array{labels: array<int, string>, data_count: array<int, int>, data_nilai: array<int, float>},
     *     kondisi_unit: array{labels: array<int, string>, baik: array<int, int>, rusak_ringan: array<int, int>, rusak_berat: array<int, int>},
     *     aktivitas: array{labels: array<int, string>, pengadaan: array<int, int>, mutasi: array<int, int>, total_pengadaan: int, total_mutasi: int}
     * }
     */
    public function kalkulasiGrafik(
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        CarbonInterface $acuan,
        ?int $unitLokasiId = null,
        string $jenisPeriode = 'bulanan',
        ?Collection $asetList = null
    ): array {
        // Ambil data aset snapshot per t_acuan jika belum disediakan oleh pemanggil
        if ($asetList === null) {
            $asetList = $this->asetQueryAt($acuan, $unitLokasiId)
                ->with([
                    'barang.kategori',
                    'lokasi',
                    'skpd',
                    'pemegangSaatIni.pegawai.ruangan.lokasi',
                    'penempatanAktif.ruangan',
                ])
                ->get();
        } else {
            $asetList->loadMissing([
                'barang.kategori',
                'lokasi',
                'skpd',
                'pemegangSaatIni.pegawai.ruangan.lokasi',
                'penempatanAktif.ruangan',
            ]);
        }

        $totalAset = $asetList->count();
        $bisaAksesTanah = $this->tanahScope->bolehAkses();

        // -------------------------------------------------------------
        // 1. KOMPOSISI KONDISI ASET (Donut Chart)
        // -------------------------------------------------------------
        $kondisiBaik = 0;
        $kondisiRusakRingan = 0;
        $kondisiRusakBerat = 0;
        $kondisiLainnya = 0;

        foreach ($asetList as $aset) {
            match ($aset->kondisi) {
                'Baik' => $kondisiBaik++,
                'Rusak Ringan' => $kondisiRusakRingan++,
                'Rusak Berat', 'Rusak', 'Rusak Berat / Tidak Layak' => $kondisiRusakBerat++,
                default => $kondisiLainnya++,
            };
        }

        $labelsKondisi = ['Baik', 'Rusak Ringan', 'Rusak Berat'];
        $dataKondisi = [$kondisiBaik, $kondisiRusakRingan, $kondisiRusakBerat];
        $persenKondisi = [
            $totalAset > 0 ? round(($kondisiBaik / $totalAset) * 100, 1) : 0.0,
            $totalAset > 0 ? round(($kondisiRusakRingan / $totalAset) * 100, 1) : 0.0,
            $totalAset > 0 ? round(($kondisiRusakBerat / $totalAset) * 100, 1) : 0.0,
        ];

        if ($kondisiLainnya > 0) {
            $labelsKondisi[] = 'Lainnya';
            $dataKondisi[] = $kondisiLainnya;
            $persenKondisi[] = $totalAset > 0 ? round(($kondisiLainnya / $totalAset) * 100, 1) : 0.0;
        }

        $grafikKondisi = [
            'labels' => $labelsKondisi,
            'data' => $dataKondisi,
            'persen' => $persenKondisi,
            'total' => $totalAset,
            'keterangan' => 'Kondisi seluruh aset aktif berdasarkan data kondisi terakhir.',
        ];

        // -------------------------------------------------------------
        // 2. DISTRIBUSI KATEGORI ASET (Bar Chart)
        // Menampilkan 3 kelompok: Peralatan dan Mesin, Kendaraan Bermotor,
        // dan Tanah (KIB A)* sesuai TanahScope.
        // Aset tanpa relasi kategori ditangani secara eksplisit sebagai anomali ('Tanpa Kategori').
        // -------------------------------------------------------------
        $kategoriCounts = [];
        $kendaraanCount = 0;

        foreach ($asetList as $aset) {
            if ($aset->is_kendaraan) {
                $kendaraanCount++;
            } else {
                // Ambil kategori resmi dari relasi master_barang.kategori
                $namaKategori = $aset->barang?->kategori?->nama_kategori;
                if ($namaKategori === null || trim($namaKategori) === '') {
                    $namaKategori = 'Tanpa Kategori';
                }
                $kategoriCounts[$namaKategori] = ($kategoriCounts[$namaKategori] ?? 0) + 1;
            }
        }

        // Urutkan kategori barang terbanyak
        arsort($kategoriCounts);

        $labelsKategori = [];
        $dataKategori = [];

        foreach ($kategoriCounts as $katNama => $count) {
            $labelsKategori[] = $katNama;
            $dataKategori[] = $count;
        }

        // Tambahkan Kendaraan Bermotor sebagai kelompok resmi tersendiri
        $labelsKategori[] = 'Kendaraan Bermotor';
        $dataKategori[] = $kendaraanCount;

        // Tambahkan Tanah KIB A secara terpisah (Khusus Admin UPT PSBP & P2BTP bernilai 0)
        $isDinas = ($unitLokasiId === 1 || ($unitLokasiId === null && $this->scope->isAdminAset()));
        $totalBidangTanah = 0;
        if ($isDinas) {
            $totalBidangTanah = Tanah::query()
                ->where(function ($q) use ($acuan) {
                    $q->whereNotNull('tanggal_perolehan')
                      ->where('tanggal_perolehan', '<=', $acuan)
                      ->orWhere(function ($qNull) use ($acuan) {
                          $qNull->whereNull('tanggal_perolehan')
                                ->where(function ($qb) use ($acuan) {
                                    $qb->whereNull('tanggal_buku')
                                       ->orWhere('tanggal_buku', '<=', $acuan);
                                });
                      });
                })->count();

            $labelsKategori[] = 'Tanah (KIB A)*';
            $dataKategori[] = $totalBidangTanah;
        }

        $grafikKategori = [
            'labels' => $labelsKategori,
            'data' => $dataKategori,
            'total' => array_sum($dataKategori),
        ];

        // -------------------------------------------------------------
        // 3. NILAI PEROLEHAN VS NILAI BUKU (Bar Chart)
        // Membandingkan nilai perolehan dan nilai buku aset bergerak.
        // Hanya mencakup Barang Inventaris dan Kendaraan Bermotor
        // (Tanah KIB A tidak disusutkan dan tidak masuk dalam cakupan nilai buku Monitoring Aset).
        // -------------------------------------------------------------
        $perolehanBarang = 0.0;
        $bukuBarang = 0.0;
        $perolehanKendaraan = 0.0;
        $bukuKendaraan = 0.0;

        foreach ($asetList as $aset) {
            $np = $aset->nilai_perolehan !== null ? (float) $aset->nilai_perolehan : 0.0;
            $eval = $this->masaPakai->evaluasi($aset, $acuan);
            $nb = $eval['nilai_buku'] !== null ? (float) $eval['nilai_buku'] : $np;

            if ($aset->is_kendaraan) {
                $perolehanKendaraan += $np;
                $bukuKendaraan += $nb;
            } else {
                $perolehanBarang += $np;
                $bukuBarang += $nb;
            }
        }

        $labelsNilai = ['Barang Inventaris', 'Kendaraan Bermotor'];
        $dataPerolehan = [$perolehanBarang, $perolehanKendaraan];
        $dataBuku = [$bukuBarang, $bukuKendaraan];

        $grafikNilai = [
            'labels' => $labelsNilai,
            'nilai_perolehan' => $dataPerolehan,
            'nilai_buku' => $dataBuku,
            'total_perolehan' => array_sum($dataPerolehan),
            'total_buku' => array_sum($dataBuku),
        ];

        // -------------------------------------------------------------
        // 4. TREN PENGADAAN ASET (Line/Bar Chart)
        // Berdasarkan tahun perolehan (aset.tanggal_pengadaan), abaikan NULL.
        // -------------------------------------------------------------
        $asetDenganTanggal = $asetList->filter(fn ($a) => $a->tanggal_pengadaan !== null && $a->tanggal_pengadaan <= $acuan);
        $trenPerTahun = [];

        foreach ($asetDenganTanggal as $a) {
            $thn = (int) $a->tanggal_pengadaan->format('Y');
            if (! isset($trenPerTahun[$thn])) {
                $trenPerTahun[$thn] = ['count' => 0, 'nilai' => 0.0];
            }
            $trenPerTahun[$thn]['count']++;
            $trenPerTahun[$thn]['nilai'] += ($a->nilai_perolehan !== null ? (float) $a->nilai_perolehan : 0.0);
        }

        // Tentukan rentang tahun: tepat 5 tahun terakhir sampai dengan tahun acuan
        $tahunAcuan = (int) $acuan->year;
        $startTahun = $tahunAcuan - 4;

        $labelsTren = [];
        $dataTrenCount = [];
        $dataTrenNilai = [];

        for ($t = $startTahun; $t <= $tahunAcuan; $t++) {
            $labelsTren[] = (string) $t;
            $dataTrenCount[] = $trenPerTahun[$t]['count'] ?? 0;
            $dataTrenNilai[] = $trenPerTahun[$t]['nilai'] ?? 0.0;
        }

        $grafikTren = [
            'labels' => $labelsTren,
            'data_count' => $dataTrenCount,
            'data_nilai' => $dataTrenNilai,
        ];

        // -------------------------------------------------------------
        // 5. KONDISI ASET BERDASARKAN LOKASI FISIK (Grouped Stacked Bar Chart)
        // Pengelompokan murni berdasarkan LOKASI FISIK (aset.id_lokasi).
        // Setiap lokasi menampilkan 2 kelompok aset: Barang Inventaris & Kendaraan Bermotor.
        // Masing-masing kelompok memiliki stacked series kondisi (Baik, Rusak Ringan, Rusak Berat).
        // Aset tanpa lokasi masuk kategori "Belum Ditetapkan Lokasi".
        // Tanah KIB A dikecualikan sepenuhnya dari grafik ini.
        // Dilengkapi ringkasan penempatan ruangan & pemegang tanpa drilldown.
        // -------------------------------------------------------------
        $lokasiRef = $this->scope->lokasiTerlihat();
        if ($unitLokasiId !== null) {
            $lokasiRef = $lokasiRef->where('id_lokasi', $unitLokasiId);
        }

        $namaLokasiList = [];
        foreach ($lokasiRef as $lRef) {
            $namaLokasiList[] = $lRef->nama_lokasi;
        }

        // Untuk Admin Aset (konsolidasi semua unit), pastikan urutan resmi lokasi
        if ($this->scope->cakupanPenuh() && $unitLokasiId === null) {
            $urutanResmi = [
                'Kantor Dinas Perkebunan Provinsi Jawa Timur',
                'Kantor UPT P2BTP',
                'Kantor UPT PSBP',
            ];
            $namaLokasiList = array_values(array_intersect($urutanResmi, $namaLokasiList));
            $namaLokasiList[] = 'Belum Ditetapkan Lokasi';
        }

        $fallbackUnitNama = $unitLokasiId !== null ? Lokasi::find($unitLokasiId)?->nama_lokasi : null;

        $rekapLokasi = [];
        foreach ($namaLokasiList as $nl) {
            $rekapLokasi[$nl] = [
                'lokasi' => $nl,
                'barang_baik' => 0,
                'barang_rusak_ringan' => 0,
                'barang_rusak_berat' => 0,
                'barang_belum_diisi' => 0,
                'barang_total' => 0,

                'kendaraan_baik' => 0,
                'kendaraan_rusak_ringan' => 0,
                'kendaraan_rusak_berat' => 0,
                'kendaraan_belum_diisi' => 0,
                'kendaraan_total' => 0,

                'baik' => 0,
                'rusak_ringan' => 0,
                'rusak_berat' => 0,
                'belum_diisi' => 0,
                'total_aset' => 0,

                'dengan_pemegang' => 0,
                'tanpa_pemegang' => 0,
                'dengan_ruangan' => 0,
                'tanpa_ruangan' => 0,
            ];
        }

        foreach ($asetList as $aset) {
            // Tentukan lokasi penempatan / unit aset:
            // 1. Lokasi penempatan ruangan aktif terbaru
            // 2. Lokasi ruangan pemegang aktif terkini
            // 3. Fallback unit kerja (SKPD) / lokasi pembuat aset
            $namaLokasi = null;

            if ($aset->is_kendaraan) {
                // Skenario 5C: Prioritas Lokasi Kendaraan
                // Prioritas 1: Master Lokasi (aset.id_lokasi)
                if ($aset->id_lokasi) {
                    if ($aset->id_lokasi === 3) {
                        $namaLokasi = 'Kantor UPT PSBP';
                    } elseif ($aset->id_lokasi === 2) {
                        $namaLokasi = 'Kantor UPT P2BTP';
                    } elseif ($aset->id_lokasi === 1) {
                        $namaLokasi = 'Kantor Dinas Perkebunan Provinsi Jawa Timur';
                    } else {
                        $namaLokasi = $aset->lokasi?->nama_lokasi ?? 'Kantor Dinas Perkebunan Provinsi Jawa Timur';
                    }
                } elseif ($aset->pemegangSaatIni?->pegawai) {
                    // Prioritas 2: Fallback ke Ruangan Pemegang Aktif jika master NULL
                    $p = $aset->pemegangSaatIni->pegawai;
                    $rLokId = $p->ruangan?->id_lokasi;
                    if ($rLokId === 3 || $p->id_skpd === 7) {
                        $namaLokasi = 'Kantor UPT PSBP';
                    } elseif ($rLokId === 2 || $p->id_skpd === 6) {
                        $namaLokasi = 'Kantor UPT P2BTP';
                    } elseif ($rLokId === 1) {
                        $namaLokasi = 'Kantor Dinas Perkebunan Provinsi Jawa Timur';
                    } elseif ($rLokId !== null) {
                        $namaLokasi = $p->ruangan?->lokasi?->nama_lokasi;
                    }
                }
                // Prioritas 3: Masuk ke Belum Ditetapkan Lokasi jika master NULL dan tanpa ruangan pemegang
                if (! $namaLokasi) {
                    $namaLokasi = 'Belum Ditetapkan Lokasi';
                }
            } else {
                // Barang Inventaris
                // Prioritas 1: Penempatan Ruangan Aktif
                if ($aset->penempatanAktif?->ruangan) {
                    $r = $aset->penempatanAktif->ruangan;
                    if ($r->id_lokasi === 3 || $r->id_skpd === 7) {
                        $namaLokasi = 'Kantor UPT PSBP';
                    } elseif ($r->id_lokasi === 2 || $r->id_skpd === 6) {
                        $namaLokasi = 'Kantor UPT P2BTP';
                    } else {
                        $namaLokasi = $r->lokasi?->nama_lokasi ?? 'Kantor Dinas Perkebunan Provinsi Jawa Timur';
                    }
                } elseif ($aset->id_lokasi) {
                    // Prioritas 2: Master Lokasi aset
                    if ($aset->id_lokasi === 3) {
                        $namaLokasi = 'Kantor UPT PSBP';
                    } elseif ($aset->id_lokasi === 2) {
                        $namaLokasi = 'Kantor UPT P2BTP';
                    } elseif ($aset->id_lokasi === 1) {
                        $namaLokasi = 'Kantor Dinas Perkebunan Provinsi Jawa Timur';
                    } else {
                        $namaLokasi = $aset->lokasi?->nama_lokasi ?? 'Kantor Dinas Perkebunan Provinsi Jawa Timur';
                    }
                } else {
                    // Prioritas 3: Belum Ditetapkan Lokasi (Barang TIDAK boleh fallback ke ruangan pemegang)
                    $namaLokasi = 'Belum Ditetapkan Lokasi';
                }
            }

            // Jika lokasi belum terdaftar di array rekap, daftarkan
            if (! isset($rekapLokasi[$namaLokasi])) {
                $rekapLokasi[$namaLokasi] = [
                    'lokasi' => $namaLokasi,
                    'barang_baik' => 0,
                    'barang_rusak_ringan' => 0,
                    'barang_rusak_berat' => 0,
                    'barang_belum_diisi' => 0,
                    'barang_total' => 0,

                    'kendaraan_baik' => 0,
                    'kendaraan_rusak_ringan' => 0,
                    'kendaraan_rusak_berat' => 0,
                    'kendaraan_belum_diisi' => 0,
                    'kendaraan_total' => 0,

                    'baik' => 0,
                    'rusak_ringan' => 0,
                    'rusak_berat' => 0,
                    'belum_diisi' => 0,
                    'total_aset' => 0,

                    'dengan_pemegang' => 0,
                    'tanpa_pemegang' => 0,
                    'dengan_ruangan' => 0,
                    'tanpa_ruangan' => 0,
                ];
            }

            // Evaluasi kondisi fisik aset
            $kondisiKey = match ($aset->kondisi) {
                'Baik' => 'baik',
                'Rusak Ringan' => 'rusak_ringan',
                'Rusak Berat', 'Rusak', 'Rusak Berat / Tidak Layak' => 'rusak_berat',
                default => 'belum_diisi',
            };

            $prefix = $aset->is_kendaraan ? 'kendaraan_' : 'barang_';
            $rekapLokasi[$namaLokasi][$prefix . $kondisiKey]++;
            $rekapLokasi[$namaLokasi][$prefix . 'total']++;

            $rekapLokasi[$namaLokasi][$kondisiKey]++;
            $rekapLokasi[$namaLokasi]['total_aset']++;

            // Evaluasi Pemegang (riwayat aktif pemegangSaatIni)
            if ($aset->pemegangSaatIni !== null) {
                $rekapLokasi[$namaLokasi]['dengan_pemegang']++;
            } else {
                $rekapLokasi[$namaLokasi]['tanpa_pemegang']++;
            }

            // Evaluasi Penempatan Ruangan (riwayat aktif penempatanAktif)
            if ($aset->penempatanAktif !== null) {
                $rekapLokasi[$namaLokasi]['dengan_ruangan']++;
            } else {
                $rekapLokasi[$namaLokasi]['tanpa_ruangan']++;
            }
        }

        $labelsLokasi = [];
        $barangBaik = [];
        $barangRusakRingan = [];
        $barangRusakBerat = [];
        $barangBelumDiisi = [];

        $kendaraanBaik = [];
        $kendaraanRusakRingan = [];
        $kendaraanRusakBerat = [];
        $kendaraanBelumDiisi = [];

        // Agregat gabungan (kompatibilitas mundur dengan test & consumer lama)
        $unitBaik = [];
        $unitRusakRingan = [];
        $unitRusakBerat = [];
        $unitBelumDiisi = [];

        $ringkasanPerLokasi = [];
        $totalRingkasan = [
            'lokasi' => 'Total Konsolidasi',
            'total_aset' => 0,
            'barang_count' => 0,
            'kendaraan_count' => 0,
            'dengan_pemegang' => 0,
            'tanpa_pemegang' => 0,
            'dengan_ruangan' => 0,
            'tanpa_ruangan' => 0,
        ];

        foreach ($rekapLokasi as $namaLok => $r) {
            $labelsLokasi[] = $namaLok;

            $barangBaik[] = $r['barang_baik'];
            $barangRusakRingan[] = $r['barang_rusak_ringan'];
            $barangRusakBerat[] = $r['barang_rusak_berat'];
            $barangBelumDiisi[] = $r['barang_belum_diisi'];

            $kendaraanBaik[] = $r['kendaraan_baik'];
            $kendaraanRusakRingan[] = $r['kendaraan_rusak_ringan'];
            $kendaraanRusakBerat[] = $r['kendaraan_rusak_berat'];
            $kendaraanBelumDiisi[] = $r['kendaraan_belum_diisi'];

            $unitBaik[] = $r['baik'];
            $unitRusakRingan[] = $r['rusak_ringan'];
            $unitRusakBerat[] = $r['rusak_berat'];
            $unitBelumDiisi[] = $r['belum_diisi'];

            $ringkasanPerLokasi[] = [
                'lokasi' => $namaLok,
                'total_aset' => $r['total_aset'],
                'barang_count' => $r['barang_total'],
                'kendaraan_count' => $r['kendaraan_total'],
                'dengan_pemegang' => $r['dengan_pemegang'],
                'tanpa_pemegang' => $r['tanpa_pemegang'],
                'dengan_ruangan' => $r['dengan_ruangan'],
                'tanpa_ruangan' => $r['tanpa_ruangan'],
            ];

            $totalRingkasan['total_aset'] += $r['total_aset'];
            $totalRingkasan['barang_count'] += $r['barang_total'];
            $totalRingkasan['kendaraan_count'] += $r['kendaraan_total'];
            $totalRingkasan['dengan_pemegang'] += $r['dengan_pemegang'];
            $totalRingkasan['tanpa_pemegang'] += $r['tanpa_pemegang'];
            $totalRingkasan['dengan_ruangan'] += $r['dengan_ruangan'];
            $totalRingkasan['tanpa_ruangan'] += $r['tanpa_ruangan'];
        }

        $grafikKondisiUnit = [
            'labels' => $labelsLokasi,
            'baik' => $unitBaik,
            'rusak_ringan' => $unitRusakRingan,
            'rusak_berat' => $unitRusakBerat,
            'belum_diisi' => $unitBelumDiisi,

            // Rincian Grouped Stacked Bar (Barang vs Kendaraan)
            'barang_baik' => $barangBaik,
            'barang_rusak_ringan' => $barangRusakRingan,
            'barang_rusak_berat' => $barangRusakBerat,
            'barang_belum_diisi' => $barangBelumDiisi,

            'kendaraan_baik' => $kendaraanBaik,
            'kendaraan_rusak_ringan' => $kendaraanRusakRingan,
            'kendaraan_rusak_berat' => $kendaraanRusakBerat,
            'kendaraan_belum_diisi' => $kendaraanBelumDiisi,

            // Informasi Pendukung Pemegang & Ruangan (Tanpa Drilldown)
            'ringkasan' => [
                'per_lokasi' => $ringkasanPerLokasi,
                'total' => $totalRingkasan,
            ],
        ];

        // -------------------------------------------------------------
        // 6. AKTIVITAS ASET (Pengadaan Baru vs Mutasi) DALAM PERIODE
        // Berdasarkan data transaksi riil dalam rentang [startDate, endDate]
        // -------------------------------------------------------------
        $labelsAktivitas = [];
        $dataAktivitasPengadaan = [];
        $dataAktivitasMutasi = [];

        if (strtolower($jenisPeriode) === 'tahunan') {
            $bulanNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            $tahunAktif = $acuan->year;

            // Ambil mutasi dalam tahun tersebut sesuai AsetScope
            $mutasiTahun = MutasiAset::whereBetween('tanggal_mutasi', [$startDate, $endDate])
                ->whereHas('details.aset', fn ($a) => $this->scope->terapkan($a))
                ->get(['id_mutasi', 'tanggal_mutasi']);

            for ($m = 1; $m <= 12; $m++) {
                $labelsAktivitas[] = $bulanNames[$m - 1];

                $mStart = Carbon::create($tahunAktif, $m, 1)->startOfMonth();
                $mEnd = Carbon::create($tahunAktif, $m, 1)->endOfMonth();

                $pengadaanBulan = $asetList->filter(function ($a) use ($mStart, $mEnd) {
                    return $a->tanggal_pengadaan !== null
                        && $a->tanggal_pengadaan >= $mStart
                        && $a->tanggal_pengadaan <= $mEnd;
                })->count();

                $mutasiBulan = $mutasiTahun->filter(function ($mu) use ($mStart, $mEnd) {
                    return $mu->tanggal_mutasi !== null
                        && $mu->tanggal_mutasi >= $mStart
                        && $mu->tanggal_mutasi <= $mEnd;
                })->count();

                $dataAktivitasPengadaan[] = $pengadaanBulan;
                $dataAktivitasMutasi[] = $mutasiBulan;
            }
        } else {
            // Laporan Bulanan: rincian per 4 interval mingguan dalam bulan tersebut
            $daysInMonth = (int) $endDate->day;
            $intervals = [
                ['label' => 'Tgl 1 - 7', 'start' => 1, 'end' => 7],
                ['label' => 'Tgl 8 - 14', 'start' => 8, 'end' => 14],
                ['label' => 'Tgl 15 - 21', 'start' => 15, 'end' => 21],
                ['label' => "Tgl 22 - {$daysInMonth}", 'start' => 22, 'end' => $daysInMonth],
            ];

            $mutasiBulan = MutasiAset::whereBetween('tanggal_mutasi', [$startDate, $endDate])
                ->whereHas('details.aset', fn ($a) => $this->scope->terapkan($a))
                ->get(['id_mutasi', 'tanggal_mutasi']);

            foreach ($intervals as $itv) {
                $labelsAktivitas[] = $itv['label'];

                $iStart = Carbon::create($acuan->year, $acuan->month, $itv['start'])->startOfDay();
                $iEnd = Carbon::create($acuan->year, $acuan->month, $itv['end'])->endOfDay();

                $pengadaanItv = $asetList->filter(function ($a) use ($iStart, $iEnd) {
                    return $a->tanggal_pengadaan !== null
                        && $a->tanggal_pengadaan >= $iStart
                        && $a->tanggal_pengadaan <= $iEnd;
                })->count();

                $mutasiItv = $mutasiBulan->filter(function ($mu) use ($iStart, $iEnd) {
                    return $mu->tanggal_mutasi !== null
                        && $mu->tanggal_mutasi >= $iStart
                        && $mu->tanggal_mutasi <= $iEnd;
                })->count();

                $dataAktivitasPengadaan[] = $pengadaanItv;
                $dataAktivitasMutasi[] = $mutasiItv;
            }
        }

        $grafikAktivitas = [
            'labels' => $labelsAktivitas,
            'pengadaan' => $dataAktivitasPengadaan,
            'mutasi' => $dataAktivitasMutasi,
            'total_pengadaan' => array_sum($dataAktivitasPengadaan),
            'total_mutasi' => array_sum($dataAktivitasMutasi),
        ];

        return [
            'kondisi' => $grafikKondisi,
            'kategori' => $grafikKategori,
            'nilai' => $grafikNilai,
            'tren_pengadaan' => $grafikTren,
            'kondisi_unit' => $grafikKondisiUnit,
            'aktivitas' => $grafikAktivitas,
        ];
    }
}
