<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\KategoriAset;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use App\Models\TemplateDokumen;
use App\Models\UsulanPenghapusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class AsetBarangController extends Controller
{
    const KONDISI = ['Baik', 'Rusak Ringan', 'Rusak Berat'];
    const KATEGORI_MESIN = 'Peralatan dan Mesin';

    /**
     * Role-aware index:
     *  - Admin Aset  -> tabel flat seluruh aset (kolom Pemegang, aksi, filter).
     *  - Non-admin   -> daftar pegawai (grouped) lalu lihat aset per orang.
     */
    public function index(Request $request)
    {
        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';

        if ($isAdminAset) {
            return $this->adminIndex($request);
        }

        $search = $request->query('search');

        $pegawaiList = Pegawai::with('skpd')
            ->withCount(['pemegangAset as jumlah_aset' => function ($q) {
                $q->where('status', 'aktif')
                    ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false));
            }])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_pegawai', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_pegawai')
            ->paginate(10)
            ->withQueryString();

        return view('aset-barang.index', compact('pegawaiList', 'isAdminAset'));
    }

    /**
     * Tabel flat seluruh aset untuk Admin Aset.
     */
    private function adminIndex(Request $request)
    {
        $search = $request->query('search');
        $penempatan = $request->query('penempatan'); // 'pemegang' | 'tanpa_pemegang' | null (semua)
        $pemegangId = $request->query('pemegang');

        $asetList = Aset::with(['barang', 'pemegangSaatIni.pegawai', 'penempatanAktif.ruangan'])
            ->barang()
            ->where('status_aset', 'aktif')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nomor_kartu_barang', 'like', "%{$search}%")
                        ->orWhere('merk', 'like', "%{$search}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama_barang', 'like', "%{$search}%"))
                        ->orWhereHas('pemegangSaatIni.pegawai', fn ($p) => $p->where('nama_pegawai', 'like', "%{$search}%"));
                });
            })
            ->when($penempatan === 'pemegang', function ($query) {
                $query->whereHas('pemegangSaatIni');
            })
            ->when($penempatan === 'tanpa_pemegang', function ($query) {
                $query->whereDoesntHave('pemegangSaatIni');
            })
            ->when($pemegangId, function ($query, $pemegangId) {
                $query->whereHas('pemegangSaatIni', fn ($p) => $p->where('pemegang_aset.id_pegawai', $pemegangId));
            })
            ->orderByDesc('id_aset')
            ->paginate(10)
            ->withQueryString();

        // Dropdown filter pemegang: pegawai yang saat ini memegang aset (non-kendaraan).
        $pemegangOptions = Pegawai::whereHas('pemegangAset', function ($q) {
            $q->where('status', 'aktif')
                ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false));
        })
            ->orderBy('nama_pegawai')
            ->get(['id_pegawai', 'nama_pegawai']);

        // Dropdown pemegang (Tambah/Edit): semua pegawai.
        $allPegawai = Pegawai::orderBy('nama_pegawai')->get(['id_pegawai', 'nama_pegawai', 'id_ruangan']);

        // Dropdown ruangan (Tambah/Edit Aset): hanya ruangan aktif.
        $ruanganOptions = Ruangan::where('status', 'Aktif')->orderBy('nama_ruangan')->get(['id_ruangan', 'nama_ruangan']);

        $kondisiList = self::KONDISI;

        return view('aset-barang.admin-index', compact(
            'asetList',
            'pemegangOptions',
            'allPegawai',
            'ruanganOptions',
            'kondisiList'
        ));
    }

    /**
     * Detail per orang: aset barang yang sedang dipegang (`pemegang_aset` aktif).
     */
    public function show(Pegawai $pegawai)
    {
        $pegawai->load('skpd');

        $asetList = PemegangAset::with('aset.barang.kategori')
            ->where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
            ->orderByDesc('id_pemegang')
            ->get();

        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';
        $kondisiList = self::KONDISI;
        $allPegawai = Pegawai::orderBy('nama_pegawai')->get(['id_pegawai', 'nama_pegawai']);

        return view('aset-barang.show', compact('pegawai', 'asetList', 'kondisiList', 'allPegawai', 'isAdminAset'));
    }

    /**
     * Tambah aset baru. Pemegang diambil dari request `id_pegawai`
     * (dropdown pada tabel flat) atau default pegawai route.
     */
    public function store(Request $request, Pegawai $pegawai)
    {
        $pemegangId = $request->input('id_pegawai') ?: $pegawai->id_pegawai;

        $warning = $this->createAsetFor($request, $pemegangId);

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil ditambahkan.',
            'warning' => $warning,
        ]);
    }

    /**
     * Tambah aset dari tabel flat (tanpa pegawai di route).
     */
    public function storeFlat(Request $request)
    {
        $warning = $this->createAsetFor($request, $request->input('id_pegawai'));

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil ditambahkan.',
            'warning' => $warning,
        ]);
    }

    private function createAsetFor(Request $request, ?int $pegawaiId): ?string
    {
        $data = $this->validateData($request);
        $data['id_barang'] = $this->resolveBarang($request->input('nama_barang'));

        $aset = Aset::create($data);

        // Alur manual: aset melekat langsung ke ruangan, tanpa pemegang.
        if (!$pegawaiId) {
            $this->placeAtRoom($aset, $request->input('id_ruangan'));
            return null;
        }

        // Alur otomatis: aset dipegang pegawai, mengikuti ruangan kerja pegawai.
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawaiId,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        $this->placeAtPegawaiRoom($aset, $pegawaiId);

        $penempatanAktif = PenempatanAset::where('id_aset', $aset->id_aset)->where('status', 'aktif')->exists();
        if (!$penempatanAktif) {
            return 'Pegawai belum punya ruangan. Aset akan tampil tanpa ruangan sampai ruangan pegawai diisi.';
        }

        return null;
    }

    /**
     * Tempatkan aset secara manual ke ruangan tertentu (tanpa pemegang).
     */
    private function placeAtRoom(Aset $aset, ?int $ruanganId): ?int
    {
        return $aset->placeAtRoom($ruanganId);
    }

    /**
     * Data aset sebagai JSON untuk modal edit.
     */
    public function showAset(Aset $aset)
    {
        $pemegang = $aset->pemegangSaatIni;

        return response()->json([
            'id_aset' => $aset->id_aset,
            'id_barang' => $aset->id_barang,
            'nama_barang' => $aset->barang?->nama_barang ?? '',
            'id_pegawai' => $pemegang?->id_pegawai ?? null,
            'nama_pemegang' => $pemegang?->pegawai?->nama_pegawai ?? '',
            'id_ruangan' => $aset->penempatanAktif?->id_ruangan ?? null,
            'nomor_kartu_barang' => $aset->nomor_kartu_barang,
            'merk' => $aset->merk,
            'tanggal_pengadaan' => $aset->tanggal_pengadaan?->format('Y-m-d'),
            'tanggal_perolehan' => $aset->tanggal_perolehan?->format('Y-m-d'),
            'tanggal_habis_pakai' => $aset->tanggal_habis_pakai?->format('Y-m-d'),
            'nilai_perolehan' => $aset->nilai_perolehan,
            'kondisi' => $aset->kondisi,
            'status_aset' => $aset->status_aset,
        ]);
    }

    /**
     * Detail aset (read-only): informasi lengkap + riwayat mutasi per barang.
     */
    public function detailAset(Aset $aset)
    {
        $aset->load([
            'barang.kategori',
            'pemegangSaatIni.pegawai.skpd',
            'penempatanAktif.ruangan.skpd',
        ]);

        $riwayatMutasi = $aset->mutasiDetails()
            ->with([
                'mutasi.userPenginput.pegawai',
                'mutasi.userPenginput.role',
                'pegawaiLama',
                'pegawaiBaru',
                'ruanganLama',
                'ruanganBaru',
            ])
            ->orderByDesc('id_detail')
            ->get();

        $kondisiList = self::KONDISI;
        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';
        $allPegawai = Pegawai::orderBy('nama_pegawai')->get();
        $ruanganList = Ruangan::where('status', 'Aktif')->with('skpd')->orderBy('nama_ruangan')->get();

        return view('aset-barang.detail', compact(
            'aset',
            'riwayatMutasi',
            'kondisiList',
            'isAdminAset',
            'allPegawai',
            'ruanganList'
        ));
    }

    /**
     * Label QR untuk aset: menampilkan QR code nomor kartu barang sebagai PNG.
     */
    public function qrLabel(Aset $aset)
    {
        $content = $aset->nomor_kartu_barang ?: ('aset-' . $aset->id_aset);

        $qr = \QrCode::format('svg')->size(300)->margin(1)->generate($content);

        return response($qr, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="label-' . $content . '.svg"',
        ]);
    }

    /**
     * Unduh label seluruh aset aktif pegawai dalam SATU sheet tunggal (.xlsx).
     * Blok label master (B2:F7) diduplikasi ke bawah berjeda 1 baris kosong via
     * duplicateStyle + mergeCells, lalu placeholder diganti per barang:
     * {no_kartu}, {nama_barang}, {merk_tipe}, {tahun}, {ruangan}, {lokasi_user}.
     */
    public function downloadSemuaLabel($id_pegawai)
    {
        $pegawai = Pegawai::with([
            'pemegangAset' => function ($q) {
                $q->where('status', 'aktif')
                    ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
                    ->with(['aset.barang', 'aset.penempatanAktif.ruangan']);
            },
        ])->findOrFail($id_pegawai);

        $asetList = $pegawai->pemegangAset
            ->map(fn ($pemegang) => $pemegang->aset)
            ->filter()
            ->values();

        if ($asetList->isEmpty()) {
            return back()->with('error', 'Pegawai ini belum memegang aset aktif.');
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
            $sheet->setTitle('Daftar Label');

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
            $originalDrawings = $sheet->getDrawingCollection();
            $baseDrawing = null;
            $logoSourcePath = null;
            $logoTempPath = null;

            foreach ($originalDrawings as $drawing) {
                if ($drawing instanceof Drawing) {
                    // Ambil gambar yang posisinya berada di sekitar sel B2
                    if (str_starts_with($drawing->getCoordinates(), 'B')) {
                        $baseDrawing = $drawing;
                        break;
                    }
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
                        $colStr   = Coordinate::stringFromColumnIndex($c);

                        // Copy nilai & formula (setCellValue: sel tujuan masih baru)
                        $sheet->setCellValue($colStr . $toRow, $sheet->getCell($colStr . $fromRow)->getValue());

                        // Copy format background hijau, border, font
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

                // 3. DUPLIKASI LOGO DISBUN KE KOTAK BARU
                if ($baseDrawing && $logoSourcePath && file_exists($logoSourcePath)) {
                    $newDrawing = new Drawing();
                    $newDrawing->setName($baseDrawing->getName());
                    $newDrawing->setDescription($baseDrawing->getDescription() ?? '');
                    $newDrawing->setPath($logoSourcePath);
                    $newDrawing->setHeight($baseDrawing->getHeight());
                    $newDrawing->setWidth($baseDrawing->getWidth());
                    $newDrawing->setOffsetX($baseDrawing->getOffsetX());
                    $newDrawing->setOffsetY($baseDrawing->getOffsetY());

                    // Pasang logo ke sel B di awal baris kotak baru (misal B9, B16, dst)
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
                $lokasi     = $pegawai->nama_pegawai;
                $ruangan    = $aset->penempatanAktif?->ruangan?->nama_ruangan ?? 'Sekretariat';

                // Loop sel dalam batas kotak barang ini
                for ($r = $currentRow; $r < ($currentRow + $labelHeight); $r++) {
                    for ($c = $colStart; $c <= $colEnd; $c++) {
                        $colStr = Coordinate::stringFromColumnIndex($c);
                        $cell   = $sheet->getCell($colStr . $r);
                        $val    = (string) $cell->getValue();

                        if ($val !== '' && str_contains($val, '{')) {
                            $newVal = str_replace(
                                ['{no_kartu}', '{nama_barang}', '{merk_tipe}', '{tahun}', '{lokasi_user}', '{ruangan}'],
                                [$noKartu, $namaBarang, $merk, $tahun, $lokasi, $ruangan],
                                $val
                            );
                            $cell->setValue($newVal);
                        }
                    }
                }
            }

            // 3. Ekspor 1 sheet tunggal
            $namaFile = 'Label_Aset_' . Str::slug($pegawai->nama_pegawai, '_') . '.xlsx';
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
     * Unduh label SATU aset (.xlsx) dari template label master.
     * Hanya memanfaatkan blok awal (B2:F7) untuk mengisi data aset yang dipilih.
     */
    public function cetakLabelSatuan($id)
    {
        $aset = Aset::with(['barang', 'penempatanAktif.ruangan'])->findOrFail($id);

        $template = TemplateDokumen::where('kode_template', 'label')->first();
        if (!$template || !$template->file_path || !Storage::disk('public')->exists($template->file_path)) {
            return back()->with('error', 'File template label belum diunggah.');
        }

        try {
            $spreadsheet = IOFactory::load(Storage::disk('public')->path($template->file_path));
            while ($spreadsheet->getSheetCount() > 1) {
                $spreadsheet->removeSheetByIndex(1);
            }

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Label Aset');

            $srcStartRow = 2;
            $srcEndRow   = 7;
            $labelHeight = 6;
            $colStart = Coordinate::columnIndexFromString('B');
            $colEnd   = Coordinate::columnIndexFromString('F');

            $noKartu    = $aset->nomor_kartu_barang ?? '-';
            $namaBarang = $aset->barang?->nama_barang ?? '-';
            $merk       = $aset->merk ?? '-';
            $tahun      = $aset->tanggal_perolehan ? \Carbon\Carbon::parse($aset->tanggal_perolehan)->format('Y') : date('Y');
            $ruangan    = $aset->penempatanAktif?->ruangan?->nama_ruangan ?? 'Sekretariat';
            $lokasi     = $aset->pemegangSaatIni?->pegawai?->nama_pegawai ?? $ruangan;

            for ($r = $srcStartRow; $r < ($srcStartRow + $labelHeight); $r++) {
                for ($c = $colStart; $c <= $colEnd; $c++) {
                    $colStr = Coordinate::stringFromColumnIndex($c);
                    $cell   = $sheet->getCell($colStr . $r);
                    $val    = (string) $cell->getValue();

                    if ($val !== '' && str_contains($val, '{')) {
                        $newVal = str_replace(
                            ['{no_kartu}', '{nama_barang}', '{merk_tipe}', '{tahun}', '{lokasi_user}', '{ruangan}'],
                            [$noKartu, $namaBarang, $merk, $tahun, $lokasi, $ruangan],
                            $val
                        );
                        $cell->setValue($newVal);
                    }
                }
            }

            $namaFile = 'Label_' . Str::slug($aset->barang?->nama_barang ?? 'Aset', '_') . '.xlsx';
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $namaFile, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $namaFile . '"',
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses template label. ' . $e->getMessage());
        }
    }

    /**
     * Perbarui data aset (tanpa pemegang/ruangan).
     * Ganti pemegang / pindah ruangan dilakukan via aksi "Mutasi" terpisah.
     */
    public function update(Request $request, Aset $aset)
    {
        $data = $this->validateUpdateData($request, $aset);
        $data['id_barang'] = $this->resolveBarang($request->input('nama_barang'));

        $aset->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil diperbarui.',
        ]);
    }

    /**
     * Aksi mutasi terpisah: Ganti Pemegang (`tipe=pegawai`) atau
     * Pindah Ruangan (`tipe=ruangan`). Setiap perubahan tercatat di
     * tabel mutasi_aset + detail_mutasi_aset.
     */
    public function mutasi(Request $request, Aset $aset)
    {
        $data = $request->validate([
            'tipe' => ['required', Rule::in(['pegawai', 'ruangan'])],
            'id_pegawai' => ['nullable', 'exists:pegawai,id_pegawai'],
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['tipe'] === 'pegawai') {
            $pemegangBaru = $data['id_pegawai'] ?? null;
            if (!$pemegangBaru) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'id_pegawai' => 'Pilih pemegang baru untuk aset ini.',
                ]);
            }

            $pemegangLama = $aset->pemegangSaatIni?->pegawai?->id_pegawai;
            $mutasi = $this->mutatePemegang($aset, $pemegangLama, $pemegangBaru, $data['keterangan'] ?? null);

            // Auto-download BAST setelah reload: flash URL tersedia satu kali di blade.
            session()->flash('download_bast_url', route('mutasi-aset.bast.download', $mutasi->id_mutasi));

            $warning = null;
            $penempatanAktif = PenempatanAset::where('id_aset', $aset->id_aset)->where('status', 'aktif')->exists();
            if (!$penempatanAktif) {
                $warning = 'Pegawai belum punya ruangan. Aset akan tampil tanpa ruangan sampai ruangan pegawai diisi.';
            }

            return response()->json([
                'success' => true,
                'message' => 'Pemegang aset berhasil diganti.',
                'warning' => $warning,
            ]);
        }

        // tipe = ruangan (Pindah Ruangan)
        if ($aset->pemegangSaatIni) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tipe' => 'Aset ber-pemegang selalu mengikuti ruangan kerja pegawainya. Gunakan mode Ganti Pemegang.',
            ]);
        }

        $ruanganBaru = $data['id_ruangan'] ?? null;
        if (!$ruanganBaru) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id_ruangan' => 'Pilih ruangan tujuan.',
            ]);
        }

        $ruanganLama = $aset->penempatanAktif?->id_ruangan;
        $this->placeAtRoom($aset, $ruanganBaru);

        if ($ruanganLama == $ruanganBaru) {
            return response()->json([
                'success' => true,
                'message' => 'Aset sudah berada di ruangan tersebut.',
            ]);
        }

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => $data['keterangan'] ?? null,
            'id_user_penginput' => auth()->id(),
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => null,
            'pegawai_baru' => null,
            'ruangan_lama' => $ruanganLama,
            'ruangan_baru' => $ruanganBaru,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil dipindah ruangan.',
        ]);
    }

    /**
     * Usulkan penghapusan aset (bukan hard delete, sesuai SOP BMD):
     * status aset -> 'diusulkan_hapus' (hilang dari daftar aktif) dan
     * catat usulan ke tabel usulan_penghapusans untuk diproses admin.
     */
    public function destroy(Aset $aset)
    {
        if ($aset->status_aset !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya aset berstatus aktif yang dapat diusulkan penghapusan.',
            ], 422);
        }

        if (UsulanPenghapusan::where('id_aset', $aset->id_aset)->where('status_usulan', 'diajukan')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Aset sudah memiliki usulan penghapusan yang aktif.',
            ], 422);
        }

        $aset->update(['status_aset' => 'diusulkan_hapus']);

        UsulanPenghapusan::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai_penghapus' => auth()->user()?->pegawai?->id_pegawai,
            'tanggal_usulan' => now()->toDateString(),
            'alasan_penghapusan' => 'Lainnya',
            'status_usulan' => 'diajukan',
            'keterangan' => 'Diusulkan melalui tombol hapus pada daftar aset.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Aset diusulkan penghapusan dan dikeluarkan dari daftar aset aktif.',
        ]);
    }

    /**
     * Tutup pemegang lama, buka pemegang baru, pindah ruangan, catat riwayat mutasi.
     */
    private function mutatePemegang(Aset $aset, $pemegangLamaId, $pemegangBaruId, ?string $keterangan): MutasiAset
    {
        $now = now()->toDateString();

        // Tutup pemegang lama
        PemegangAset::where('id_aset', $aset->id_aset)
            ->where('status', 'aktif')
            ->update(['status' => 'tidak aktif', 'tanggal_selesai' => $now]);

        // Buka pemegang baru
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pemegangBaruId,
            'tanggal_mulai' => $now,
            'status' => 'aktif',
        ]);

        // Ruangan lama -> baru (otomatis ikut ruangan kerja pemegang baru)
        $ruanganLama = $aset->penempatanAktif?->id_ruangan;
        $ruanganBaru = $this->placeAtPegawaiRoom($aset, $pemegangBaruId);
        $ruanganBaru = $ruanganBaru?: $ruanganLama;

        // Catat mutasi sebagai riwayat
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => $now,
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => $keterangan,
            'id_user_penginput' => auth()->id(),
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $pemegangLamaId,
            'pegawai_baru' => $pemegangBaruId,
            'ruangan_lama' => $ruanganLama,
            'ruangan_baru' => $ruanganBaru,
        ]);

        return $mutasi;
    }

    /**
     * Tempatkan aset di ruangan kerja utama pegawai. Kembalikan id_ruangan (nullable).
     */
    private function placeAtPegawaiRoom(Aset $aset, int $pegawaiId): ?int
    {
        $pegawai = Pegawai::find($pegawaiId);
        return $aset->placeAtRoom($pegawai?->id_ruangan);
    }

    private function validateData(Request $request, ?Aset $aset = null): array
    {
        $asetId = $aset?->id_aset;

        return $request->validate([
            'id_pegawai' => ['nullable', 'exists:pegawai,id_pegawai'],
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
            'nama_barang' => ['required', 'string', 'max:100'],
            'nomor_kartu_barang' => ['required', 'string', 'max:255', Rule::unique('aset', 'nomor_kartu_barang')->ignore($asetId, 'id_aset')],
            'merk' => ['nullable', 'string', 'max:100'],
            'tanggal_pengadaan' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'tanggal_habis_pakai' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'status_aset' => ['required', 'string', 'max:50'],
        ]);
    }

    private function validateUpdateData(Request $request, ?Aset $aset = null): array
    {
        $asetId = $aset?->id_aset;

        $rules = [
            'nama_barang' => ['required', 'string', 'max:100'],
            'nomor_kartu_barang' => ['required', 'string', 'max:255', Rule::unique('aset', 'nomor_kartu_barang')->ignore($asetId, 'id_aset')],
            'merk' => ['nullable', 'string', 'max:100'],
            'tanggal_pengadaan' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'tanggal_habis_pakai' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'status_aset' => ['required', 'string', 'max:50'],
        ];

        $data = $request->validate($rules);

        return $data;
    }

    /**
     * Cari master_barang berdasarkan nama; jika belum ada, buat baru
     * dengan kategori "Peralatan dan Mesin" (hard-coded).
     */
    private function resolveBarang(string $namaBarang): ?int
    {
        $namaBarang = trim($namaBarang);

        $barang = MasterBarang::whereRaw('LOWER(nama_barang) = ?', [mb_strtolower($namaBarang)])->first();

        if ($barang) {
            return $barang->id_barang;
        }

        $kategori = KategoriAset::firstOrCreate(['nama_kategori' => self::KATEGORI_MESIN]);

        $barang = MasterBarang::create([
            'nama_barang' => $namaBarang,
            'id_kategori' => $kategori->id_kategori,
        ]);

        return $barang->id_barang;
    }
}
