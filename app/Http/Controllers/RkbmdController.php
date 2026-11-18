<?php

namespace App\Http\Controllers;

use App\Models\ArsipRkbmd;
use App\Models\Aset;
use App\Models\MasterKegiatanRkbmd;
use App\Models\MasterKodeBarangRkbmd;
use App\Models\Skpd;
use App\Models\UsulanRkbmd;
use App\Services\RkbmdExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RkbmdController extends Controller
{
    const JENIS = ['Pengadaan', 'Pemeliharaan', 'Penghapusan', 'Pemindahtanganan', 'Pemanfaatan'];

    /**
     * Pemetaan kode pendek (parameter export) ke nilai `jenis_usulan`.
     * Sumber tunggal agar dropdown export, filter, dan label file konsisten.
     */
    const JENIS_KODE = [
        'pengadaan' => 'Pengadaan',
        'pemeliharaan' => 'Pemeliharaan',
        'penghapusan' => 'Penghapusan',
        'pemindahtanganan' => 'Pemindahtanganan',
        'pemanfaatan' => 'Pemanfaatan',
    ];

    const SATUAN = ['Unit', 'Paket', 'Set', 'Buah'];

    const STATUS = ['Draft', 'Diajukan', 'Disetujui Pengurus Barang', 'Ditolak'];

    /**
     * Disk PRIVAT untuk file arsip RKBMD (tidak dapat diakses langsung web).
     */
    const DISK_ARSIP = 'arsip_rkbmd';

    /**
     * Disk lama tempat arsip disimpan sebelum perbaikan keamanan (PUBLIK).
     * Masih dibaca sebagai fallback supaya file lama tetap bisa diunduh lewat
     * endpoint terproteksi. Tidak ada file lama yang dipindah/dihapus otomatis.
     */
    const DISK_ARSIP_LAMA = 'public';

    /**
     * Admin Aset / Super Admin berperan sebagai "Pengurus Barang" yang menyetujui/menolak usulan.
     */
    private function isAdmin(): bool
    {
        $role = auth()->user()?->role?->nama_role;

        return in_array($role, ['Admin Aset', 'Super Admin'], true);
    }

    /**
     * SKPD milik user pengusul (Bidang/UPT). Null untuk admin.
     */
    private function userSkpd(): ?Skpd
    {
        $pegawai = auth()->user()?->pegawai;

        return $pegawai?->skpd;
    }

    /**
     * Cross-check SKPD: user non-admin hanya boleh menyentuh usulan milik
     * SKPD-nya sendiri (mencegah manipulasi lintas unit via URL/id meski
     * pengecekan owner `id_pegawai` sudah ada).
     */
    private function authorizeSkpdUsulan(UsulanRkbmd $usulan): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $mySkpd = $this->userSkpd();

        abort_unless(
            $mySkpd !== null && (int) $usulan->id_skpd === (int) $mySkpd->id_skpd,
            403,
            'Anda tidak memiliki hak akses untuk usulan milik unit lain.'
        );
    }

    /**
     * Query dasar usulan dengan seluruh filter (daftar + ekspor).
     */
    private function applyFilters(Request $request)
    {
        $tahun = (int) $request->query('tahun');
        $jenis = $request->query('jenis');
        $bidang = (array) $request->query('bidang', []);
        $bidang = array_values(array_filter(array_map('intval', $bidang), fn ($x) => $x > 0));

        $query = UsulanRkbmd::with(['skpd', 'pegawai']);

        $isAdmin = $this->isAdmin();
        $currentPegawaiId = auth()->user()?->pegawai?->id_pegawai;

        if (! $isAdmin) {
            $mySkpd = $this->userSkpd();
            if ($mySkpd) {
                $query->where('id_skpd', $mySkpd->id_skpd);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Visibilitas Draft (Opsi A):
        // 1. Usulan berstatus Draft hanya boleh terlihat oleh pengusul (pegawai) yang membuatnya.
        // 2. Berlaku untuk seluruh peran: Super Admin, Admin Aset, Admin Bidang, maupun Admin UPT
        //    dapat melihat Draf yang dibuat oleh dirinya sendiri.
        // 3. Tidak ada pengguna yang dapat melihat Draf milik pengguna lain (termasuk antar-Admin Aset).
        // 4. Usulan berstatus selain Draft (Diajukan, Disetujui Pengurus Barang, Ditolak)
        //    tetap mengikuti aturan visibilitas dan cakupan SKPD yang berlaku.
        $query->where(function ($q) use ($currentPegawaiId) {
            $q->where('status_usulan', '!=', 'Draft');
            if ($currentPegawaiId) {
                $q->orWhere(function ($sub) use ($currentPegawaiId) {
                    $sub->where('status_usulan', 'Draft')
                        ->where('id_pegawai', $currentPegawaiId);
                });
            }
        });

        return $query
            ->when($tahun, fn ($q) => $q->where('tahun_anggaran', $tahun))
            // Hanya jenis nilai tampilan (mis. 'Pengadaan') yang disaring di
            // sini; kode pendek ekspor (mis. 'pemanfaatan') ditangani export()
            // via petaJenis agar tidak terjadi filter ganda (0 baris).
            ->when(in_array($jenis, self::JENIS, true), fn ($q) => $q->where('jenis_usulan', $jenis))
            ->when(count($bidang), fn ($q) => $q->whereIn('id_skpd', $bidang));
    }

    /**
     * Daftar usulan RKBMD (role-aware + filter Tahun/Jenis/Bidang).
     */
    public function index(Request $request)
    {
        $isAdmin = $this->isAdmin();

        $usulanList = $this->applyFilters($request)
            ->orderByDesc('id_usulan')
            ->paginate(10)
            ->withQueryString();

        // Tahun anggaran yang tersedia (data) + tahun berjalan + tahun depan.
        $tahunList = UsulanRkbmd::whereNotNull('tahun_anggaran')
            ->distinct()
            ->orderByDesc('tahun_anggaran')
            ->pluck('tahun_anggaran')
            ->merge([now()->year, now()->year + 1])
            ->unique()
            ->sortDesc()
            ->values();

        $bidangOptions = Skpd::orderBy('id_skpd')->get(['id_skpd', 'nama_skpd']);
        $jenisList = self::JENIS;
        $satuanList = self::SATUAN;

        // Master Data RKBMD untuk form usulan
        $programList = MasterKegiatanRkbmd::daftarProgram();
        $kegiatanMaster = MasterKegiatanRkbmd::orderBy('nama_program')
            ->orderBy('nama_sub_kegiatan')
            ->get(['id_kegiatan', 'bidang_urusan', 'nama_program', 'nama_sub_kegiatan']);

        $mySkpd = $isAdmin ? null : $this->userSkpd();

        // Arsip dokumen juga di-scope per unit untuk non-admin; tanpa SKPD
        // sah -> nol baris (fail closed), konsisten dengan applyFilters().
        $arsipList = ArsipRkbmd::with('skpd')
            ->when(! $isAdmin, function ($q) use ($mySkpd) {
                $mySkpd
                    ? $q->where('id_skpd', $mySkpd->id_skpd)
                    : $q->whereRaw('1 = 0');
            })
            ->orderByDesc('id_arsip')
            ->get();

        $activeBidang = array_map('intval', $request->query('bidang', []));

        return view('rkbmd.index', compact(
            'usulanList',
            'tahunList',
            'bidangOptions',
            'jenisList',
            'satuanList',
            'arsipList',
            'isAdmin',
            'mySkpd',
            'activeBidang',
            'programList',
            'kegiatanMaster',
        ));
    }

    /**
     * Pencarian master kode barang (JSON) untuk Select2 pada form usulan RKBMD.
     *
     * Sumber data: tabel `master_kode_barang_rkbmd` (12.307 data unik).
     * Mendukung pencarian server-side berdasarkan `kode_barang` atau `nama_barang`
     * dengan pagination 25 record per halaman (infinite scroll Select2).
     */
    public function searchMasterBarang(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        $query = MasterKodeBarangRkbmd::query();

        if ($keyword !== '') {
            $query->cari($keyword);
        }

        $paginator = $query->orderBy('kode_barang')
            ->simplePaginate($perPage, ['id_master_barang', 'kode_barang', 'nama_barang'], 'page', $page);

        $results = $paginator->map(function ($item) {
            $kode = (string) $item->kode_barang;
            $nama = (string) $item->nama_barang;

            return [
                'id' => $kode,
                'text' => $kode . ' - ' . $nama,
                'kode_barang' => $kode,
                'nama_barang' => $nama,
            ];
        })->values();

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Simpan usulan baru (pengusul = SKPD user, atau admin memilih bidang).
     */
    public function store(Request $request)
    {
        $isAdmin = $this->isAdmin();

        $data = $this->validateUsulan($request, $isAdmin);

        if ($isAdmin) {
            $data['id_skpd'] = $request->input('id_skpd');
        } else {
            $mySkpd = $this->userSkpd();
            abort_unless($mySkpd, 403, 'Akun Anda tidak terhubung ke unit/SKPD manapun.');
            $data['id_skpd'] = $mySkpd->id_skpd;
        }

        $data = array_merge($data, [
            'id_pegawai' => auth()->user()?->pegawai?->id_pegawai,
            'status_usulan' => 'Draft',
            'tanggal_usulan' => now()->toDateString(),
        ]);

        DB::transaction(function () use ($data) {
            return UsulanRkbmd::create($data);
        });

        return back()->with('success', 'Usulan RKBMD berhasil disimpan sebagai Draft.');
    }

    /**
     * Detail satu usulan RKBMD.
     * Dapat diakses oleh Super Admin, Admin Bidang, dan Admin UPT
     * dengan pembatasan cakupan SKPD yang ditegakkan di backend.
     */
    public function show(Request $request, UsulanRkbmd $usulan)
    {
        // Visibilitas Draft: HANYA boleh dibuka oleh pengusul pemilik draft
        if ($usulan->status_usulan === 'Draft') {
            $currentPegawaiId = auth()->user()?->pegawai?->id_pegawai;
            abort_unless(
                $currentPegawaiId && $usulan->id_pegawai === $currentPegawaiId,
                403,
                'Usulan draf hanya dapat diakses oleh pengusul yang membuatnya.'
            );
        }

        $this->authorizeSkpdUsulan($usulan);

        $usulan->load(['skpd', 'pegawai', 'approver', 'kegiatan']);

        $isAdmin = $this->isAdmin();
        $canDecide = $isAdmin && $usulan->status_usulan === 'Diajukan';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'usulan' => $usulan,
                'can_decide' => $canDecide,
            ]);
        }

        return view('rkbmd.show', compact('usulan', 'isAdmin', 'canDecide'));
    }

    /**
     * Perbarui usulan (pemilik selama belum dikunci, atau admin).
     */
    public function update(Request $request, UsulanRkbmd $usulan)
    {
        $isAdmin = $this->isAdmin();

        $this->authorizeUpdate($usulan, $isAdmin);

        $data = $this->validateUsulan($request, $isAdmin, $usulan);

        if ($isAdmin) {
            $data['id_skpd'] = $request->input('id_skpd');
        }

        DB::transaction(function () use ($usulan, $data) {
            $usulan->update($data);
        });

        return back()->with('success', 'Usulan RKBMD berhasil diperbarui.');
    }

    /**
     * Kirim usulan (Draft -> Diajukan), hanya oleh pemilik.
     */
    public function submit(UsulanRkbmd $usulan)
    {
        $this->authorizeSkpdUsulan($usulan);
        abort_unless($usulan->id_pegawai === auth()->user()?->pegawai?->id_pegawai, 403);
        abort_unless($usulan->status_usulan === 'Draft', 422, 'Hanya usulan berstatus Draft yang dapat diajukan.');

        $usulan->update(['status_usulan' => 'Diajukan']);

        return back()->with('success', 'Usulan diajukan dan menunggu verifikasi Pengurus Barang.');
    }

    /**
     * Setujui / tolak usulan (khusus Admin Aset / Pengurus Barang).
     * Hanya usulan berstatus Diajukan yang dapat disetujui atau ditolak.
     */
    public function decide(Request $request, UsulanRkbmd $usulan)
    {
        abort_unless($this->isAdmin(), 403, 'Hanya Super Admin / Admin Aset yang berwenang menyetujui atau menolak usulan.');
        abort_unless($usulan->status_usulan === 'Diajukan', 422, 'Hanya usulan berstatus Diajukan yang dapat diverifikasi.');

        $data = $request->validate([
            'keputusan' => ['required', 'in:setuju,tolak'],
            'catatan_pengurus' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['keputusan'] === 'tolak') {
            abort_unless(trim((string) ($data['catatan_pengurus'] ?? '')), 422, 'Alasan penolakan wajib diisi.');
        }

        $usulan->update([
            'status_usulan' => $data['keputusan'] === 'setuju' ? 'Disetujui Pengurus Barang' : 'Ditolak',
            'catatan_pengurus' => $data['catatan_pengurus'] ?? null,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $pesan = $data['keputusan'] === 'setuju'
            ? 'Usulan disetujui sebagai usulan resmi RKBMD.'
            : 'Usulan ditolak. Catatan telah dikirim ke pengusul.';

        return back()->with('success', $pesan);
    }

    /**
     * Hapus / Batalkan usulan:
     * - Status Draf: aksi "Batalkan Usulan" oleh pengusul pemilik draf.
     * - Status Diajukan: tidak dapat dihapus oleh siapapun.
     * - Status Diterima (Disetujui Pengurus Barang) / Ditolak: hanya Super Admin / Admin Aset.
     */
    public function destroy(UsulanRkbmd $usulan)
    {
        $isAdmin = $this->isAdmin();
        $isOwner = $usulan->id_pegawai && $usulan->id_pegawai === auth()->user()?->pegawai?->id_pegawai;

        // 1. Status Draf: aksi "Batalkan Usulan" oleh pengusul pemilik draf
        if ($usulan->status_usulan === 'Draft') {
            abort_unless($isOwner, 403, 'Hanya pengusul yang berwenang membatalkan usulan draf ini.');
            $usulan->delete();

            return back()->with('success', 'Usulan draf berhasil dibatalkan.');
        }

        // 2. Status Diajukan: tidak dapat dihapus oleh siapapun
        if ($usulan->status_usulan === 'Diajukan') {
            abort(422, 'Usulan yang sedang diajukan tidak dapat dihapus.');
        }

        // 3. Status Diterima ('Disetujui Pengurus Barang') atau Ditolak:
        // Hanya Super Admin / Admin Aset yang berwenang menghapus usulan
        if (in_array($usulan->status_usulan, ['Disetujui Pengurus Barang', 'Ditolak'], true)) {
            abort_unless($isAdmin, 403, 'Hanya Super Admin / Admin Aset yang berwenang menghapus usulan ini.');
            $usulan->delete();

            return back()->with('success', 'Usulan RKBMD berhasil dihapus.');
        }

        abort(422, 'Status usulan tidak dapat dihapus.');
    }

    /**
     * Ekspor usulan RKBMD berbasis 5 template resmi Excel.
     * Hak akses: Admin Aset, Super Admin, Admin Bidang, Admin UPT.
     * Menggunakan template resmi tunggal per jenis usulan, pengelompokan 5 field, dan format dinamis.
     */
    public function export(Request $request)
    {
        $role = auth()->user()?->role?->nama_role;
        $allowedRoles = ['Admin Aset', 'Super Admin', 'Admin Bidang', 'Admin UPT'];
        abort_unless(in_array($role, $allowedRoles, true), 403, 'Akses ditolak. Fitur Ekspor RKBMD hanya dapat diakses oleh Admin Aset, Admin Bidang, atau Admin UPT.');

        $isAdmin = $this->isAdmin();
        $query = UsulanRkbmd::with(['skpd', 'pegawai', 'kegiatan']);

        // Scope data berdasarkan peran
        if ($isAdmin) {
            $bidangIds = (array) $request->query('bidang', []);
            $bidangIds = array_values(array_filter(array_map('intval', $bidangIds), fn ($x) => $x > 0));
            if (count($bidangIds) > 0) {
                $query->whereIn('id_skpd', $bidangIds);
            }
        } else {
            $mySkpd = $this->userSkpd();
            abort_unless($mySkpd, 403, 'Akun Anda tidak terhubung ke unit/SKPD manapun.');
            // Kunci SKPD untuk non-admin, abaikan manipulasi parameter bidang pada URL
            $query->where('id_skpd', $mySkpd->id_skpd);
            $bidangIds = [$mySkpd->id_skpd];
        }

        // Seluruh usulan berstatus Draft tidak boleh ikut diekspor
        $query->where('status_usulan', '!=', 'Draft');

        // Filter Tahun Anggaran
        $tahun = (int) $request->query('tahun');
        if ($tahun > 0) {
            $query->where('tahun_anggaran', $tahun);
        }

        // Filter Jenis Usulan (pilihan salah satu dari 5 template resmi)
        $jenisParam = (string) $request->query('jenis', 'pengadaan');
        $jenisKey = strtolower(trim($jenisParam));
        if (isset(self::JENIS_KODE[$jenisKey])) {
            $query->where('jenis_usulan', self::JENIS_KODE[$jenisKey]);
        } elseif (in_array($jenisParam, self::JENIS, true)) {
            $key = array_search($jenisParam, self::JENIS_KODE, true);
            $jenisKey = $key !== false ? $key : 'pengadaan';
            $query->where('jenis_usulan', $jenisParam);
        } else {
            $jenisKey = 'pengadaan';
            $query->where('jenis_usulan', 'Pengadaan');
        }

        // Filter Status Usulan (jika difilter spesifik)
        $statusKode = $request->query('status');
        if ($statusKode === 'disetujui') {
            $query->where('status_usulan', 'Disetujui Pengurus Barang');
        } elseif ($statusKode === 'diajukan') {
            $query->where('status_usulan', 'Diajukan');
        } elseif ($statusKode === 'ditolak') {
            $query->where('status_usulan', 'Ditolak');
        }

        $rows = $query->orderBy('id_usulan')->get();

        try {
            $exportService = app(RkbmdExportService::class);
            $filterSkpd = ($isAdmin && count($bidangIds) === 1) ? Skpd::find($bidangIds[0]) : null;
            $spreadsheet = $exportService->generateSpreadsheet(
                $rows,
                $jenisKey,
                auth()->user(),
                $filterSkpd,
                $tahun > 0 ? $tahun : null
            );

            $filename = $exportService->getExportFilename($jenisKey, $tahun > 0 ? $tahun : null);

            return self::streamXlsx($spreadsheet, $filename);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Membangun berkas Excel komprehensif 31 kolom dengan header berjenjang 2 baris
     * dan format teks presisi untuk identitas kode barang & NIP (tanpa ID usulan).
     */
    private function buildExportSpreadsheet(Collection $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Usulan RKBMD');

        // Baris 1: Header Grup Kategori (Merged, total 31 kolom A-AE)
        $groups = [
            'A1:H1' => 'A. IDENTITAS USULAN',
            'I1:L1' => 'B. PROGRAM & KEGIATAN RKBMD',
            'M1:Q1' => 'C. RINCIAN BARANG YANG DIUSULKAN',
            'R1:T1' => 'D. ANALISIS KEBUTUHAN',
            'U1:Z1' => 'E. BARANG YANG DAPAT DIOPTIMALKAN',
            'AA1:AE1' => 'F. KETERANGAN & VERIFIKASI',
        ];

        foreach ($groups as $range => $label) {
            $sheet->mergeCells($range);
            $startCell = explode(':', $range)[0];
            $sheet->setCellValue($startCell, $label);
        }

        // Baris 2: Sub-Header Kolom Spesifik (31 Kolom)
        $subHeaders = [
            'A2' => 'No',
            'B2' => 'Bidang / Unit Kerja',
            'C2' => 'Nama Pengusul',
            'D2' => 'NIP Pengusul',
            'E2' => 'Tahun Anggaran',
            'F2' => 'Jenis Usulan',
            'G2' => 'Tanggal Pengajuan',
            'H2' => 'Status Usulan',
            'I2' => 'Bidang Urusan',
            'J2' => 'Nama Program',
            'K2' => 'Nama Sub Kegiatan',
            'L2' => 'Program / Kegiatan Gabungan',
            'M2' => 'Kode Barang',
            'N2' => 'Nama Barang',
            'O2' => 'Spesifikasi Teknis',
            'P2' => 'Jumlah Usulan',
            'Q2' => 'Satuan Usulan',
            'R2' => 'Kebutuhan Maksimum',
            'S2' => 'Kebutuhan Riil',
            'T2' => 'Satuan Kebutuhan',
            'U2' => 'Status Optimalisasi',
            'V2' => 'Kode Barang Lama',
            'W2' => 'Nama Barang Lama',
            'X2' => 'Jumlah Optimalisasi',
            'Y2' => 'Satuan Optimalisasi',
            'Z2' => 'Keterangan Optimalisasi',
            'AA2' => 'Alasan Kebutuhan',
            'AB2' => 'Catatan Pengurus Barang',
            'AC2' => 'Alasan Penolakan',
            'AD2' => 'Petugas Verifikasi',
            'AE2' => 'Waktu Verifikasi',
        ];

        foreach ($subHeaders as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Styling Baris 1 (Header Grup Kategori)
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getStyle('A1:AE1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '047857'], // Emerald Disbun
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Styling Baris 2 (Header Kolom Rinci)
        $sheet->getRowDimension(2)->setRowHeight(28);
        $sheet->getStyle('A2:AE2')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '1E293B'], // Slate-800
                'size' => 9,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'ECFDF5'], // Emerald-50
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Data Rows (Mulai dari Baris 3)
        $rowNum = 3;
        foreach ($rows as $i => $usulan) {
            $sheet->setCellValue("A{$rowNum}", $i + 1);
            $sheet->setCellValue("B{$rowNum}", $usulan->skpd?->nama_skpd ?? '-');
            $sheet->setCellValue("C{$rowNum}", $usulan->pegawai?->nama_pegawai ?? '-');
            $sheet->setCellValueExplicit("D{$rowNum}", (string) ($usulan->pegawai?->nip ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValue("E{$rowNum}", (int) $usulan->tahun_anggaran);
            $sheet->setCellValue("F{$rowNum}", (string) $usulan->jenis_usulan);
            $sheet->setCellValue("G{$rowNum}", $usulan->tanggal_usulan ? $usulan->tanggal_usulan->format('d/m/Y') : '-');

            $statusLabel = match ($usulan->status_usulan) {
                'Draft' => 'Draf',
                'Diajukan' => 'Diajukan',
                'Disetujui Pengurus Barang' => 'Diterima',
                'Ditolak' => 'Ditolak',
                default => $usulan->status_usulan ?: '-',
            };
            $sheet->setCellValue("H{$rowNum}", $statusLabel);

            $sheet->setCellValue("I{$rowNum}", $usulan->bidang_urusan ?: ($usulan->kegiatan?->bidang_urusan ?: '-'));
            $sheet->setCellValue("J{$rowNum}", $usulan->nama_program ?: ($usulan->kegiatan?->nama_program ?: '-'));
            $sheet->setCellValue("K{$rowNum}", $usulan->nama_sub_kegiatan ?: ($usulan->kegiatan?->nama_sub_kegiatan ?: '-'));
            $sheet->setCellValue("L{$rowNum}", $usulan->program_kegiatan ?: '-');

            $sheet->setCellValueExplicit("M{$rowNum}", (string) ($usulan->kode_barang ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue("N{$rowNum}", (string) ($usulan->nama_barang ?? ''));
            $sheet->setCellValue("O{$rowNum}", $usulan->spesifikasi ?: '-');
            $sheet->setCellValue("P{$rowNum}", $usulan->jumlah !== null ? (int) $usulan->jumlah : '');
            $sheet->setCellValue("Q{$rowNum}", (string) ($usulan->satuan ?? ''));

            $sheet->setCellValue("R{$rowNum}", $usulan->kebutuhan_maksimum !== null ? (int) $usulan->kebutuhan_maksimum : '-');
            $sheet->setCellValue("S{$rowNum}", $usulan->kebutuhan_riil !== null ? (int) $usulan->kebutuhan_riil : '-');
            $sheet->setCellValue("T{$rowNum}", (string) ($usulan->satuan ?? ''));

            // Barang Optimalisasi
            $opt = $usulan->barang_optimalisasi;
            $adaOpt = is_array($opt) && (!empty($opt['nama']) || !empty($opt['kode']) || !empty($opt['jumlah']));
            $sheet->setCellValue("U{$rowNum}", $adaOpt ? 'Ada Optimalisasi' : 'Tidak Ada');
            $sheet->setCellValueExplicit("V{$rowNum}", (string) ($adaOpt && !empty($opt['kode']) ? $opt['kode'] : '-'), DataType::TYPE_STRING);
            $sheet->setCellValue("W{$rowNum}", $adaOpt && !empty($opt['nama']) ? $opt['nama'] : '-');
            $sheet->setCellValue("X{$rowNum}", $adaOpt && isset($opt['jumlah']) ? (int) $opt['jumlah'] : 0);
            $sheet->setCellValue("Y{$rowNum}", $adaOpt && !empty($opt['satuan']) ? $opt['satuan'] : ($adaOpt ? $usulan->satuan : '-'));
            $sheet->setCellValue("Z{$rowNum}", $adaOpt ? 'Dioptimalkan untuk usulan ini' : '-');

            // Keterangan & Verifikasi (Catatan Pengurus vs Alasan Penolakan dipisahkan)
            $sheet->setCellValue("AA{$rowNum}", $usulan->alasan_kebutuhan ?: '-');
            $sheet->setCellValue("AB{$rowNum}", $usulan->status_usulan !== 'Ditolak' ? ($usulan->catatan_pengurus ?: '-') : '-');
            $sheet->setCellValue("AC{$rowNum}", $usulan->status_usulan === 'Ditolak' ? ($usulan->catatan_pengurus ?: 'Ditolak tanpa catatan') : '-');
            $sheet->setCellValue("AD{$rowNum}", $usulan->approver?->namaLengkap() ?? $usulan->approver?->username ?? '-');
            $sheet->setCellValue("AE{$rowNum}", $usulan->approved_at ? $usulan->approved_at->format('d/m/Y H:i') . ' WIB' : '-');

            $rowNum++;
        }

        $lastRow = max(2, $rowNum - 1);

        // Alignment per kolom
        $centerCols = ['A', 'D', 'E', 'G', 'H', 'M', 'Q', 'T', 'U', 'V', 'Y', 'Z', 'AE'];
        foreach ($centerCols as $col) {
            $sheet->getStyle("{$col}3:{$col}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $rightCols = ['P', 'R', 'S', 'X'];
        foreach ($rightCols as $col) {
            $sheet->getStyle("{$col}3:{$col}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $leftCols = ['B', 'C', 'F', 'I', 'J', 'K', 'L', 'N', 'O', 'W', 'AA', 'AB', 'AC', 'AD'];
        foreach ($leftCols as $col) {
            $sheet->getStyle("{$col}3:{$col}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // Wrap text untuk kolom teks panjang
        foreach (['O', 'AA', 'AB', 'AC'] as $col) {
            $sheet->getStyle("{$col}3:{$col}{$lastRow}")->getAlignment()->setWrapText(true);
        }

        // Border tipis ke seluruh sel tabel
        $sheet->getStyle("A1:AE{$lastRow}")->applyFromArray([
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

        // Auto-size kolom A sampai AE
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE'];
        foreach ($cols as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Freeze pane pada A3 agar baris header tetap terlihat saat scroll
        $sheet->freezePane('A3');

        return $spreadsheet;
    }

    /**
     * Label jenis usulan untuk nama file export.
     */
    private static function labelJenis(?string $kode): string
    {
        return self::JENIS_KODE[$kode] ?? 'Semua';
    }

    /**
     * Label bidang/SKPD untuk nama file export.
     */
    private function labelBidang($kode): string
    {
        if (! $kode || $kode === 'all') {
            return 'Semua-Bidang';
        }

        $skpd = Skpd::find((int) $kode);

        return $skpd ? self::slugify($skpd->nama_skpd) : (string) $kode;
    }

    private static function slugify(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9]+/', '-', trim($value));
    }

    /**
     * Upload arsip dokumen sah RKBMD (PDF/Scan SK bertanda tangan).
     *
     * Hak akses:
     * - Admin Aset / Super Admin: dapat mengunggah arsip secara global dan memilih SKPD tujuan.
     * - Admin Bidang: dapat mengunggah arsip hanya untuk SKPD Bidangnya sendiri.
     * - Admin UPT: dapat mengunggah arsip hanya untuk UPT-nya sendiri (P2BTP / PSBP terisolasi).
     * - Pegawai biasa: ditolak (HTTP 403).
     */
    public function storeArsip(Request $request)
    {
        $role = auth()->user()?->role?->nama_role;
        $allowedRoles = ['Admin Aset', 'Super Admin', 'Admin Bidang', 'Admin UPT'];
        abort_unless(in_array($role, $allowedRoles, true), 403, 'Akses ditolak. Anda tidak memiliki izin untuk mengunggah arsip dokumen RKBMD.');

        $isAdmin = $this->isAdmin();

        if ($isAdmin) {
            $validatedSkpd = $request->validate([
                'id_skpd' => ['required', 'exists:skpd,id_skpd'],
            ]);
            $idSkpd = (int) $validatedSkpd['id_skpd'];
        } else {
            $mySkpd = $this->userSkpd();
            abort_unless($mySkpd, 403, 'Akun Anda tidak terhubung ke unit/SKPD manapun.');

            // Jangan percaya nilai id_skpd dari request non-admin.
            // Bila klien mengirimkan id_skpd yang berbeda dari SKPD-nya, tolak manipulasi dengan HTTP 403.
            if ($request->filled('id_skpd') && (int) $request->input('id_skpd') !== (int) $mySkpd->id_skpd) {
                abort(403, 'Akses ditolak. Anda hanya diizinkan mengunggah arsip untuk unit kerja Anda sendiri.');
            }

            $idSkpd = (int) $mySkpd->id_skpd;
        }

        $data = $request->validate([
            'tahun_anggaran' => ['required', 'integer', 'between:2000,2100'],
            'jenis_dokumen' => ['required', 'in:'.implode(',', ArsipRkbmd::JENIS_DOKUMEN)],
            'nama_dokumen' => ['nullable', 'string', 'max:255'],
            'tanggal_pengesahan' => ['nullable', 'date'],
            'file_dokumen' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $data['id_skpd'] = $idSkpd;

        // Disimpan di disk PRIVAT: file tidak dapat diakses langsung dari web.
        $data['file_path'] = $request->file('file_dokumen')->store('arsip-rkbmd', self::DISK_ARSIP);
        $data['uploaded_by'] = auth()->id();

        ArsipRkbmd::create($data);

        return back()->with('success', 'Arsip dokumen RKBMD berhasil diunggah.');
    }

    /**
     * Otorisasi akses file arsip berdasarkan record + cakupan SKPD pengguna.
     *
     * Sumber kebenaran TUNGGAL untuk preview & download:
     *   - Admin Aset   -> semua arsip,
     *   - non-admin    -> hanya arsip dengan `id_skpd` = SKPD user (dari
     *                     relasi `pegawai`), selain itu DITOLAK,
     *   - tanpa SKPD   -> DITOLAK (fail closed).
     *
     * Dipanggil untuk SETIAP request file; bukan hanya filter daftar/Blade.
     */
    private function authorizeArsip(ArsipRkbmd $arsip): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $mySkpd = $this->userSkpd();

        abort_if($mySkpd === null, 403, 'Anda tidak memiliki hak akses untuk arsip dokumen ini.');

        abort_unless(
            $arsip->id_skpd !== null && (int) $arsip->id_skpd === (int) $mySkpd->id_skpd,
            403,
            'Anda tidak memiliki hak akses untuk arsip dokumen milik unit lain.'
        );
    }

    /**
     * Ambil file arsip dalam bentuk respons HTTP stream.
     *
     * $disposition: 'inline' (pratinjau) atau 'attachment' (unduh).
     * Nama file di disk SELALU diambil dari record database — tidak pernah
     * dari parameter request.
     */
    private function streamArsip(ArsipRkbmd $arsip, string $disposition)
    {
        // Path dari DB, bukan dari request (cegah path traversal).
        $path = basename((string) $arsip->file_path);

        if ($path === '' || $path === '.') {
            abort(404, 'Berkas arsip tidak tersedia.');
        }

        $disk = null;

        // Utamakan disk privat; file lama (belum dimigrasi) masih di disk publik.
        if (Storage::disk(self::DISK_ARSIP)->exists('arsip-rkbmd/'.$path)) {
            $disk = self::DISK_ARSIP;
        } elseif (Storage::disk(self::DISK_ARSIP_LAMA)->exists('arsip-rkbmd/'.$path)) {
            $disk = self::DISK_ARSIP_LAMA;
        }

        abort_if($disk === null, 404, 'Berkas arsip tidak ditemukan di penyimpanan.');

        $namaUnduh = $this->namaFileArsip($arsip);

        return Storage::disk($disk)->response(
            'arsip-rkbmd/'.$path,
            $namaUnduh,
            ['Content-Type' => 'application/pdf'],
            $disposition
        );
    }

    /**
     * Nama file yang ditampilkan saat diunduh (aman, tanpa karakter path).
     */
    private function namaFileArsip(ArsipRkbmd $arsip): string
    {
        $dasar = $arsip->nama_dokumen ?: 'Arsip-RKBMD';
        $bersih = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $dasar), '-');

        if ($bersih === '') {
            $bersih = 'Arsip-RKBMD';
        }

        $tahun = $arsip->tahun_anggaran ? '-'.$arsip->tahun_anggaran : '';

        return $bersih.$tahun.'.pdf';
    }

    /**
     * Pratinjau PDF arsip (inline) — melewati otorisasi role + SKPD.
     */
    public function previewArsip(ArsipRkbmd $arsip)
    {
        $this->authorizeArsip($arsip);

        return $this->streamArsip($arsip, 'inline');
    }

    /**
     * Unduh PDF arsip (attachment) — melewati otorisasi role + SKPD.
     */
    public function downloadArsip(ArsipRkbmd $arsip)
    {
        $this->authorizeArsip($arsip);

        return $this->streamArsip($arsip, 'attachment');
    }

    /**
     * Penghapusan arsip dokumen dinonaktifkan:
     * Seluruh pengguna (Super Admin, Admin Aset, Admin Bidang, Admin UPT)
     * tidak diperbolehkan menghapus arsip dokumen RKBMD yang telah diunggah.
     */
    public function destroyArsip(ArsipRkbmd $arsip)
    {
        abort(403, 'Arsip dokumen RKBMD yang telah diunggah bersifat permanen dan tidak dapat dihapus.');
    }

    /**
     * Hapus file fisik arsip dari disk privat DAN disk publik (file lama).
     */
    private function hapusFileArsip(ArsipRkbmd $arsip): void
    {
        $path = basename((string) $arsip->file_path);

        if ($path === '' || $path === '.') {
            return;
        }

        foreach ([self::DISK_ARSIP, self::DISK_ARSIP_LAMA] as $disk) {
            $full = 'arsip-rkbmd/'.$path;

            if (Storage::disk($disk)->exists($full)) {
                Storage::disk($disk)->delete($full);
            }
        }
    }

    private function validateUsulan(Request $request, bool $isAdmin, ?UsulanRkbmd $usulan = null): array
    {
        $isLegacyUsulan = $usulan && $usulan->id_kegiatan === null;

        $idKegiatanRules = ['integer', 'exists:master_kegiatan_rkbmd,id_kegiatan'];
        if ($isLegacyUsulan && ! $request->filled('id_kegiatan')) {
            array_unshift($idKegiatanRules, 'nullable');
        } else {
            array_unshift($idKegiatanRules, 'required');
        }

        $rules = [
            'tahun_anggaran' => ['required', 'integer', 'between:2000,2100'],
            'jenis_usulan' => ['required', 'in:'.implode(',', self::JENIS)],
            // Master Kegiatan terintegrasi: id_kegiatan wajib valid untuk usulan baru
            'id_kegiatan' => $idKegiatanRules,
            'program_kegiatan' => ['nullable', 'string', 'max:500'],
            'nama_barang' => ['nullable', 'string', 'max:255'],
            // Kode Barang opsional; diisi otomatis jika memilih master barang.
            'kode_barang' => ['nullable', 'string', 'max:50'],
            'spesifikasi' => ['nullable', 'string', 'max:1000'],
            'jumlah' => ['nullable', 'integer', 'min:1'],
            'satuan' => ['nullable', 'string', 'max:50'],
            // Kebutuhan Maksimum & Riil opsional (bagian Optimalisasi).
            'kebutuhan_maksimum' => ['nullable', 'integer', 'min:0'],
            'kebutuhan_riil' => ['nullable', 'integer', 'min:0'],
            // Bagian "Barang yang Dapat Dioptimalkan" opsional seluruhnya.
            'opt_kode' => ['nullable', 'string', 'max:50'],
            'opt_nama' => ['nullable', 'string', 'max:255'],
            'opt_jumlah' => ['nullable', 'integer', 'min:1'],
            'opt_satuan' => ['nullable', 'string', 'max:50'],
            // Alasan kebutuhan / keterangan bersifat opsional.
            'alasan_kebutuhan' => ['nullable', 'string', 'max:2000'],
        ];

        if ($isAdmin) {
            $rules['id_skpd'] = ['required', 'exists:skpd,id_skpd'];
        }

        $data = $request->validate($rules);

        // Sumber kebenaran Master Kegiatan: ambil langsung dari database
        if (! empty($data['id_kegiatan'])) {
            $masterKegiatan = MasterKegiatanRkbmd::findOrFail($data['id_kegiatan']);
            $data['bidang_urusan'] = $masterKegiatan->bidang_urusan;
            $data['nama_program'] = $masterKegiatan->nama_program;
            $data['nama_sub_kegiatan'] = $masterKegiatan->nama_sub_kegiatan;
            $data['program_kegiatan'] = "{$masterKegiatan->bidang_urusan} - {$masterKegiatan->nama_program} - {$masterKegiatan->nama_sub_kegiatan}";
        } elseif ($isLegacyUsulan) {
            // Pertahankan nilai historis lama jika usulan lama diedit tanpa memilih master kegiatan baru
            $data['program_kegiatan'] = $data['program_kegiatan'] ?: $usulan->program_kegiatan;
            $data['id_kegiatan'] = null;
            $data['bidang_urusan'] = $usulan->bidang_urusan;
            $data['nama_program'] = $usulan->nama_program;
            $data['nama_sub_kegiatan'] = $usulan->nama_sub_kegiatan;
        }

        // Kemas "Barang yang Dapat Dioptimalkan" menjadi satu field JSON.
        $adaOpt = ($data['opt_kode'] ?? '') !== '' || ($data['opt_nama'] ?? '') !== '' || ($data['opt_jumlah'] ?? '') !== '';
        $data['barang_optimalisasi'] = $adaOpt ? [
            'kode' => $data['opt_kode'] ?? null,
            'nama' => $data['opt_nama'] ?? null,
            'jumlah' => $data['opt_jumlah'] ?? null,
            'satuan' => $data['opt_satuan'] ?? null,
        ] : null;

        unset($data['opt_kode'], $data['opt_nama'], $data['opt_jumlah'], $data['opt_satuan']);

        // Pastikan field opsional usulan bernilai null eksplisit jika kosong/tidak diisi
        $data['nama_barang'] = $data['nama_barang'] ?? null;
        $data['kode_barang'] = $data['kode_barang'] ?? null;
        $data['jumlah'] = isset($data['jumlah']) && $data['jumlah'] !== '' ? (int) $data['jumlah'] : null;
        $data['satuan'] = ! empty($data['satuan']) ? $data['satuan'] : null;
        $data['kebutuhan_maksimum'] = isset($data['kebutuhan_maksimum']) && $data['kebutuhan_maksimum'] !== '' ? (int) $data['kebutuhan_maksimum'] : null;
        $data['kebutuhan_riil'] = isset($data['kebutuhan_riil']) && $data['kebutuhan_riil'] !== '' ? (int) $data['kebutuhan_riil'] : null;

        return $data;
    }

    private function authorizeUpdate(UsulanRkbmd $usulan, bool $isAdmin): void
    {
        abort_unless($usulan->status_usulan === 'Draft', 422, 'Hanya usulan berstatus Draf yang dapat diedit.');

        $isOwner = $usulan->id_pegawai && $usulan->id_pegawai === auth()->user()?->pegawai?->id_pegawai;

        if ($isAdmin && ! $isOwner) {
            abort(403, 'Anda tidak memiliki hak untuk mengedit usulan draf ini.');
        }

        $this->authorizeSkpdUsulan($usulan);
        abort_unless($isOwner, 403, 'Anda hanya dapat mengedit usulan draf milik Anda sendiri.');
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
