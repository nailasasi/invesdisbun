<?php

namespace App\Services;

use App\Models\Aset;
use App\Models\PemegangAset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Service untuk memformat dan membangun berkas Excel (.xlsx) Monitoring Aset.
 *
 * Mendukung 4 jenis laporan:
 * 1. Rekap Seluruh Aset (seluruh)
 * 2. Rekap Aset per SKPD Pemegang (per_skpd)
 * 3. Rekap Aset per Pemegang (per_pemegang)
 * 4. Rekap Aset per Ruangan (per_ruangan)
 */
class MonitoringAsetExporter
{
    public const JENIS_SELURUH = 'seluruh';
    public const JENIS_PER_SKPD = 'per_skpd';
    public const JENIS_PER_PEMEGANG = 'per_pemegang';
    public const JENIS_PER_RUANGAN = 'per_ruangan';

    public const TANPA_NILAI = '-';

    /**
     * Bangun spreadsheet lengkap berdasarkan jenis laporan.
     */
    public function generate(
        Collection $asetList,
        string $jenisLaporan,
        string $periodeDipilih,
        array $filterInfo = []
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Normalisasi jenis laporan
        $jenisLaporan = match ($jenisLaporan) {
            self::JENIS_PER_SKPD, 'skpd' => self::JENIS_PER_SKPD,
            self::JENIS_PER_PEMEGANG, 'pemegang', 'per_pengguna' => self::JENIS_PER_PEMEGANG,
            self::JENIS_PER_RUANGAN, 'ruangan', 'melekat_ruangan' => self::JENIS_PER_RUANGAN,
            default => self::JENIS_SELURUH,
        };

        match ($jenisLaporan) {
            self::JENIS_PER_SKPD => $this->buildPerSkpd($spreadsheet, $sheet, $asetList, $periodeDipilih, $filterInfo),
            self::JENIS_PER_PEMEGANG => $this->buildPerPemegang($spreadsheet, $sheet, $asetList, $periodeDipilih, $filterInfo),
            self::JENIS_PER_RUANGAN => $this->buildPerRuangan($spreadsheet, $sheet, $asetList, $periodeDipilih, $filterInfo),
            default => $this->buildSeluruhAset($spreadsheet, $sheet, $asetList, $periodeDipilih, $filterInfo),
        };

        // Pastikan active sheet kembali ke sheet pertama
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * 1. REKAP SELURUH ASET
     * Memuat semua aset. Kolom ke-10 menampilkan Ruangan agar aset berpemegang
     * dan berpenempatan fisik sekaligus tampak pada baris yang sama.
     */
    private function buildSeluruhAset(
        Spreadsheet $spreadsheet,
        Worksheet $sheet,
        Collection $asetList,
        string $periodeDipilih,
        array $filterInfo
    ): void {
        $sheet->setTitle('Rekap Seluruh Aset');

        $headers = [
            'No',
            'No. Kartu Barang',
            'Bidang Pengadaan',
            'Tahun',
            'Merk',
            'Type/Model',
            'Kondisi',
            'Hasil Sensus',
            'Nama Pengguna',
            'Unit Kerja Pengguna',
            'Ruangan',
        ];

        $this->tulisKopLaporan(
            $sheet,
            'REKAPITULASI SELURUH ASET',
            'Laporan inventaris seluruh aset dinas yang aktif (berpemegang, melekat ruangan, maupun belum memiliki penempatan).',
            $periodeDipilih,
            $filterInfo,
            count($headers),
            $asetList->count()
        );

        $headerRow = 5;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $this->styleHeaderRow($sheet, "A{$headerRow}:K{$headerRow}");

        $rowNum = $headerRow + 1;
        $no = 1;

        foreach ($asetList as $aset) {
            $pemegangs = $this->pemegangAktifList($aset);
            $namaPengguna = self::TANPA_NILAI;
            $unitKerja = self::TANPA_NILAI;

            if ($pemegangs->count() === 1) {
                $p = $pemegangs->first();
                $namaPengguna = $p->pegawai?->nama_pegawai ?: self::TANPA_NILAI;
                $unitKerja = $p->pegawai?->skpd?->nama_skpd ?: self::TANPA_NILAI;
            } elseif ($pemegangs->count() > 1) {
                $namaPengguna = $pemegangs->map(fn ($p) => $p->pegawai?->nama_pegawai)->filter()->implode(', ') ?: self::TANPA_NILAI;
                $unitKerja = 'Pemegang Ganda (' . $pemegangs->count() . ')';
            } elseif ($aset->is_kendaraan && ! empty($aset->kendaraan?->pemegang)) {
                $namaPengguna = trim((string) $aset->kendaraan->pemegang);
                $unitKerja = $aset->skpd?->nama_skpd ?: self::TANPA_NILAI;
            }

            $ruangan = $aset->penempatanAktif?->ruangan?->nama_ruangan
                ?: ($aset->lokasi?->nama_lokasi ?: 'Lokasi Belum Ditentukan');

            $sheet->fromArray([
                $no++,
                (string) ($aset->nomor_kartu_barang ?: self::TANPA_NILAI),
                self::TANPA_NILAI,
                $this->tahunPengadaan($aset),
                $this->merkAset($aset),
                $this->typeModelAset($aset),
                $aset->kondisi ?: self::TANPA_NILAI,
                $this->hasilSensusAset($aset),
                $namaPengguna,
                $unitKerja,
                $ruangan,
            ], null, "A{$rowNum}");

            $sheet->getRowDimension($rowNum)->setRowHeight(20);
            $rowNum++;
        }

        $lastDataRow = max($headerRow, $rowNum - 1);

        // Baris Grand Total
        $totalRow = $rowNum;
        $sheet->mergeCells("A{$totalRow}:J{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL KESELURUHAN');
        $sheet->setCellValue("K{$totalRow}", ($no - 1) . ' Barang');
        $this->styleTotalRow($sheet, "A{$totalRow}:K{$totalRow}");

        // Borders dan Alignment
        $this->styleTabelBordersDanAlignment($sheet, 6, $lastDataRow, 'K');
        $sheet->getStyle("K{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // AutoFilter & Freeze Pane
        $sheet->setAutoFilter("A{$headerRow}:K{$lastDataRow}");
        $sheet->freezePane('A6');

        $this->setupPrintPage($sheet, 'K');
    }

    /**
     * 2. REKAP ASET PER SKPD PEMEGANG
     * Hanya aset dengan tepat satu pemegang aktif, dikelompokkan per SKPD.
     */
    private function buildPerSkpd(
        Spreadsheet $spreadsheet,
        Worksheet $sheet,
        Collection $asetList,
        string $periodeDipilih,
        array $filterInfo
    ): void {
        $sheet->setTitle('Aset per SKPD');

        // Pisahkan aset valid (tepat 1 pemegang aktif atau kendaraan berpemegang) dan anomali (>1 pemegang)
        $validAsets = $asetList->filter(fn (Aset $a) => $this->pemegangTunggalEfektif($a) !== null);
        $anomaliAsets = $asetList->filter(fn (Aset $a) => $this->pemegangAktifList($a)->count() > 1);

        $headers = [
            'No',
            'Bidang Pengadaan',
            'Tahun',
            'Merk',
            'Type/Model',
            'Kondisi',
            'Hasil Sensus',
            'Nama Pengguna',
            'Unit Kerja Pengguna',
        ];

        $anomaliNote = $anomaliAsets->isNotEmpty()
            ? ' (Catatan: Terdapat ' . $anomaliAsets->count() . ' aset pemegang ganda dikecualikan — lihat sheet "Anomali Pemegang")'
            : '';

        $this->tulisKopLaporan(
            $sheet,
            'REKAPITULASI ASET PER SKPD PEMEGANG',
            'Hanya memuat aset dinas yang memiliki tepat 1 pemegang aktif, dikelompokkan berdasarkan SKPD pemegang.' . $anomaliNote,
            $periodeDipilih,
            $filterInfo,
            count($headers),
            $validAsets->count()
        );

        $headerRow = 5;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $this->styleHeaderRow($sheet, "A{$headerRow}:I{$headerRow}");

        // Kelompokkan per SKPD pemegang
        $grouped = $validAsets->groupBy(function (Aset $a) {
            $info = $this->pemegangTunggalEfektif($a);
            return $info['unit'] ?? 'Tanpa SKPD';
        })->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);

        $rowNum = $headerRow + 1;
        $no = 1;
        $dataRows = [];

        foreach ($grouped as $namaSkpd => $items) {
            // Baris Judul Kelompok
            $sheet->mergeCells("A{$rowNum}:I{$rowNum}");
            $sheet->setCellValue("A{$rowNum}", "SKPD: {$namaSkpd} ({$items->count()} Barang)");
            $this->styleGroupHeaderRow($sheet, "A{$rowNum}:I{$rowNum}");
            $rowNum++;

            // Urutkan item dalam kelompok berdasarkan nama pegawai, lalu type/model
            $sortedItems = $items->sort(function (Aset $a, Aset $b) {
                $pA = $this->pemegangTunggalEfektif($a)['nama'] ?? '';
                $pB = $this->pemegangTunggalEfektif($b)['nama'] ?? '';
                $cmp = strcasecmp($pA, $pB);
                return $cmp !== 0 ? $cmp : ($a->id_aset <=> $b->id_aset);
            });

            foreach ($sortedItems as $aset) {
                $info = $this->pemegangTunggalEfektif($aset);

                $sheet->fromArray([
                    $no++,
                    self::TANPA_NILAI,
                    $this->tahunPengadaan($aset),
                    $this->merkAset($aset),
                    $this->typeModelAset($aset),
                    $aset->kondisi ?: self::TANPA_NILAI,
                    $this->hasilSensusAset($aset),
                    $info['nama'] ?? self::TANPA_NILAI,
                    $info['unit'] ?? self::TANPA_NILAI,
                ], null, "A{$rowNum}");

                $sheet->getRowDimension($rowNum)->setRowHeight(20);
                $dataRows[] = $rowNum;
                $rowNum++;
            }
        }

        // Grand Total
        $totalRow = $rowNum;
        $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL KESELURUHAN');
        $sheet->mergeCells("H{$totalRow}:I{$totalRow}");
        $sheet->setCellValue("H{$totalRow}", ($no - 1) . ' Barang');
        $this->styleTotalRow($sheet, "A{$totalRow}:I{$totalRow}");
        $sheet->getStyle("H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Styling border & perataan baris data
        foreach ($dataRows as $r) {
            $this->styleTabelBordersDanAlignment($sheet, $r, $r, 'I');
        }

        $sheet->freezePane('A6');
        $this->setupPrintPage($sheet, 'I');

        // Jika ada anomali, tambahkan sheet kedua
        if ($anomaliAsets->isNotEmpty()) {
            $this->buildSheetAnomali($spreadsheet, $anomaliAsets);
        }
    }

    /**
     * 3. REKAP ASET PER PEMEGANG
     * Hanya aset dengan tepat satu pemegang aktif, dikelompokkan per Pegawai.
     */
    private function buildPerPemegang(
        Spreadsheet $spreadsheet,
        Worksheet $sheet,
        Collection $asetList,
        string $periodeDipilih,
        array $filterInfo
    ): void {
        $sheet->setTitle('Aset per Pemegang');

        $validAsets = $asetList->filter(fn (Aset $a) => $this->pemegangTunggalEfektif($a) !== null);
        $anomaliAsets = $asetList->filter(fn (Aset $a) => $this->pemegangAktifList($a)->count() > 1);

        $headers = [
            'No',
            'Bidang Pengadaan',
            'Tahun',
            'Merk',
            'Type/Model',
            'Kondisi',
            'Hasil Sensus',
            'Nama Pengguna',
            'Unit Kerja Pengguna',
        ];

        $anomaliNote = $anomaliAsets->isNotEmpty()
            ? ' (Catatan: Terdapat ' . $anomaliAsets->count() . ' aset pemegang ganda dikecualikan — lihat sheet "Anomali Pemegang")'
            : '';

        $this->tulisKopLaporan(
            $sheet,
            'REKAPITULASI ASET PER PEMEGANG',
            'Hanya memuat aset dinas yang memiliki tepat 1 pemegang aktif, dikelompokkan berdasarkan pegawai pemegang.' . $anomaliNote,
            $periodeDipilih,
            $filterInfo,
            count($headers),
            $validAsets->count()
        );

        $headerRow = 5;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $this->styleHeaderRow($sheet, "A{$headerRow}:I{$headerRow}");

        // Kelompokkan per Pegawai pemegang
        $grouped = $validAsets->groupBy(function (Aset $a) {
            $info = $this->pemegangTunggalEfektif($a);
            $nama = $info['nama'] ?? 'Tanpa Nama Pegawai';
            $skpd = $info['unit'] ?? '-';
            return "{$nama} (SKPD: {$skpd})";
        })->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);

        $rowNum = $headerRow + 1;
        $no = 1;
        $dataRows = [];

        foreach ($grouped as $namaGrup => $items) {
            $sheet->mergeCells("A{$rowNum}:I{$rowNum}");
            $sheet->setCellValue("A{$rowNum}", "Pemegang: {$namaGrup} — {$items->count()} Barang");
            $this->styleGroupHeaderRow($sheet, "A{$rowNum}:I{$rowNum}");
            $rowNum++;

            $sortedItems = $items->sort(function (Aset $a, Aset $b) {
                return strcasecmp($this->typeModelAset($a), $this->typeModelAset($b)) ?: ($a->id_aset <=> $b->id_aset);
            });

            foreach ($sortedItems as $aset) {
                $info = $this->pemegangTunggalEfektif($aset);

                $sheet->fromArray([
                    $no++,
                    self::TANPA_NILAI,
                    $this->tahunPengadaan($aset),
                    $this->merkAset($aset),
                    $this->typeModelAset($aset),
                    $aset->kondisi ?: self::TANPA_NILAI,
                    $this->hasilSensusAset($aset),
                    $info['nama'] ?? self::TANPA_NILAI,
                    $info['unit'] ?? self::TANPA_NILAI,
                ], null, "A{$rowNum}");

                $sheet->getRowDimension($rowNum)->setRowHeight(20);
                $dataRows[] = $rowNum;
                $rowNum++;
            }
        }

        // Grand Total
        $totalRow = $rowNum;
        $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL KESELURUHAN');
        $sheet->mergeCells("H{$totalRow}:I{$totalRow}");
        $sheet->setCellValue("H{$totalRow}", ($no - 1) . ' Barang');
        $this->styleTotalRow($sheet, "A{$totalRow}:I{$totalRow}");
        $sheet->getStyle("H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($dataRows as $r) {
            $this->styleTabelBordersDanAlignment($sheet, $r, $r, 'I');
        }

        $sheet->freezePane('A6');
        $this->setupPrintPage($sheet, 'I');

        if ($anomaliAsets->isNotEmpty()) {
            $this->buildSheetAnomali($spreadsheet, $anomaliAsets);
        }
    }

    /**
     * 4. REKAP ASET PER RUANGAN
     * Hanya aset yang memiliki penempatan ruangan aktif, dikelompokkan per Ruangan.
     * Aset yang juga memiliki pemegang perorangan tetap dicantumkan pemegangnya.
     */
    private function buildPerRuangan(
        Spreadsheet $spreadsheet,
        Worksheet $sheet,
        Collection $asetList,
        string $periodeDipilih,
        array $filterInfo
    ): void {
        $sheet->setTitle('Aset per Ruangan');

        // Filter aset yang memiliki penempatan ruangan aktif
        $validAsets = $asetList->filter(fn (Aset $a) => $a->penempatanAktif !== null && $a->penempatanAktif->ruangan !== null);

        $headers = [
            'No',
            'Bidang Pengadaan',
            'Tahun',
            'Merk',
            'Type/Model',
            'Kondisi',
            'Hasil Sensus',
            'Nama Pengguna',
            'Unit Kerja Pengguna',
        ];

        $this->tulisKopLaporan(
            $sheet,
            'REKAPITULASI ASET PER RUANGAN',
            'Memuat aset dinas yang ditempatkan pada ruangan aktif, dikelompokkan berdasarkan nama ruangan fisik.',
            $periodeDipilih,
            $filterInfo,
            count($headers),
            $validAsets->count()
        );

        $headerRow = 5;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $this->styleHeaderRow($sheet, "A{$headerRow}:I{$headerRow}");

        $grouped = $validAsets->groupBy(function (Aset $a) {
            $ruangan = $a->penempatanAktif?->ruangan;
            $namaRuangan = $ruangan?->nama_ruangan ?: 'Tanpa Nama Ruangan';
            $skpdRuangan = $ruangan?->skpd?->nama_skpd;
            return $skpdRuangan ? "{$namaRuangan} ({$skpdRuangan})" : $namaRuangan;
        })->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);

        $rowNum = $headerRow + 1;
        $no = 1;
        $dataRows = [];

        foreach ($grouped as $namaGrupRuangan => $items) {
            $sheet->mergeCells("A{$rowNum}:I{$rowNum}");
            $sheet->setCellValue("A{$rowNum}", "Ruangan: {$namaGrupRuangan} — {$items->count()} Barang");
            $this->styleGroupHeaderRow($sheet, "A{$rowNum}:I{$rowNum}");
            $rowNum++;

            $sortedItems = $items->sort(function (Aset $a, Aset $b) {
                return strcasecmp($this->typeModelAset($a), $this->typeModelAset($b)) ?: ($a->id_aset <=> $b->id_aset);
            });

            foreach ($sortedItems as $aset) {
                $pemegangs = $this->pemegangAktifList($aset);
                $ruangan = $aset->penempatanAktif?->ruangan;

                if ($pemegangs->count() === 1) {
                    $p = $pemegangs->first();
                    $namaPengguna = $p->pegawai?->nama_pegawai ?: self::TANPA_NILAI;
                    $unitKerja = $p->pegawai?->skpd?->nama_skpd ?: ($ruangan?->skpd?->nama_skpd ?: self::TANPA_NILAI);
                } elseif ($pemegangs->count() > 1) {
                    $namaPengguna = $pemegangs->map(fn ($p) => $p->pegawai?->nama_pegawai)->filter()->implode(', ') ?: self::TANPA_NILAI;
                    $unitKerja = $ruangan?->skpd?->nama_skpd ?: 'Pemegang Ganda (' . $pemegangs->count() . ')';
                } elseif ($aset->is_kendaraan && ! empty($aset->kendaraan?->pemegang)) {
                    $namaPengguna = trim((string) $aset->kendaraan->pemegang);
                    $unitKerja = $aset->skpd?->nama_skpd ?: ($ruangan?->skpd?->nama_skpd ?: self::TANPA_NILAI);
                } else {
                    $namaPengguna = self::TANPA_NILAI;
                    // Untuk aset ruangan tanpa pemegang, unit kerja mengikuti SKPD penanggung jawab ruangan
                    $unitKerja = $ruangan?->skpd?->nama_skpd ?: self::TANPA_NILAI;
                }

                $sheet->fromArray([
                    $no++,
                    self::TANPA_NILAI,
                    $this->tahunPengadaan($aset),
                    $this->merkAset($aset),
                    $this->typeModelAset($aset),
                    $aset->kondisi ?: self::TANPA_NILAI,
                    $this->hasilSensusAset($aset),
                    $namaPengguna,
                    $unitKerja,
                ], null, "A{$rowNum}");

                $sheet->getRowDimension($rowNum)->setRowHeight(20);
                $dataRows[] = $rowNum;
                $rowNum++;
            }
        }

        // Grand Total
        $totalRow = $rowNum;
        $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL KESELURUHAN');
        $sheet->mergeCells("H{$totalRow}:I{$totalRow}");
        $sheet->setCellValue("H{$totalRow}", ($no - 1) . ' Barang');
        $this->styleTotalRow($sheet, "A{$totalRow}:I{$totalRow}");
        $sheet->getStyle("H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($dataRows as $r) {
            $this->styleTabelBordersDanAlignment($sheet, $r, $r, 'I');
        }

        $sheet->freezePane('A6');
        $this->setupPrintPage($sheet, 'I');
    }

    /**
     * SHEET KEDUA: ANOMALI PEMEGANG GANDA
     * Dibuat jika pada laporan per SKPD atau per Pemegang ditemukan aset dengan >1 pemegang aktif.
     */
    private function buildSheetAnomali(Spreadsheet $spreadsheet, Collection $anomaliAsets): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Anomali Pemegang');

        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'DAFTAR ASET DENGAN PEMEGANG AKTIF GANDA (ANOMALI)');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('991B1B');

        $sheet->mergeCells('A2:G2');
        $sheet->setCellValue('A2', 'Aset-aset di bawah memiliki lebih dari satu pemegang berstatus aktif. Dikecualikan dari rekap per SKPD/Pemegang agar tidak menimbulkan duplikasi.');
        $sheet->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('64748B');

        $headers = [
            'No',
            'No. Kartu Barang',
            'Nama Barang',
            'Merk',
            'Type/Model',
            'Jumlah Pemegang',
            'Daftar Pemegang Aktif (NIP - Nama)',
        ];

        $headerRow = 4;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DC2626']], // Rose / Red header
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        $rowNum = $headerRow + 1;
        $no = 1;

        foreach ($anomaliAsets as $aset) {
            $pemegangs = $this->pemegangAktifList($aset);
            $daftarNama = $pemegangs->map(function (PemegangAset $p) {
                $nip = $p->pegawai?->nip ?: '-';
                $nama = $p->pegawai?->nama_pegawai ?: 'Tanpa Nama';
                return "{$nip} - {$nama}";
            })->implode('; ');

            $sheet->fromArray([
                $no++,
                (string) ($aset->nomor_kartu_barang ?: self::TANPA_NILAI),
                (string) ($aset->barang?->nama_barang ?: ($aset->kendaraan?->namaTampil() ?: self::TANPA_NILAI)),
                $this->merkAset($aset),
                $this->typeModelAset($aset),
                $pemegangs->count(),
                $daftarNama,
            ], null, "A{$rowNum}");

            $sheet->getRowDimension($rowNum)->setRowHeight(20);
            $rowNum++;
        }

        $lastRow = max($headerRow, $rowNum - 1);
        $this->styleTabelBordersDanAlignment($sheet, 5, $lastRow, 'G');
        $sheet->getStyle("F5:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Auto width
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->freezePane('A5');
    }

    /**
     * Header & Kop Resmi Dokumen (Baris 1-3).
     */
    private function tulisKopLaporan(
        Worksheet $sheet,
        string $judul,
        string $deskripsi,
        string $periodeDipilih,
        array $filterInfo,
        int $jumlahKolom,
        int $totalAset
    ): void {
        $lastCol = chr(ord('A') + $jumlahKolom - 1);

        // Baris 1: Judul Laporan
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', $judul);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '047857']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        // Baris 2: Deskripsi & Periode Sensus
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', "{$deskripsi} — Periode Sensus: {$periodeDipilih}");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Baris 3: Filter Aktif & Timestamp Unduh
        $filterSummary = $this->ringkasanFilter($filterInfo);
        $tanggalCetak = Carbon::now()->translatedFormat('d F Y, H:i') . ' WIB';

        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->setCellValue('A3', "Filter: {$filterSummary} | Tanggal Unduh: {$tanggalCetak} | Total: {$totalAset} Aset");
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '64748B'], 'italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // Baris 4: Spacing kosong
        $sheet->getRowDimension(4)->setRowHeight(8);
    }

    /**
     * Styling baris header tabel kolom.
     */
    private function styleHeaderRow(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '065F46'],
                ],
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(26);
    }

    /**
     * Styling baris header kelompok (group header).
     */
    private function styleGroupHeaderRow(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E2E8F0']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);
    }

    /**
     * Styling baris grand total tabel.
     */
    private function styleTotalRow(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F1F5F9']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_DOUBLE,
                    'color' => ['rgb' => '475569'],
                ],
                'left' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
                'right' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);
    }

    /**
     * Borders dan Alignment standar baris data.
     */
    private function styleTabelBordersDanAlignment(Worksheet $sheet, int $startRow, int $endRow, string $maxCol): void
    {
        if ($startRow > $endRow) {
            return;
        }

        $range = "A{$startRow}:{$maxCol}{$endRow}";

        // Border thin abu-abu lembut
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Perataan horizontal spesifik kolom:
        if ($maxCol === 'K') {
            // Rekap Seluruh Aset (11 kolom: A=No, B=No.Kartu, C=Bidang, D=Tahun, E=Merk, F=Type, G=Kondisi, H=Sensus, I=Pengguna, J=Unit, K=Ruangan)
            $sheet->getStyle("A{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$startRow}:F{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("G{$startRow}:H{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$startRow}:K{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        } elseif ($maxCol === 'I') {
            // Laporan Kelompok (9 kolom: A=No, B=Bidang, C=Tahun, D=Merk, E=Type, F=Kondisi, G=Sensus, H=Pengguna, I=Unit)
            $sheet->getStyle("A{$startRow}:C{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$startRow}:E{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("F{$startRow}:G{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H{$startRow}:I{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        } else {
            // Default fallback
            $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$startRow}:{$maxCol}{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        $sheet->getStyle("D{$startRow}:E{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("H{$startRow}:{$maxCol}{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    /**
     * Konfigurasi orientasi halaman cetak Landscape dan auto column width.
     */
    private function setupPrintPage(Worksheet $sheet, string $maxCol): void
    {
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 5);

        foreach (range('A', $maxCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(6);
    }

    /**
     * Ambil list pemegang aktif aset.
     */
    private function pemegangAktifList(Aset $aset): Collection
    {
        return $aset->relationLoaded('pemegang')
            ? $aset->pemegang->where('status', 'aktif')->values()
            : $aset->pemegang()->where('status', 'aktif')->with('pegawai.skpd')->get();
    }

    /**
     * Ambil data pemegang tunggal efektif aset (relasi aktif atau string pemegang kendaraan).
     *
     * @return array{nama: string, unit: string}|null
     */
    private function pemegangTunggalEfektif(Aset $aset): ?array
    {
        $pemegangs = $this->pemegangAktifList($aset);

        if ($pemegangs->count() === 1) {
            $p = $pemegangs->first();

            return [
                'nama' => $p->pegawai?->nama_pegawai ?: self::TANPA_NILAI,
                'unit' => $p->pegawai?->skpd?->nama_skpd ?: ($aset->skpd?->nama_skpd ?: 'Tanpa SKPD'),
            ];
        }

        if ($pemegangs->isEmpty() && $aset->is_kendaraan && ! empty($aset->kendaraan?->pemegang)) {
            return [
                'nama' => trim((string) $aset->kendaraan->pemegang),
                'unit' => $aset->skpd?->nama_skpd ?: 'Tanpa SKPD',
            ];
        }

        return null;
    }

    /**
     * Tahun pengadaan aset (format YYYY).
     */
    private function tahunPengadaan(Aset $aset): string
    {
        $tgl = $aset->tanggal_pengadaan;
        if (! $tgl) {
            return self::TANPA_NILAI;
        }

        try {
            return Carbon::parse($tgl)->format('Y');
        } catch (\Throwable) {
            return self::TANPA_NILAI;
        }
    }

    /**
     * Merk aset: kendaraan menggunakan kendaraan->merk (fallback aset->merk),
     * barang menggunakan aset->merk.
     */
    private function merkAset(Aset $aset): string
    {
        $merk = $aset->is_kendaraan
            ? ($aset->kendaraan?->merk ?: $aset->merk)
            : $aset->merk;

        $merk = trim((string) $merk);

        return $merk !== '' ? $merk : self::TANPA_NILAI;
    }

    /**
     * Type/Model aset: kendaraan menggunakan kendaraan->tipe (fallback namaTampil()),
     * non-kendaraan menggunakan master_barang->nama_barang.
     */
    private function typeModelAset(Aset $aset): string
    {
        if ($aset->is_kendaraan) {
            $tipe = trim((string) $aset->kendaraan?->tipe);
            $plat = trim((string) ($aset->kendaraan?->platAktif?->nomor_plat ?? ''));
            $platSuffix = $plat !== '' ? " ({$plat})" : '';

            if ($tipe !== '') {
                return $tipe . $platSuffix;
            }

            $nama = $aset->kendaraan?->namaTampil() ?: self::TANPA_NILAI;

            return $nama !== self::TANPA_NILAI ? ($nama . $platSuffix) : self::TANPA_NILAI;
        }

        $namaBarang = trim((string) $aset->barang?->nama_barang);

        return $namaBarang !== '' ? $namaBarang : self::TANPA_NILAI;
    }

    /**
     * Hasil sensus pada periode yang sedang dievaluasi.
     */
    private function hasilSensusAset(Aset $aset): string
    {
        $sensus = $aset->relationLoaded('sensus')
            ? $aset->sensus->first()
            : null;

        return $sensus?->hasil_sensus ?: 'Belum Disensus';
    }

    /**
     * String ringkasan filter aktif.
     */
    private function ringkasanFilter(array $filterInfo): string
    {
        $parts = [];

        if (! empty($filterInfo['search'])) {
            $parts[] = "Pencarian: '{$filterInfo['search']}'";
        }
        if (! empty($filterInfo['tahun'])) {
            $parts[] = "Tahun: {$filterInfo['tahun']}";
        }
        if (! empty($filterInfo['status'])) {
            $parts[] = "Status: {$filterInfo['status']}";
        }
        if (! empty($filterInfo['jenis_aset'])) {
            $parts[] = 'Jenis: ' . ($filterInfo['jenis_aset'] === 'kendaraan' ? 'Kendaraan' : 'Barang');
        }
        if (! empty($filterInfo['penempatan'])) {
            $penempatanText = match ($filterInfo['penempatan']) {
                'memiliki_pemegang' => 'Memiliki Pemegang',
                'melekat_ruangan' => 'Melekat Ruangan',
                default => $filterInfo['penempatan'],
            };
            $parts[] = "Penempatan: {$penempatanText}";
        }

        return ! empty($parts) ? implode(' | ', $parts) : 'Semua Data (Tanpa Filter Tambahan)';
    }
}
