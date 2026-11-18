<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\SensusAset;
use App\Models\UsulanPenghapusan;
use App\Services\AsetScope;
use App\Services\MasaPakaiAset;
use App\Services\MonitoringAsetExporter;
use App\Services\PeriodeSensus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MonitoringAsetController extends Controller
{
    private readonly MonitoringAsetExporter $exporter;

    public function __construct(
        private readonly AsetScope $scope,
        private readonly MasaPakaiAset $masaPakai,
        ?MonitoringAsetExporter $exporter = null
    ) {
        $this->exporter = $exporter ?? app(MonitoringAsetExporter::class);
    }

    /**
     * Pusat pengawasan nilai buku & masa manfaat aset.
     */
    public function index(Request $request)
    {
        $daftarPeriode = PeriodeSensus::daftarPeriodeMonitoring();
        $periodeAktifList = PeriodeSensus::daftarPeriodeAktifList();

        $periodeAktif = PeriodeSensus::periodeAktif();
        $periodeDipilih = $request->filled('periode')
            ? PeriodeSensus::normalisasi($request->query('periode'))
            : $periodeAktif;

        if (! in_array($periodeDipilih, $daftarPeriode, true)) {
            [$m, $s] = PeriodeSensus::urai($periodeDipilih);
            if ($s - $m === 2) {
                array_unshift($daftarPeriode, $periodeDipilih);
            }
        }

        $query = $this->applyFilters($request, $periodeDipilih);

        $queryKpi = (clone $query)
            ->setEagerLoads([])
            ->select([
                'id_aset',
                'tanggal_pengadaan',
                'tanggal_habis_pakai',
                'nilai_perolehan',
            ]);

        [$totalNilaiBuku, $asetHabisCount, $asetKritisCount] = $this->ringkasan($queryKpi->get());

        // Card Aset Belum Disensus: menghitung aset yang belum disensus pada periode aktifnya masing-masing
        $asetBelumDisensusCount = PeriodeSensus::hitungAsetBelumDisensusAktif($query);

        $tahunList = Aset::query()
            ->selectRaw('YEAR(tanggal_pengadaan) as tahun')
            ->whereNotNull('tanggal_pengadaan')
            ->whereIn('status_aset', ['aktif', 'Aktif'])
            ->tap(fn ($q) => $this->terapkanScopeMonitoring($q))
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun');

        $pendingAsetIds = UsulanPenghapusan::where('status_usulan', 'diajukan')->pluck('id_aset');

        $asetList = $query->orderByDesc('id_aset')->paginate(10)->withQueryString();
        $asetList->setCollection($asetList->getCollection()->map(
            fn (Aset $a) => $this->toRow($a, $pendingAsetIds, $periodeDipilih)
        ));

        return view('monitoring-aset.index', compact(
            'asetList', 'totalNilaiBuku', 'asetHabisCount', 'asetKritisCount', 'asetBelumDisensusCount', 'tahunList',
            'periodeAktif', 'periodeDipilih', 'daftarPeriode', 'periodeAktifList'
        ));
    }

    /**
     * Ekspor rekap inventaris aset (.xlsx) dengan filter yang sedang aktif.
     * Mendukung 4 jenis laporan:
     * - seluruh: Rekap Seluruh Aset
     * - per_skpd: Rekap Aset per SKPD Pemegang
     * - per_pemegang: Rekap Aset per Pemegang
     * - per_ruangan: Rekap Aset per Ruangan
     */
    public function export(Request $request)
    {
        $periodeAktif = PeriodeSensus::periodeAktif();
        $periodeDipilih = $request->filled('periode')
            ? PeriodeSensus::normalisasi($request->query('periode'))
            : $periodeAktif;

        // Tentukan jenis laporan (dengan pemetaan backwards-compatibility)
        $jenisLaporan = trim((string) $request->query('jenis_laporan', 'seluruh'));
        $bentukRekap = trim((string) $request->query('bentuk_rekap'));

        if ($bentukRekap === 'per_pengguna') {
            $jenisLaporan = 'per_skpd';
        } elseif ($bentukRekap === 'melekat_ruangan') {
            $jenisLaporan = 'per_ruangan';
        }

        // Susun query dengan filter aktif dan AsetScope
        $query = $this->applyFilters($request, $periodeDipilih);

        // Eager load lengkap untuk mencegah N+1 query
        $with = [
            'barang',
            'kendaraan.platAktif',
            'pemegang' => fn ($q) => $q->where('status', 'aktif')->with('pegawai.skpd'),
            'penempatanAktif.ruangan.skpd',
            'sensus' => fn ($q) => $q->where('periode', $periodeDipilih),
        ];

        $asetList = $query
            ->with($with)
            ->orderByDesc('id_aset')
            ->get();

        $filterInfo = [
            'search' => trim((string) $request->query('search')),
            'tahun' => (int) $request->query('tahun') ?: null,
            'status' => $request->query('status'),
            'jenis_aset' => trim((string) $request->query('jenis_aset')),
            'penempatan' => trim((string) $request->query('penempatan')),
        ];

        $spreadsheet = $this->exporter->generate($asetList, $jenisLaporan, $periodeDipilih, $filterInfo);

        $slugJenis = match ($jenisLaporan) {
            MonitoringAsetExporter::JENIS_PER_SKPD, 'skpd' => 'rekap-aset-per-skpd',
            MonitoringAsetExporter::JENIS_PER_PEMEGANG, 'pemegang', 'per_pengguna' => 'rekap-aset-per-pemegang',
            MonitoringAsetExporter::JENIS_PER_RUANGAN, 'ruangan', 'melekat_ruangan' => 'rekap-aset-per-ruangan',
            default => 'rekap-seluruh-aset',
        };

        $filename = sprintf('%s-%s.xlsx', $slugJenis, now()->format('Ymd'));
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static fn () => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * Endpoint kompatibilitas mundur: Ekspor aset per pengguna.
     */
    public function exportPerPengguna(Request $request)
    {
        $request->merge(['jenis_laporan' => MonitoringAsetExporter::JENIS_PER_SKPD]);

        return $this->export($request);
    }

    /**
     * Endpoint kompatibilitas mundur: Ekspor aset melekat ruangan.
     */
    private function exportMelekatRuangan(Request $request)
    {
        $request->merge(['jenis_laporan' => MonitoringAsetExporter::JENIS_PER_RUANGAN]);

        return $this->export($request);
    }

    /**
     * Query dasar = seluruh aset yang belum "dihapuskan", dihiasi filter.
     */
    private function applyFilters(Request $request, ?string $periode = null)
    {
        $search = trim((string) $request->query('search'));
        $tahun = (int) $request->query('tahun');
        $status = $request->query('status');
        $status = is_string($status) ? trim($status) : null;
        $jenisAset = trim((string) $request->query('jenis_aset'));
        $penempatan = trim((string) $request->query('penempatan'));

        $with = ['barang', 'kendaraan.platAktif', 'penempatanAktif.ruangan.lokasi', 'pemegangSaatIni.pegawai', 'skpd', 'lokasi'];
        $relevantPeriods = array_values(array_unique(array_filter(array_merge(
            $periode ? [$periode] : [],
            PeriodeSensus::daftarPeriodeAktifList()
        ))));
        $with['sensus'] = fn ($q) => $q->whereIn('periode', $relevantPeriods);

        return Aset::query()
            ->with($with)
            ->whereIn('status_aset', ['aktif', 'Aktif'])
            ->tap(fn ($q) => $this->terapkanScopeMonitoring($q))
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('nomor_kartu_barang', 'like', "%{$search}%")
                    ->orWhere('merk', 'like', "%{$search}%")
                    ->orWhereHas('barang', function ($b) use ($search) {
                        $b->where('nama_barang', 'like', "%{$search}%")
                            ->orWhere('kode_barang', 'like', "%{$search}%");
                    })
                    ->orWhereHas('pemegangSaatIni.pegawai', function ($p) use ($search) {
                        $p->where('nama_pegawai', 'like', "%{$search}%");
                    })
                    ->orWhereHas('kendaraan', function ($k) use ($search) {
                        $k->where('nama_kendaraan', 'like', "%{$search}%")
                            ->orWhere('merk', 'like', "%{$search}%")
                            ->orWhere('tipe', 'like', "%{$search}%")
                            ->orWhere('jenis_kendaraan', 'like', "%{$search}%")
                            ->orWhere('pemegang', 'like', "%{$search}%")
                            ->orWhereHas('platAktif', fn ($p) => $p->where('nomor_plat', 'like', "%{$search}%"));
                    });
            }))
            ->when($jenisAset === 'barang', fn ($q) => $q->where(fn ($q2) => $q2->whereNull('is_kendaraan')->orWhere('is_kendaraan', false)))
            ->when($jenisAset === 'kendaraan', fn ($q) => $q->where('is_kendaraan', true))
            ->when($penempatan === 'memiliki_pemegang', fn ($q) => $q->whereHas('pemegang', fn ($pm) => $pm->where('status', 'aktif')))
            ->when($penempatan === 'melekat_ruangan', fn ($q) => $q->whereHas('penempatanAktif'))
            ->when($tahun, fn ($q) => $q->whereYear('tanggal_pengadaan', $tahun))
            ->tap(fn ($q) => $this->masaPakai->terapkanFilterStatus($q, $status));
    }

    /**
     * Agregasi 3 KPI dari kumpulan data yang sama dengan tabel/filter.
     * Status bersifat mutually exclusive agar satu aset tidak dihitung ganda.
     *
     * @return array{float, int, int}
     */
    private function ringkasan(Collection $asetList): array
    {
        $total = 0.0;
        $habis = 0;
        $kritis = 0;

        foreach ($asetList as $aset) {
            $calc = $this->masaPakai->evaluasi($aset);

            // Nilai buku yang tidak dapat ditentukan tidak boleh dihitung
            // sebagai Rp0 pada total KPI.
            if ($calc['nilai_buku'] !== null) {
                $total += $calc['nilai_buku'];
            }

            if ($calc['masa_status'] === 'habis') {
                $habis++;
            } elseif ($calc['masa_status'] === 'kritis') {
                $kritis++;
            }
        }

        return [round($total, 2), $habis, $kritis];
    }

    /**
     * Dekorasi aset menjadi baris tampilan + metadata sensus aset.
     */
    private function toRow(Aset $aset, Collection $pendingAsetIds, string $periodeDipilih = ''): object
    {
        $calc = $this->masaPakai->evaluasi($aset);
        $punyaNilai = $aset->nilai_perolehan !== null;

        $dalamAntrean = $aset->status_aset === 'diusulkan_hapus' || $pendingAsetIds->contains($aset->id_aset);

        $usulanAlasan = match (true) {
            $calc['masa_status'] === 'habis' => 'Masa Pakai Habis',
            in_array($aset->kondisi, ['Rusak Berat', 'Rusak', 'Rusak Berat / Tidak Layak'], true) => 'Rusak Berat',
            default => 'Lainnya',
        };

        if ($periodeDipilih === '') {
            $periodeDipilih = PeriodeSensus::periodeAktif();
        }

        $periodeAktifAset = PeriodeSensus::periodeAktif(null, $aset);

        // Sensus untuk periode yang sedang difilter pada tabel (untuk kolom Hasil Sensus)
        $sensusFilter = $aset->relationLoaded('sensus')
            ? $aset->sensus->firstWhere('periode', $periodeDipilih)
            : $aset->sensusPeriode($periodeDipilih)->first();

        $hasilSensus = $sensusFilter?->hasil_sensus ?? 'Belum Disensus';
        $sudahDisensus = $sensusFilter !== null;

        // Sensus untuk periode aktif aset itu sendiri (untuk aksi tombol Tambah / Edit Sensus)
        $sensusAktif = ($periodeDipilih === $periodeAktifAset && $sensusFilter !== null)
            ? $sensusFilter
            : ($aset->relationLoaded('sensus')
                ? $aset->sensus->firstWhere('periode', $periodeAktifAset)
                : $aset->sensusPeriode($periodeAktifAset)->first());

        $sudahDisensusAktif = $sensusAktif !== null;
        $hasilSensusAktif = $sensusAktif?->hasil_sensus;
        $tanggalSensusAktif = $sensusAktif?->tanggal_sensus
            ? Carbon::parse($sensusAktif->tanggal_sensus)->format('Y-m-d')
            : date('Y-m-d');
        $catatanSensusAktif = $sensusAktif?->catatan ?? '';

        // Fallback pemegang: relasi pemegang aktif -> nama_pegawai, atau kolom kendaraan.pemegang
        $pemegangNama = $aset->pemegangSaatIni?->pegawai?->nama_pegawai;
        if (! $pemegangNama && $aset->is_kendaraan) {
            $pemegangNama = $aset->kendaraan?->pemegang;
        }

        // Nomor polisi kendaraan
        $nomorPlat = $aset->is_kendaraan ? $aset->kendaraan?->platAktif?->nomor_plat : null;

        // Status penempatan: berdasarkan keberadaan relasi penempatan aktif dan nilai is_finalized
        $penempatanAktif = $aset->penempatanAktif;
        $statusPenempatan = match (true) {
            ! $penempatanAktif => 'Belum Ditempatkan',
            (int) ($penempatanAktif->is_finalized ?? 0) === 1 => 'Sudah Difinalisasi',
            default => 'Belum Difinalisasi',
        };

        // Lokasi fisik / ruangan: ruangan aktif -> lokasi master -> null
        $namaRuangan = $penempatanAktif?->ruangan?->nama_ruangan;
        $namaLokasi = $penempatanAktif?->ruangan?->lokasi?->nama_lokasi ?? $aset->lokasi?->nama_lokasi;
        $lokasiTampil = $namaRuangan ?? $namaLokasi;

        return (object) [
            'id_aset' => $aset->id_aset,
            'id_kendaraan' => $aset->kendaraan?->id_kendaraan,
            'is_kendaraan' => (bool) $aset->is_kendaraan,
            'nama_barang' => $aset->kendaraan?->namaTampil() ?? $aset->barang?->nama_barang,
            'nomor_kartu' => $aset->nomor_kartu_barang,
            'lokasi' => $lokasiTampil,
            'nama_ruangan' => $namaRuangan,
            'nama_lokasi' => $namaLokasi,
            'status_penempatan' => $statusPenempatan,
            'pemegang' => $pemegangNama,
            'nomor_plat' => $nomorPlat,
            'kondisi' => $aset->kondisi,
            'nilai_perolehan' => $punyaNilai ? (float) $aset->nilai_perolehan : null,
            'punya_nilai' => $punyaNilai,
            'nilai_buku' => $calc['nilai_buku'],
            'tanggal_pengadaan' => $aset->tanggal_pengadaan,
            'sisa_hari' => $calc['sisa_hari'],
            'masa_status' => $calc['masa_status'],
            'hasil_sensus' => $hasilSensus,
            'sudah_disensus' => $sudahDisensus,
            'periode_sensus' => $periodeDipilih,
            'periode_sensus_aktif' => $periodeAktifAset,
            'sudah_disensus_aktif' => $sudahDisensusAktif,
            'hasil_sensus_aktif' => $hasilSensusAktif,
            'tanggal_sensus_aktif' => $tanggalSensusAktif,
            'catatan_sensus_aktif' => $catatanSensusAktif,
            'periode_berlangsung' => true,
            'daftar_periode_sensus' => [$periodeAktifAset],
            'catatan_sensus' => $sensusFilter?->catatan ?? '',
            'tanggal_sensus' => $sensusFilter?->tanggal_sensus ? Carbon::parse($sensusFilter->tanggal_sensus)->format('Y-m-d') : date('Y-m-d'),
            'bisa_usul' => $this->scope->isAdminAset()
                && $aset->status_aset === 'aktif'
                && ! $dalamAntrean,
            'dalam_antrean' => $dalamAntrean,
            'usulan_alasan' => $usulanAlasan,
        ];
    }

    /**
     * Scope hak akses khusus Monitoring Aset.
     *
     * - Admin Aset: cakupan penuh (seluruh aset dan kendaraan di seluruh dinas/unit).
     * - Admin UPT:
     *   1. Aset yang secara fisik berada di lokasi UPT (aset.id_lokasi IN lokasi UPT).
     *   2. ATAU aset yang belum memiliki lokasi fisik (aset.id_lokasi IS NULL), tetapi
     *      terbukti sah milik SKPD UPT ini (aset.id_skpd = skpdId UPT).
     *   Aset pool pusat (id_lokasi NULL & id_skpd NULL) maupun aset milik UPT lain
     *   TIDAK AKAN PERNAH bocor ke Admin UPT.
     */
    private function terapkanScopeMonitoring(Builder $query): Builder
    {
        if ($this->scope->isAdminUpt()) {
            $idLokasi = $this->scope->idLokasi() ?: [0];
            $idSkpd = $this->scope->skpdId();

            return $query->where(function ($q) use ($idLokasi, $idSkpd) {
                // Kuadran I & IV: Berada di lokasi fisik UPT (termasuk ruang bersama)
                $q->whereIn('aset.id_lokasi', $idLokasi);

                // Kuadran II: Belum memiliki lokasi fisik, tetapi SKPD terbukti sah milik UPT ini
                if ($idSkpd) {
                    $q->orWhere(function ($q2) use ($idSkpd) {
                        $q2->whereNull('aset.id_lokasi')
                            ->where('aset.id_skpd', $idSkpd);
                    });
                }
            });
        }

        return $this->scope->terapkan($query);
    }

    private function masaText(object $row): string
    {
        if ($row->masa_status === 'habis') {
            return $row->sisa_hari !== null && $row->sisa_hari > 0
                ? 'Nilai buku habis'
                : 'Masa pakai habis';
        }

        if ($row->sisa_hari === null) {
            return 'Tidak ditentukan';
        }

        return sprintf('%d hari lagi', $row->sisa_hari);
    }
}
