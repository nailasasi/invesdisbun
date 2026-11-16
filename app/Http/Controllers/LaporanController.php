<?php

namespace App\Http\Controllers;

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
        abort_unless($this->scope->isAdminAset(), 403, 'Hanya Admin Aset yang berwenang mengakses Modul Laporan.');

        $data = $this->resolveReportContext($request);

        return view('laporan.index', [
            'jenis' => $data['jenis'],
            'tahun' => $data['tahun'],
            'bulan' => $data['bulan'],
            'periode' => $data['periode'],
            'unitLokasiId' => $data['unitLokasiId'],
            'namaUnitTerpilih' => $data['meta']['nama_unit'],
            'isAdminAset' => $data['meta']['is_admin_aset'],
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
        abort_unless($this->scope->isAdminAset(), 403, 'Hanya Admin Aset yang berwenang mengekspor Laporan.');

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
        abort_unless($this->scope->isAdminAset(), 403, 'Hanya Admin Aset yang berwenang mengekspor Laporan.');

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
     */
    private function resolveReportContext(Request $request): array
    {
        abort_unless($this->scope->isAdminAset(), 403, 'Hanya Admin Aset yang berwenang mengakses data Modul Laporan.');

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

        // Penanganan Unit Filter (hanya Admin Aset yang boleh memilih unit)
        $isAdminAset = $this->scope->cakupanPenuh();
        $unitParam = $request->query('unit');
        $unitLokasiId = null;

        if ($isAdminAset && $unitParam && is_numeric($unitParam)) {
            $unitLokasiId = (int) $unitParam;
        }

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

        $lokasiList = $isAdminAset ? $this->scope->lokasiTerlihat() : collect();

        // Nama unit terpilih yang akurat sesuai hak akses
        $namaUnitTerpilih = 'Seluruh Unit Kerja (Konsolidasi)';
        if (! $isAdminAset) {
            $namaUnitTerpilih = $this->scope->user()?->pegawai?->skpd?->nama_skpd ?? 'Unit Saya';
        } elseif ($unitLokasiId !== null) {
            $lokasiModel = $lokasiList->firstWhere('id_lokasi', $unitLokasiId);
            $namaUnitTerpilih = $lokasiModel?->nama_lokasi ?? 'Unit Terpilih';
        }

        $meta = [
            'nama_unit' => $namaUnitTerpilih,
            'is_admin_aset' => $isAdminAset,
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
