<?php

namespace App\Http\Controllers;

use App\Models\Lokasi;
use App\Services\AsetScope;
use App\Services\LaporanEksekutifExporter;
use App\Services\LaporanEksekutifService;
use App\Services\TanahScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class LaporanController extends Controller
{
    public function __construct(
        private readonly LaporanEksekutifService $service,
        private readonly LaporanEksekutifExporter $exporter,
        private readonly AsetScope $scope,
        private readonly TanahScope $tanahScope
    ) {
    }

    /**
     * Halaman Utama Modul Laporan Eksekutif (Phase 1 & Phase 2).
     */
    public function index(Request $request)
    {
        abort_unless($this->scope->isAdminAset() || $this->scope->isAdminUpt(), 403, 'Hanya Admin Aset dan Admin UPT yang berwenang mengakses Modul Laporan.');

        $data = $this->resolveReportContext($request);

        return view('laporan.index', [
            'jenis' => $data['jenis'],
            'tahun' => $data['tahun'],
            'bulan' => $data['bulan'],
            'periode' => $data['periode'],
            'unitLokasiId' => $data['unitLokasiId'],
            'namaUnitTerpilih' => $data['meta']['nama_unit'],
            'isAdminAset' => $data['meta']['is_admin_aset'],
            'isAdminUpt' => $data['meta']['is_admin_upt'],
            'daftarTahun' => $data['daftarTahun'],
            'daftarBulan' => $data['daftarBulan'],
            'lokasiList' => $data['lokasiList'],
            'k' => $data['kalkulasi'],
            'grafik' => $data['grafik'],
        ]);
    }

    /**
     * Ekspor Laporan Eksekutif ke format Excel (.xlsx) dengan 6 Sheet Lengkap (Phase 3).
     */
    public function exportExcel(Request $request)
    {
        abort_unless($this->scope->isAdminAset() || $this->scope->isAdminUpt(), 403, 'Hanya Admin Aset dan Admin UPT yang berwenang mengekspor Laporan.');

        $data = $this->resolveReportContext($request);

        $spreadsheet = $this->exporter->generateExcel(
            $data['periode'],
            $data['kalkulasi'],
            $data['grafik'],
            $data['meta']
        );

        $filename = $this->buildFilename('xlsx', $data['periode'], $data['meta']);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Ekspor Laporan Eksekutif ke format PDF resmi A4 Landscape (Phase 3).
     */
    public function exportPdf(Request $request)
    {
        abort_unless($this->scope->isAdminAset() || $this->scope->isAdminUpt(), 403, 'Hanya Admin Aset dan Admin UPT yang berwenang mengekspor Laporan.');

        $data = $this->resolveReportContext($request);

        $pdfBinary = $this->exporter->renderPdf(
            $data['periode'],
            $data['kalkulasi'],
            $data['grafik'],
            $data['meta']
        );

        $filename = $this->buildFilename('pdf', $data['periode'], $data['meta']);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Resolusi terpusat parameter request, hak akses unit, dan data kalkulasi (Single Source of Truth).
     * Menerapkan pembatasan role ketat di backend:
     * - Admin Aset: hanya dapat mengakses dan mengekspor aset Dinas.
     * - Admin UPT PSBP: hanya dapat mengakses dan mengekspor aset UPT PSBP.
     * - Admin UPT P2BTP: hanya dapat mengakses dan mengekspor aset UPT P2BTP.
     */
    private function resolveReportContext(Request $request): array
    {
        abort_unless($this->scope->isAdminAset() || $this->scope->isAdminUpt(), 403, 'Hanya Admin Aset dan Admin UPT yang berwenang mengakses data Modul Laporan.');

        // Tentukan unit kewenangan resmi berdasarkan role & SKPD pegawai pengguna
        if ($this->scope->isAdminAset()) {
            $unitParam = $request->query('unit');
            if ($unitParam === null || $unitParam === '' || $unitParam === 'semua') {
                $unitLokasiId = null;
                $authorizedUnitName = 'Semua Unit';
            } else {
                $unitInt = (int) $unitParam;
                $lokasiModel = Lokasi::find($unitInt);
                if (! $lokasiModel) {
                    abort(404, 'Unit kerja / lokasi tidak ditemukan.');
                }
                $unitLokasiId = $unitInt;
                $authorizedUnitName = $lokasiModel->nama_lokasi;
            }

            // Dropdown untuk Admin Aset menampilkan seluruh unit:
            // Semua Unit, Kantor Dinas Perkebunan (1), UPT PSBP (3), UPT P2BTP (2)
            $lokasiList = Lokasi::where('status', 'Aktif')
                ->orderByRaw('FIELD(id_lokasi, 1, 3, 2)')
                ->get();
        } elseif ($this->scope->isAdminUpt()) {
            $skpdId = (int) $this->scope->skpdId();
            if ($skpdId === 7) {
                $authorizedUnitId = 3;
                $authorizedUnitName = 'Kantor UPT PSBP';
            } elseif ($skpdId === 6) {
                $authorizedUnitId = 2;
                $authorizedUnitName = 'Kantor UPT P2BTP';
            } else {
                $lokasiUpt = $this->scope->idLokasi();
                $authorizedUnitId = ! empty($lokasiUpt) ? (int) $lokasiUpt[0] : 0;
                $lokasiModel = Lokasi::find($authorizedUnitId);
                $authorizedUnitName = $lokasiModel?->nama_lokasi ?? 'Kantor UPT';
            }

            // Pembatasan Backend untuk Admin UPT: Cegah manipulasi request unit lain atau konsolidasi seluruh unit
            $unitParam = $request->query('unit');
            if ($unitParam !== null && $unitParam !== '') {
                if ($unitParam === 'semua' || (int) $unitParam !== $authorizedUnitId) {
                    abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses laporan unit lain.');
                }
            }

            $unitLokasiId = $authorizedUnitId;
            $lokasiList = Lokasi::where('id_lokasi', $authorizedUnitId)->get();
        } else {
            abort(403, 'Anda tidak memiliki hak akses untuk Modul Laporan.');
        }

        $jenis = strtolower(trim((string) $request->query('jenis', 'bulanan')));
        if (! in_array($jenis, ['bulanan', 'tahunan'], true)) {
            $jenis = 'bulanan';
        }

        $now = Carbon::now();
        $tahun = (int) $request->query('tahun', $now->year);
        if ($tahun < 1990 || $tahun > 2100) {
            $tahun = $now->year;
        }

        $bulan = (int) $request->query('bulan', $now->month);
        if ($bulan < 1 || $bulan > 12) {
            $bulan = $now->month;
        }

        // Tentukan periode dan tanggal acuan
        $periode = $this->service->resolvePeriode($jenis, $tahun, $bulan);

        // Eksekusi kalkulasi seluruh KPI dan indikator
        $kalkulasi = $this->service->kalkulasi(
            $periode['start_date'],
            $periode['end_date'],
            $periode['acuan'],
            $unitLokasiId
        );

        // Eksekusi kalkulasi visualisasi grafik eksekutif (Phase 2)
        $grafik = $this->service->kalkulasiGrafik(
            $periode['start_date'],
            $periode['end_date'],
            $periode['acuan'],
            $unitLokasiId,
            $periode['jenis']
        );

        // Data pendukung form filter
        $daftarTahun = range(max(2020, $now->year - 5), $now->year + 1);
        rsort($daftarTahun);

        $daftarBulan = [
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
        ];


        $meta = [
            'nama_unit' => $authorizedUnitName,
            'is_admin_aset' => $this->scope->isAdminAset(),
            'is_admin_upt' => $this->scope->isAdminUpt(),
            'unit_lokasi_id' => $unitLokasiId,
            'jenis' => $jenis,
            'tahun' => $tahun,
            'bulan' => $bulan,
        ];

        return [
            'jenis' => $jenis,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'periode' => $periode,
            'unitLokasiId' => $unitLokasiId,
            'meta' => $meta,
            'daftarTahun' => $daftarTahun,
            'daftarBulan' => $daftarBulan,
            'lokasiList' => $lokasiList,
            'kalkulasi' => $kalkulasi,
            'grafik' => $grafik,
        ];
    }

    /**
     * Membangun nama file dinamis, aman, dan informatif untuk dokumen ekspor.
     * Contoh: Laporan_Eksekutif_BMD_November_2026_Seluruh_Unit_Kerja_Konsolidasi.xlsx
     */
    private function buildFilename(string $ext, array $periode, array $meta): string
    {
        $periodeStr = $periode['jenis'] === 'bulanan'
            ? Carbon::create($periode['tahun'], $periode['bulan'], 1)->translatedFormat('F_Y')
            : 'Tahun_' . $periode['tahun'];
        $unitSlug = Str::slug($meta['nama_unit'], '_');

        return "Laporan_Eksekutif_BMD_{$periodeStr}_{$unitSlug}.{$ext}";
    }
}
