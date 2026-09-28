<?php

namespace App\Http\Controllers;

use App\Models\DokumenPbbHistory;
use App\Models\Tanah;
use App\Models\TanahHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TanahController extends Controller
{
    /**
     * Menampilkan daftar seluruh data tanah.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $tanahList = Tanah::with([
            'dokumenPbb',
            'retribusi',
        ])
            ->when($search, function ($query, $search) {
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
            })
            ->orderByDesc('id_tanah')
            ->paginate(10)
            ->withQueryString();

        return view('tanah.index', compact('tanahList'));
    }

    /**
     * Menampilkan detail tanah.
     */
    public function show(Tanah $tanah)
    {
        $tanah->load([
            'retribusi',
            'dokumenPbb.uploader',
            'dokumenPbbHistories.user',
            'histories.user',
        ]);

        $historyPbb = DokumenPbbHistory::with('user')
            ->whereIn(
                'id_pbb',
                $tanah->dokumenPbb->pluck('id_pbb')
            )
            ->latest()
            ->get();

        return view('tanah.show', compact(
            'tanah',
            'historyPbb'
        ));
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

        if ($request->hasFile('foto')) {
            $data['foto_tanah'] = $request->file('foto')->store('tanah/foto', 'public');
        }
        $data['video_tanah'] = $validated['video_url'] ?: null;

        Tanah::create($data);

        return redirect()
            ->route('tanah.index')
            ->with('success', 'Data tanah berhasil ditambahkan.');
    }

    /**
     * Menampilkan form edit tanah.
     */
    public function edit(Tanah $tanah)
    {
        abort_unless($this->isAdminTanah(), 403);

        return view('tanah.edit', compact('tanah'));
    }

    /**
     * Memperbarui data tanah.
     */
    public function update(Request $request, Tanah $tanah)
    {
        abort_unless($this->isAdminTanah(), 403);

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

        $dataLama = $tanah->toArray();

        $data = $validated;
        unset($data['foto'], $data['video_url']);

        if ($request->hasFile('foto')) {
            $data['foto_tanah'] = $request->file('foto')->store('tanah/foto', 'public');
        }
        $data['video_tanah'] = $validated['video_url'] ?: null;

        $tanah->update($data);

        $dataBaru = $tanah->fresh()->toArray();

        TanahHistory::create([
            'id_tanah' => $tanah->id_tanah,
            'id_user' => auth()->id(),
            'aksi' => 'UPDATE',
            'data_lama' => $dataLama,
            'data_baru' => $dataBaru,
        ]);

        return redirect()
            ->route('tanah.index')
            ->with('success', 'Data tanah berhasil diperbarui.');
    }

    /**
     * Otorisasi akses data tanah (lihat/edit) untuk Admin Aset & Admin P2BTP.
     */
    private function isAdminTanah(): bool
    {
        $role = strtolower((string) (
            auth()->user()?->role?->nama_role
            ?? auth()->user()?->level
            ?? ''
        ));

        return in_array($role, [
            'admin',
            'admin_aset',
            'admin aset',
            'admin_p2btp',
            'admin p2btp',
            'upt p2btp',
            'p2btp',
        ]);
    }

    private const KOLOM_TEMPLATE = [
        'No', 'No Excel', 'KIB / No Barang', 'Tanggal Buku', 'Tanggal Perolehan',
        'Nilai Perolehan (Rp)', 'Deskripsi Objek', 'Luas Tanah (m2)', 'Alamat',
        'Kelurahan', 'Status Hak', 'Nomor Sertifikat', 'Tanggal Sertifikat',
        'Penggunaan', 'Penggunaan Air', 'Kondisi', 'Keterangan', 'Nama Petugas',
        'No HP Petugas', 'Google Maps', 'Satuan',
    ];

    /**
     * Unduh template Excel baku isian data tanah (KIB A).
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template KIB A Tanah');

        $sheet->fromArray(array_merge(self::KOLOM_TEMPLATE, [
            1, 'CONTOH - 001', 'KIB A', '01-01-2026', '01-05-2026',
            250000000, 'Tanah untuk bangunan kantor', 1200.5, 'Jl. Contoh No. 1',
            'Kelurahan Contoh', 'SHM', 'CONTOH - NOMOR SERTIFIKAT 0001', '15-06-2026',
            'Perkantoran', 'Tidak Ada', 'Baik', 'Contoh data isian (akan dilewati saat import)',
            'Budi Santoso', '081234567890', 'https://maps.app.goo.gl/xyz', 'm2',
        ]), null, 'A2');

        $sheet->getStyle('A1:U1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach (range('A', 'U') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return self::streamXlsx($spreadsheet, 'Template-KIB-A-Tanah.xlsx');
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

        foreach ($rows as $no => $row) {
            if ($no < 2) {
                continue; // baris 1 = judul kolom
            }

            $mapped = $this->mapRow($row, $colMap);

            // Lewati baris contoh bawaan template & baris kosong.
            $isContoh = str_contains((string) ($mapped['kib'] ?? ''), 'CONTOH')
                || str_contains((string) ($mapped['nomor_sertifikat'] ?? ''), 'CONTOH');
            $isEmpty = empty($mapped['nomor_sertifikat'])
                && empty($mapped['kib'])
                && empty($mapped['deskripsi_objek'])
                && empty($mapped['alamat']);
            if ($isContoh || $isEmpty) {
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

                TanahHistory::create([
                    'id_tanah' => $existing->id_tanah,
                    'id_user' => auth()->id(),
                    'aksi' => 'UPDATE',
                    'data_lama' => $dataLama,
                    'data_baru' => $existing->fresh()->toArray(),
                ]);

                $updated++;

                continue;
            }

            Tanah::create($mapped);
            $created++;
        }

        $pesan = "Import selesai: {$created} data baru ditambahkan";
        if ($updated) {
            $pesan .= ", {$updated} data diperbarui";
        }
        $pesan .= ". {$skipped} baris duplikat/kosong dilewati.";

        return redirect()
            ->route('tanah.index')
            ->with('success', $pesan);
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
            if ($key !== '') {
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
            'no_excel' => $resolve(['noexcel', 'no excel', 'no'], ['B']),
            'kib' => $resolve(['kib', 'kibnobarang', 'nobarang', 'kodennibarang'], ['C']),
            'tanggal_buku' => $resolve(['tanggalbuku', 'tglbuku'], ['D']),
            'tanggal_perolehan' => $resolve(['tanggalperolehan', 'tglperolehan'], ['E']),
            'nilai_perolehan' => $resolve(['nilaiperolehan', 'perolehan', 'nilai'], ['F']),
            'deskripsi_objek' => $resolve(['deskobj', 'deskripsiobjek', 'deskripsi'], ['G']),
            'luas_tanah' => $resolve(['luas', 'luastanah', 'luastanahm2'], ['H']),
            'alamat' => $resolve(['alamat'], ['I']),
            'ketkel' => $resolve(['ketkel', 'kelurahan', 'letakkelurahan'], ['J']),
            'status_hak' => $resolve(['statushak', 'status hak'], ['K']),
            'nomor_sertifikat' => $resolve(['nosert', 'nomorsertifikat', 'nosertifikat'], ['L']),
            'tanggal_sertifikat' => $resolve(['tglsert', 'tanggalsertifikat', 'tglsertifikat'], ['M']),
            'penggunaan' => $resolve(['pengunaan', 'penggunaan', 'penggunaantanah'], ['N']),
            'penggunaan_air' => $resolve(['penggunaanairsilahkandiisipilihansbgberikut', 'penggunaanair', 'penggunaan air'], ['S', 'O']),
            'kondisi' => $resolve(['kondisi'], ['P']),
            'keterangan' => $resolve(['keterangan', 'keterangan1', 'statusobjekretribusi'], ['Q']),
            'nama_petugas' => $resolve(['namapetugas', 'nama petugas', 'petugas'], ['P', 'R']),
            'nomor_hp_petugas' => $resolve(['nomortlppetugas', 'nomortlp', 'nomor tlp petugas', 'tlppetugas', 'nomorhppetugas', 'nohppetugas', 'nohp'], ['Q', 'S']),
            'google_maps' => $resolve(['gmaps', 'googlemaps', 'maps'], ['R', 'T']),
            'satuan' => $resolve(['satuan'], ['U']),
        ];
    }

    /**
     * Petakan satu baris Excel ke atribut model Tanah berdasar posisi kolom hasil resolveColumns().
     */
    private function mapRow(array $row, array $colMap): array
    {
        $value = function (string $field) use ($row, $colMap) {
            $letter = $colMap[$field] ?? null;

            return $letter !== null && array_key_exists($letter, $row) ? $row[$letter] : null;
        };

        $str = fn ($v) => trim((string) $v) !== '' ? trim((string) $v) : null;

        $num = function ($v) {
            $v = trim((string) $v);

            return $v === '' ? null : (float) str_replace(',', '.', $v);
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
            'nilai_perolehan' => $num($value('nilai_perolehan')),
            'deskripsi_objek' => $str($value('deskripsi_objek')),
            'luas_tanah' => $num($value('luas_tanah')),
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
            $filename
        );
    }
}
