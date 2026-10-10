<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\Lokasi;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\TemplateDokumen;
use App\Services\AsetPenempatan;
use App\Services\AsetScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpWord\TemplateProcessor;

class AsetRuanganController extends Controller
{
    /**
     * Status aset yang boleh tampil di daftar & dipilih untuk penempatan.
     * Status sistem penghapusan tidak ikut (Aturan 15).
     */
    const STATUS_USER = ['aktif', 'nonaktif'];

    public function __construct(private readonly AsetScope $scope)
    {
    }

    /**
     * Role Admin Aset (case/format tolerant: Admin Aset, admin_aset, admin).
     */
    private function isAdminAset(): bool
    {
        return $this->scope->isAdminAset();
    }

    private function authorizeAdminAset(string $pesan = 'Hanya Admin Aset yang diizinkan.'): void
    {
        abort_unless($this->isAdminAset(), 403, $pesan);
    }

    /**
     * Pastikan user boleh menyentuh ruangan ini (object-level check).
     */
    private function authorizeRuangan(Ruangan $ruangan, string $aksi = 'mengelola ruangan'): void
    {
        abort_unless(
            $this->scope->bolehAksesRuangan($ruangan->id_ruangan),
            403,
            $this->scope->pesanAkses($aksi)
        );
    }

    /**
     * Validasi input `id_lokasi` / `id_skpd` pada form ruangan agar user
     * non-Admin-Aset tidak dapat menulis ruangan ke lokasi/SKPD luar scope
     * (termasuk mengosongkan lokasi agar ruangan jadi tak terscope).
     */
    private function authorizeInputRuangan(array $data): void
    {
        if ($this->scope->cakupanPenuh()) {
            return;
        }

        $idLokasi = $data['id_lokasi'] ?? null;

        abort_unless(
            $idLokasi !== null && $this->scope->bolehAksesLokasi((int) $idLokasi),
            403,
            $this->scope->pesanAkses('memilih lokasi ruangan')
        );

        if (($data['id_skpd'] ?? null) !== null) {
            abort_unless(
                (int) $data['id_skpd'] === (int) $this->scope->skpdId(),
                403,
                'Anda tidak dapat menetapkan SKPD di luar unit Anda.'
            );
        }
    }

    /**
     * Batasi query ruangan pada lokasi fisik dalam cakupan user.
     *
     * Cakupan berdasarkan LOKASI FISIK sesuai role + penempatan skpd
     * (lihat AsetScope::idLokasi()): Admin Aset tanpa filter, Admin UPT
     * hanya lokasi UPT-nya, Admin Bidang hanya lokasi Dinas, identitas
     * invalid -> nol baris.
     */
    private function scopeLokasi(Builder $query): Builder
    {
        if ($this->scope->cakupanPenuh()) {
            return $query;
        }

        return $query->whereIn('id_lokasi', $this->scope->idLokasi() ?: [0]);
    }

    /**
     * Daftar ruangan + jumlah aset aktif di dalamnya.
     */
    public function index(Request $request)
    {
        $ruanganList = $this->scopeLokasi(
            Ruangan::with(['skpd', 'lokasi'])
                ->withCount([
                    'penempatanAset as jumlah_aset' => fn ($q) => $q->where('status', 'aktif')
                        ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false)),
                ])
        )
            ->when($request->filled('search'), fn ($q) => $q->where('nama_ruangan', 'like', '%' . trim($request->search) . '%'))
            ->when($request->filled('lokasi'), fn ($q) => $q->where('id_lokasi', $request->integer('lokasi')))
            ->orderBy('id_ruangan')
            ->paginate(10)
            ->withQueryString();

        // Dropdown lokasi fisik untuk filter & modal tambah/edit ruangan —
        // disesuaikan cakupan user (non-admin hanya lokasi dalam scope-nya).
        $idLokasiScope = $this->scope->cakupanPenuh() ? null : ($this->scope->idLokasi() ?: [0]);

        $lokasiOptions = Lokasi::where('status', 'Aktif')->orderBy('jenis_lokasi')->orderBy('nama_lokasi')
            ->when($idLokasiScope !== null, fn ($q) => $q->whereIn('id_lokasi', $idLokasiScope))
            ->get(['id_lokasi', 'nama_lokasi', 'jenis_lokasi']);

        // SKPD pemilik ruangan = unit organisasi nyata (Sekretariat/Bidang/UPT).
        // Ruang bersama tidak punya SKPD (opsional di form). Non-admin hanya
        // melihat SKPD-nya sendiri.
        $skpdOptions = Skpd::whereNotNull('jenis_skpd')
            ->when(! $this->scope->cakupanPenuh(), fn ($q) => $q->where('id_skpd', $this->scope->skpdId()))
            ->orderBy('jenis_skpd')->orderBy('nama_skpd')
            ->get(['id_skpd', 'nama_skpd', 'jenis_skpd']);

        $isAdminAset = $this->isAdminAset();

        return view('aset-ruangan.index', compact(
            'ruanganList',
            'lokasiOptions',
            'skpdOptions',
            'isAdminAset',
        ));
    }

    /**
     * Detail aset yang berada di dalam satu ruangan.
     */
    public function show(Ruangan $ruangan)
    {
        // Guard di backend: user UPT tidak boleh membuka ruang di luar lokasi UPT-nya.
        if (! $this->scope->cakupanPenuh()) {
            abort_unless(
                in_array($ruangan->id_lokasi, $this->scope->idLokasi() ?: [], true),
                403,
                $this->scope->pesanAkses('melihat isi ruangan')
            );
        }

        $ruangan->load(['skpd', 'lokasi']);

        $asetList = PenempatanAset::with(['aset.barang.kategori', 'aset.pemegangSaatIni.pegawai', 'aset.skpd', 'aset.lokasi'])
            ->where('id_ruangan', $ruangan->id_ruangan)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false)->whereIn('status_aset', self::STATUS_USER))
            ->get()
            ->map(fn ($p) => $p->aset)
            ->filter();

        $isAdminAset = $this->isAdminAset();

        // Kandidat "Pasang Aset": master barang aktif, tanpa pemegang aktif,
        // dan belum punya penempatan aktif (Aturan 6).
        $asetPasang = Aset::with('barang')
            ->barang()
            ->where('status_aset', 'aktif')
            ->whereDoesntHave('pemegangSaatIni')
            ->whereDoesntHave('penempatanAktif')
            ->tap(fn ($q) => $this->scope->terapkan($q))
            ->orderBy('nomor_kartu_barang')
            // Hanya kolom yang benar-benar ada di tabel `aset`. Nama barang
            // dibaca lewat relasi `barang` (tabel master_barang).
            ->get(['id_aset', 'nomor_kartu_barang', 'id_barang']);

        return view('aset-ruangan.show', compact(
            'ruangan',
            'asetList',
            'isAdminAset',
            'asetPasang',
        ));
    }

    /**
     * Penempatan awal aset ke ruangan: memasang MASTER aset yang sudah ada.
     *
     * Tidak membuat record `aset` baru, tidak membuat `pemegang_aset`, dan
     * tidak menulis `mutasi_aset` / `detail_mutasi_aset` (Aturan 5 & 10).
     * Ruangan tujuan adalah ruang yang sedang dibuka — bukan pilihan user.
     */
    public function pasangAset(Ruangan $ruangan, Aset $aset)
    {
        $this->authorizeAdminAset('Akses ditolak: hanya Admin Aset yang dapat memasang aset ke ruangan.');

        abort_if((bool) $aset->is_kendaraan, 422, 'Aset kendaraan tidak dapat dipasang sebagai aset barang.');
        abort_if($aset->status_aset !== 'aktif', 422, 'Hanya aset berstatus aktif yang dapat dipasang.');

        // Row locking + recheck: tidak ada unique index untuk baris aktif,
        // jadi duplikasi dicegah di level aplikasi.
        $aset = DB::transaction(function () use ($aset, $ruangan) {
            $asetLock = Aset::where('id_aset', $aset->id_aset)->lockForUpdate()->first();

            abort_unless(
                $this->scope->bolehAksesAset($asetLock),
                403,
                $this->scope->pesanAkses('memasang aset ke ruangan')
            );

            $pemegang = $asetLock->pemegangSaatIni?->pegawai?->nama_pegawai;

            if ($pemegang) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Aset sudah dipegang '.$pemegang.'. Ruangan mengikuti pemegang — gunakan Mutasi.',
                ]);
            }

            if ($asetLock->penempatanAktif()->exists()) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Aset sudah berada di ruangan '.$asetLock->penempatanAktif->ruangan?->nama_ruangan
                        .'. Gunakan Mutasi → Pindah Ruangan.',
                ]);
            }

            PenempatanAset::create([
                'id_aset' => $asetLock->id_aset,
                'id_ruangan' => $ruangan->id_ruangan,
                'tanggal_mulai' => now()->toDateString(),
                'status' => 'aktif',
            ]);

            // Aset tanpa pemegang yang dipasang ke ruangan ini mengikuti ruangan:
            // `aset.id_skpd` = `ruangan.id_skpd` dan `aset.id_lokasi` =
            // `ruangan.id_lokasi`. Keduanya diturunkan dari master ruangan,
            // bukan dari input client.
            AsetPenempatan::sinkronAset($asetLock, null, $ruangan->id_ruangan);

            return $asetLock->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil dipasang di '.$ruangan->nama_ruangan.'.',
            'id_lokasi' => $aset->id_lokasi,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_ruangan' => ['required', 'string', 'max:100'],
            'lantai' => ['nullable', 'string', 'max:20'],
            'id_lokasi' => ['nullable', 'exists:lokasi,id_lokasi'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
        ]);

        $this->authorizeInputRuangan($data);

        Ruangan::create($data);

        return redirect()->route('aset-ruangan.index')
            ->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function update(Request $request, Ruangan $ruangan)
    {
        $this->authorizeRuangan($ruangan, 'mengubah ruangan');

        $data = $request->validate([
            'nama_ruangan' => ['required', 'string', 'max:100'],
            'lantai' => ['nullable', 'string', 'max:20'],
            'id_lokasi' => ['nullable', 'exists:lokasi,id_lokasi'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
        ]);

        $this->authorizeInputRuangan($data);

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
        $this->authorizeRuangan($ruangan, 'mengubah status ruangan');

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
        $this->authorizeAdminAset('Akses ditolak: Hanya Admin Aset yang berhak mencetak label aset ruangan.');

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
        $this->authorizeAdminAset('Akses ditolak: Hanya Admin Aset yang berhak mencetak Kartu Inventaris Ruangan (KIR).');

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
        $this->authorizeRuangan($ruangan, 'mengeluarkan aset dari ruangan');

        abort_unless(
            $this->scope->bolehAksesAset($aset),
            403,
            $this->scope->pesanAkses('mengeluarkan aset dari ruangan')
        );

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
