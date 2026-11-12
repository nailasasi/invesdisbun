<?php

namespace App\Http\Controllers;

use App\Models\DokumenPbbTanah;
use App\Models\RetribusiTanah;
use App\Models\Tanah;
use App\Rules\FotoKameraValid;
use App\Services\BuktiOtorisasi;
use App\Services\RiwayatTanah;
use App\Services\TanahScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TanahController extends Controller
{
    /**
     * Gerbang modul Tanah: Admin Aset atau Admin UPT P2BTP saja.
     *
     * Ditaruh di constructor agar SELURUH method (termasuk yang belum
     * ada sekarang) otomatis tergates — deny by default.
     */
    public function __construct(
        private readonly TanahScope $tanahScope,
        private readonly BuktiOtorisasi $bukti,
        private readonly RiwayatTanah $riwayat,
    ) {
        // Guard auth()->check(): route tanah sudah dibungkus middleware
        // `auth` + `role:Admin Aset,Admin UPT`, jadi gate ini jaring
        // lapis kedua HANYA untuk user terautentikasi (mis. Admin UPT
        // non-P2BTP yang lolos middleware role). Tanpanya, CLI
        // (route:list menginstansiasi controller) ikut abort 403.
        if (auth()->check()) {
            abort_unless(
                $this->tanahScope->bolehAkses(),
                403,
                'Anda tidak memiliki hak akses untuk modul data tanah.'
            );
        }
    }

    /**
     * Menampilkan daftar seluruh data tanah.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $tahunParam = self::normalizeTahun($request->query('tahun'));
        $tahun = $tahunParam ?? (int) date('Y');

        // Filter tahun hanya memengaruhi penyajian kolom retribusi & PBB di
        // tabel daftar. Daftar Tanah tetap utuh (tanpa whereHas / join) sehingga
        // record tanpa data pada tahun terpilih tidak pernah hilang.
        // Relasi dimuat utuh; pemilihan tahun dilakukan berdasarkan tahun aktif.
        $tanahList = Tanah::with([
            'retribusi',
            'dokumenPbb',
        ])
            ->when($search, fn ($query, $value) => self::terapkanPencarian($query, $value))
            ->orderByDesc('id_tanah')
            ->paginate(10)
            ->withQueryString();

        return view('tanah.index', [
            'tanahList' => $tanahList,
            'tahun' => $tahun,
            'tahunOptions' => $this->tahunOptions($tahun),
        ]);
    }

    /**
     * Menampilkan detail tanah.
     */
    public function show(Tanah $tanah)
    {
        // Detail selalu menampilkan SELURUH data & histori (semua tahun),
        // tidak dibatasi oleh filter tahun dari halaman daftar.
        $tanah->load([
            'retribusi',
            'dokumenPbb.uploader',
            'dokumenPbbHistories.user',
            'histories.user',
        ]);

        $lastEdit = $tanah->histories->sortByDesc('created_at')->first();

        return view('tanah.show', compact('tanah', 'lastEdit'));
    }

    /**
     * Normalisasi parameter tahun dari query string.
     * Mengembalikan null untuk nilai kosong/tidak valid.
     */
    private static function normalizeTahun($value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $nilai = trim((string) $value);
        if ($nilai === '' || ! ctype_digit($nilai)) {
            return null;
        }

        $tahun = (int) $nilai;

        return ($tahun >= 1900 && $tahun <= 2100) ? $tahun : null;
    }

    /**
     * Daftar tahun global: gabungan tahun retribusi & tahun PBB (semua data,
     * lintas tanah), unik dan terbaru dulu.
     */
    private function tahunOptions(?int $tahunAktif = null): array
    {
        $tahunBerjalan = (int) date('Y');
        $tahunRetribusi = RetribusiTanah::query()
            ->whereNotNull('tahun')
            ->pluck('tahun')
            ->map(fn ($tahun) => (int) $tahun);

        $tahunPbb = DokumenPbbTanah::query()
            ->whereNotNull('tahun_pbb')
            ->pluck('tahun_pbb')
            ->map(fn ($tahun) => (int) $tahun);

        $options = $tahunRetribusi
            ->merge($tahunPbb)
            ->merge([$tahunBerjalan, $tahunAktif])
            ->filter(fn ($tahun) => $tahun !== null)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $options;
    }

    /**
     * Menampilkan form tambah tanah.
     */
    public function create()
    {
        return view('tanah.create');
    }

    /**
     * Menyimpan data tanah baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kib' => ['nullable', 'string', 'max:100'],
            'tanggal_buku' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric', 'min:0'],
            'deskripsi_objek' => ['nullable', 'string'],

            'luas_tanah' => ['nullable', 'numeric', 'min:0'],
            'alamat' => ['nullable', 'string'],
            'ketkel' => ['nullable', 'string', 'max:100'],
            'status_hak' => ['nullable', 'string', 'max:50'],

            'nomor_sertifikat' => ['nullable', 'string', 'max:100'],
            'tanggal_sertifikat' => ['nullable', 'date'],

            'penggunaan' => ['nullable', 'string', 'max:100'],
            'penggunaan_air' => ['nullable', 'string', 'max:100'],
            'kondisi' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],

            'nama_petugas' => ['nullable', 'string', 'max:100'],
            'nomor_hp_petugas' => ['nullable', 'string', 'max:20'],
            'google_maps' => ['nullable', 'string'],

            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'video_url' => ['nullable', 'url', 'max:500'],
        ]);

        $data = $validated;
        unset($data['foto'], $data['video_url']);

        $fotoBaru = null;
        if ($request->hasFile('foto')) {
            $fotoBaru = $request->file('foto')->store('tanah/foto', 'public');
            $data['foto_tanah'] = $fotoBaru;
        }
        $data['video_tanah'] = ($validated['video_url'] ?? null) ?: null;

        // Data + riwayat ditulis dalam SATU transaksi database agar tidak ada
        // kondisi "data tersimpan tetapi riwayat gagal".
        try {
            DB::transaction(function () use ($data) {
                $tanah = Tanah::create($data);
                $this->riwayat->catat($tanah, 'CREATE', 'tanah', null, null, $tanah->fresh()->toArray(), null);
            });
        } catch (\Throwable $e) {
            if ($fotoBaru) {
                Storage::disk('public')->delete($fotoBaru);
            }

            return back()
                ->withInput()
                ->withErrors(['error' => 'Data gagal disimpan: '.$e->getMessage()]);
        }

        return redirect()
            ->route('tanah.index')
            ->with('success', 'Data tanah berhasil ditambahkan.');
    }

    /**
     * Menampilkan form edit tanah.
     */
    public function edit(Tanah $tanah)
    {
        return view('tanah.edit', compact('tanah'));
    }

    /**
     * Memperbarui data tanah.
     */
    public function update(Request $request, Tanah $tanah)
    {
        $validated = $request->validate([
            'kib' => ['nullable', 'string', 'max:100'],
            'tanggal_buku' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric', 'min:0'],
            'deskripsi_objek' => ['nullable', 'string'],

            'luas_tanah' => ['nullable', 'numeric', 'min:0'],
            'alamat' => ['nullable', 'string'],
            'ketkel' => ['nullable', 'string', 'max:100'],
            'status_hak' => ['nullable', 'string', 'max:50'],

            'nomor_sertifikat' => ['nullable', 'string', 'max:100'],
            'tanggal_sertifikat' => ['nullable', 'date'],

            'penggunaan' => ['nullable', 'string', 'max:100'],
            'penggunaan_air' => ['nullable', 'string', 'max:100'],
            'kondisi' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],

            'nama_petugas' => ['nullable', 'string', 'max:100'],
            'nomor_hp_petugas' => ['nullable', 'string', 'max:20'],
            'google_maps' => ['nullable', 'string'],

            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'video_url' => ['nullable', 'url', 'max:500'],

            // Konfirmasi ulang password akun yang sedang login (step-up
            // otorisasi). Diverifikasi server-side, tidak pernah disimpan.
            'password_otorisasi' => ['required', 'current_password:web'],

            // Foto bukti kamera langsung (data URL). Wajib; divalidasi isi file.
            'foto_bukti_otentikasi' => ['required', 'string', new FotoKameraValid],
        ], [
            'password_otorisasi.required' => 'Password otorisasi wajib diisi.',
            'password_otorisasi.current_password' => 'Password otorisasi tidak cocok dengan akun Anda.',
            'foto_bukti_otentikasi.required' => 'Silakan ambil foto konfirmasi terlebih dahulu sebelum menyimpan.',
        ]);

        $dataLama = $tanah->toArray();

        $data = $validated;
        unset($data['foto'], $data['video_url'], $data['password_otorisasi'], $data['foto_bukti_otentikasi']);

        $fotoBaru = null;
        if ($request->hasFile('foto')) {
            $fotoBaru = $request->file('foto')->store('tanah/foto', 'public');
            $data['foto_tanah'] = $fotoBaru;
        }
        $data['video_tanah'] = ($validated['video_url'] ?? null) ?: null;

        try {
            $fotoBukti = $this->bukti->simpan($validated['foto_bukti_otentikasi']);
        } catch (\Throwable $e) {
            if ($fotoBaru) {
                Storage::disk('public')->delete($fotoBaru);
            }

            return back()
                ->withInput($request->except(['password_otorisasi', 'foto_bukti_otentikasi']))
                ->withErrors(['foto_bukti_otentikasi' => 'Foto bukti gagal disimpan. Silakan ambil foto ulang.']);
        }

        try {
            DB::transaction(function () use ($tanah, $data, $dataLama, $fotoBukti) {
                $tanah->update($data);
                $this->riwayat->catat($tanah, 'UPDATE', 'tanah', null, $dataLama, $tanah->fresh()->toArray(), $fotoBukti);
            });
        } catch (\Throwable $e) {
            $this->bukti->hapus($fotoBukti);
            if ($fotoBaru) {
                Storage::disk('public')->delete($fotoBaru);
            }

            return back()
                ->withInput($request->except(['password_otorisasi', 'foto_bukti_otentikasi']))
                ->withErrors(['foto_bukti_otentikasi' => 'Data gagal disimpan sehingga foto bukti dibatalkan. Silakan coba lagi.']);
        }

        // Hapus file foto lama jika upload foto baru berhasil dan transaksi DB sukses
        $fotoLama = $dataLama['foto_tanah'] ?? null;
        if ($fotoBaru && $fotoLama && $fotoLama !== $fotoBaru) {
            $exists = Storage::disk('public')->exists($fotoLama);
            $dipakaiLain = Tanah::where('id_tanah', '!=', $tanah->id_tanah)
                ->where('foto_tanah', $fotoLama)
                ->exists();
            if (str_starts_with($fotoLama, 'tanah/foto/') && $exists && ! $dipakaiLain) {
                Storage::disk('public')->delete($fotoLama);
            }
        }

        return redirect()
            ->route('tanah.index')
            ->with('success', 'Data tanah berhasil diperbarui.');
    }

    /**
     * Kolom resmi template import tanah, persis mengikuti file Excel sumber
     * (Aset Tanah BMD) — 19 kolom, ejaan & urutan disamakan.
     */
    private const KOLOM_TEMPLATE = [
        'No', 'KIB', 'TglBuku', 'TglPeroleh', 'Perolehan', 'DeskObj', 'Luas',
        'Alamat', 'Ketkel', 'StatusHAK', 'NoSert', 'TglSert', 'Pengunaan',
        'Kondisi', 'Keterangan', 'Nama Petugas', 'Nomor tlp Petugas', 'Gmaps',
        'Penggunaan Air (Silahkan diisi pilihan sbg berikut)',
    ];

    /**
     * Unduh template Excel baku isian data tanah (KIB A).
     * Layout mengikuti pola template Import Kendaraan: header baris 1,
     * contoh data baris 2.
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import Tanah');

        $sheet->fromArray(self::KOLOM_TEMPLATE, null, 'A1');

        // Baris contoh data acuan (sesuai file Excel sumber).
        $sheet->fromArray([
            1,
            '11.01.35.00.020301.00000.00000.1980-1.3.1.01.01.02.003.000001',
            '17/12/2025',
            '31/12/1980',
            '2.213.000,00',
            'Tanah Gudang OPC',
            '1,770',
            'Desa Bumirejo, Kecamatan Dampit, Kabupaten Malang',
            'KAB. MALANG.Dampit.Bumirejo',
            'PENGELOLAAN',
            // Penanda eksplisit: baris ini adalah contoh. Import akan
            // melewati baris yang memuat "CONTOH" pada KIB/Nomor Sertifikat.
            'CONTOH',
            '17/12/2025',
            'Idle/ Rencana Disewakan',
            'BAIK',
            '-',
            'Asmari',
            '0822 3011 9624',
            'https://maps.app.goo.gl/ByBsHYJhTeGqK4rW7?g_st=ic',
            'Mata Air',
        ], null, 'A2');

        $sheet->getStyle('A1:S1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        foreach (range('A', 'S') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Lebar eksplisit agar kolom panjang tetap terbaca.
        foreach (['B' => 46, 'H' => 42, 'R' => 34, 'S' => 30] as $col => $width) {
            $sheet->getColumnDimension($col)->setAutoSize(false);
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return self::streamXlsx($spreadsheet, 'Template-Import-Tanah.xlsx');
    }

    /**
     * Impor data tanah (KIB A) dari file Excel sesuai template.
     * Duplikat (berdasarkan nomor sertifikat / KIB) dapat dilewati atau diperbarui.
     */
    public function import(Request $request)
    {
        $data = $request->validate([
            'file_excel' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'update_existing' => ['required', 'in:skip,update'],
        ]);

        $updateExisting = $data['update_existing'] === 'update';

        $sheet = IOFactory::load($request->file('file_excel')->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // Petakan kolom berdasar judul baris (baris 1), bukan patokan posisi tetap,
        // agar kompatibel dengan "Aset Tanah BMD.xlsx" (kolom bergeser) & template sendiri.
        $colMap = $this->resolveColumns($rows[1] ?? []);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        // Seluruh proses dibungkus transaksi: bila terjadi kegagalan tak terduga,
        // tidak ada baris yang tersimpan setengah jalan (partial import).
        try {
            DB::transaction(function () use ($rows, $colMap, $updateExisting, &$created, &$updated, &$skipped, &$failed, &$errors) {
                foreach ($rows as $no => $row) {
                    if ($no < 2) {
                        continue; // baris 1 = judul kolom
                    }

                    $rowErrors = [];
                    $mapped = $this->mapRow($row, $colMap, $rowErrors);

                    // Lewati baris contoh bawaan template & baris kosong.
                    $isContoh = self::isBarisContoh($mapped);
                    $isEmpty = empty($mapped['nomor_sertifikat'])
                        && empty($mapped['kib'])
                        && empty($mapped['deskripsi_objek'])
                        && empty($mapped['alamat']);
                    if ($isContoh || $isEmpty) {
                        continue;
                    }

                    // Nilai ambigu (mis. format angka tak jelas) dilaporkan, tidak ditebak.
                    if ($rowErrors) {
                        $failed++;
                        $errors[] = ['row' => $no, 'messages' => $rowErrors];

                        continue;
                    }

                    $existing = Tanah::where(function ($q) use ($mapped) {
                        if ($mapped['nomor_sertifikat']) {
                            $q->where('nomor_sertifikat', $mapped['nomor_sertifikat']);
                        } else {
                            $q->whereNull('nomor_sertifikat')->where('kib', $mapped['kib']);
                        }
                    })->first();

                    if ($existing) {
                        if (! $updateExisting) {
                            $skipped++;

                            continue;
                        }

                        $dataLama = $existing->toArray();
                        $perubahan = collect($mapped)
                            ->filter(fn ($v) => $v !== null && $v !== '')
                            ->all();

                        $existing->update($perubahan);

                        $this->riwayat->catat(
                            $existing,
                            'UPDATE',
                            'tanah',
                            null,
                            $dataLama,
                            $existing->fresh()->toArray()
                        );

                        $updated++;

                        continue;
                    }

                    Tanah::create($mapped);
                    $created++;
                }
            });
        } catch (\Throwable $e) {
            return redirect()
                ->route('tanah.index')
                ->with('error', 'Import dibatalkan, tidak ada data yang tersimpan. Penyebab: '.$e->getMessage());
        }

        $pesan = "Import selesai: {$created} data baru ditambahkan, {$updated} data diperbarui, {$skipped} baris dilewati, {$failed} baris gagal.";

        $redirect = redirect()
            ->route('tanah.index')
            ->with('success', $pesan);

        if ($errors) {
            $redirect->with('import_errors', $errors);
        }

        return $redirect;
    }

    /**
     * Petakan judul kolom (baris 1) menjadi posisi huruf kolom untuk tiap field.
     * Kolom dicari berdasarkan nama heading terlebih dahulu, lalu fallback posisi.
     */
    private function resolveColumns(array $headerRow): array
    {
        $normalize = fn ($v) => strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', (string) $v));

        $byHeading = [];
        foreach ($headerRow as $letter => $value) {
            $key = $normalize($value);
            // Simpan kemunculan PERTAMA saja. Berkas sumber bisa memuat dua
            // kolom bernama "Keterangan" (tanah & retribusi); yang dipakai
            // untuk master tanah adalah yang pertama, kolom retribusi diabaikan.
            if ($key !== '' && ! isset($byHeading[$key])) {
                $byHeading[$key] = $letter;
            }
        }

        $resolve = function (array $headings, array $fallbackLetters) use ($byHeading) {
            foreach ($headings as $heading) {
                if (isset($byHeading[$heading])) {
                    return $byHeading[$heading];
                }
            }

            return $fallbackLetters[0] ?? null;
        };

        return [
            // 'No' pada file sumber adalah nomor urut baris, BUKAN kolom
            // register "No Excel". Karena itu 'No' TIDAK dipetakan ke no_excel;
            // field no_excel hanya terisi bila berkas memiliki kolom khusus
            // "No Excel" (kompatibilitas berkas lama) dan selain itu dibiarkan
            // null (kolom DB no_excel memang nullable).
            'no_excel' => $resolve(['noexcel', 'no excel'], []),
            'kib' => $resolve(['kib', 'kibnobarang', 'nobarang', 'kodennibarang'], ['B']),
            'tanggal_buku' => $resolve(['tanggalbuku', 'tglbuku'], ['C']),
            'tanggal_perolehan' => $resolve(['tanggalperolehan', 'tglperolehan'], ['D']),
            'nilai_perolehan' => $resolve(['nilaiperolehan', 'nilaiperolehanrp', 'perolehan', 'nilai'], ['E']),
            'deskripsi_objek' => $resolve(['deskobj', 'deskripsiobjek', 'deskripsi'], ['F']),
            'luas_tanah' => $resolve(['luas', 'luastanah', 'luastanahm2'], ['G']),
            'alamat' => $resolve(['alamat'], ['H']),
            'ketkel' => $resolve(['ketkel', 'kelurahan', 'letakkelurahan'], ['I']),
            'status_hak' => $resolve(['statushak', 'status hak'], ['J']),
            'nomor_sertifikat' => $resolve(['nosert', 'nomorsertifikat', 'nosertifikat'], ['K']),
            'tanggal_sertifikat' => $resolve(['tglsert', 'tanggalsertifikat', 'tglsertifikat'], ['L']),
            'penggunaan' => $resolve(['pengunaan', 'penggunaan', 'penggunaantanah'], ['M']),
            'kondisi' => $resolve(['kondisi'], ['N']),
            'keterangan' => $resolve(['keterangantanah', 'keterangan', 'keterangan1', 'statusobjekretribusi'], ['O']),
            'nama_petugas' => $resolve(['namapetugas', 'nama petugas', 'petugas'], ['P']),
            'nomor_hp_petugas' => $resolve(['nomortlppetugas', 'nomortlp', 'nomor tlp petugas', 'tlppetugas', 'nomorhppetugas', 'nohppetugas', 'nohp'], ['Q']),
            'google_maps' => $resolve(['gmaps', 'googlemaps', 'maps'], ['R']),
            'penggunaan_air' => $resolve(['penggunaanairsilahkandiisipilihansbgberikut', 'penggunaanair', 'penggunaan air'], ['S']),
            // 'Satuan' tidak ada pada format sumber. Bila tidak tersedia,
            // mapRow() memakai default 'm2' (aturan DB lama dipertahankan).
            'satuan' => $resolve(['satuan'], []),
        ];
    }

    /**
     * Menandai baris contoh bawaan template agar tidak tersimpan sebagai data
     * sungguhan. Penanda "CONTOH" diperiksa pada kolom identitas terstruktur
     * (KIB & Nomor Sertifikat) dan TIDAK pada deskripsi objek, supaya rekaman
     * valid yang deskripsinya kebetulan memuat kata "contoh" tidak ikut
     * terlewat. Perbandingan mengabaikan huruf besar/kecil.
     */
    private static function isBarisContoh(array $mapped): bool
    {
        foreach (['kib', 'nomor_sertifikat'] as $field) {
            $nilai = strtoupper(trim((string) ($mapped[$field] ?? '')));
            if ($nilai !== '' && str_contains($nilai, 'CONTOH')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Petakan satu baris Excel ke atribut model Tanah berdasar posisi kolom hasil resolveColumns().
     */
    private function mapRow(array $row, array $colMap, array &$errors = []): array
    {
        $value = function (string $field) use ($row, $colMap) {
            $letter = $colMap[$field] ?? null;

            return $letter !== null && array_key_exists($letter, $row) ? $row[$letter] : null;
        };

        $str = fn ($v) => trim((string) $v) !== '' ? trim((string) $v) : null;

        $num = function ($v, string $label) use (&$errors) {
            // Nilai numerik asli dari Excel (int/float) langsung dikonversi.
            if (is_int($v) || is_float($v)) {
                return (float) $v;
            }

            if ($v === null) {
                return null;
            }

            $raw = trim((string) $v);

            // Kosong / tanda hubung / penanda kosong lain => null (BUKAN nol).
            if ($raw === '' || in_array(strtolower($raw), ['-', 'null', 'n/a', 'na', '#n/a', 'none'], true)) {
                return null;
            }

            // Buang spasi (termasuk non-breaking) dan awalan mata uang "Rp".
            $s = preg_replace('/[\s\x{00A0}]+/u', '', $raw);
            $s = preg_replace('/^rp/i', '', $s);

            if (! preg_match('/^-?[\d.,]+$/', $s)) {
                $errors[] = "{$label}: nilai '{$raw}' bukan angka yang valid.";

                return null;
            }

            $hasDot = str_contains($s, '.');
            $hasComma = str_contains($s, ',');

            // Pola Indonesia: titik ribuan + koma desimal, mis. 2.500,00.
            if ($hasDot && $hasComma) {
                if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {
                    return (float) str_replace(['.', ','], ['', '.'], $s);
                }

                $errors[] = "{$label}: format angka '{$raw}' ambigu (campuran titik & koma).";

                return null;
            }

            // Hanya titik: titik ribuan bertingkat (1.000.000) atau titik desimal (2500.50).
            if ($hasDot) {
                if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
                    return (float) str_replace('.', '', $s);
                }

                if (preg_match('/^-?\d+\.\d+$/', $s)) {
                    return (float) $s;
                }

                $errors[] = "{$label}: format angka '{$raw}' ambigu (penggunaan titik tidak konsisten).";

                return null;
            }

            // Hanya koma: koma = desimal gaya Indonesia, mis. 2500,50 / 1,770.
            // Beberapa koma (pemisah ribuan gaya lain) => ambigu.
            if ($hasComma) {
                if (preg_match('/^-?\d+,\d+$/', $s)) {
                    return (float) str_replace(',', '.', $s);
                }

                $errors[] = "{$label}: format angka '{$raw}' ambigu (penggunaan koma tidak jelas).";

                return null;
            }

            return (float) $s;
        };

        $date = function ($v) {
            // 1. Nilai berobjek tanggal (DateTime / Carbon).
            if ($v instanceof \DateTimeInterface) {
                return $v->format('Y-m-d');
            }

            // 2. Serial tanggal Excel (angka berurutan sejak 1900).
            if (is_numeric($v)) {
                $serial = (float) $v;
                if ($serial >= 25569 && $serial <= 2958465) {
                    try {
                        return SpreadsheetDate::excelToDateTimeObject($serial)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        return null;
                    }
                }
            }

            $v = trim((string) $v);

            // 4. Kosong, tanda '-', atau tak terbaca -> null (tanpa exception).
            if ($v === '' || $v === '-') {
                return null;
            }

            // 3a. Format Eropa/Indonesia hari/bulan/tahun: 31/12/1980.
            if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $v)) {
                try {
                    return Carbon::createFromFormat('d/m/Y', $v)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            }

            // 3b. Format pemisah titik/garis: 31.12.1980 atau 31-12-1980.
            if (preg_match('#^\d{1,2}[-.]\d{1,2}[-.]\d{4}$#', $v)) {
                try {
                    return Carbon::createFromFormat('d-m-Y', str_replace(['.', '/'], '-', $v))->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            }

            // 3c. Format lain (Y-m-d, m/d/Y, dst.) - gagal parse dikembalikan null.
            try {
                return Carbon::parse($v)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        };

        $nomorHp = $str($value('nomor_hp_petugas'));

        // Kolom DB max 20 char; potong agar tidak memicu error 1406 "Data too long".
        $nomorHp = trim(substr((string) $nomorHp, 0, 20));
        $nomorHp = $nomorHp !== '' ? $nomorHp : null;

        return [
            'no_excel' => ($no = $str($value('no_excel'))) === null ? null : (int) $no,
            'kib' => $str($value('kib')),
            'tanggal_buku' => $date($value('tanggal_buku')),
            'tanggal_perolehan' => $date($value('tanggal_perolehan')),
            'nilai_perolehan' => $num($value('nilai_perolehan'), 'Nilai Perolehan'),
            'deskripsi_objek' => $str($value('deskripsi_objek')),
            'luas_tanah' => $num($value('luas_tanah'), 'Luas Tanah'),
            'alamat' => $str($value('alamat')),
            'ketkel' => $str($value('ketkel')),
            'status_hak' => $str($value('status_hak')),
            'nomor_sertifikat' => $str($value('nomor_sertifikat')),
            'tanggal_sertifikat' => $date($value('tanggal_sertifikat')),
            'penggunaan' => $str($value('penggunaan')),
            'penggunaan_air' => $str($value('penggunaan_air')),
            'kondisi' => $str($value('kondisi')),
            'keterangan' => $str($value('keterangan')),
            'nama_petugas' => $str($value('nama_petugas')),
            'nomor_hp_petugas' => $nomorHp,
            'google_maps' => $str($value('google_maps')),
            'satuan' => $str($value('satuan')) ?? 'm2',
        ];
    }

    private static function streamXlsx(Spreadsheet $spreadsheet, string $filename)
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static fn () => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * Ekspor data master tanah + PAD & tarif retribusi TAHUN TERPILIH ke Excel.
     *
     * Kolom master (19) diselaraskan dengan template import (KOLOM_TEMPLATE) agar
     * tetap dapat diimpor kembali; empat kolom tahunan ditambahkan di sebelah
     * kanan: "PAD {tahun}", "Tarif Retribusi {tahun}", "Biaya Pengurusan Kebun
     * /1 tahun {tahun}", dan "Total Tarif Jika Disewakan {tahun}". Nilai diambil
     * dari tabel `retribusi_tanah` berdasarkan pasangan (id_tanah, tahun) — BUKAN
     * dari kolom `tanah.penerimaan_pad` yang merupakan akumulasi lintas tahun.
     *
     * Tahun mengikuti filter halaman daftar; bila parameter tidak ada, dipakai
     * tahun berjalan (sama seperti index()). Pencarian & urutan data master
     * dipertahankan.
     */
    public function export(Request $request)
    {
        $search = $request->query('search');
        $tahun = self::tahunEkspor($request);

        $tanahList = Tanah::with('retribusi')
            ->when($search, fn ($query, $value) => self::terapkanPencarian($query, $value))
            ->orderByDesc('id_tanah')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventaris Tanah');

        self::tulisSheetExport($sheet, $tanahList, $tahun);

        $filename = 'Inventaris-Tanah-' . now()->format('Ymd-His') . '.xlsx';

        return self::streamXlsx($spreadsheet, $filename);
    }

    /**
     * Terapkan filter pencarian halaman daftar ke query Tanah.
     * Dipakai bersama oleh index() & export() agar perilaku pencarian konsisten.
     */
    private static function terapkanPencarian($query, $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('no_excel', 'like', "%{$search}%")
                ->orWhere('kib', 'like', "%{$search}%")
                ->orWhere('deskripsi_objek', 'like', "%{$search}%")
                ->orWhere('alamat', 'like', "%{$search}%")
                ->orWhere('nomor_sertifikat', 'like', "%{$search}%")
                ->orWhere('status_hak', 'like', "%{$search}%")
                ->orWhere('penggunaan', 'like', "%{$search}%")
                ->orWhere('penggunaan_air', 'like', "%{$search}%")
                ->orWhere('nama_petugas', 'like', "%{$search}%");
        });
    }

    /**
     * Tahun yang dipakai untuk export: parameter filter halaman daftar bila
     * valid, selain itu tahun berjalan (konsisten dengan index()).
     */
    private static function tahunEkspor(Request $request): int
    {
        return self::normalizeTahun($request->query('tahun')) ?? (int) date('Y');
    }

    /**
     * Header export: 19 kolom master + empat kolom retribusi tahunan bertanda
     * tahun (PAD, Tarif Retribusi, Biaya Pengurusan Kebun, Total Tarif Jika
     * Disewakan).
     */
    private static function kolomEkspor(int $tahun): array
    {
        return array_merge(self::KOLOM_TEMPLATE, [
            "PAD {$tahun}",
            "Tarif Retribusi {$tahun}",
            "Biaya Pengurusan Kebun /1 tahun {$tahun}",
            "Total Tarif Jika Disewakan {$tahun}",
        ]);
    }

    /**
     * Pilih baris retribusi untuk satu Tanah pada tahun tertentu.
     *
     * ATURAN PEMILIHAN (menampilkan, BUKAN menghapus/mengubah data): bila ada
     * lebih dari satu baris pada tahun yang sama, dipilih baris dengan
     * `id_retribusi` TERBESAR secara deterministik. Data duplikat di database
     * tidak pernah dihapus/diubah oleh proses export ini.
     *
     * @param  \Illuminate\Support\Collection|array|null  $retribusi
     * @return \App\Models\RetribusiTanah|null
     */
    private static function pilihRetribusiTahun($retribusi, int $tahun)
    {
        return collect($retribusi ?? [])
            ->where('tahun', $tahun)
            ->sortByDesc('id_retribusi')
            ->first();
    }

    /**
     * Susun satu baris export: 19 kolom master + empat kolom retribusi tahunan
     * (PAD, Tarif Retribusi, Biaya Pengurusan Kebun, Total Tarif Jika Disewakan).
     * Tanpa data retribusi pada tahun tsb => empat kolom terakhir dibiarkan kosong.
     */
    private static function barisEkspor(Tanah $tanah, int $tahun, int $no): array
    {
        $retribusi = self::pilihRetribusiTahun($tanah->retribusi, $tahun);

        return [
            $no,
            $tanah->kib ?? '-',
            $tanah->tanggal_buku ? $tanah->tanggal_buku->format('d/m/Y') : '-',
            $tanah->tanggal_perolehan ? $tanah->tanggal_perolehan->format('d/m/Y') : '-',
            $tanah->nilai_perolehan !== null ? (float) $tanah->nilai_perolehan : '-',
            $tanah->deskripsi_objek ?? '-',
            $tanah->luas_tanah !== null ? (float) $tanah->luas_tanah : '-',
            $tanah->alamat ?? '-',
            $tanah->ketkel ?? '-',
            $tanah->status_hak ?? '-',
            $tanah->nomor_sertifikat ?? '-',
            $tanah->tanggal_sertifikat ? $tanah->tanggal_sertifikat->format('d/m/Y') : '-',
            $tanah->penggunaan ?? '-',
            $tanah->kondisi ?? '-',
            $tanah->keterangan ?? '-',
            $tanah->nama_petugas ?? '-',
            $tanah->nomor_hp_petugas ?? '-',
            $tanah->google_maps ?? '-',
            $tanah->penggunaan_air ?? '-',
            $retribusi && $retribusi->PAD !== null ? (float) $retribusi->PAD : '',
            $retribusi && $retribusi->tarif_retribusi !== null ? (float) $retribusi->tarif_retribusi : '',
            $retribusi && $retribusi->biaya_pengurusan !== null ? (float) $retribusi->biaya_pengurusan : '',
            $retribusi && $retribusi->total_tarif_sewa !== null ? (float) $retribusi->total_tarif_sewa : '',
        ];
    }

    /**
     * Tulis header + baris ke worksheet. Satu baris per Tanah (duplikat
     * retribusi TIDAK menggandakan baris master).
     */
    private static function tulisSheetExport($sheet, iterable $tanahList, int $tahun): void
    {
        $sheet->fromArray(self::kolomEkspor($tahun), null, 'A1');

        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $rowNum = 2;
        $no = 0;
        foreach ($tanahList as $tanah) {
            $no++;
            $sheet->fromArray(self::barisEkspor($tanah, $tahun, $no), null, "A{$rowNum}");
            $rowNum++;
        }

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
