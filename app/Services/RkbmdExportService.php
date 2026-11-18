<?php

namespace App\Services;

use App\Models\Skpd;
use App\Models\TemplateDokumen;
use App\Models\User;
use App\Models\UsulanRkbmd;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RkbmdExportService
{
    public const MAP_JENIS = [
        'pengadaan' => 'Pengadaan',
        'pemeliharaan' => 'Pemeliharaan',
        'penghapusan' => 'Penghapusan',
        'pemindahtanganan' => 'Pemindahtanganan',
        'pemanfaatan' => 'Pemanfaatan',
    ];

    /**
     * Membangun Spreadsheet dari template resmi berdasarkan jenis usulan dan data yang sudah digabung.
     */
    public function generateSpreadsheet(
        Collection $rows,
        string $jenisKey,
        ?User $user,
        ?Skpd $filterSkpd = null,
        ?int $tahun = null
    ): Spreadsheet {
        $jenisKey = strtolower(trim($jenisKey));
        $templateCode = "rkbmd_{$jenisKey}";
        $jenisLabel = self::MAP_JENIS[$jenisKey] ?? ucfirst($jenisKey);

        $templateRecord = TemplateDokumen::where('kode_template', $templateCode)->first();
        if (! $templateRecord || ! $templateRecord->fileExists()) {
            throw new \RuntimeException("Template dokumen untuk jenis usulan {$jenisLabel} tidak ditemukan di penyimpanan server.");
        }

        $templatePath = $templateRecord->realPath();
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Grouping data usulan berdasarkan 5 field
        $groupedData = $this->groupUsulan($rows);

        // 2. Tahun Anggaran (dari filter atau data pertama atau tahun saat ini)
        $tahunAnggaran = $tahun ?: ($rows->first()?->tahun_anggaran ?: now()->year);

        // 3. Header Pengguna Barang
        $penggunaBarang = $this->getPenggunaBarangText($user, $filterSkpd);

        // 4. Isi lembar kerja sesuai spesifikasi template
        match ($jenisKey) {
            'pengadaan' => $this->populatePengadaan($sheet, $groupedData, $penggunaBarang, $tahunAnggaran),
            'pemeliharaan' => $this->populatePemeliharaan($sheet, $groupedData, $penggunaBarang, $tahunAnggaran),
            'penghapusan' => $this->populatePenghapusan($sheet, $groupedData, $penggunaBarang, $tahunAnggaran),
            'pemindahtanganan' => $this->populatePemindahtanganan($sheet, $groupedData, $penggunaBarang, $tahunAnggaran),
            'pemanfaatan' => $this->populatePemanfaatan($sheet, $groupedData, $penggunaBarang, $tahunAnggaran),
            default => throw new \InvalidArgumentException("Jenis usulan {$jenisKey} tidak didukung."),
        };

        // 5. Perbarui tanggal pada blok tanda tangan jika ada
        $this->updateSignatureDate($sheet);

        return $spreadsheet;
    }

    /**
     * Nama berkas unduhan standar: Ekspor_RKBMD_[Jenis]_[Tahun].xlsx
     */
    public function getExportFilename(string $jenisKey, ?int $tahun = null): string
    {
        $jenisKey = strtolower(trim($jenisKey));
        $label = self::MAP_JENIS[$jenisKey] ?? ucfirst($jenisKey);
        $tahunPart = $tahun ? (string) $tahun : 'SemuaTahun';

        return "Ekspor_RKBMD_{$label}_{$tahunPart}.xlsx";
    }

    /**
     * Gabungkan teks deskriptif secara aman dengan pemisah ' / ', menghindari duplikasi teks yang persis sama.
     */
    public function mergeDescriptiveText(?string $existing, ?string $new): ?string
    {
        $existingParts = [];
        if ($existing !== null && trim($existing) !== '') {
            $parts = explode(' / ', $existing);
            foreach ($parts as $p) {
                $pTrim = trim($p);
                if ($pTrim !== '' && ! in_array($pTrim, $existingParts, true)) {
                    $existingParts[] = $pTrim;
                }
            }
        }

        if ($new !== null && trim($new) !== '') {
            $parts = explode(' / ', $new);
            foreach ($parts as $p) {
                $pTrim = trim($p);
                if ($pTrim !== '' && ! in_array($pTrim, $existingParts, true)) {
                    $existingParts[] = $pTrim;
                }
            }
        }

        if (empty($existingParts)) {
            return null;
        }

        return implode(' / ', $existingParts);
    }

    /**
     * Kelompokkan data usulan berdasarkan 5 atribut identik:
     * 1. Program
     * 2. Sub Kegiatan
     * 3. Kode Barang
     * 4. Nama Barang
     * 5. Satuan
     */
    public function groupUsulan(Collection $rows): Collection
    {
        $groups = [];

        foreach ($rows as $item) {
            $program = trim((string) ($item->nama_program ?: ($item->kegiatan?->nama_program ?: '')));
            $subKegiatan = trim((string) ($item->nama_sub_kegiatan ?: ($item->kegiatan?->nama_sub_kegiatan ?: '')));
            $kode = trim((string) ($item->kode_barang ?? ''));
            $nama = trim((string) ($item->nama_barang ?? ''));
            $satuan = trim((string) ($item->satuan ?? ''));

            $key = mb_strtolower("{$program}|{$subKegiatan}|{$kode}|{$nama}|{$satuan}");

            $opt = $item->barang_optimalisasi;
            $hasOpt = is_array($opt) && (! empty($opt['kode']) || ! empty($opt['nama']) || ! empty($opt['jumlah']) || ! empty($opt['kode_barang']) || ! empty($opt['nama_barang']));
            $initialOpt = null;
            if ($hasOpt) {
                $initialOpt = [
                    'kode' => $opt['kode'] ?? $opt['kode_barang'] ?? null,
                    'nama' => $opt['nama'] ?? $opt['nama_barang'] ?? null,
                    'jumlah' => isset($opt['jumlah']) && $opt['jumlah'] !== null && $opt['jumlah'] !== '' ? (int) $opt['jumlah'] : null,
                    'satuan' => $opt['satuan'] ?? null,
                ];
            }

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'base' => $item,
                    'program' => $program,
                    'sub_kegiatan' => $subKegiatan,
                    'kode_barang' => $kode,
                    'nama_barang' => $nama,
                    'satuan' => $satuan,
                    'jumlah' => $item->jumlah !== null ? (int) $item->jumlah : null,
                    'kebutuhan_maksimum' => $item->kebutuhan_maksimum !== null ? (int) $item->kebutuhan_maksimum : null,
                    'kebutuhan_riil' => $item->kebutuhan_riil !== null ? (int) $item->kebutuhan_riil : null,
                    'barang_optimalisasi' => $initialOpt,
                    'alasan_kebutuhan' => $item->alasan_kebutuhan,
                    'spesifikasi' => $item->spesifikasi,
                    'catatan_pengurus' => $item->catatan_pengurus,
                    'skpd' => $item->skpd,
                    'tahun_anggaran' => $item->tahun_anggaran,
                ];
            } else {
                // 1. Kolom numerik akumulatif dijumlahkan secara matematis (SUM)
                if ($item->jumlah !== null) {
                    $groups[$key]['jumlah'] = ($groups[$key]['jumlah'] ?? 0) + (int) $item->jumlah;
                }
                if ($item->kebutuhan_maksimum !== null) {
                    $groups[$key]['kebutuhan_maksimum'] = ($groups[$key]['kebutuhan_maksimum'] ?? 0) + (int) $item->kebutuhan_maksimum;
                }
                if ($item->kebutuhan_riil !== null) {
                    $groups[$key]['kebutuhan_riil'] = ($groups[$key]['kebutuhan_riil'] ?? 0) + (int) $item->kebutuhan_riil;
                }

                // 2. Optimalisasi: jumlah di-SUM, atribut teks digabung deskriptif
                if ($hasOpt) {
                    if ($groups[$key]['barang_optimalisasi'] === null) {
                        $groups[$key]['barang_optimalisasi'] = $initialOpt;
                    } else {
                        if ($initialOpt['jumlah'] !== null) {
                            $groups[$key]['barang_optimalisasi']['jumlah'] = ($groups[$key]['barang_optimalisasi']['jumlah'] ?? 0) + $initialOpt['jumlah'];
                        }
                        $groups[$key]['barang_optimalisasi']['kode'] = $this->mergeDescriptiveText(
                            $groups[$key]['barang_optimalisasi']['kode'] ?? null,
                            $initialOpt['kode'] ?? null
                        );
                        $groups[$key]['barang_optimalisasi']['nama'] = $this->mergeDescriptiveText(
                            $groups[$key]['barang_optimalisasi']['nama'] ?? null,
                            $initialOpt['nama'] ?? null
                        );
                        $groups[$key]['barang_optimalisasi']['satuan'] = $this->mergeDescriptiveText(
                            $groups[$key]['barang_optimalisasi']['satuan'] ?? null,
                            $initialOpt['satuan'] ?? null
                        );
                    }
                }

                // 3. Kolom teks deskriptif digabung dengan pemisah ' / ' tanpa duplikasi
                $groups[$key]['alasan_kebutuhan'] = $this->mergeDescriptiveText(
                    $groups[$key]['alasan_kebutuhan'],
                    $item->alasan_kebutuhan
                );
                $groups[$key]['spesifikasi'] = $this->mergeDescriptiveText(
                    $groups[$key]['spesifikasi'],
                    $item->spesifikasi
                );
                $groups[$key]['catatan_pengurus'] = $this->mergeDescriptiveText(
                    $groups[$key]['catatan_pengurus'],
                    $item->catatan_pengurus
                );
            }
        }

        return collect(array_values($groups));
    }

    /**
     * Teks header PENGGUNA BARANG sesuai peran pengguna.
     */
    public function getPenggunaBarangText(?User $user, ?Skpd $filterSkpd = null): string
    {
        $roleName = $user?->role?->nama_role;
        $isAdmin = in_array($roleName, ['Admin Aset', 'Super Admin'], true);

        if ($isAdmin) {
            if ($filterSkpd) {
                $nama = strtoupper(trim($filterSkpd->nama_skpd));

                return 'PENGGUNA BARANG ' . $nama;
            }

            return 'PENGGUNA BARANG SEMUA BIDANG DAN UPT';
        }

        $skpd = $user?->pegawai?->skpd;
        $nama = $skpd ? strtoupper(trim($skpd->nama_skpd)) : '';

        if ($roleName === 'Admin Bidang') {
            if (str_starts_with($nama, 'BIDANG')) {
                return 'PENGGUNA BARANG ' . $nama;
            }

            return 'PENGGUNA BARANG BIDANG ' . $nama;
        }

        return 'PENGGUNA BARANG ' . ($nama ?: 'UPT');
    }

    /**
     * Format Kuasa Pengguna Barang/Program/Kegiatan/Output: "Program | Sub Kegiatan".
     */
    protected function formatProgramKegiatan(string $program, string $subKegiatan): string
    {
        if ($program !== '' && $subKegiatan !== '') {
            return "{$program} | {$subKegiatan}";
        }
        if ($program !== '') {
            return $program;
        }
        if ($subKegiatan !== '') {
            return $subKegiatan;
        }

        return 'NIHIL';
    }

    protected function nihilOrString($value): string
    {
        if ($value === null) {
            return 'NIHIL';
        }
        $str = trim((string) $value);
        if ($str === '' || $str === '-') {
            return 'NIHIL';
        }

        return $str;
    }

    protected function nihilOrInt($value)
    {
        if ($value === null || $value === '' || $value === '-') {
            return 'NIHIL';
        }

        return (int) $value;
    }

    /**
     * Isi data ke lembar kerja RKBMD Pengadaan.
     * Kolom A-O: No, Program|SubKegiatan, Usulan (Kode, Nama, Jml, Sat), Kebutuhan Maks (Jml, Sat),
     * Optimalisasi (Kode, Nama, Jml, Sat), Kebutuhan Riil (Jml, Sat), Keterangan.
     */
    protected function populatePengadaan($sheet, Collection $items, string $penggunaBarang, int $tahun): void
    {
        $sheet->setCellValue('A3', $penggunaBarang);
        $sheet->setCellValue('A4', "TAHUN {$tahun}");

        $startRow = 11;
        $count = $items->count();

        if ($count === 0) {
            // Tampilkan satu baris NIHIL
            $sheet->setCellValue('A11', 1);
            foreach (range('B', 'O') as $col) {
                $sheet->setCellValueExplicit("{$col}11", 'NIHIL', DataType::TYPE_STRING);
            }
            $this->applyRowStyle($sheet, 11, 'O');

            return;
        }

        foreach ($items as $idx => $item) {
            $r = $startRow + $idx;
            if ($idx > 0) {
                $sheet->insertNewRowBefore($r, 1);
                $sheet->duplicateStyle($sheet->getStyle("A{$startRow}:O{$startRow}"), "A{$r}:O{$r}");
            }

            $progSub = $this->formatProgramKegiatan($item['program'], $item['sub_kegiatan']);
            $opt = $item['barang_optimalisasi'];
            $hasOpt = is_array($opt) && (! empty($opt['kode']) || ! empty($opt['nama']) || ! empty($opt['jumlah']));

            $sheet->setCellValue("A{$r}", $idx + 1);
            $sheet->setCellValue("B{$r}", $progSub);
            $sheet->setCellValueExplicit("C{$r}", $this->nihilOrString($item['kode_barang']), DataType::TYPE_STRING);
            $sheet->setCellValue("D{$r}", $this->nihilOrString($item['nama_barang']));
            $this->setNumberOrNihil($sheet, "E{$r}", $item['jumlah']);
            $sheet->setCellValue("F{$r}", $this->nihilOrString($item['satuan']));

            // Kebutuhan Maksimum
            $this->setNumberOrNihil($sheet, "G{$r}", $item['kebutuhan_maksimum']);
            $sheet->setCellValue("H{$r}", $item['kebutuhan_maksimum'] !== null ? $this->nihilOrString($item['satuan']) : 'NIHIL');

            // Optimalisasi
            $sheet->setCellValueExplicit("I{$r}", $hasOpt ? $this->nihilOrString($opt['kode'] ?? $opt['kode_barang'] ?? null) : 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValue("J{$r}", $hasOpt ? $this->nihilOrString($opt['nama'] ?? $opt['nama_barang'] ?? null) : 'NIHIL');
            $this->setNumberOrNihil($sheet, "K{$r}", $hasOpt ? ($opt['jumlah'] ?? null) : null);
            $sheet->setCellValue("L{$r}", $hasOpt ? $this->nihilOrString($opt['satuan'] ?? null) : 'NIHIL');

            // Kebutuhan Riil
            $this->setNumberOrNihil($sheet, "M{$r}", $item['kebutuhan_riil']);
            $sheet->setCellValue("N{$r}", $item['kebutuhan_riil'] !== null ? $this->nihilOrString($item['satuan']) : 'NIHIL');

            // Keterangan
            $sheet->setCellValue("O{$r}", $this->nihilOrString($item['alasan_kebutuhan']));

            $this->applyRowStyle($sheet, $r, 'O');
        }
    }

    /**
     * Isi data ke lembar kerja RKBMD Pemeliharaan.
     * Kolom A-F: No, Program|SubKegiatan, Barang Dipelihara (Kode, Nama, Jml, Sat).
     * Kolom G-N: Dibiarkan KOSONG ("") untuk entri manual Admin.
     */
    protected function populatePemeliharaan($sheet, Collection $items, string $penggunaBarang, int $tahun): void
    {
        $sheet->setCellValue('A3', $penggunaBarang);
        $sheet->setCellValue('A4', "TAHUN {$tahun}");

        $startRow = 12;
        $count = $items->count();

        if ($count === 0) {
            $sheet->setCellValue('A12', 1);
            $sheet->setCellValue('B12', 'NIHIL');
            $sheet->setCellValueExplicit('C12', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValue('D12', 'NIHIL');
            $sheet->setCellValueExplicit('E12', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValue('F12', 'NIHIL');
            foreach (range('G', 'N') as $col) {
                $sheet->setCellValue("{$col}12", '');
            }
            $this->applyRowStyle($sheet, 12, 'N');

            return;
        }

        foreach ($items as $idx => $item) {
            $r = $startRow + $idx;
            if ($idx > 0) {
                $sheet->insertNewRowBefore($r, 1);
                $sheet->duplicateStyle($sheet->getStyle("A{$startRow}:N{$startRow}"), "A{$r}:N{$r}");
            }

            $progSub = $this->formatProgramKegiatan($item['program'], $item['sub_kegiatan']);

            $sheet->setCellValue("A{$r}", $idx + 1);
            $sheet->setCellValue("B{$r}", $progSub);
            $sheet->setCellValueExplicit("C{$r}", $this->nihilOrString($item['kode_barang']), DataType::TYPE_STRING);
            $sheet->setCellValue("D{$r}", $this->nihilOrString($item['nama_barang']));
            $this->setNumberOrNihil($sheet, "E{$r}", $item['jumlah']);
            $sheet->setCellValue("F{$r}", $this->nihilOrString($item['satuan']));

            // Kolom G sampai N dibiarkan KOSONG ("")
            foreach (range('G', 'N') as $col) {
                $sheet->setCellValue("{$col}{$r}", '');
            }

            $this->applyRowStyle($sheet, $r, 'N');
        }
    }

    /**
     * Isi data ke lembar kerja RKBMD Penghapusan.
     * Kolom A-J: NO, Kode Barang, Nama Barang, Spesifikasi, NIBAR (NIHIL),
     * Jumlah (F:G merged), Nilai Perolehan (NIHIL), Alasan, Keterangan.
     */
    protected function populatePenghapusan($sheet, Collection $items, string $penggunaBarang, int $tahun): void
    {
        $sheet->setCellValue('A4', $penggunaBarang);
        $sheet->setCellValue('A5', "TAHUN ANGGARAN {$tahun}");

        $startRow = 16;
        $count = $items->count();

        if ($count === 0) {
            $sheet->setCellValue('A16', 1);
            $sheet->setCellValueExplicit('B16', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValue('C16', 'NIHIL');
            $sheet->setCellValue('D16', 'NIHIL');
            $sheet->setCellValueExplicit('E16', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F16', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('H16', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValue('I16', 'NIHIL');
            $sheet->setCellValue('J16', 'NIHIL');
            $this->applyRowStyle($sheet, 16, 'J');

            return;
        }

        foreach ($items as $idx => $item) {
            $r = $startRow + $idx;
            if ($idx > 0) {
                $sheet->insertNewRowBefore($r, 1);
                $sheet->duplicateStyle($sheet->getStyle("A{$startRow}:J{$startRow}"), "A{$r}:J{$r}");
                $sheet->mergeCells("F{$r}:G{$r}");
            }

            $sheet->setCellValue("A{$r}", $idx + 1);
            $sheet->setCellValueExplicit("B{$r}", $this->nihilOrString($item['kode_barang']), DataType::TYPE_STRING);
            $sheet->setCellValue("C{$r}", $this->nihilOrString($item['nama_barang']));
            $sheet->setCellValue("D{$r}", $this->nihilOrString($item['spesifikasi']));
            $sheet->setCellValueExplicit("E{$r}", 'NIHIL', DataType::TYPE_STRING);
            $this->setNumberOrNihil($sheet, "F{$r}", $item['jumlah']);
            $sheet->setCellValueExplicit("H{$r}", 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValue("I{$r}", $this->nihilOrString($item['alasan_kebutuhan']));
            $sheet->setCellValue("J{$r}", $this->nihilOrString($item['catatan_pengurus']));

            $this->applyRowStyle($sheet, $r, 'J');
        }
    }

    /**
     * Isi data ke lembar kerja RKBMD Pemindahtanganan.
     * Kolom A-L: NO, Kode Barang, Nama Barang, Spesifikasi, NIBAR (NIHIL),
     * Jumlah (F:G merged), Lokasi (NIHIL), Nilai (NIHIL), Bentuk (NIHIL), Alasan, Keterangan.
     */
    protected function populatePemindahtanganan($sheet, Collection $items, string $penggunaBarang, int $tahun): void
    {
        $sheet->setCellValue('A3', $penggunaBarang);
        $sheet->setCellValue('A4', "TAHUN ANGGARAN {$tahun}");

        $startRow = 15;
        $count = $items->count();

        if ($count === 0) {
            $sheet->setCellValue('A15', 1);
            $sheet->setCellValueExplicit('B15', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValue('C15', 'NIHIL');
            $sheet->setCellValue('D15', 'NIHIL');
            $sheet->setCellValueExplicit('E15', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F15', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('H15', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('I15', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('J15', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValue('K15', 'NIHIL');
            $sheet->setCellValue('L15', 'NIHIL');
            $this->applyRowStyle($sheet, 15, 'L');

            return;
        }

        foreach ($items as $idx => $item) {
            $r = $startRow + $idx;
            if ($idx > 0) {
                $sheet->insertNewRowBefore($r, 1);
                $sheet->duplicateStyle($sheet->getStyle("A{$startRow}:L{$startRow}"), "A{$r}:L{$r}");
                $sheet->mergeCells("F{$r}:G{$r}");
            }

            $sheet->setCellValue("A{$r}", $idx + 1);
            $sheet->setCellValueExplicit("B{$r}", $this->nihilOrString($item['kode_barang']), DataType::TYPE_STRING);
            $sheet->setCellValue("C{$r}", $this->nihilOrString($item['nama_barang']));
            $sheet->setCellValue("D{$r}", $this->nihilOrString($item['spesifikasi']));
            $sheet->setCellValueExplicit("E{$r}", 'NIHIL', DataType::TYPE_STRING);
            $this->setNumberOrNihil($sheet, "F{$r}", $item['jumlah']);
            $sheet->setCellValueExplicit("H{$r}", 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("I{$r}", 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("J{$r}", 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValue("K{$r}", $this->nihilOrString($item['alasan_kebutuhan']));
            $sheet->setCellValue("L{$r}", $this->nihilOrString($item['catatan_pengurus']));

            $this->applyRowStyle($sheet, $r, 'L');
        }
    }

    /**
     * Isi data ke lembar kerja RKBMD Pemanfaatan.
     * Kolom A-X: No, SKPD (B:D merged), Nama Barang (E), Kode Barang (F),
     * Reg (G=NIHIL), Jumlah (H), Deskripsi Barang (I=NIHIL), Luas-Validasi (J-S=NIHIL),
     * Peruntukan (T), Bentuk-Jangka (U-W=NIHIL), Keterangan (X).
     */
    protected function populatePemanfaatan($sheet, Collection $items, string $penggunaBarang, int $tahun): void
    {
        $sheet->setCellValue('A3', $penggunaBarang);
        $sheet->setCellValue('A4', "TAHUN {$tahun}");

        $startRow = 12;
        $count = $items->count();

        // Pastikan H12:I12 pada template di-unmerge agar Kolom H (Jumlah) dan I (Deskripsi Barang) terpisah
        try {
            $sheet->unmergeCells('H12:I12');
        } catch (\Throwable $e) {
        }

        if ($count === 0) {
            $sheet->setCellValue('A12', 1);
            $sheet->setCellValue('B12', 'NIHIL');
            $sheet->setCellValue('E12', 'NIHIL');
            $sheet->setCellValueExplicit('F12', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('G12', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('H12', 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('I12', 'NIHIL', DataType::TYPE_STRING);
            foreach (range('J', 'S') as $col) {
                $sheet->setCellValueExplicit("{$col}12", 'NIHIL', DataType::TYPE_STRING);
            }
            $sheet->setCellValue('T12', 'NIHIL');
            foreach (range('U', 'W') as $col) {
                $sheet->setCellValueExplicit("{$col}12", 'NIHIL', DataType::TYPE_STRING);
            }
            $sheet->setCellValue('X12', 'NIHIL');
            $this->applyRowStyle($sheet, 12, 'X');

            return;
        }

        foreach ($items as $idx => $item) {
            $r = $startRow + $idx;
            if ($idx > 0) {
                $sheet->insertNewRowBefore($r, 1);
                $sheet->duplicateStyle($sheet->getStyle("A{$startRow}:X{$startRow}"), "A{$r}:X{$r}");
                $sheet->mergeCells("B{$r}:D{$r}");
            }

            try {
                $sheet->unmergeCells("H{$r}:I{$r}");
            } catch (\Throwable $e) {
            }

            $namaSkpd = $item['skpd']?->nama_skpd;

            $sheet->setCellValue("A{$r}", $idx + 1);
            $sheet->setCellValue("B{$r}", $this->nihilOrString($namaSkpd));
            $sheet->setCellValue("E{$r}", $this->nihilOrString($item['nama_barang']));
            $sheet->setCellValueExplicit("F{$r}", $this->nihilOrString($item['kode_barang']), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("G{$r}", 'NIHIL', DataType::TYPE_STRING);
            $this->setNumberOrNihil($sheet, "H{$r}", $item['jumlah']);
            $sheet->setCellValueExplicit("I{$r}", 'NIHIL', DataType::TYPE_STRING);

            // Kolom J sampai S adalah data teknis pemanfaatan yang tidak ada di sistem -> diisi NIHIL
            foreach (range('J', 'S') as $col) {
                $sheet->setCellValueExplicit("{$col}{$r}", 'NIHIL', DataType::TYPE_STRING);
            }

            // Peruntukan Pemanfaatan (T)
            $sheet->setCellValue("T{$r}", $this->nihilOrString($item['alasan_kebutuhan']));

            // Kolom U, V, W -> Bentuk, Kategori, Jangka Waktu -> NIHIL
            $sheet->setCellValueExplicit("U{$r}", 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("V{$r}", 'NIHIL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("W{$r}", 'NIHIL', DataType::TYPE_STRING);

            // Keterangan (X)
            $sheet->setCellValue("X{$r}", $this->nihilOrString($item['catatan_pengurus'] ?: $item['alasan_kebutuhan']));

            $this->applyRowStyle($sheet, $r, 'X');
        }
    }

    /**
     * Tulis angka jika ada, atau string 'NIHIL' jika null/kosong.
     */
    protected function setNumberOrNihil($sheet, string $cell, $value): void
    {
        if ($value === null || $value === '' || $value === '-') {
            $sheet->setCellValueExplicit($cell, 'NIHIL', DataType::TYPE_STRING);
        } else {
            $sheet->setCellValue($cell, (int) $value);
        }
    }

    /**
     * Terapkan border tipis dan perataan vertikal tengah untuk baris data.
     */
    protected function applyRowStyle($sheet, int $row, string $lastCol): void
    {
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    /**
     * Cari sel tanggal tanda tangan di bawah tabel dan perbarui ke tanggal terkini jika ada.
     */
    protected function updateSignatureDate($sheet): void
    {
        $maxRow = $sheet->getHighestRow();
        $colMaxIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $formattedDate = 'Surabaya, ' . Carbon::now()->translatedFormat('d F Y');

        for ($r = 15; $r <= $maxRow; $r++) {
            for ($c = 1; $c <= $colMaxIndex; $c++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $val = (string) $sheet->getCell("{$colLetter}{$r}")->getValue();
                if (stripos($val, 'Surabaya,') !== false) {
                    $sheet->setCellValue("{$colLetter}{$r}", $formattedDate);

                    return;
                }
            }
        }
    }
}
