<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class LaporanEksekutifExporter
{
    private const COLOR_PRIMARY = '047857'; // Emerald 700
    private const COLOR_HEADER_BG = '065F46'; // Emerald 800
    private const COLOR_SECTION_BG = 'ECFDF5'; // Emerald 50
    private const COLOR_SUBHEADER_BG = 'F1F5F9'; // Slate 100
    private const COLOR_TEXT_MUTED = '64748B'; // Slate 500

    /**
     * Format angka Rupiah untuk PhpSpreadsheet.
     */
    private const FORMAT_RUPIAH = '"Rp "#,##0;("Rp "#,##0);"-"';
    private const FORMAT_JUMLAH = '#,##0';
    private const FORMAT_PERSEN = '0.0%';

    /**
     * Hasilkan objek PhpSpreadsheet dengan 6 sheet resmi.
     *
     * @param array $periode
     * @param array $k Metrik kalkulasi KPI
     * @param array $grafik Dataset visual & ringkasan
     * @param array $meta [nama_unit, is_admin_aset, unit_lokasi_id, jenis, tahun, bulan]
     * @return Spreadsheet
     */
    public function generateExcel(array $periode, array $k, array $grafik, array $meta): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('INVENSBUN - Dinas Perkebunan Provinsi Jawa Timur')
            ->setLastModifiedBy('INVENSBUN Sistem Aset')
            ->setTitle('Laporan Eksekutif Pengelolaan BMD')
            ->setSubject('Laporan Eksekutif Pengelolaan Barang Milik Daerah')
            ->setDescription('Rekapitulasi Laporan Eksekutif Pengelolaan BMD Dinas Perkebunan Provinsi Jawa Timur');

        // Sheet 1: Ringkasan Eksekutif
        $s1 = $spreadsheet->getActiveSheet();
        $s1->setTitle('Ringkasan Eksekutif');
        $this->buildSheetRingkasanEksekutif($s1, $periode, $k, $grafik, $meta);

        // Sheet 2: Distribusi Aset
        $s2 = $spreadsheet->createSheet();
        $s2->setTitle('Distribusi Aset');
        $this->buildSheetDistribusiAset($s2, $periode, $k, $grafik, $meta);

        // Sheet 3: Nilai dan Penyusutan
        $s3 = $spreadsheet->createSheet();
        $s3->setTitle('Nilai dan Penyusutan');
        $this->buildSheetNilaiPenyusutan($s3, $periode, $k, $grafik, $meta);

        // Sheet 4: Kondisi dan Risiko
        $s4 = $spreadsheet->createSheet();
        $s4->setTitle('Kondisi dan Risiko');
        $this->buildSheetKondisiRisiko($s4, $periode, $k, $grafik, $meta);

        // Sheet 5: Lokasi dan Penempatan
        $s5 = $spreadsheet->createSheet();
        $s5->setTitle('Lokasi dan Penempatan');
        $this->buildSheetLokasiPenempatan($s5, $periode, $k, $grafik, $meta);

        // Sheet 6: Aktivitas dan Pajak
        $s6 = $spreadsheet->createSheet();
        $s6->setTitle('Aktivitas dan Pajak');
        $this->buildSheetAktivitasPajak($s6, $periode, $k, $grafik, $meta);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Render HTML Laporan Eksekutif ke binary string PDF via Dompdf.
     */
    public function renderPdf(array $periode, array $k, array $grafik, array $meta): string
    {
        $logoJatimPath = public_path('image/logo-jatim.png');
        $logoInvensbunPath = public_path('image/logo-invensbun.png');

        $logoJatim = file_exists($logoJatimPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoJatimPath))
            : null;
        $logoInvensbun = file_exists($logoInvensbunPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoInvensbunPath))
            : null;

        $html = view('laporan.pdf', [
            'periode' => $periode,
            'k' => $k,
            'grafik' => $grafik,
            'meta' => $meta,
            'logoJatim' => $logoJatim,
            'logoInvensbun' => $logoInvensbun,
            'waktuCetak' => now()->translatedFormat('d F Y H:i:s') . ' WIB',
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont('Helvetica', 'normal');
        $fontBold = $fontMetrics->getFont('Helvetica', 'bold');
        $size = 8;
        $color = [0.35, 0.40, 0.45];
        $w = $canvas->get_width();
        $h = $canvas->get_height();

        // Kiri Bawah: Identitas Sistem & Kerahasiaan
        $canvas->page_text(35, $h - 26, "INVENSBUN | Dokumen Resmi Eksekutif Pengelolaan BMD - Pemerintah Provinsi Jawa Timur", $font, $size, $color);

        // Kanan Bawah: Halaman X dari Y di setiap halaman
        $canvas->page_text($w - 110, $h - 26, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", $fontBold, $size, $color);

        return $dompdf->output();
    }

    // =========================================================================
    // BUILDER SHEET EXCEL (1 SAMPAI 6)
    // =========================================================================

    private function buildHeaderBlock($sheet, string $sheetTitle, array $periode, array $meta): int
    {
        $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA TIMUR - DINAS PERKEBUNAN');
        $sheet->setCellValue('A2', 'LAPORAN EKSEKUTIF PENGELOLAAN BARANG MILIK DAERAH (BMD)');
        $sheet->setCellValue('A3', strtoupper($sheetTitle));

        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');
        $sheet->mergeCells('A3:G3');

        $sheet->getStyle('A1:A3')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(11);
        $sheet->getStyle('A3')->getFont()->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::COLOR_HEADER_BG));

        $sheet->setCellValue('A5', 'Periode Laporan:');
        $sheet->setCellValue('B5', $periode['judul_periode']);
        $sheet->setCellValue('D5', 'Tanggal Acuan Snapshot:');
        $sheet->setCellValue('E5', $periode['acuan']->translatedFormat('d F Y') . ' (23:59:59 WIB)');

        $sheet->setCellValue('A6', 'Cakupan Wilayah / Unit:');
        $sheet->setCellValue('B6', $meta['nama_unit']);
        $sheet->setCellValue('D6', 'Waktu Ekspor Dokumen:');
        $sheet->setCellValue('E6', now()->translatedFormat('d F Y H:i:s') . ' WIB');

        $sheet->getStyle('A5:A6')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::COLOR_TEXT_MUTED));
        $sheet->getStyle('D5:D6')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::COLOR_TEXT_MUTED));
        $sheet->getStyle('B5:B6')->getFont()->setBold(true);
        $sheet->getStyle('E5:E6')->getFont()->setBold(true);

        return 8; // Baris berikutnya untuk konten
    }

    private function styleTableHeaders($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '047857']]],
        ]);
        $sheet->getRowDimension(explode(':', $range)[0][1] ?? 8)->setRowHeight(24);
    }

    private function styleSectionBanner($sheet, string $cell, string $text, string $mergeRange): void
    {
        $sheet->setCellValue($cell, $text);
        $sheet->mergeCells($mergeRange);
        $sheet->getStyle($mergeRange)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '065F46']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_SECTION_BG]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'A7F3D0']]],
        ]);
    }

    private function styleDataBorders($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function styleTotalRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_SUBHEADER_BG]],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '64748B']],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '0F172A']],
            ],
        ]);
    }

    private function autoFitColumns($sheet, string $maxCol = 'G'): void
    {
        foreach (range('A', $maxCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // -------------------------------------------------------------------------
    // SHEET 1: RINGKASAN EKSEKUTIF
    // -------------------------------------------------------------------------
    private function buildSheetRingkasanEksekutif($sheet, array $periode, array $k, array $grafik, array $meta): void
    {
        $row = $this->buildHeaderBlock($sheet, 'Sheet 1: Ringkasan Eksekutif & Neraca Saldo Aset', $periode, $meta);

        // Seksi 1: Neraca Saldo Historis
        $this->styleSectionBanner($sheet, "A{$row}", 'I. NERACA SALDO ASET HISTORIS & VALUASI KEUANGAN (SAP)', "A{$row}:F{$row}");
        $row += 2;

        $hRow = $row;
        $sheet->fromArray([
            'No', 'Indikator Neraca / Portofolio', 'Jumlah Fisik', 'Satuan', 'Nilai Finansial (Rp)', 'Keterangan Metodologi'
        ], null, "A{$hRow}", true);
        $this->styleTableHeaders($sheet, "A{$hRow}:F{$hRow}");
        $row++;

        $startData = $row;
        $items = [
            [1, 'Total Aset Bergerak (Barang & Kendaraan)', $k['total_barang_dan_kendaraan'], 'Unit', $k['total_nilai_perolehan_aset'], 'Total nilai perolehan seluruh aset bergerak aktif'],
            [2, 'Barang Inventaris (Peralatan & Mesin)', $k['total_barang'], 'Unit', $grafik['nilai']['nilai_perolehan'][0] ?? 0.0, 'Nilai perolehan barang inventaris non-kendaraan'],
            [3, 'Kendaraan Bermotor Dinas Operasional', $k['total_kendaraan'], 'Unit', $grafik['nilai']['nilai_perolehan'][1] ?? 0.0, 'Nilai perolehan armada kendaraan roda 2, 3, dan 4'],
            [4, 'Akumulasi Penyusutan Aset Bergerak', null, '-', $k['total_akumulasi_penyusutan_gabungan'], 'Beban penyusutan kumulatif berdasarkan masa manfaat SAP'],
            [5, 'Nilai Buku Bersih Aset Bergerak', null, '-', $k['total_nilai_buku_aset'], 'Valuasi riil aset bergerak setelah dikurangi penyusutan'],
        ];

        if ($k['bisa_akses_tanah']) {
            $items[] = [6, 'Register Tanah Daerah (KIB A)*', $k['total_bidang_tanah'], 'Bidang', $k['total_nilai_perolehan_tanah'], "Luas: {$k['total_luas_tanah']} m2. Tanah tidak disusutkan (nilai buku = nilai perolehan)"];
            $items[] = [7, 'TOTAL VALUASI SALDO GABUNGAN', null, '-', $k['total_nilai_buku_gabungan'], 'Total Nilai Buku Aset Bergerak + Register Tanah KIB A'];
        } else {
            $items[] = [6, 'TOTAL VALUASI SALDO GABUNGAN', null, '-', $k['total_nilai_buku_aset'], 'Total Nilai Buku Bersih (Tanah dibatasi hak akses pengguna)'];
        }

        foreach ($items as $item) {
            $sheet->fromArray($item, null, "A{$row}", true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        $this->styleDataBorders($sheet, "A{$startData}:F" . ($row - 1));
        $this->styleTotalRow($sheet, "A" . ($row - 1) . ":F" . ($row - 1));

        $sheet->setCellValue("A{$row}", "* Catatan Pemisahan Populasi: Jumlah aset pengadaan mengikuti tahun pengadaan yang dipilih pada filter laporan (Tahun {$meta['tahun']}: {$k['jumlah_aset_pengadaan']} unit senilai Rp " . number_format($k['nilai_pengadaan_tahun'], 0, ',', '.') . "). Rekap kondisi fisik dan portofolio neraca saldo mencakup seluruh aset aktif berdasarkan data kondisi terakhir.");
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::COLOR_TEXT_MUTED));

        $row += 2;

        // Seksi 2: Indikator Tata Kelola Operasional
        $this->styleSectionBanner($sheet, "A{$row}", 'II. INDIKATOR TATA KELOLA OPERASIONAL & KEPATUHAN', "A{$row}:F{$row}");
        $row += 2;

        $hRow2 = $row;
        $sheet->fromArray([
            'No', 'Indikator Tata Kelola', 'Capaian Riil', 'Target / Populasi', 'Rasio (%)', 'Keterangan Analitis'
        ], null, "A{$hRow2}", true);
        $this->styleTableHeaders($sheet, "A{$hRow2}:F{$hRow2}");
        $row++;

        $startData2 = $row;
        $indikatorOperasional = [
            [
                1,
                'Tingkat Penempatan & Pemegang Aktif',
                $k['memiliki_penempatan_pemegang_count'],
                $k['total_barang_dan_kendaraan'],
                $k['persentase_penempatan_pemegang'] / 100,
                'Proporsi aset yang telah ditempatkan di ruangan atau dipegang resmi oleh pegawai'
            ],
            [
                2,
                "Progres Sensus Fisik Siklus ({$k['siklus_sensus_aktif']})",
                $k['sensus_sudah_count'],
                $k['sensus_wajib_count'],
                $k['sensus_persentase'] / 100,
                'Progres audit sensus fisik 3-tahunan siklus berjalan terhadap seluruh populasi aset'
            ],
            [
                3,
                'Realisasi Pembayaran Pajak Kendaraan',
                $k['pajak_flow_kendaraan_count'],
                $k['total_kendaraan'],
                $k['total_kendaraan'] > 0 ? ($k['pajak_flow_kendaraan_count'] / $k['total_kendaraan']) : 0.0,
                "Total realisasi bayar periode ini: Rp " . number_format($k['pajak_flow_total_nominal'], 0, ',', '.') . " ({$k['pajak_flow_transaksi_count']} transaksi)"
            ],
            [
                4,
                'Kepatuhan Pajak Kendaraan Aman (Snapshot)',
                $k['pajak_snapshot_aman'],
                $k['total_kendaraan'],
                $k['total_kendaraan'] > 0 ? ($k['pajak_snapshot_aman'] / $k['total_kendaraan']) : 0.0,
                'Kendaraan dengan masa berlaku pajak > 30 hari pada akhir periode laporan'
            ],
        ];

        foreach ($indikatorOperasional as $io) {
            $sheet->fromArray($io, null, "A{$row}", true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}:D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
            $sheet->getStyle("C{$row}:D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        $this->styleDataBorders($sheet, "A{$startData2}:F" . ($row - 1));

        $this->autoFitColumns($sheet, 'F');
    }

    // -------------------------------------------------------------------------
    // SHEET 2: DISTRIBUSI ASET
    // -------------------------------------------------------------------------
    private function buildSheetDistribusiAset($sheet, array $periode, array $k, array $grafik, array $meta): void
    {
        $row = $this->buildHeaderBlock($sheet, 'Sheet 2: Distribusi Kategori Aset & Tren Pengadaan', $periode, $meta);

        // Bagian A: Komposisi Berdasarkan Kategori
        $this->styleSectionBanner($sheet, "A{$row}", 'I. DISTRIBUSI ASET BERDASARKAN KATEGORI', "A{$row}:E{$row}");
        $row += 2;

        $hRow = $row;
        $sheet->fromArray([
            'No', 'Kategori Aset', 'Jumlah Unit / Bidang', 'Persentase (%)', 'Klasifikasi Portofolio'
        ], null, "A{$hRow}", true);
        $this->styleTableHeaders($sheet, "A{$hRow}:E{$hRow}");
        $row++;

        $startData = $row;
        $totalKat = $grafik['kategori']['total'] ?? 1;
        $no = 1;

        foreach ($grafik['kategori']['labels'] as $idx => $label) {
            $cnt = $grafik['kategori']['data'][$idx] ?? 0;
            $persen = $totalKat > 0 ? ($cnt / $totalKat) : 0.0;
            $klasifikasi = str_contains($label, 'Kendaraan') ? 'Aset Kendaraan Bergerak' : (str_contains($label, 'Tanah') ? 'Aset Tetap Tidak Bergerak' : 'Aset Peralatan & Mesin');

            $sheet->fromArray([$no++, $label, $cnt, $persen, $klasifikasi], null, "A{$row}", true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        // Baris Total
        $sheet->setCellValue("A{$row}", 'TOTAL KATEGORI ASET');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("C{$row}", "=SUM(C{$startData}:C" . ($row - 1) . ")");
        $sheet->setCellValue("D{$row}", "=SUM(D{$startData}:D" . ($row - 1) . ")");
        $sheet->setCellValue("E{$row}", 'Konsolidasi seluruh kategori yang terhitung');

        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);

        $this->styleDataBorders($sheet, "A{$startData}:E{$row}");
        $this->styleTotalRow($sheet, "A{$row}:E{$row}");

        $row += 2;

        // Bagian B: Tren Pengadaan Historis 5 Tahun Terakhir
        $this->styleSectionBanner($sheet, "A{$row}", 'II. TREN PENGADAAN ASET 5 TAHUN TERAKHIR', "A{$row}:E{$row}");
        $row += 2;

        $hRow2 = $row;
        $sheet->fromArray([
            'No', 'Tahun Pengadaan', 'Jumlah Unit Terdaftar', 'Total Nilai Perolehan (Rp)', 'Rata-rata Nilai per Unit (Rp)'
        ], null, "A{$hRow2}", true);
        $this->styleTableHeaders($sheet, "A{$hRow2}:E{$hRow2}");
        $row++;

        $startData2 = $row;
        $no2 = 1;

        foreach ($grafik['tren_pengadaan']['labels'] as $idx => $tahunLabel) {
            $count = $grafik['tren_pengadaan']['data_count'][$idx] ?? 0;
            $nilai = $grafik['tren_pengadaan']['data_nilai'][$idx] ?? 0.0;
            $avgFormula = "=IF(C{$row}>0, D{$row}/C{$row}, 0)";

            $sheet->fromArray([$no2++, "Tahun {$tahunLabel}", $count, $nilai, $avgFormula], null, "A{$row}", true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("D{$row}:E{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $sheet->getStyle("D{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        // Baris Total Tren
        $sheet->setCellValue("A{$row}", 'TOTAL PENGADAAN 5 TAHUN TERAKHIR');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("C{$row}", "=SUM(C{$startData2}:C" . ($row - 1) . ")");
        $sheet->setCellValue("D{$row}", "=SUM(D{$startData2}:D" . ($row - 1) . ")");
        $sheet->setCellValue("E{$row}", "=IF(C{$row}>0, D{$row}/C{$row}, 0)");

        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
        $sheet->getStyle("D{$row}:E{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);

        $this->styleDataBorders($sheet, "A{$startData2}:E{$row}");
        $this->styleTotalRow($sheet, "A{$row}:E{$row}");

        $this->autoFitColumns($sheet, 'E');
    }

    // -------------------------------------------------------------------------
    // SHEET 3: NILAI DAN PENYUSUTAN
    // -------------------------------------------------------------------------
    private function buildSheetNilaiPenyusutan($sheet, array $periode, array $k, array $grafik, array $meta): void
    {
        $row = $this->buildHeaderBlock($sheet, 'Sheet 3: Nilai Perolehan, Penyusutan, & Masa Manfaat', $periode, $meta);

        // Bagian A: Perbandingan Finansial per Kelompok
        $this->styleSectionBanner($sheet, "A{$row}", 'I. PERBANDINGAN NILAI PEROLEHAN VS NILAI BUKU BERSIH (SAP)', "A{$row}:G{$row}");
        $row += 2;

        $hRow = $row;
        $sheet->fromArray([
            'No', 'Kelompok Aset', 'Nilai Perolehan (Rp)', 'Akumulasi Penyusutan (Rp)', 'Nilai Buku Bersih (Rp)', 'Sisa Nilai (%)', 'Status Akuntansi'
        ], null, "A{$hRow}", true);
        $this->styleTableHeaders($sheet, "A{$hRow}:G{$hRow}");
        $row++;

        $startData = $row;
        $npBarang = $grafik['nilai']['nilai_perolehan'][0] ?? 0.0;
        $nbBarang = $grafik['nilai']['nilai_buku'][0] ?? 0.0;
        $npKendaraan = $grafik['nilai']['nilai_perolehan'][1] ?? 0.0;
        $nbKendaraan = $grafik['nilai']['nilai_buku'][1] ?? 0.0;

        $finansialItems = [
            [
                1, 'Barang Inventaris', $npBarang, "=C{$row}-E{$row}", $nbBarang,
                "=IF(C{$row}>0, E{$row}/C{$row}, 0)", 'Disusutkan berkala per SAP'
            ],
            [
                2, 'Kendaraan Bermotor', $npKendaraan, "=C" . ($row + 1) . "-E" . ($row + 1), $nbKendaraan,
                "=IF(C" . ($row + 1) . ">0, E" . ($row + 1) . "/C" . ($row + 1) . ", 0)", 'Disusutkan berkala per SAP'
            ],
        ];

        if ($k['bisa_akses_tanah']) {
            $finansialItems[] = [
                3, 'Tanah (KIB A)*', $k['total_nilai_perolehan_tanah'], 0.0, $k['total_nilai_buku_tanah'],
                1.0, 'Tanah tidak disusutkan (Nilai Buku = Nilai Perolehan)'
            ];
        }

        foreach ($finansialItems as $fi) {
            $sheet->fromArray($fi, null, "A{$row}", true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}:E{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            $sheet->getStyle("C{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        // Total Finansial
        $sheet->setCellValue("A{$row}", 'TOTAL GABUNGAN');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("C{$row}", "=SUM(C{$startData}:C" . ($row - 1) . ")");
        $sheet->setCellValue("D{$row}", "=SUM(D{$startData}:D" . ($row - 1) . ")");
        $sheet->setCellValue("E{$row}", "=SUM(E{$startData}:E" . ($row - 1) . ")");
        $sheet->setCellValue("F{$row}", "=IF(C{$row}>0, E{$row}/C{$row}, 0)");
        $sheet->setCellValue("G{$row}", 'Konsolidasi portofolio aset daerah');

        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C{$row}:E{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);

        $this->styleDataBorders($sheet, "A{$startData}:G{$row}");
        $this->styleTotalRow($sheet, "A{$row}:G{$row}");

        $row += 2;

        // Bagian B: Rekapitulasi Masa Manfaat
        $this->styleSectionBanner($sheet, "A{$row}", 'II. ANALISIS KESEHATAN MASA MANFAAT ASET BERGERAK', "A{$row}:G{$row}");
        $row += 2;

        $hRow2 = $row;
        $sheet->fromArray([
            'No', 'Status Masa Manfaat', 'Jumlah Unit', 'Rasio Populasi (%)', 'Kriteria & Implikasi Manajerial', '', ''
        ], null, "A{$hRow2}", true);
        $sheet->mergeCells("E{$hRow2}:G{$hRow2}");
        $this->styleTableHeaders($sheet, "A{$hRow2}:G{$hRow2}");
        $row++;

        $startData2 = $row;
        $totAset = (int) ($k['total_barang_dan_kendaraan'] ?? 0);

        $masaManfaatItems = [
            [1, 'Masa Manfaat Normal', $k['masa_normal'], $totAset > 0 ? ($k['masa_normal'] / $totAset) : 0.0, 'Masa pakai aman (> 30 hari tersisa)'],
            [2, 'Masa Manfaat Kritis', $k['masa_kritis'], $totAset > 0 ? ($k['masa_kritis'] / $totAset) : 0.0, 'Mendekati habis pakai (<= 30 hari), perlu perencanaan peremajaan'],
            [3, 'Masa Manfaat Habis', $k['masa_habis'], $totAset > 0 ? ($k['masa_habis'] / $totAset) : 0.0, 'Telah melampaui masa manfaat SAP, valuasi nilai buku = Rp 1 / residu'],
            [4, 'Belum Terdefinisi (NaN)', $k['masa_nan'], $totAset > 0 ? ($k['masa_nan'] / $totAset) : 0.0, 'Tanggal pengadaan / habis pakai belum lengkap'],
        ];

        foreach ($masaManfaatItems as $mmi) {
            $sheet->setCellValue("A{$row}", $mmi[0]);
            $sheet->setCellValue("B{$row}", $mmi[1]);
            $sheet->setCellValue("C{$row}", $mmi[2]);
            $sheet->setCellValue("D{$row}", $mmi[3]);
            $sheet->setCellValue("E{$row}", $mmi[4]);
            $sheet->mergeCells("E{$row}:G{$row}");

            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        // Total Masa Manfaat
        $sheet->setCellValue("A{$row}", 'TOTAL ASET BERGERAK DIEVALUASI');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("C{$row}", "=SUM(C{$startData2}:C" . ($row - 1) . ")");
        $sheet->setCellValue("D{$row}", "=SUM(D{$startData2}:D" . ($row - 1) . ")");
        $sheet->setCellValue("E{$row}", 'Seluruh aset bergerak aktif yang masuk saldo neraca');
        $sheet->mergeCells("E{$row}:G{$row}");

        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);

        $this->styleDataBorders($sheet, "A{$startData2}:G{$row}");
        $this->styleTotalRow($sheet, "A{$row}:G{$row}");

        $this->autoFitColumns($sheet, 'G');
    }

    // -------------------------------------------------------------------------
    // SHEET 4: KONDISI DAN RISIKO
    // -------------------------------------------------------------------------
    private function buildSheetKondisiRisiko($sheet, array $periode, array $k, array $grafik, array $meta): void
    {
        $row = $this->buildHeaderBlock($sheet, 'Sheet 4: Kondisi Fisik, Kepatuhan Pajak, & Risiko', $periode, $meta);

        // Bagian A: Kondisi Fisik Master Aset
        $this->styleSectionBanner($sheet, "A{$row}", 'I. DISTRIBUSI KONDISI FISIK SELURUH ASET AKTIF', "A{$row}:F{$row}");
        $row++;
        $sheet->setCellValue("A{$row}", 'Keterangan: Kondisi seluruh aset aktif berdasarkan data kondisi terakhir.');
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::COLOR_TEXT_MUTED));
        $row += 2;

        $hRow = $row;
        $sheet->fromArray([
            'No', 'Status Kondisi Fisik', 'Jumlah Unit', 'Rasio (%)', 'Tingkat Risiko Operasional', 'Catatan Kebijakan'
        ], null, "A{$hRow}", true);
        $this->styleTableHeaders($sheet, "A{$hRow}:F{$hRow}");
        $row++;

        $startData = $row;
        $totAset = (int) ($k['total_barang_dan_kendaraan'] ?? 0);

        $kondisiItems = [
            [1, 'Baik', $k['kondisi_baik'], $totAset > 0 ? ($k['kondisi_baik'] / $totAset) : 0.0, 'Rendah', 'Aset siap operasi penuh dan layak pakai'],
            [2, 'Rusak Ringan', $k['kondisi_rusak_ringan'], $totAset > 0 ? ($k['kondisi_rusak_ringan'] / $totAset) : 0.0, 'Sedang', 'Memerlukan pemeliharaan rutin / suku cadang'],
            [3, 'Rusak Berat', $k['kondisi_rusak_berat'], $totAset > 0 ? ($k['kondisi_rusak_berat'] / $totAset) : 0.0, 'Tinggi', 'Tidak dapat beroperasi optimal, prioritas perbaikan / usulan mutasi'],
            [4, 'Belum Diisi / Lainnya', $k['kondisi_lainnya'], $totAset > 0 ? ($k['kondisi_lainnya'] / $totAset) : 0.0, 'Perlu Audit', 'Perlu pembaruan data kondisi fisik master'],
        ];

        foreach ($kondisiItems as $ki) {
            $sheet->fromArray($ki, null, "A{$row}", true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        // Total Kondisi
        $sheet->setCellValue("A{$row}", 'TOTAL ASET FISIK DIEVALUASI');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("C{$row}", "=SUM(C{$startData}:C" . ($row - 1) . ")");
        $sheet->setCellValue("D{$row}", "=SUM(D{$startData}:D" . ($row - 1) . ")");
        $sheet->setCellValue("E{$row}", '-');
        $sheet->setCellValue("F{$row}", 'Kondisi seluruh aset aktif berdasarkan data kondisi terakhir.');

        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);

        $this->styleDataBorders($sheet, "A{$startData}:F{$row}");
        $this->styleTotalRow($sheet, "A{$row}:F{$row}");

        $row += 2;

        // Bagian B: Kepatuhan Pajak Kendaraan
        $this->styleSectionBanner($sheet, "A{$row}", 'II. STATUS KEPATUHAN PAJAK KENDARAAN BERMOTOR (SNAPSHOT PER AKHIR PERIODE)', "A{$row}:F{$row}");
        $row += 2;

        $hRow2 = $row;
        $sheet->fromArray([
            'No', 'Kategori Kepatuhan Pajak (PKB)', 'Jumlah Unit', 'Rasio Kendaraan (%)', 'Status Kepatuhan', 'Tindakan Manajerial'
        ], null, "A{$hRow2}", true);
        $this->styleTableHeaders($sheet, "A{$hRow2}:F{$hRow2}");
        $row++;

        $startData2 = $row;
        $totKendaraan = (int) ($k['total_kendaraan'] ?? 0);

        $pajakItems = [
            [1, 'Pajak Aman', $k['pajak_snapshot_aman'], $totKendaraan > 0 ? ($k['pajak_snapshot_aman'] / $totKendaraan) : 0.0, 'Aman', 'Masa berlaku pajak > 30 hari'],
            [2, 'Pajak Kritis', $k['pajak_snapshot_kritis'], $totKendaraan > 0 ? ($k['pajak_snapshot_kritis'] / $totKendaraan) : 0.0, 'Peringatan', 'Jatuh tempo dalam <= 30 hari, segera proses pembayaran'],
            [3, 'Pajak Terlambat', $k['pajak_snapshot_terlambat'], $totKendaraan > 0 ? ($k['pajak_snapshot_terlambat'] / $totKendaraan) : 0.0, 'Pelanggaran / Denda', 'Masa berlaku telah kedaluwarsa, potensi sanksi administrasi'],
            [4, 'Belum Ada Data Pajak', $k['pajak_snapshot_tanpa_data'], $totKendaraan > 0 ? ($k['pajak_snapshot_tanpa_data'] / $totKendaraan) : 0.0, 'Data Kosong', 'Riwayat PKB / STNK belum diunggah ke sistem'],
        ];

        foreach ($pajakItems as $pi) {
            $sheet->fromArray($pi, null, "A{$row}", true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        // Total Pajak
        $sheet->setCellValue("A{$row}", 'TOTAL ARMADA KENDARAAN');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("C{$row}", "=SUM(C{$startData2}:C" . ($row - 1) . ")");
        $sheet->setCellValue("D{$row}", "=SUM(D{$startData2}:D" . ($row - 1) . ")");
        $sheet->setCellValue("E{$row}", '-');
        $sheet->setCellValue("F{$row}", 'Seluruh kendaraan bermotor aktif yang terdaftar');

        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);

        $this->styleDataBorders($sheet, "A{$startData2}:F{$row}");
        $this->styleTotalRow($sheet, "A{$row}:F{$row}");

        $row += 2;

        // Catatan Keterbatasan Data Historis & Pemisahan Populasi
        $sheet->setCellValue("A{$row}", '* Catatan: Kondisi seluruh aset aktif berdasarkan data kondisi terakhir. Rekap kondisi mencakup seluruh aset yang masih aktif dalam cakupan unit laporan, tanpa membatasi tahun pengadaan. Aset dari tahun pengadaan sebelumnya tetap dihitung selama masih menjadi aset aktif Disbun.');
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::COLOR_TEXT_MUTED));

        $this->autoFitColumns($sheet, 'F');
    }

    // -------------------------------------------------------------------------
    // SHEET 5: LOKASI DAN PENEMPATAN
    // -------------------------------------------------------------------------
    private function buildSheetLokasiPenempatan($sheet, array $periode, array $k, array $grafik, array $meta): void
    {
        $row = $this->buildHeaderBlock($sheet, 'Sheet 5: Rekapitulasi Lokasi Fisik, Penempatan, & Pemegang', $periode, $meta);

        $this->styleSectionBanner($sheet, "A{$row}", 'REKAPITULASI ASET BERDASARKAN LOKASI FISIK (GROUPED STACKED)', "A{$row}:Q{$row}");
        $row += 2;

        // Header 2 tingkat
        $h1 = $row;
        $h2 = $row + 1;

        $sheet->setCellValue("A{$h1}", 'No');
        $sheet->setCellValue("B{$h1}", 'Lokasi Fisik Aset');
        $sheet->setCellValue("C{$h1}", 'BARANG INVENTARIS (KONDISI FISIK)');
        $sheet->setCellValue("H{$h1}", 'KENDARAAN BERMOTOR (KONDISI FISIK)');
        $sheet->setCellValue("M{$h1}", 'TOTAL ASET');
        $sheet->setCellValue("N{$h1}", 'STATUS RUANGAN');
        $sheet->setCellValue("P{$h1}", 'STATUS PEMEGANG');

        $sheet->mergeCells("A{$h1}:A{$h2}");
        $sheet->mergeCells("B{$h1}:B{$h2}");
        $sheet->mergeCells("C{$h1}:G{$h1}");
        $sheet->mergeCells("H{$h1}:L{$h1}");
        $sheet->mergeCells("M{$h1}:M{$h2}");
        $sheet->mergeCells("N{$h1}:O{$h1}");
        $sheet->mergeCells("P{$h1}:Q{$h1}");

        $subHeaders = [
            'Baik', 'R. Ringan', 'R. Berat', 'Blm Diisi', 'Subtotal', // C-G
            'Baik', 'R. Ringan', 'R. Berat', 'Blm Diisi', 'Subtotal', // H-L
            'Ada Ruangan', 'Tanpa Ruang',                             // N-O
            'Ada Pemegang', 'Tanpa Pemegang'                          // P-Q
        ];

        $colIdx = 'C';
        foreach (array_slice($subHeaders, 0, 5) as $sh) {
            $sheet->setCellValue("{$colIdx}{$h2}", $sh);
            $colIdx++;
        }
        foreach (array_slice($subHeaders, 5, 5) as $sh) {
            $sheet->setCellValue("{$colIdx}{$h2}", $sh);
            $colIdx++;
        }
        // Lewati M (Total Aset)
        $colIdx = 'N';
        foreach (array_slice($subHeaders, 10, 4) as $sh) {
            $sheet->setCellValue("{$colIdx}{$h2}", $sh);
            $colIdx++;
        }

        $this->styleTableHeaders($sheet, "A{$h1}:Q{$h2}");
        $row = $h2 + 1;

        $startData = $row;
        $lokasiList = $grafik['kondisi_unit']['ringkasan']['per_lokasi'] ?? [];
        $no = 1;

        foreach ($lokasiList as $idx => $rLok) {
            $namaLok = $rLok['lokasi'];

            $bBaik = $grafik['kondisi_unit']['barang_baik'][$idx] ?? 0;
            $bRR = $grafik['kondisi_unit']['barang_rusak_ringan'][$idx] ?? 0;
            $bRB = $grafik['kondisi_unit']['barang_rusak_berat'][$idx] ?? 0;
            $bBlm = $grafik['kondisi_unit']['barang_belum_diisi'][$idx] ?? 0;
            $bSub = $rLok['barang_count'] ?? ($bBaik + $bRR + $bRB + $bBlm);

            $kBaik = $grafik['kondisi_unit']['kendaraan_baik'][$idx] ?? 0;
            $kRR = $grafik['kondisi_unit']['kendaraan_rusak_ringan'][$idx] ?? 0;
            $kRB = $grafik['kondisi_unit']['kendaraan_rusak_berat'][$idx] ?? 0;
            $kBlm = $grafik['kondisi_unit']['kendaraan_belum_diisi'][$idx] ?? 0;
            $kSub = $rLok['kendaraan_count'] ?? ($kBaik + $kRR + $kRB + $kBlm);

            $totLok = $rLok['total_aset'] ?? ($bSub + $kSub);
            $adaRuang = $rLok['dengan_ruangan'] ?? 0;
            $tanpaRuang = $rLok['tanpa_ruangan'] ?? 0;
            $adaPemegang = $rLok['dengan_pemegang'] ?? 0;
            $tanpaPemegang = $rLok['tanpa_pemegang'] ?? 0;

            $sheet->fromArray([
                $no++, $namaLok,
                $bBaik, $bRR, $bRB, $bBlm, "=SUM(C{$row}:F{$row})",
                $kBaik, $kRR, $kRB, $kBlm, "=SUM(H{$row}:K{$row})",
                "=G{$row}+L{$row}",
                $adaRuang, $tanpaRuang,
                $adaPemegang, $tanpaPemegang
            ], null, "A{$row}", true);

            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}:Q{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
            $sheet->getStyle("C{$row}:Q{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        // Baris Total Konsolidasi
        $sheet->setCellValue("A{$row}", 'TOTAL KONSOLIDASI');
        $sheet->mergeCells("A{$row}:B{$row}");

        foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q'] as $c) {
            $sheet->setCellValue("{$c}{$row}", "=SUM({$c}{$startData}:{$c}" . ($row - 1) . ")");
            $sheet->getStyle("{$c}{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
            $sheet->getStyle("{$c}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $this->styleDataBorders($sheet, "A{$startData}:Q{$row}");
        $this->styleTotalRow($sheet, "A{$row}:Q{$row}");

        $row += 2;
        $sheet->setCellValue("A{$row}", '* Lokasi fisik murni menggunakan aset.id_lokasi. Khusus kendaraan bermotor menggunakan lokasi master dengan fallback lokasi ruangan pemegang aktif. Register Tanah KIB A disajikan terpisah dari tabel ini.');
        $sheet->mergeCells("A{$row}:Q{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::COLOR_TEXT_MUTED));

        $this->autoFitColumns($sheet, 'Q');
    }

    // -------------------------------------------------------------------------
    // SHEET 6: AKTIVITAS DAN PAJAK
    // -------------------------------------------------------------------------
    private function buildSheetAktivitasPajak($sheet, array $periode, array $k, array $grafik, array $meta): void
    {
        $row = $this->buildHeaderBlock($sheet, 'Sheet 6: Aktivitas Mutasi/Pengadaan & Realisasi Pajak', $periode, $meta);

        // Bagian A: Aktivitas Berjalan Dalam Periode
        $this->styleSectionBanner($sheet, "A{$row}", 'I. AKTIVITAS PENGADAAN BARU & MUTASI ASET (FLOW DALAM PERIODE)', "A{$row}:E{$row}");
        $row += 2;

        $hRow = $row;
        $sheet->fromArray([
            'No', 'Interval Waktu / Bulan', 'Pengadaan Baru (Unit)', 'Mutasi Aset (Transaksi)', 'Keterangan Aktivitas'
        ], null, "A{$hRow}", true);
        $this->styleTableHeaders($sheet, "A{$hRow}:E{$hRow}");
        $row++;

        $startData = $row;
        $labelsAkt = $grafik['aktivitas']['labels'] ?? [];
        $dataPengadaan = $grafik['aktivitas']['pengadaan'] ?? [];
        $dataMutasi = $grafik['aktivitas']['mutasi'] ?? [];
        $no = 1;

        foreach ($labelsAkt as $idx => $lbl) {
            $p = $dataPengadaan[$idx] ?? 0;
            $m = $dataMutasi[$idx] ?? 0;

            $sheet->fromArray([$no++, $lbl, $p, $m, 'Aktivitas riil pada interval tersebut'], null, "A{$row}", true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}:D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
            $sheet->getStyle("C{$row}:D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        // Total Aktivitas
        $sheet->setCellValue("A{$row}", 'TOTAL AKTIVITAS PERIODE');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("C{$row}", "=SUM(C{$startData}:C" . ($row - 1) . ")");
        $sheet->setCellValue("D{$row}", "=SUM(D{$startData}:D" . ($row - 1) . ")");
        $sheet->setCellValue("E{$row}", "Total Nilai Belanja Pengadaan Baru: Rp " . number_format($k['pengadaan_baru_nilai'], 0, ',', '.'));

        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C{$row}:D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);

        $this->styleDataBorders($sheet, "A{$startData}:E{$row}");
        $this->styleTotalRow($sheet, "A{$row}:E{$row}");

        $row += 2;

        // Bagian B: Realisasi Pajak Kendaraan (Flow)
        $this->styleSectionBanner($sheet, "A{$row}", 'II. REALISASI PEMBAYARAN PAJAK KENDARAAN (FLOW PERIODE)', "A{$row}:E{$row}");
        $row += 2;

        $hRow2 = $row;
        $sheet->fromArray([
            'No', 'Indikator Realisasi Pajak', 'Capaian Riil', 'Satuan', 'Keterangan Finansial'
        ], null, "A{$hRow2}", true);
        $this->styleTableHeaders($sheet, "A{$hRow2}:E{$hRow2}");
        $row++;

        $startData2 = $row;
        $realisasiPajak = [
            [1, 'Jumlah Transaksi Pembayaran Pajak', $k['pajak_flow_transaksi_count'], 'Transaksi', 'Frekuensi pembayaran PKB pada periode ini'],
            [2, 'Jumlah Kendaraan Bermotor Terbayar', $k['pajak_flow_kendaraan_count'], 'Unit', 'Unit kendaraan fisik yang diselesaikan kewajiban pajaknya'],
            [3, 'Total Realisasi Anggaran Pembayaran Pajak', $k['pajak_flow_total_nominal'], 'Rupiah', 'Total nominal belanja pajak (PKB + SWDKLLJ)'],
        ];

        foreach ($realisasiPajak as $rp) {
            $sheet->fromArray($rp, null, "A{$row}", true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            if ($rp[3] === 'Rupiah') {
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
            } else {
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_JUMLAH);
            }
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $this->styleDataBorders($sheet, "A{$startData2}:E" . ($row - 1));

        $this->autoFitColumns($sheet, 'E');
    }
}

