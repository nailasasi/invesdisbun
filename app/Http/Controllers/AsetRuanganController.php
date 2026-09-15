<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\TemplateDokumen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpWord\TemplateProcessor;

class AsetRuanganController extends Controller
{
    /**
     * Apakah role saat ini adalah salah satu unit bidang/UPT (bukan Admin Aset).
     * Unit hanya boleh melihat data pada SKPD-nya sendiri.
     */
    private function isUnitUser(): bool
    {
        $role = auth()->user()?->role?->nama_role ?? '';
        return str_starts_with($role, 'Bidang') || str_starts_with($role, 'UPT');
    }

    /**
     * SKPD milik user (untuk filter unit). Null untuk Admin Aset.
     */
    private function userSkpdId(): ?int
    {
        return $this->isUnitUser() ? auth()->user()->pegawai?->id_skpd : null;
    }

    /**
     * Daftar ruangan + jumlah aset aktif di dalamnya.
     */
    public function index(Request $request)
    {
        $userSkpd = $this->userSkpdId();

        $ruanganList = Ruangan::with('skpd')
            ->withCount([
                'penempatanAset as jumlah_aset' => fn ($q) => $q->where('status', 'aktif')
                    ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false)),
            ])
            ->when($userSkpd, fn ($q) => $q->where(fn ($q2) => $q2->where('id_skpd', $userSkpd)->orWhereNull('id_skpd')))
            ->when($request->filled('search'), fn ($q) => $q->where('nama_ruangan', 'like', '%' . trim($request->search) . '%'))
            ->orderBy('id_ruangan')
            ->paginate(10)
            ->withQueryString();

        // Dropdown SKPD untuk modal tambah/edit ruangan.
        $skpdOptions = Skpd::orderBy('nama_skpd')
            ->get(['id_skpd', 'nama_skpd']);

        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';

        return view('aset-ruangan.index', compact(
            'ruanganList',
            'skpdOptions',
            'isAdminAset',
            'userSkpd',
        ));
    }

    /**
     * Detail aset yang berada di dalam satu ruangan.
     */
    public function show(Ruangan $ruangan)
    {
        $userSkpd = $this->userSkpdId();
        if ($userSkpd && $ruangan->id_skpd !== null && $ruangan->id_skpd !== $userSkpd) {
            abort(403, 'Anda tidak memiliki hak akses ke ruangan ini.');
        }

        $ruangan->load('skpd');

        $asetList = PenempatanAset::with(['aset.barang.kategori', 'aset.pemegangSaatIni.pegawai'])
            ->where('id_ruangan', $ruangan->id_ruangan)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
            ->get()
            ->map(fn ($p) => $p->aset)
            ->filter();

        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';

        return view('aset-ruangan.show', compact(
            'ruangan',
            'asetList',
            'isAdminAset',
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_ruangan' => ['required', 'string', 'max:100'],
            'lantai' => ['nullable', 'string', 'max:20'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
        ]);

        Ruangan::create($data);

        return redirect()->route('aset-ruangan.index')
            ->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function update(Request $request, Ruangan $ruangan)
    {
        $data = $request->validate([
            'nama_ruangan' => ['required', 'string', 'max:100'],
            'lantai' => ['nullable', 'string', 'max:20'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
        ]);

        $ruangan->update($data);

        return redirect()->route('aset-ruangan.index')
            ->with('success', 'Ruangan berhasil diperbarui.');
    }

    /**
     * Ubah status ruangan (Aktif <-> Nonaktif / Soft State Management).
     * Ruangan tidak boleh di-nonaktifkan jika masih ada aset aktif di dalamnya.
     */
    public function toggleStatus(Ruangan $ruangan)
    {
        if ($ruangan->status === 'Aktif') {
            $jumlahAsetAktif = $ruangan->penempatanAset()
                ->where('status', 'aktif')
                ->count();

            if ($jumlahAsetAktif > 0) {
                return back()->with(
                    'error',
                    'Ruangan tidak dapat dinonaktifkan karena masih terdapat aset di dalamnya. Silakan mutasikan aset terlebih dahulu.'
                );
            }

            $ruangan->update(['status' => 'Nonaktif']);

            return back()->with('success', "Ruangan '{$ruangan->nama_ruangan}' berhasil dinonaktifkan.");
        }

        $ruangan->update(['status' => 'Aktif']);

        return back()->with('success', "Ruangan '{$ruangan->nama_ruangan}' berhasil diaktifkan kembali.");
    }

    /**
     * Unduh semua label aset aktif di satu ruangan (.xlsx).
     * Menggunakan master template label, menduplikasi blok B2:F7 ke bawah.
     */
    public function downloadLabelRuangan($id_ruangan)
    {
        $ruangan = Ruangan::with([
            'penempatanAset' => function ($q) {
                $q->where('status', 'aktif')
                    ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
                    ->with(['aset.barang', 'aset.pemegangSaatIni.pegawai']);
            },
        ])->findOrFail($id_ruangan);

        $asetList = $ruangan->penempatanAset
            ->map(fn ($p) => $p->aset)
            ->filter()
            ->values();

        if ($asetList->isEmpty()) {
            return back()->with('error', 'Belum ada aset aktif yang ditempatkan di ruangan ini.');
        }

        $template = TemplateDokumen::where('kode_template', 'label')->first();
        if (!$template || !$template->file_path || !Storage::disk('public')->exists($template->file_path)) {
            return back()->with('error', 'File template label belum diunggah.');
        }

        try {
            $spreadsheet = IOFactory::load(Storage::disk('public')->path($template->file_path));

            // Hapus sheet tambahan jika template punya lebih dari 1 sheet bawaan.
            while ($spreadsheet->getSheetCount() > 1) {
                $spreadsheet->removeSheetByIndex(1);
            }

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Label Ruangan');

            // Dimensi 1 blok label: Baris 2 sampai 7, Kolom B sampai F
            $srcStartRow = 2;
            $srcEndRow   = 7;
            $labelHeight = 6;
            $gap         = 1; // 1 baris kosong sebagai pembatas potong gunting
            $step        = $labelHeight + $gap; // Tiap label baru berjarak 7 baris ke bawah

            $colStart = Coordinate::columnIndexFromString('B');
            $colEnd   = Coordinate::columnIndexFromString('F');

            // Catat seluruh posisi sel gabungan (merged cells) di blok B2:F7
            $baseMerges = [];
            foreach ($sheet->getMergeCells() as $mergeRange) {
                if (preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $mergeRange, $matches)) {
                    $r1 = (int) $matches[2];
                    $r2 = (int) $matches[4];
                    if ($r1 >= $srcStartRow && $r2 <= $srcEndRow) {
                        $baseMerges[] = [
                            'col1' => $matches[1],
                            'row1_offset' => $r1 - $srcStartRow,
                            'col2' => $matches[3],
                            'row2_offset' => $r2 - $srcStartRow,
                        ];
                    }
                }
            }

            // Ambil logo master yang ada di sheet (jika ada)
            $baseDrawing = null;
            $logoSourcePath = null;
            $logoTempPath = null;

            foreach ($sheet->getDrawingCollection() as $drawing) {
                if ($drawing instanceof Drawing && str_starts_with($drawing->getCoordinates(), 'B')) {
                    $baseDrawing = $drawing;
                    break;
                }
            }

            if ($baseDrawing) {
                $logoPath = $baseDrawing->getPath();

                // Path gambar dari worksheet hasil load berbentuk zip://...#xl/media/xxx
                // yang tidak bisa dipakai langsung, jadi ekstrak biner media ke file sementara.
                if (str_starts_with($logoPath, 'zip://') && preg_match('/#(xl\/media\/.+)$/', $logoPath, $mediaMatch)) {
                    $xlsxPath = Storage::disk('public')->path($template->file_path);
                    $zip = new \ZipArchive();

                    if ($zip->open($xlsxPath) === true) {
                        $binary = $zip->getFromName($mediaMatch[1]);
                        $zip->close();

                        if ($binary !== false) {
                            $ext = strtolower(pathinfo($mediaMatch[1], PATHINFO_EXTENSION)) ?: 'png';
                            $logoTempPath = storage_path('app/private/temp/label_' . uniqid() . '.' . $ext);
                            if (!is_dir(dirname($logoTempPath))) {
                                mkdir(dirname($logoTempPath), 0755, true);
                            }
                            file_put_contents($logoTempPath, $binary);
                            $logoSourcePath = $logoTempPath;
                        }
                    }
                } else {
                    $logoSourcePath = $logoPath;
                }
            }

            // 1. Gandakan layout blok kotak ke bawah di sheet yang sama
            for ($i = 1; $i < $asetList->count(); $i++) {
                $destStartRow = $srcStartRow + ($i * $step);

                for ($r = 0; $r < $labelHeight; $r++) {
                    $fromRow = $srcStartRow + $r;
                    $toRow   = $destStartRow + $r;

                    // Samakan tinggi baris
                    $rowHeight = $sheet->getRowDimension($fromRow)->getRowHeight();
                    if ($rowHeight > 0) {
                        $sheet->getRowDimension($toRow)->setRowHeight($rowHeight);
                    }

                    for ($c = $colStart; $c <= $colEnd; $c++) {
                        $colStr = Coordinate::stringFromColumnIndex($c);

                        // Copy nilai & formula (setCellValue: sel tujuan masih baru)
                        $sheet->setCellValue($colStr . $toRow, $sheet->getCell($colStr . $fromRow)->getValue());

                        // Copy format background, border, font
                        $sheet->duplicateStyle($sheet->getStyle($colStr . $fromRow), $colStr . $toRow);
                    }
                }

                // Terapkan merge cells pada blok baru
                foreach ($baseMerges as $m) {
                    $sheet->mergeCells(
                        $m['col1'] . ($destStartRow + $m['row1_offset']) . ':'
                            . $m['col2'] . ($destStartRow + $m['row2_offset'])
                    );
                }

                // Duplikasi logo DISBUN ke kotak baru
                if ($baseDrawing && $logoSourcePath && file_exists($logoSourcePath)) {
                    $newDrawing = new Drawing();
                    $newDrawing->setName($baseDrawing->getName());
                    $newDrawing->setDescription($baseDrawing->getDescription() ?? '');
                    $newDrawing->setPath($logoSourcePath);
                    $newDrawing->setHeight($baseDrawing->getHeight());
                    $newDrawing->setWidth($baseDrawing->getWidth());
                    $newDrawing->setOffsetX($baseDrawing->getOffsetX());
                    $newDrawing->setOffsetY($baseDrawing->getOffsetY());
                    $newDrawing->setCoordinates('B' . $destStartRow);
                    $newDrawing->setWorksheet($sheet);
                }
            }

            // 2. Isi nilai & replace placeholder untuk setiap barang
            foreach ($asetList as $index => $aset) {
                $currentRow = $srcStartRow + ($index * $step);

                $noKartu    = $aset->nomor_kartu_barang ?? '-';
                $namaBarang = $aset->barang->nama_barang ?? '-';
                $merk       = $aset->merk ?? '-';
                $tahun      = $aset->tanggal_perolehan ? \Carbon\Carbon::parse($aset->tanggal_perolehan)->format('Y') : date('Y');
                $lokasi     = $aset->pemegangSaatIni?->pegawai?->nama_pegawai ?? 'Umum / ' . $ruangan->nama_ruangan;
                $namaRuang  = $ruangan->nama_ruangan;

                // Loop sel dalam batas kotak barang ini
                for ($r = $currentRow; $r < ($currentRow + $labelHeight); $r++) {
                    for ($c = $colStart; $c <= $colEnd; $c++) {
                        $colStr = Coordinate::stringFromColumnIndex($c);
                        $cell   = $sheet->getCell($colStr . $r);
                        $val    = (string) $cell->getValue();

                        if ($val !== '' && str_contains($val, '{')) {
                            $newVal = str_replace(
                                ['{no_kartu}', '{nama_barang}', '{merk_tipe}', '{tahun}', '{lokasi_user}', '{ruangan}'],
                                [$noKartu, $namaBarang, $merk, $tahun, $lokasi, $namaRuang],
                                $val
                            );
                            $cell->setValue($newVal);
                        }
                    }
                }
            }

            // 3. Ekspor 1 sheet tunggal
            $namaFile = 'Label_Ruangan_' . Str::slug($ruangan->nama_ruangan, '_') . '.xlsx';
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

            return response()->streamDownload(function () use ($writer, $logoTempPath) {
                $writer->save('php://output');
                if ($logoTempPath && is_file($logoTempPath)) {
                    @unlink($logoTempPath);
                }
            }, $namaFile, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $namaFile . '"',
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses template label. ' . $e->getMessage());
        }
    }

    /**
     * Unduh Kartu Inventaris Ruangan (KIR) (.docx).
     * Berbasis template Word 'kir': placeholder nama ruangan bisa berupa
     * ${NAMA_RUANGAN}/${nama_ruangan} maupun {NAMA_RUANGAN}/{nama_ruangan} tanpa
     * tanda dollar (ditangani via patch XML sebelum saveAs). Placeholder lain:
     * ${tanggal_cetak}, ${nama_pejabat_1}/${nip_pejabat_1},
     * ${nama_pejabat_2}/${nip_pejabat_2}, dan baris tabel ${no}, ${nama_barang},
     * ${merk}, ${volume}, ${kondisi_layak}, ${kondisi_tidak} yang di-clone per
     * kelompok (nama_barang + merk) aset aktif.
     */
    public function downloadKIR($id_ruangan)
    {
        $ruangan = Ruangan::findOrFail($id_ruangan);

        // 1. Ambil seluruh aset aktif di ruangan tersebut
        $penempatanList = PenempatanAset::with(['aset.barang'])
            ->where('id_ruangan', $id_ruangan)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
            ->get();

        if ($penempatanList->isEmpty()) {
            return back()->with('error', 'Belum ada aset yang ditempatkan di ruangan ini.');
        }

        // 2. Grouping aset berdasarkan (nama_barang + merk) untuk menghitung Volume
        $groupedItems = $penempatanList->groupBy(function ($item) {
            $nama = $item->aset->barang->nama_barang ?? 'Lainnya';
            $merk = $item->aset->merk ?? '-';
            return Str::upper(trim($nama)) . '|' . Str::upper(trim($merk));
        })->map(function ($items) {
            $firstItem = $items->first();
            $totalVol  = $items->count();

            // Hitung kondisi dominan
            $rusak = $items->filter(fn ($i) => in_array(trim(strtolower($i->aset->kondisi ?? '')), ['rusak ringan', 'rusak berat']))->count();
            $layak = $totalVol - $rusak;

            return [
                'nama_barang'   => $firstItem->aset->barang->nama_barang ?? '-',
                'merk'          => $firstItem->aset->merk ?? '-',
                'volume'        => $totalVol,
                'kondisi_layak' => $layak > 0 ? 'v' : '',
                'kondisi_tidak' => $rusak > 0 ? 'v' : '',
            ];
        })->values();

        // 3. Load Template Word KIR
        $templateDoc = TemplateDokumen::where('kode_template', 'kir')->first();
        if (!$templateDoc || !$templateDoc->file_path || !Storage::disk('public')->exists($templateDoc->file_path)) {
            return back()->with('error', 'File template KIR belum diunggah. Silakan unggah di menu Pengaturan Template Dokumen.');
        }

        $templatePath = storage_path('app/public/' . $templateDoc->file_path);
        $phpWord = new TemplateProcessor($templatePath);

        // 4. Set Header Ruangan & Tanggal
        // Ganti nama ruangan untuk format placeholder huruf besar maupun kecil.
        $namaRuangUpper = Str::upper($ruangan->nama_ruangan);
        $phpWord->setValue('nama_ruangan', $namaRuangUpper);
        $phpWord->setValue('NAMA_RUANGAN', $namaRuangUpper);
        $phpWord->setValue('tanggal_cetak', now()->translatedFormat('d F Y'));

        // 5. Data Pejabat (Nama & NIP diambil dinamis dari DB).
        // Jika belum ditemukan, isi garis titik-titik agar tanda tangan bisa manual.
        $kasubag = \App\Models\Pegawai::where('jabatan', 'like', '%Kasubag Umum%')
            ->orWhere('jabatan', 'like', '%Sub Bagian Umum%')->first();
        $pengurus = \App\Models\Pegawai::where('jabatan', 'like', '%Pengurus Barang%')->first();

        $phpWord->setValue('nama_pejabat_1', $kasubag->nama_pegawai ?? '( .................................................... )');
        $phpWord->setValue('nip_pejabat_1', $kasubag->nip ?? '....................................................');

        $phpWord->setValue('nama_pejabat_2', $pengurus->nama_pegawai ?? '( .................................................... )');
        $phpWord->setValue('nip_pejabat_2', $pengurus->nip ?? '....................................................');

        // 6. Gandakan Baris Tabel (cloneRow)
        $phpWord->cloneRow('no', $groupedItems->count());

        foreach ($groupedItems as $index => $row) {
            $no = $index + 1;
            $phpWord->setValue("no#{$no}", $no);
            $phpWord->setValue("nama_barang#{$no}", $row['nama_barang']);
            $phpWord->setValue("merk#{$no}", $row['merk']);
            $phpWord->setValue("volume#{$no}", $row['volume']);
            $phpWord->setValue("kondisi_layak#{$no}", $row['kondisi_layak']);
            $phpWord->setValue("kondisi_tidak#{$no}", $row['kondisi_tidak']);
        }

        // 7. Simpan & Unduh
        // Patch langsung ke XML untuk placeholder tanpa tanda dollar ($),
        // misal {NAMA_RUANGAN} / {nama_ruangan} yang tidak diproses setValue().
        try {
            $ref = new \ReflectionClass($phpWord);
            foreach (['tempDocumentMainPart', 'tempDocumentHeaders', 'tempDocumentFooters'] as $propName) {
                if (!$ref->hasProperty($propName)) {
                    continue;
                }
                $prop = $ref->getProperty($propName);
                $prop->setAccessible(true);
                $partXML = $prop->getValue($phpWord);
                if (!is_string($partXML) || $partXML === '') {
                    continue;
                }
                $partXML = str_replace(
                    ['{NAMA_RUANGAN}', '{nama_ruangan}', '${NAMA_RUANGAN}', '${nama_ruangan}'],
                    htmlspecialchars($namaRuangUpper),
                    $partXML
                );
                $prop->setValue($phpWord, $partXML);
            }
        } catch (\Throwable $th) {
            // Abaikan; fallback ke nilai placeholder yang masih tersisa di template.
        }

        $namaFile = 'KIR_' . Str::slug($ruangan->nama_ruangan, '_') . '.docx';
        $tempPath = tempnam(sys_get_temp_dir(), 'KIR_');
        $phpWord->saveAs($tempPath);

        return response()->download($tempPath, $namaFile)->deleteFileAfterSend(true);
    }

    /**
     * Keluarkan aset dari ruangan (nonaktifkan penempatan aktifnya).
     */
    public function detachAset(Ruangan $ruangan, Aset $aset)
    {
        // Aset yang sedang dipegang pegawai tidak boleh dikeluarkan manual,
        // karena ruangannya selalu mengikuti ruangan pegawai.
        if ($aset->pemegangSaatIni) {
            return redirect()->route('aset-ruangan.show', $ruangan->id_ruangan)
                ->with('error', 'Aset sedang dipegang pegawai, ruangan mengikuti pegawai dan tidak dapat dikeluarkan secara manual.');
        }

        PenempatanAset::where('id_aset', $aset->id_aset)
            ->where('status', 'aktif')
            ->update(['status' => 'tidak aktif', 'tanggal_selesai' => now()->toDateString()]);

        return redirect()->route('aset-ruangan.show', $ruangan->id_ruangan)
            ->with('success', 'Aset dikeluarkan dari ruangan.');
    }
}
