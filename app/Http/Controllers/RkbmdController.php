<?php

namespace App\Http\Controllers;

use App\Models\ArsipRkbmd;
use App\Models\Aset;
use App\Models\Skpd;
use App\Models\UsulanRkbmd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RkbmdController extends Controller
{
    const JENIS = ['Pengadaan', 'Pemeliharaan', 'Penghapusan'];

    const SATUAN = ['Unit', 'Paket', 'Set', 'Buah'];

    const STATUS = ['Draft', 'Diajukan', 'Disetujui Pengurus Barang', 'Ditolak'];

    /**
     * Admin Aset berperan sebagai "Pengurus Barang" yang menyetujui/menolak usulan.
     */
    private function isAdmin(): bool
    {
        return auth()->user()?->role?->nama_role === 'Admin Aset';
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
     * Query dasar usulan dengan seluruh filter (daftar + ekspor).
     */
    private function applyFilters(Request $request)
    {
        $tahun = (int) $request->query('tahun');
        $jenis = $request->query('jenis');
        $bidang = (array) $request->query('bidang', []);
        $bidang = array_values(array_filter(array_map('intval', $bidang), fn ($x) => $x > 0));

        $query = UsulanRkbmd::with(['skpd', 'pegawai']);

        if (! $this->isAdmin()) {
            $mySkpd = $this->userSkpd();
            if ($mySkpd) {
                $query->where('id_skpd', $mySkpd->id_skpd);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query
            ->when($tahun, fn ($q) => $q->where('tahun_anggaran', $tahun))
            ->when($jenis && $jenis !== 'semua', fn ($q) => $q->where('jenis_usulan', $jenis))
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

        $arsipList = ArsipRkbmd::with('skpd')->orderByDesc('id_arsip')->get();

        $mySkpd = $isAdmin ? null : $this->userSkpd();

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
        ));
    }

    /**
     * Pencarian aset (JSON) untuk Select2 pada form usulan RKBMD.
     *
     * Sumber data: tabel `aset` (hanya status aktif), kode = nomor_kartu_barang,
     * nama = nama barang master (fallback merk aset).
     */
    public function searchMasterBarang(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));

        $aset = Aset::query()
            ->with('barang')
            ->whereIn('status_aset', ['aktif', 'Aktif'])
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('nomor_kartu_barang', 'like', "%{$keyword}%")
                        ->orWhere('merk', 'like', "%{$keyword}%")
                        ->orWhereHas('barang', function ($b) use ($keyword) {
                            $b->where('nama_barang', 'like', "%{$keyword}%")
                                ->orWhere('kode_barang', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderBy('nomor_kartu_barang')
            ->limit(20)
            ->get(['id_aset', 'id_barang', 'nomor_kartu_barang', 'merk']);

        return response()->json(
            $aset->map(function ($a) {
                $nama = $a->barang?->nama_barang ?: $a->merk ?: '(Tanpa nama barang)';

                return [
                    'id' => $a->id_aset,
                    'text' => $nama,
                    'nama_barang' => $nama,
                    'kode_barang' => $a->nomor_kartu_barang,
                ];
            })->values()
        );
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

        UsulanRkbmd::create($data);

        return back()->with('success', 'Usulan RKBMD berhasil disimpan sebagai Draft.');
    }

    /**
     * Perbarui usulan (pemilik selama belum dikunci, atau admin).
     */
    public function update(Request $request, UsulanRkbmd $usulan)
    {
        $isAdmin = $this->isAdmin();

        $this->authorizeUpdate($usulan, $isAdmin);

        $data = $this->validateUsulan($request, $isAdmin);

        if ($isAdmin) {
            $data['id_skpd'] = $request->input('id_skpd');
        }

        $usulan->update($data);

        return back()->with('success', 'Usulan RKBMD berhasil diperbarui.');
    }

    /**
     * Kirim usulan (Draft -> Diajukan), hanya oleh pemilik.
     */
    public function submit(UsulanRkbmd $usulan)
    {
        abort_unless($usulan->id_pegawai === auth()->user()?->pegawai?->id_pegawai, 403);
        abort_unless($usulan->status_usulan === 'Draft', 422, 'Hanya usulan berstatus Draft yang dapat diajukan.');

        $usulan->update(['status_usulan' => 'Diajukan']);

        return back()->with('success', 'Usulan diajukan dan menunggu verifikasi Pengurus Barang.');
    }

    /**
     * Setujui / tolak usulan (khusus Admin Aset / Pengurus Barang).
     */
    public function decide(Request $request, UsulanRkbmd $usulan)
    {
        abort_unless($this->isAdmin(), 403);
        abort_unless(in_array($usulan->status_usulan, ['Draft', 'Diajukan']), 422, 'Usulan sudah dalam status final.');

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
     * Hapus usulan (pemilik selama belum final, atau admin).
     */
    public function destroy(UsulanRkbmd $usulan)
    {
        $isAdmin = $this->isAdmin();
        $isOwner = $usulan->id_pegawai === auth()->user()?->pegawai?->id_pegawai;

        abort_unless($isAdmin || $isOwner, 403);
        abort_unless($isAdmin || in_array($usulan->status_usulan, ['Draft', 'Diajukan']), 422, 'Usulan final tidak dapat dihapus.');

        $usulan->delete();

        return back()->with('success', 'Usulan RKBMD berhasil dihapus.');
    }

    /**
     * Unduh template Excel baku isian usulan RKBMD.
     */
    public function downloadTemplate()
    {
        $spreadsheet = self::makeSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Usulan RKBMD');

        // Baris contoh sebagai panduan pengisian.
        $sheet->fromArray([
            1,
            'Bidang Produksi Tanaman Tahunan',
            now()->year + 1,
            'Pengadaan',
            'Laptop Notebook Core i5',
            'RAM 8 GB, SSD 256 GB, garansi resmi',
            2,
            'Unit',
            'Pegawai belum memiliki laptop untuk mendukung kegiatan administrasi perkantoran.',
        ], null, 'A2');

        return self::streamXlsx($spreadsheet, 'Template-Usulan-RKBMD.xlsx');
    }

    /**
     * Ekspor rekap usulan (.xlsx) mengikuti parameter export: jenis, bidang, tahun, status.
     */
    public function export(Request $request)
    {
        $query = $this->applyFilters($request);

        // Filter "Jenis Usulan / Lampiran" khusus export (kode pendek dari modal).
        $jenisKode = $request->query('jenis');
        if ($jenisKode === 'pengadaan') {
            $query->where('jenis_usulan', 'Pengadaan');
        } elseif ($jenisKode === 'pemeliharaan') {
            $query->where('jenis_usulan', 'Pemeliharaan');
        } elseif ($jenisKode === 'pemanfaatan') {
            $query->where('jenis_usulan', 'Penghapusan');
        }

        // Filter "Status Usulan" (khusus export).
        $statusKode = $request->query('status');
        if ($statusKode === 'disetujui') {
            $query->where('status_usulan', 'Disetujui Pengurus Barang');
        } elseif ($statusKode === 'draft') {
            $query->whereIn('status_usulan', ['Draft', 'Diajukan']);
        }

        $rows = $query->orderBy('id_usulan')->get();

        $spreadsheet = self::makeSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Usulan RKBMD');

        $rowNum = 2;
        foreach ($rows as $i => $usulan) {
            $sheet->fromArray([
                $i + 1,
                $usulan->skpd?->nama_skpd ?? '-',
                $usulan->tahun_anggaran,
                $usulan->jenis_usulan,
                $usulan->nama_barang,
                $usulan->spesifikasi,
                $usulan->jumlah,
                $usulan->satuan,
                $usulan->alasan_kebutuhan,
                $usulan->status_usulan,
            ], null, "A{$rowNum}");
            $sheet->getStyle("G{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $rowNum++;
        }

        $namaFile = 'RKBMD_'
            .self::labelJenis($jenisKode)
            .'_'
            .$this->labelBidang($request->query('bidang'))
            .'_'
            .($request->query('tahun') ?: 'semua-tahun')
            .'.xlsx';

        return self::streamXlsx($spreadsheet, $namaFile);
    }

    /**
     * Label jenis usulan untuk nama file export.
     */
    private static function labelJenis(?string $kode): string
    {
        return match ($kode) {
            'pengadaan' => 'Pengadaan',
            'pemeliharaan' => 'Pemeliharaan',
            'pemanfaatan' => 'Pemanfaatan',
            default => 'Semua',
        };
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
     * Impor usulan dari file Excel sesuai template (baris 3+; baris contoh template dilewati).
     */
    public function import(Request $request)
    {
        $isAdmin = $this->isAdmin();

        $data = $request->validate([
            'file_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
            'tahun_anggaran' => ['required', 'integer', 'between:2000,2100'],
        ]);

        if ($isAdmin) {
            abort_unless($data['id_skpd'] ?? null, 422, 'Pilih Bidang / Unit Kerja pengusul terlebih dahulu.');
            $idSkpd = $data['id_skpd'];
        } else {
            $mySkpd = $this->userSkpd();
            abort_unless($mySkpd, 403, 'Akun Anda tidak terhubung ke unit/SKPD manapun.');
            $idSkpd = $mySkpd->id_skpd;
        }

        $tahun = (int) $data['tahun_anggaran'];
        $rows = IOFactory::load($request->file('file_excel')->getRealPath())
            ->getActiveSheet()
            ->toArray(null, true, true, true);

        $created = 0;
        $skipped = 0;

        foreach ($rows as $no => $row) {
            if ($no < 2) {
                continue; // baris 1 = judul kolom
            }

            // Lewati baris contoh bawaan template (baris 2).
            if ($no === 2
                && trim((string) ($row['B'] ?? '')) === 'Bidang Produksi Tanaman Tahunan'
                && trim((string) ($row['D'] ?? '')) === 'Pengadaan'
                && str_contains((string) ($row['E'] ?? ''), 'Laptop Notebook')) {
                continue;
            }

            $nama = trim((string) ($row['E'] ?? ''));
            if ($nama === '') {
                continue; // baris kosong
            }

            $jenis = ucfirst(strtolower(trim((string) ($row['D'] ?? ''))));
            if (! in_array($jenis, self::JENIS, true)) {
                $skipped++;

                continue;
            }

            $jumlah = max(1, (int) ($row['G'] ?? 1));
            $satuan = trim((string) ($row['H'] ?? ''));
            $satuan = $satuan !== '' ? $satuan : 'Unit';
            $alasan = trim((string) ($row['I'] ?? ''));
            $alasan = $alasan !== '' ? $alasan : 'Import data template RKBMD.';

            UsulanRkbmd::create([
                'id_skpd' => $idSkpd,
                'id_pegawai' => auth()->user()?->pegawai?->id_pegawai,
                'tahun_anggaran' => $tahun,
                'jenis_usulan' => $jenis,
                'nama_barang' => $nama,
                'spesifikasi' => trim((string) ($row['F'] ?? '')) ?: null,
                'jumlah' => $jumlah,
                'satuan' => $satuan,
                'alasan_kebutuhan' => $alasan,
                'status_usulan' => 'Draft',
                'tanggal_usulan' => now()->toDateString(),
            ]);

            $created++;
        }

        $pesan = "Berhasil mengimpor {$created} usulan RKBMD sebagai Draft.";
        if ($skipped) {
            $pesan .= " {$skipped} baris dilewati (jenis tidak valid / kosong).";
        }

        return back()->with('success', $pesan);
    }

    /**
     * Upload arsip dokumen sah RKBMD (PDF/Scan SK bertanda tangan).
     */
    public function storeArsip(Request $request)
    {
        abort_unless($this->isAdmin(), 403);

        $data = $request->validate([
            'id_skpd' => ['required', 'exists:skpd,id_skpd'],
            'tahun_anggaran' => ['required', 'integer', 'between:2000,2100'],
            'nama_dokumen' => ['nullable', 'string', 'max:255'],
            'tanggal_pengesahan' => ['nullable', 'date'],
            'file_dokumen' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $data['file_path'] = $request->file('file_dokumen')->store('arsip-rkbmd', 'public');
        $data['uploaded_by'] = auth()->id();

        ArsipRkbmd::create($data);

        return back()->with('success', 'Arsip dokumen RKBMD berhasil diunggah.');
    }

    /**
     * Hapus arsip dokumen (khusus Admin Aset).
     */
    public function destroyArsip(ArsipRkbmd $arsip)
    {
        abort_unless($this->isAdmin(), 403);

        if ($arsip->file_path) {
            Storage::disk('public')->delete($arsip->file_path);
        }
        $arsip->delete();

        return back()->with('success', 'Arsip dokumen RKBMD berhasil dihapus.');
    }

    private function validateUsulan(Request $request, bool $isAdmin): array
    {
        $rules = [
            'tahun_anggaran' => ['required', 'integer', 'between:2000,2100'],
            'jenis_usulan' => ['required', 'in:'.implode(',', self::JENIS)],
            'program_kegiatan' => ['nullable', 'string', 'max:500'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'kode_barang' => ['nullable', 'string', 'max:50'],
            'spesifikasi' => ['nullable', 'string', 'max:1000'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'satuan' => ['required', 'string', 'max:50'],
            'kebutuhan_maksimum' => ['nullable', 'integer', 'min:0'],
            'kebutuhan_riil' => ['nullable', 'integer', 'min:0'],
            'opt_kode' => ['nullable', 'string', 'max:50'],
            'opt_nama' => ['nullable', 'string', 'max:255'],
            'opt_jumlah' => ['nullable', 'integer', 'min:1'],
            'opt_satuan' => ['nullable', 'string', 'max:50'],
            'alasan_kebutuhan' => ['required', 'string', 'max:2000'],
        ];

        if ($isAdmin) {
            $rules['id_skpd'] = ['required', 'exists:skpd,id_skpd'];
        }

        $data = $request->validate($rules);

        // Kemas "Barang yang Dapat Dioptimalkan" menjadi satu field JSON.
        $adaOpt = ($data['opt_kode'] ?? '') !== '' || ($data['opt_nama'] ?? '') !== '' || ($data['opt_jumlah'] ?? '') !== '';
        $data['barang_optimalisasi'] = $adaOpt ? [
            'kode' => $data['opt_kode'] ?? null,
            'nama' => $data['opt_nama'] ?? null,
            'jumlah' => $data['opt_jumlah'] ?? null,
            'satuan' => $data['opt_satuan'] ?? null,
        ] : null;

        unset($data['opt_kode'], $data['opt_nama'], $data['opt_jumlah'], $data['opt_satuan']);

        return $data;
    }

    private function authorizeUpdate(UsulanRkbmd $usulan, bool $isAdmin): void
    {
        $isOwner = $usulan->id_pegawai === auth()->user()?->pegawai?->id_pegawai;

        if ($isAdmin) {
            abort_unless(in_array($usulan->status_usulan, ['Draft', 'Diajukan']), 422, 'Usulan final tidak dapat diubah.');

            return;
        }

        abort_unless($isOwner, 403);
        abort_unless(in_array($usulan->status_usulan, ['Draft', 'Diajukan']), 422, 'Usulan sudah dikunci (final).');
    }

    private static function makeSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'No', 'Bidang / Unit Kerja', 'Tahun Anggaran', 'Jenis Usulan',
            'Nama Barang', 'Spesifikasi Teknis', 'Jumlah', 'Satuan',
            'Keterangan / Alasan Kebutuhan', 'Status Usulan',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
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
