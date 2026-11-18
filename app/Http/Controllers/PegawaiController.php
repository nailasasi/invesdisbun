<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\Lokasi;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\Ruangan;
use App\Services\ActivityLogger;
use App\Services\AsetPenempatan;
use App\Services\UserManagementScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * MASTER DATA -> DATA PEGAWAI.
 *
 * Berurusan dengan DATA ORANG saja (NIP, nama, jabatan, SKPD, ruangan).
 * Akun login (username, role, status) dikelola di UserController.
 *
 * Konsep Fase 1A:
 *   Pegawai -> 0 atau 1 User -> Role -> Hak Akses
 *
 * Pembuatan pegawai TIDAK membuat akun. Akun dibuat dari halaman detail
 * pegawai / User Management melalui UserController::storeAccount().
 */
class PegawaiController extends Controller
{
    /**
     * Label field untuk timeline riwayat.
     */
    public const FIELD_LABELS = [
        'nama_pegawai' => 'Nama Pegawai',
        'nip' => 'NIP',
        'jabatan' => 'Jabatan',
        'skpd' => 'SKPD',
        'lokasi' => 'Lokasi',
        'ruangan' => 'Ruangan',
    ];

    /**
     * Judul kolom template import data pegawai (urutan baku).
     */
    private const KOLOM_TEMPLATE = [
        'NIP *', 'Nama Pegawai *', 'Jabatan *', 'Nama SKPD *', 'Nama Ruangan',
    ];

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly UserManagementScope $scope,
    ) {
    }

    /**
     * Pencarian pegawai untuk dropdown (JSON minimal).
     */
    public function search(Request $request)
    {
        abort_unless($this->scope->bolehKelolaModul(), 403, 'Anda tidak memiliki hak akses untuk menu ini.');

        $search = trim((string) $request->query('q', $request->query('search', '')));
        if ($search === '') {
            return response()->json(['data' => []]);
        }

        $limit = $request->query('limit');
        $limit = ctype_digit((string) $limit) ? (int) $limit : 10;
        $limit = max(1, min($limit, 30));

        $pegawais = $this->scope->batasiQuery(Pegawai::query())
            ->select(['id_pegawai', 'nip', 'nama_pegawai', 'jabatan', 'id_skpd', 'id_ruangan'])
            ->where(function ($q) use ($search) {
                $q->where('nama_pegawai', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            })
            ->orderBy('nama_pegawai')
            ->limit($limit)
            ->get()
            ->map(function ($p) {
                return [
                    'id_pegawai' => $p->id_pegawai,
                    'nip' => $p->nip,
                    'nama_pegawai' => $p->nama_pegawai,
                    'jabatan' => $p->jabatan,
                    'id_skpd' => $p->id_skpd,
                    'id_ruangan' => $p->id_ruangan,
                ];
            });

        return response()->json(['data' => $pegawais]);
    }

    /**
     * Daftar pegawai + status akunnya (halaman Data Pegawai).
     */
    public function index(Request $request)
    {
        abort_unless($this->scope->bolehKelolaModul(), 403, 'Anda tidak memiliki hak akses untuk menu ini.');

        $search = trim((string) $request->query('search'));
        $search = $search === '' ? null : $search;

        $idSkpd = $request->query('skpd');
        $idSkpd = $idSkpd !== null && $idSkpd !== '' && ctype_digit((string) $idSkpd) ? (int) $idSkpd : null;

        $jenisSkpd = $request->query('jenis_skpd');
        $jenisSkpd = in_array($jenisSkpd, ['Sekretariat', 'Bidang', 'UPT'], true) ? $jenisSkpd : null;

        $statusAkun = $request->query('status_akun');
        $statusAkun = in_array($statusAkun, ['belum', 'aktif', 'nonaktif'], true) ? $statusAkun : null;

        $pegawaiList = $this->scope->batasiQuery(Pegawai::query())
            ->with(['skpd', 'ruangan', 'user.role'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nip', 'like', "%{$search}%")
                        ->orWhere('nama_pegawai', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%");
                });
            })
            ->when($idSkpd, fn ($query, $idSkpd) => $query->where('id_skpd', $idSkpd))
            ->when($jenisSkpd, fn ($query, $jenisSkpd) => $query->whereHas('skpd', fn ($q) => $q->where('jenis_skpd', $jenisSkpd)))
            ->when($statusAkun === 'belum', fn ($query) => $query->whereDoesntHave('user'))
            ->when($statusAkun === 'aktif', fn ($query) => $query->whereHas('user', fn ($q) => $q->where('status_user', 'aktif')))
            ->when($statusAkun === 'nonaktif', fn ($query) => $query->whereHas('user', fn ($q) => $q->where('status_user', 'nonaktif')))
            ->orderBy('nama_pegawai')
            ->paginate(10)
            ->withQueryString();

        $skpdList = $this->scope->skpdBolehDipilih();
        $ruanganList = $this->ruanganList();
        $lokasiDinas = Lokasi::dinas();
        $fieldLabels = self::FIELD_LABELS;
        $roleList = $this->scope->roleListBoleh();
        $isAdminAset = $this->scope->isAdminAset();

        return view('master-data.pegawai.index', compact(
            'pegawaiList',
            'skpdList',
            'ruanganList',
            'lokasiDinas',
            'fieldLabels',
            'roleList',
            'isAdminAset'
        ));
    }

    /**
     * Form tambah pegawai (tanpa akun login).
     */
    public function create()
    {
        abort_unless($this->scope->bolehKelolaModul(), 403, 'Anda tidak memiliki hak akses untuk menu ini.');

        return view('master-data.pegawai.create', [
            'skpdList' => $this->scope->skpdBolehDipilih(),
            'ruanganList' => $this->ruanganList(),
            'lokasiDinas' => Lokasi::dinas(),
        ]);
    }

    /**
     * Simpan pegawai BARU. Tidak membuat akun login.
     * Status akun otomatis "Belum Ada Akun".
     */
    public function store(Request $request)
    {
        abort_unless($this->scope->bolehKelolaModul(), 403, 'Anda tidak memiliki hak akses untuk menu ini.');

        $data = $this->validatePegawai($request);

        $pegawai = Pegawai::create([
            'nip' => $data['nip'],
            'nama_pegawai' => $data['nama_pegawai'],
            'jabatan' => $data['jabatan'] ?? null,
            'id_skpd' => $data['id_skpd'],
            'id_ruangan' => $data['id_ruangan'] ?? null,
        ]);

        $pegawai->refresh()->load('skpd', 'ruangan', 'user');

        $this->logger->log(
            'pegawai.created',
            $pegawai,
            [],
            $this->snapshot($pegawai),
            sprintf('Menambahkan data pegawai %s (%s).', $pegawai->nama_pegawai, $pegawai->nip)
        );

        return $this->berhasil(
            'Data pegawai berhasil ditambahkan. Belum ada akun login — buat akun dari halaman detail pegawai.',
            route('master-data.pegawai.show', $pegawai)
        );
    }

    /**
     * Detail pegawai + blok AKUN PENGGUNA (Buat Akun / Kelola Akun).
     */
    public function show(Pegawai $pegawai)
    {
        abort_unless($this->scope->bolehLihatPegawai($pegawai), 403, 'Anda tidak memiliki hak akses untuk pegawai ini.');

        $pegawai->load('skpd', 'ruangan', 'user.role');

        $isAdminAset = $this->scope->isAdminAset();

        $riwayatLogs = $isAdminAset
            ? \App\Models\ActivityLog::query()
                ->where('subject_type', Pegawai::class)
                ->where('subject_id', $pegawai->id_pegawai)
                ->with(['actor.pegawai', 'actor.role'])
                ->orderByDesc('created_at')
                ->orderByDesc('id_activity_log')
                ->get()
            : collect();

        return view('master-data.pegawai.show', [
            'pegawai' => $pegawai,
            'lokasiPegawai' => Lokasi::untukSkpd($pegawai->id_skpd),
            'roleList' => $this->scope->rolesUntukPegawai($pegawai),
            'riwayatLogs' => $riwayatLogs,
            'fieldLabels' => self::FIELD_LABELS,
            'isAdminAset' => $isAdminAset,
        ]);
    }

    /**
     * Form edit data pegawai.
     */
    public function edit(Pegawai $pegawai)
    {
        abort_unless($this->scope->bolehLihatPegawai($pegawai), 403, 'Anda tidak memiliki hak akses untuk pegawai ini.');

        $pegawai->load('skpd', 'ruangan', 'user');

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'pegawai' => [
                    'id_pegawai' => $pegawai->id_pegawai,
                    'nip' => $pegawai->nip,
                    'nama_pegawai' => $pegawai->nama_pegawai,
                    'jabatan' => $pegawai->jabatan,
                    'id_skpd' => $pegawai->id_skpd,
                    'id_ruangan' => $pegawai->id_ruangan,
                ],
                'skpd' => $pegawai->skpd,
                'ruangan' => $pegawai->ruangan,
                'update_url' => route('master-data.pegawai.update', $pegawai),
            ]);
        }

        return view('master-data.pegawai.edit', [
            'pegawai' => $pegawai,
            'skpdList' => $this->scope->skpdBolehDipilih(),
            'ruanganList' => $this->ruanganList(),
            'lokasiDinas' => Lokasi::dinas(),
        ]);
    }

    /**
     * Perbarui data pegawai.
     *
     * Catatan: perpindahan SKPD TIDAK memindahkan aset. Penempatan aset
     * tetap mengikuti ruangan kerja pegawai (alur yang sudah ada).
     * Field akun (username/role/status) TIDAK disentuh di sini.
     */
    public function update(Request $request, Pegawai $pegawai)
    {
        abort_unless($this->scope->bolehLihatPegawai($pegawai), 403, 'Anda tidak memiliki hak akses untuk pegawai ini.');

        $data = $this->validatePegawai($request, $pegawai);

        // Validasi silang: jika pegawai memiliki akun, perubahan SKPD tidak boleh memicu konflik role
        if ($pegawai->user && isset($data['id_skpd']) && (int) $data['id_skpd'] !== (int) $pegawai->id_skpd) {
            $skpdBaru = \App\Models\Skpd::find($data['id_skpd']);
            $roleName = $pegawai->user->role?->nama_role;
            $jenisBaru = $skpdBaru?->jenis_skpd;

            $cocok = match ($roleName) {
                'Admin Aset' => $jenisBaru === 'Sekretariat',
                'Admin Bidang' => $jenisBaru === 'Bidang',
                'Admin UPT' => $jenisBaru === 'UPT',
                'Pegawai' => true,
                default => true,
            };

            if (! $cocok) {
                throw ValidationException::withMessages([
                    'id_skpd' => "Tidak dapat memindahkan SKPD ke {$skpdBaru?->nama_skpd} ({$jenisBaru}): Pegawai ini memiliki akun dengan role \"{$roleName}\". Sesuaikan role akun terlebih dahulu sebelum mengubah SKPD.",
                ]);
            }
        }

        $ruanganBaru = $data['id_ruangan'] ?? null;

        // Aset yang sedang dipegang pegawai mengikuti ruangan kerja pegawai.
        $asetPemegangIds = PemegangAset::where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->pluck('id_aset');

        if ($ruanganBaru === null && $asetPemegangIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'id_ruangan' => "Tidak dapat mengosongkan ruangan: masih ada {$asetPemegangIds->count()} aset yang dipegang pegawai ini. Pindahkan pemegangnya terlebih dahulu.",
            ]);
        }

        $sebelum = $this->snapshot($pegawai);

        $pegawai->update([
            'nip' => $data['nip'],
            'nama_pegawai' => $data['nama_pegawai'],
            'jabatan' => $data['jabatan'] ?? null,
            'id_skpd' => $data['id_skpd'],
            'id_ruangan' => $ruanganBaru,
        ]);

        // Semua aset yang sedang dipegang pegawai mengikuti master pegawai:
        //   - Ruangan aset = ruang kerja pegawai
        //   - SKPD aset    = `pegawai.id_skpd`
        //   - Lokasi aset  = `ruangan.id_lokasi` ruang kerjanya
        if ($asetPemegangIds->isNotEmpty()) {
            $perluPindah = [];
            $sudahDiRuangTepat = [];

            foreach ($asetPemegangIds as $idAset) {
                $aset = Aset::find($idAset);

                if (! $aset) {
                    continue;
                }

                if ($aset->penempatanAktif?->id_ruangan != $ruanganBaru) {
                    $perluPindah[] = $aset;
                } else {
                    $sudahDiRuangTepat[] = $aset;
                }
            }

            if (! empty($perluPindah) && $ruanganBaru !== null) {
                $mutasi = MutasiAset::create([
                    'tanggal_mutasi' => now()->toDateString(),
                    'jenis_mutasi' => 'Pindah Ruangan',
                    'keterangan' => 'Ruangan kerja pegawai diubah',
                    'id_user_penginput' => auth()->id(),
                    'status_mutasi' => 'selesai',
                ]);

                foreach ($perluPindah as $aset) {
                    $aset->moveToRoom($ruanganBaru, $pegawai->id_pegawai, null, $mutasi->id_mutasi);

                    // Kalau ruang baru belum dipetakan ke lokasi,
                    // `aset.id_lokasi` menjadi NULL, bukan menyisakan lokasi lama.
                    AsetPenempatan::sinkronAset($aset, $pegawai->id_pegawai, $ruanganBaru);
                }
            }

            // Aset yang ruangnya tidak berubah tetap ikut SKPD pegawai.
            foreach ($sudahDiRuangTepat as $aset) {
                AsetPenempatan::sinkronAset($aset, $pegawai->id_pegawai, $ruanganBaru);
            }
        }

        $pegawai->refresh()->load('skpd', 'ruangan', 'user');
        $sesudah = $this->snapshot($pegawai);

        $diff = $this->logger->diff($sebelum, $sesudah);

        if (! empty($diff['changed'])) {
            $this->logger->log(
                'pegawai.updated',
                $pegawai,
                $diff['old'],
                $diff['new'],
                $this->describe($diff['changed'], 'Memperbarui data pegawai '.$pegawai->nama_pegawai)
            );
        }

        return $this->berhasil('Data pegawai berhasil diperbarui.', route('master-data.pegawai.show', $pegawai));
    }

    /**
     * Validasi data pegawai. SKPD wajib dan harus termasuk SKPD yang
     * boleh dikelola aktor (Admin UPT: hanya UPT miliknya).
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validatePegawai(Request $request, ?Pegawai $pegawai = null): array
    {
        $skpdBoleh = $this->scope->skpdBolehDipilih()->pluck('id_skpd')->map(fn ($id) => (int) $id)->all();

        $data = $request->validate([
            'nip' => ['required', 'string', 'max:30', Rule::unique('pegawai', 'nip')->ignore($pegawai?->id_pegawai, 'id_pegawai')],
            'nama_pegawai' => ['required', 'string', 'max:100'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'id_skpd' => ['required', Rule::in($skpdBoleh)],
            'id_ruangan' => [...$this->ruanganRules($request)],
        ], [
            'id_skpd.required' => 'SKPD wajib dipilih.',
            'id_skpd.in' => 'Anda tidak berwenang memilih SKPD tersebut.',
            'id_ruangan.required' => 'Ruangan kerja wajib dipilih.',
            'id_ruangan.exists' => 'Ruangan yang dipilih tidak valid atau tidak ditemukan.',
        ]);

        return $data;
    }

    /**
     * Ruangan wajib dipilih dan harus berada pada lokasi kerja SKPD terpilih.
     * Ruangan bersama (id_skpd NULL) tetap diperbolehkan selama id_lokasi sesuai.
     *
     * @return array<int, mixed>
     */
    private function ruanganRules(Request $request): array
    {
        return [
            'required',
            'exists:ruangan,id_ruangan',
            function (string $attribute, $value, \Closure $fail) use ($request) {
                if ($value === null || $value === '') {
                    $fail('Ruangan kerja wajib dipilih.');

                    return;
                }

                $ruangan = Ruangan::find($value);
                $skpdId = $request->input('id_skpd');

                if (! $ruangan) {
                    $fail('Ruangan yang dipilih tidak valid atau tidak ditemukan.');

                    return;
                }

                $lokasiSkpd = Lokasi::untukSkpd($skpdId !== null && $skpdId !== '' ? (int) $skpdId : null);

                // Fail-closed: tanpa lokasi kerja yang sah untuk SKPD terpilih,
                // ruangan milik lokasi lain TIDAK BOLEH diterima.
                if (! $lokasiSkpd) {
                    $fail('Lokasi kerja untuk SKPD terpilih belum tersedia. Ruangan tidak dapat dipilih.');

                    return;
                }

                if ((int) $ruangan->id_lokasi !== (int) $lokasiSkpd->id_lokasi) {
                    $fail('Ruangan yang dipilih tidak sesuai dengan lokasi kerja SKPD.');
                }
            },
        ];
    }

    private function ruanganList()
    {
        return Ruangan::with('skpd')
            ->orderBy('nama_ruangan')
            ->get(['id_ruangan', 'nama_ruangan', 'id_skpd', 'id_lokasi']);
    }

    /**
     * Unduh template Excel baku isian data pegawai (1 worksheet + petunjuk pengisian di atas).
     */
    public function downloadTemplate()
    {
        abort_unless($this->scope->bolehKelolaModul(), 403, 'Anda tidak memiliki hak akses untuk menu ini.');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Data Pegawai');

        // Pastikan kolom A (NIP) diformat sebagai Text (@) agar 0 di depan tidak hilang
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        // Petunjuk Pengisian di bagian atas sheet (Baris 1 - 7)
        $sheet->setCellValue('A1', 'PETUNJUK PENGISIAN IMPORT DATA PEGAWAI');
        $sheet->setCellValue('A2', '1. Kolom Wajib (*): NIP, Nama Pegawai, Jabatan, dan Nama SKPD wajib diisi.');
        $sheet->setCellValue('A3', '2. Format NIP: Ketik angka NIP secara utuh tanpa spasi. Kolom telah diformat sebagai teks agar angka 0 di depan tidak hilang.');
        $sheet->setCellValue('A4', '3. Nama SKPD (Unit Kerja): Harus sesuai dengan nama resmi di sistem (Sekretariat, Bidang Produksi Tanaman Semusim, Bidang Produksi Tanaman Tahunan, Bidang Perlindungan Perkebunan, Bidang Pengelolahan dan Pemasaran Hasil, UPT P2BTP, atau UPT PSBP).');
        $sheet->setCellValue('A5', '4. Keunikan Data: NIP harus unik di dalam sistem. Baris dengan NIP duplikat akan dilewati atau diperbarui sesuai mode import yang dipilih.');
        $sheet->setCellValue('A6', '5. Penempatan & Nama Ruangan: Kolom Nama Ruangan bersifat opsional (boleh dikosongkan saat import dan diatur belakangan melalui menu Edit Pegawai). Jika ingin langsung diisi, penamaannya WAJIB disamakan persis dengan nama ruangan yang terdaftar pada menu "Aset Ruangan" di website sesuai SKPD terkait.');
        $sheet->setCellValue('A7', '6. Baris Contoh: Baris yang diawali dengan kata "CONTOH -" akan otomatis dilewati oleh sistem saat proses import berlangsung.');

        // Styling Petunjuk
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('0F5132');
        $sheet->getStyle('A2:A7')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('334155');

        // Baris 8 dibiarkan kosong sebagai jeda pemisah

        // Baris Header Tabel pada Baris 9
        $sheet->fromArray(self::KOLOM_TEMPLATE, null, 'A9');

        // Baris Contoh pada Baris 10 (Dilewati otomatis saat import karena mengandung penanda 'CONTOH')
        $sheet->setCellValueExplicit('A10', 'CONTOH - 198001012006041001', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B10', 'CONTOH - Budi Santoso, S.P.', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C10', 'Kepala Bidang', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D10', 'CONTOH - Sekretariat', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('E10', 'CONTOH - Ruang Kepala Dinas', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        // Styling Header Tabel (A9:E9)
        $sheet->getStyle('A9:E9')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '0F5132']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(9)->setRowHeight(28);
        $sheet->getRowDimension(10)->setRowHeight(20);

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return self::streamXlsx($spreadsheet, 'Template-Data-Pegawai.xlsx');
    }

    /**
     * Impor data pegawai dari file Excel.
     *
     * HANYA membuat/memperbarui data orang (`pegawai`). TIDAK membuat akun
     * login, penempatan/pemegang aset, mutasi, maupun relasi lain.
     *
     * Aturan:
     *  - Identitas unik = `nip`; NIP wajib diisi.
     *  - SKPD dicocokkan berdasarkan nama dan HARUS termasuk SKPD yang boleh
     *    dikelola aktor (Admin UPT: hanya UPT miliknya).
     *  - Ruangan dicocokkan berdasarkan nama pada SKPD tsb / ruang bersama.
     *  - NIP duplikat: dilewati, atau diperbarui bila mode = update.
     */
    public function import(Request $request)
    {
        abort_unless($this->scope->bolehKelolaModul(), 403, 'Anda tidak memiliki hak akses untuk menu ini.');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'mode' => ['required', 'in:skip,update'],
        ]);

        $updateExisting = $request->input('mode') === 'update';

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // Deteksi baris header: baris pertama (<=10) yang mengenali >=2 kolom.
        $headerRow = null;
        $colMap = [];
        foreach ($rows as $index => $row) {
            if ($index > 10) {
                break;
            }

            $map = $this->buildImportHeaderMap($row);
            if (count($map) >= 2) {
                $headerRow = $index;
                $colMap = $map;
                break;
            }
        }

        if ($headerRow === null) {
            return back()->with('error', 'Header kolom tidak ditemukan pada 10 baris pertama. Pastikan file sesuai template import data pegawai.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $nipDalamFile = [];

        foreach ($rows as $index => $row) {
            if ($index <= $headerRow) {
                continue;
            }

            if ($this->isContohImportRow($row)) {
                continue;
            }

            $nip = $this->importCell($row, $colMap['nip'] ?? '');
            $nama = $this->importCell($row, $colMap['nama_pegawai'] ?? '');
            $jabatan = $this->importCell($row, $colMap['jabatan'] ?? '');
            $namaSkpd = $this->importCell($row, $colMap['nama_skpd'] ?? '');
            $namaRuangan = $this->importCell($row, $colMap['nama_ruangan'] ?? '');

            // Lewati baris benar-benar kosong.
            if ($nip === '' && $nama === '' && $jabatan === '' && $namaSkpd === '' && $namaRuangan === '') {
                continue;
            }

            if ($nip === '') {
                $errors[] = "Baris {$index}: NIP wajib diisi.";
                continue;
            }

            if (mb_strlen($nip) > 30) {
                $errors[] = "Baris {$index}: NIP melebihi 30 karakter.";
                continue;
            }

            if ($nama === '') {
                $errors[] = "Baris {$index}: Nama Pegawai wajib diisi.";
                continue;
            }

            if (mb_strlen($nama) > 100) {
                $errors[] = "Baris {$index}: Nama Pegawai melebihi 100 karakter.";
                continue;
            }

            if (mb_strlen($jabatan) > 100) {
                $errors[] = "Baris {$index}: Jabatan melebihi 100 karakter.";
                continue;
            }

            if ($namaSkpd === '') {
                $errors[] = "Baris {$index}: SKPD wajib diisi.";
                continue;
            }

            $skpd = $this->resolveSkpdImport($namaSkpd);
            if ($skpd === null) {
                $errors[] = "Baris {$index}: SKPD \"{$namaSkpd}\" tidak ditemukan atau di luar wewenang Anda.";
                continue;
            }

            $idRuangan = null;
            if ($namaRuangan !== '') {
                $idRuangan = $this->resolveRuanganImport((int) $skpd->id_skpd, $namaRuangan);
                if ($idRuangan === false) {
                    $errors[] = "Baris {$index}: Ruangan \"{$namaRuangan}\" tidak ditemukan atau ambigu untuk SKPD {$skpd->nama_skpd}.";
                    continue;
                }
            }

            // Duplikat di dalam file.
            $kunci = mb_strtolower($nip);
            if (isset($nipDalamFile[$kunci])) {
                $skipped++;
                continue;
            }
            $nipDalamFile[$kunci] = true;

            // Pencarian dibatasi cakupan aktor (Admin UPT: hanya UPT miliknya).
            $existing = $this->scope->batasiQuery(Pegawai::query())->where('nip', $nip)->first();

            if ($existing) {
                if (! $updateExisting) {
                    $skipped++;
                    continue;
                }

                $existing->update([
                    'nama_pegawai' => $nama,
                    'jabatan' => $jabatan !== '' ? $jabatan : null,
                    'id_skpd' => (int) $skpd->id_skpd,
                    'id_ruangan' => $idRuangan,
                ]);
                $updated++;
                continue;
            }

            // NIP sudah dipakai pegawai di luar cakupan aktor -> tolak (jangan bypass scope).
            if (Pegawai::where('nip', $nip)->exists()) {
                $errors[] = "Baris {$index}: NIP {$nip} sudah terdaftar pada SKPD di luar wewenang Anda.";
                continue;
            }

            try {
                Pegawai::create([
                    'nip' => $nip,
                    'nama_pegawai' => $nama,
                    'jabatan' => $jabatan !== '' ? $jabatan : null,
                    'id_skpd' => (int) $skpd->id_skpd,
                    'id_ruangan' => $idRuangan,
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Baris {$index}: {$e->getMessage()}";
            }
        }

        $pesan = "Import selesai: {$created} pegawai baru ditambahkan";
        if ($updated) {
            $pesan .= ", {$updated} data diperbarui";
        }
        $pesan .= ", {$skipped} baris duplikat/kosong dilewati.";
        if ($errors) {
            $pesan .= ' '.count($errors).' baris gagal: '.implode(' | ', $errors);
        }

        return back()
            ->with($errors ? 'error' : 'success', $pesan)
            ->with('import_summary', [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => count($errors),
                'errors' => $errors,
            ]);
    }

    /**
     * Petakan judul kolom (baris header) menjadi posisi huruf kolom.
     * Hanya kolom yang dikenali yang dikembalikan.
     */
    private function buildImportHeaderMap(array $headerRow): array
    {
        $aliases = [
            'nip' => ['nip', 'nomor induk pegawai', 'no induk pegawai', 'nomor pegawai', 'no pegawai', 'id pegawai'],
            'nama_pegawai' => ['nama pegawai', 'nama', 'namapegawai'],
            'jabatan' => ['jabatan', 'posisi', 'jabatan/posisi'],
            'nama_skpd' => ['nama skpd', 'skpd', 'unit kerja', 'nama unit kerja', 'opd', 'nama opd', 'instansi'],
            'nama_ruangan' => ['nama ruangan', 'ruangan', 'ruang'],
        ];

        $normalize = fn ($v) => strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', (string) $v));

        $map = [];
        foreach ($headerRow as $letter => $value) {
            $label = $normalize($value);
            if ($label === '') {
                continue;
            }

            foreach ($aliases as $field => $list) {
                foreach ($list as $alias) {
                    if ($normalize($alias) === $label) {
                        $map[$field] = $letter;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Ambil nilai sel sebagai string (trim dan normalisasi spasi ganda), aman bila kolom tak dipetakan.
     * Mencegah konversi notasi ilmiah Excel (mis. 1.98001E+17).
     */
    private function importCell(array $row, string $letter): string
    {
        if ($letter === '' || ! array_key_exists($letter, $row)) {
            return '';
        }

        $raw = $row[$letter];
        if ($raw === null) {
            return '';
        }

        // Konversi scientific notation (e.g. 1.98001E+17) ke angka integer murni tanpa desimal
        if (is_numeric($raw) && (stripos((string) $raw, 'e+') !== false || stripos((string) $raw, 'e-') !== false)) {
            return sprintf('%.0f', (float) $raw);
        }

        return trim((string) preg_replace('/\s+/', ' ', (string) $raw));
    }

    /**
     * Normalisasi teks input import: trim dan reduksi spasi ganda menjadi spasi tunggal lowercase.
     */
    private function normalizeImportText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $collapsed = preg_replace('/\s+/', ' ', trim($text));

        return mb_strtolower((string) $collapsed);
    }

    /**
     * Apakah baris contoh bawaan template (mengandung "CONTOH").
     */
    private function isContohImportRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (is_string($cell) && stripos($cell, 'CONTOH') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cari SKPD berdasarkan nama, dibatasi SKPD yang boleh dikelola aktor.
     * Mendukung toleransi spasi ganda, case-insensitive, dan variasi ejaan 'pengolahan' / 'pengelolahan'.
     */
    private function resolveSkpdImport(string $nama): ?object
    {
        $target = $this->normalizeImportText($nama);
        if ($target === '') {
            return null;
        }

        // Normalisasi kanonikal untuk variasi ejaan 'pengolahan' vs 'pengelolahan'
        $canonicalTarget = str_replace('pengolahan', 'pengelolahan', $target);

        foreach ($this->scope->skpdBolehDipilih() as $skpd) {
            $skpdNama = $this->normalizeImportText((string) $skpd->nama_skpd);
            $skpdCanonical = str_replace('pengolahan', 'pengelolahan', $skpdNama);

            if ($skpdNama === $target || $skpdCanonical === $canonicalTarget) {
                return $skpd;
            }
        }

        return null;
    }

    /**
     * Cari id_ruangan berdasarkan nama untuk SKPD terpilih.
     * Mengembalikan id (int), false bila tidak ketemu / ambigu / tidak sesuai lokasi SKPD.
     * Mendukung toleransi spasi ganda, case-insensitive, dan nama tanpa teks tanda kurung.
     */
    private function resolveRuanganImport(int $idSkpd, string $nama): int|false
    {
        $target = $this->normalizeImportText($nama);
        if ($target === '') {
            return false;
        }

        $lokasiId = Lokasi::untukSkpd($idSkpd)?->id_lokasi;

        $candidates = Ruangan::query()
            ->where(function ($q) use ($lokasiId) {
                $q->whereNull('id_skpd');
                if ($lokasiId !== null) {
                    $q->orWhere('id_lokasi', $lokasiId);
                }
            })
            ->get(['id_ruangan', 'nama_ruangan', 'id_skpd', 'id_lokasi']);

        // 1. Pencocokan eksak (setelah normalisasi spasi ganda dan lowercase)
        $exactMatches = $candidates->filter(
            fn ($r) => $this->normalizeImportText((string) $r->nama_ruangan) === $target
        )->values();

        if ($exactMatches->count() === 1) {
            return (int) $exactMatches->first()->id_ruangan;
        }

        if ($exactMatches->count() > 1) {
            // Bila terdapat nama ruangan kembar di lokasi yang sama, utamakan yang terikat langsung ke SKPD ini
            $spesifik = $exactMatches->filter(fn ($r) => (int) $r->id_skpd === $idSkpd);
            if ($spesifik->count() === 1) {
                return (int) $spesifik->first()->id_ruangan;
            }

            return false; // Ambigu
        }

        // 2. Fallback: pencocokan terhadap nama ruangan yang dibersihkan dari teks di dalam tanda kurung
        $strippedMatches = $candidates->filter(function ($r) use ($target) {
            $cleanName = preg_replace('/\s*\([^)]*\)/', '', (string) $r->nama_ruangan);

            return $this->normalizeImportText($cleanName) === $target;
        })->values();

        if ($strippedMatches->count() === 1) {
            return (int) $strippedMatches->first()->id_ruangan;
        }

        if ($strippedMatches->count() > 1) {
            $spesifik = $strippedMatches->filter(fn ($r) => (int) $r->id_skpd === $idSkpd);
            if ($spesifik->count() === 1) {
                return (int) $spesifik->first()->id_ruangan;
            }

            return false; // Ambigu
        }

        return false;
    }

    private static function streamXlsx(Spreadsheet $spreadsheet, string $filename)
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static fn () => $writer->save('php://output'),
            $filename
        );
    }

    /**
     * Potret data pegawai (tanpa kredensial) untuk timeline riwayat.
     *
     * @return array<string, mixed>
     */
    private function snapshot(?Pegawai $pegawai): array
    {
        if (! $pegawai) {
            return [];
        }

        $pegawai->loadMissing('skpd', 'ruangan', 'user');

        return [
            'nama_pegawai' => $pegawai->nama_pegawai,
            'nip' => $pegawai->nip,
            'jabatan' => $pegawai->jabatan,
            'skpd' => $pegawai->skpd?->nama_skpd,
            'lokasi' => Lokasi::untukSkpd($pegawai->id_skpd)?->nama_lokasi,
            'ruangan' => $pegawai->ruangan?->nama_ruangan,
        ];
    }

    /**
     * @param  array<int, string>  $changed
     */
    private function describe(array $changed, string $prefix): string
    {
        if (empty($changed)) {
            return $prefix.' tanpa perubahan data berarti.';
        }

        $parts = array_map(
            fn (string $key) => self::FIELD_LABELS[$key] ?? $key,
            $changed
        );

        return $prefix.': '.implode(', ', $parts).'.';
    }

    private function berhasil(string $message, ?string $redirectTo = null)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect($redirectTo ?? route('master-data.pegawai.index'))
            ->with('success', $message);
    }
}
