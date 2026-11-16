<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\Kendaraan;
use App\Models\MutasiAset;
use App\Models\PajakKendaraan;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\RiwayatPlat;
use App\Models\Ruangan;
use App\Services\AsetPenempatan;
use App\Services\AsetScope;
use App\Services\TanggalAset;
use App\Services\UsulanPenghapusanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KendaraanController extends Controller
{
    public function __construct(
        private readonly AsetScope $scope,
        private readonly ?UsulanPenghapusanService $usulanPenghapusan = null,
    ) {
    }

    /**
     * Resolver lazy untuk service usulan penghapusan (parameter konstruktor
     * opsional agar instansiasi manual lama tetap kompatibel).
     */
    private function usulan(): UsulanPenghapusanService
    {
        return $this->usulanPenghapusan ?? app(UsulanPenghapusanService::class);
    }

    const KONDISI = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

    /**
     * Status aset yang boleh ditulis lewat form kendaraan (whitelist).
     * Status sistem (diusulkan_hapus/dihapuskan) tidak boleh dilewati.
     */
    const STATUS_USER = ['aktif', 'nonaktif'];

    const JENIS_KENDARAAN = ['Roda Dua (Sepeda Motor)', 'Roda Empat (Mobil)', 'Truk', 'Pick Up', 'Bus', 'Lainnya'];

    const STATUS_PLAT = ['Aktif', 'Tidak Aktif'];

    /**
     * Object-level check: kendaraan (via aset-nya) harus dalam cakupan
     * user. Route CUD/kelola sudah `role:Admin Aset`, tetapi dicek ulang
     * di sini agar tetap aman bila role diperluas atau route dipanggil
     * tanpa middleware — akses lintas unit via URL/id selalu 403.
     */
    private function authorizeKendaraan(Kendaraan $kendaraan, string $aksi = 'mengelola kendaraan'): void
    {
        abort_if(
            ! $kendaraan->aset || ! $this->scope->bolehAksesAset($kendaraan->aset),
            403,
            $this->scope->pesanAkses($aksi)
        );
    }

    /**
     * Daftar seluruh kendaraan beserta pemegang/penempatannya.
     */
    public function index(Request $request)
    {
        $kendaraanList = $this->filteredKendaraanQuery($request)
            ->with(['aset.penempatanAktif.ruangan', 'aset.pemegangSaatIni.pegawai', 'platAktif', 'pajakAktif'])
            ->paginate(10)
            ->withQueryString();

        $isAdminAset = $this->scope->isAdminAset();
        $isAdminUpt = $this->scope->isAdminUpt();
        $bolehKelola = $isAdminAset || $isAdminUpt;

        if ($bolehKelola) {
            $pegawaiQuery = Pegawai::query();
            if ($isAdminUpt) {
                $idLokasi = $this->scope->idLokasi() ?: [0];
                $pegawaiQuery->whereHas('ruangan', fn ($r) => $r->whereIn('id_lokasi', $idLokasi));
            }
            $pegawais = $pegawaiQuery->orderBy('nama_pegawai', 'asc')->get();
        } else {
            $pegawais = collect();
        }

        $pemegangMap = $this->petaIdPemegang($kendaraanList);

        return view('kendaraan.index', compact('kendaraanList', 'isAdminAset', 'isAdminUpt', 'pegawais', 'pemegangMap'));
    }

    /**
     * Peta nama pemegang kendaraan -> id_pegawai untuk tautan ke detail pemegang.
     *
     * Sumber identitas pemegang kendaraan adalah kolom string `kendaraan.pemegang`
     * (relasi PemegangAset kendaraan tidak selalu sinkron setelah mutasi). Tautan
     * hanya dibuat bila nama cocok PERSIS & UNIK ke satu Pegawai; jika tidak ada
     * atau lebih dari satu (nama kembar), nilainya null agar tampil sebagai teks.
     *
     * @return array<string, int|null> kunci = nama ternormalisasi (lowercase+trim)
     */
    private function petaIdPemegang(iterable $kendaraanList): array
    {
        $items = method_exists($kendaraanList, 'getCollection')
            ? $kendaraanList->getCollection()
            : collect($kendaraanList);

        $nama = $items->pluck('pemegang')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->filter(fn ($n) => $n !== '')
            ->unique()
            ->values();

        if ($nama->isEmpty()) {
            return [];
        }

        $placeholder = implode(',', array_fill(0, $nama->count(), '?'));

        $kandidat = Pegawai::whereRaw('LOWER(TRIM(nama_pegawai)) IN ('.$placeholder.')', $nama->all())
            ->get(['id_pegawai', 'nama_pegawai'])
            ->groupBy(fn ($p) => mb_strtolower(trim((string) $p->nama_pegawai)));

        $peta = [];
        foreach ($nama as $norm) {
            $grup = $kandidat->get($norm);
            $peta[$norm] = ($grup && $grup->count() === 1) ? (int) $grup->first()->id_pegawai : null;
        }

        return $peta;
    }

    /**
     * Query dasar daftar kendaraan: cakupan (AsetScope) + pencarian + status.
     *
     * Dipakai bersama oleh index() dan export() agar filter yang diekspor
     * selalu identik dengan yang terlihat di daftar. Membaca parameter via
     * input() supaya bekerja untuk request GET (daftar) maupun POST (ekspor
     * mode terpilih).
     */
    private function filteredKendaraanQuery(Request $request): Builder
    {
        $search = $request->input('search');
        $status = $request->input('status'); // 'aktif' | 'nonaktif' | null

        return Kendaraan::with([
            'aset.barang', 'aset.pemegangSaatIni.pegawai', 'aset.penempatanAktif.ruangan',
            'aset.skpd', 'aset.lokasi',
            'platAktif', 'pajakAktif',
        ])
            // Kendaraan adalah aset, jadi ikut cakupan lokasi fisik.
            ->whereHas('aset', fn ($a) => $a->tap(fn ($q) => $this->scope->terapkan($q)))
            // Hanya status pengguna (aktif/nonaktif) yang tampil. Kendaraan yang
            // sedang diusulkan hapus atau sudah dihapuskan (status sistem) tidak
            // ikut daftar — konsisten dengan daftar Aset Barang.
            ->whereHas('aset', fn ($a) => $a->whereIn('status_aset', ['aktif', 'nonaktif']))
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('pemegang', 'like', "%{$search}%")
                        ->orWhere('nomor_rangka', 'like', "%{$search}%")
                        ->orWhere('nomor_mesin', 'like', "%{$search}%")
                        ->orWhere('nama_kendaraan', 'like', "%{$search}%")
                        ->orWhere('jenis_kendaraan', 'like', "%{$search}%")
                        ->orWhere('merk', 'like', "%{$search}%")
                        ->orWhere('tipe', 'like', "%{$search}%")
                        // Nomor plat ada di riwayat_plat. Dicari ke seluruh riwayat
                        // (plat lama ikut ketemu) dan dinormalkan spasinya supaya
                        // "B1234ABC" tetap cocok dengan "B 1234 ABC".
                        ->orWhereHas('riwayatPlat', function ($p) use ($search) {
                            $tanpaSpasi = str_replace(' ', '', $search);
                            $p->where(function ($q) use ($search, $tanpaSpasi) {
                                $q->where('nomor_plat', 'like', "%{$search}%")
                                    ->orWhereRaw("REPLACE(nomor_plat, ' ', '') LIKE ?", ["%{$tanpaSpasi}%"]);
                            });
                        })
                        ->orWhereHas('aset', function ($a) use ($search) {
                            $a->where('nomor_kartu_barang', 'like', "%{$search}%")
                                ->orWhere('merk', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status === 'aktif', fn ($q) => $q->whereHas('aset', fn ($a) => $a->where('status_aset', 'aktif')))
            ->when($status === 'nonaktif', fn ($q) => $q->whereHas('aset', fn ($a) => $a->where('status_aset', '!=', 'aktif')))
            ->orderByDesc('id_kendaraan');
    }

    /**
     * Ekspor daftar kendaraan ke Excel (.xlsx).
     *
     * Tiga mode lewat parameter `mode`:
     *  - semua  : seluruh kendaraan dengan status aset 'aktif'.
     *  - segera : kendaraan aktif dengan jatuh tempo pajak (1 tahun / 5 tahun)
     *             berada pada rentang hari ini s.d. 30 hari ke depan (inklusif).
     *  - pilih  : hanya kendaraan aktif dari id_kendaraan terpilih (ids[]).
     *
     * Akses eksklusif untuk role Admin Aset (didukung otorisasi lapis kedua).
     * Seluruh mode mengunci status aset 'aktif' di backend untuk mencegah kebocoran
     * kendaraan nonaktif melalui manipulasi parameter.
     */
    public function export(Request $request)
    {
        abort_unless($this->scope->isAdminAset(), 403, 'Hanya Admin Aset yang berwenang mengekspor data kendaraan.');

        $mode = (string) $request->input('mode', 'semua');
        if (! in_array($mode, ['semua', 'segera', 'pilih'], true)) {
            $mode = 'semua';
        }

        // Kebutuhan bisnis: seluruh mode ekspor HANYA mencakup kendaraan dengan
        // status aset 'aktif'. Status pengguna 'nonaktif' dan status sistem
        // ('diusulkan_hapus', 'dihapuskan') tidak boleh disertakan.
        $exportRequest = clone $request;
        $exportRequest->merge(['status' => 'aktif']);

        $query = $this->filteredKendaraanQuery($exportRequest)
            ->whereHas('aset', fn ($a) => $a->where('status_aset', 'aktif'))
            ->with([
                'aset.barang',
                'aset.pemegangSaatIni.pegawai',
                'aset.penempatanAktif.ruangan',
                'aset.skpd',
                'aset.lokasi',
                'pajak',
                'platAktif',
            ]);

        if ($mode === 'segera') {
            // Kebutuhan bisnis Segera Habis Pajak:
            // - Rentang: hari ini sampai 30 hari ke depan (inklusif).
            // - Pajak yang sudah lewat tanggal jatuh tempo (< today) TIDAK BOLEH masuk.
            // - Pajak yang jatuh tempo > 30 hari TIDAK BOLEH masuk.
            // - Tanggal kosong (null) TIDAK BOLEH masuk.
            // - Memeriksa Pajak Tahunan (pajak_5_tahunan = false) dan Pajak 5 Tahunan (true).
            // - Jika salah satu atau kedua jenis pajak memenuhi kriteria, kendaraan masuk 1 kali.
            // - Pajak 5 tahunan diperiksa langsung dari riwayat tanggal terbaru tanpa
            //   terhalang anomali status 'Tidak Aktif' hasil impor massal.
            $today = Carbon::today()->toDateString();
            $batas = Carbon::today()->addDays(30)->toDateString();

            $query->where(function (Builder $q) use ($today, $batas) {
                // Pajak Tahunan: tanggal_berakhir terbaru berada dalam [today, batas]
                $q->whereExists(function ($sub) use ($today, $batas) {
                    $sub->selectRaw(1)
                        ->from('pajak_kendaraan as p1')
                        ->whereColumn('p1.id_kendaraan', 'kendaraan.id_kendaraan')
                        ->where('p1.pajak_5_tahunan', false)
                        ->whereNotNull('p1.tanggal_berakhir')
                        ->whereBetween('p1.tanggal_berakhir', [$today, $batas])
                        ->whereNotExists(function ($newer) {
                            $newer->selectRaw(1)
                                ->from('pajak_kendaraan as p1_newer')
                                ->whereColumn('p1_newer.id_kendaraan', 'p1.id_kendaraan')
                                ->where('p1_newer.pajak_5_tahunan', false)
                                ->whereColumn('p1_newer.tanggal_berakhir', '>', 'p1.tanggal_berakhir');
                        });
                })
                // ATAU Pajak Lima Tahunan: tanggal_berakhir terbaru berada dalam [today, batas]
                ->orWhereExists(function ($sub) use ($today, $batas) {
                    $sub->selectRaw(1)
                        ->from('pajak_kendaraan as p5')
                        ->whereColumn('p5.id_kendaraan', 'kendaraan.id_kendaraan')
                        ->where('p5.pajak_5_tahunan', true)
                        ->whereNotNull('p5.tanggal_berakhir')
                        ->whereBetween('p5.tanggal_berakhir', [$today, $batas])
                        ->whereNotExists(function ($newer) {
                            $newer->selectRaw(1)
                                ->from('pajak_kendaraan as p5_newer')
                                ->whereColumn('p5_newer.id_kendaraan', 'p5.id_kendaraan')
                                ->where('p5_newer.pajak_5_tahunan', true)
                                ->whereColumn('p5_newer.tanggal_berakhir', '>', 'p5.tanggal_berakhir');
                        });
                });
            });
        } elseif ($mode === 'pilih') {
            $ids = $this->validatedExportIds($request);
            if ($ids === []) {
                // Tidak ada id sah: hasilkan file kosong, bukan seluruh data.
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id_kendaraan', $ids);
            }
        }

        $rows = $query->get();

        return $this->streamKendaraanXlsx($rows, $mode);
    }

    /**
     * Ambil id_kendaraan yang dikirim client untuk mode terpilih.
     *
     * Hanya nilai yang benar-benar bilangan bulat positif yang lolos;
     * nilai lain (non-numerik, nol, negatif, desimal) diabaikan. Validasi
     * cakupan/scope dilakukan pada query, bukan di sini.
     *
     * @return int[]
     */
    private function validatedExportIds(Request $request): array
    {
        $raw = $request->input('ids', []);
        if (! is_array($raw)) {
            $raw = [$raw];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $s = trim((string) $value);
            if ($s === '' || ! ctype_digit($s)) {
                continue;
            }
            $id = (int) $s;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Susun & unduh file .xlsx dari sekumpulan kendaraan.
     */
    private function streamKendaraanXlsx($rows, string $mode)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Kendaraan');

        $headers = [
            'No.',
            'Nomor Kartu Barang',
            'Nama Kendaraan',
            'Jenis Kendaraan',
            'Merk',
            'Tipe',
            'Nomor Polisi',
            'No. Rangka',
            'No. Mesin',
            'Pemegang Kendaraan',
            'SKPD / Unit Kerja',
            'Kondisi Kendaraan',
            'Status Aset',
            'Jatuh Tempo Pajak Tahunan',
            'Jatuh Tempo Pajak Lima Tahunan',
            'Nominal Pajak',
            'Total Pajak',
            'Status Pajak',
            'Jenis Pajak yang Segera Habis',
            'Dokumentasi',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:T1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $today = Carbon::today();
        $batas = Carbon::today()->addDays(30);

        $rowNum = 2;
        foreach ($rows as $i => $k) {
            [$jt1, $jt5] = $this->pajakJatuhTempo($k);
            $utama = $this->pajakUtama($k);

            // Pemegang: relasi resmi penempatan aktif, fallback teks bebas
            $pemegangResmi = $k->aset?->pemegangSaatIni?->pegawai?->nama_pegawai;
            $namaPemegang = $pemegangResmi ?: ($k->pemegang ?: '-');

            // Evaluasi jenis pajak yang segera habis (inklusif hari ini s.d. 30 hari ke depan)
            $segeraHabis = $this->jenisPajakSegeraHabis($jt1, $jt5, $today, $batas);

            // Evaluasi status pajak tahunan dan 5 tahunan
            $statusPajak = $this->statusPajakEvaluasi($jt1, $jt5, $today, $batas);

            $sheet->fromArray([
                $i + 1,
                $k->aset?->nomor_kartu_barang ?? '-',
                $k->namaTampil(),
                $k->jenis_kendaraan ?? '-',
                $k->aset?->merk ?: ($k->merk ?: '-'),
                $k->tipe ?? '-',
                $k->platAktif?->nomor_plat ?? '-',
                $k->nomor_rangka ?? '-',
                $k->nomor_mesin ?? '-',
                $namaPemegang,
                $k->aset?->skpd?->nama_skpd ?? '-',
                $k->aset?->kondisi ?? '-',
                $k->aset?->status_aset ? ucfirst($k->aset->status_aset) : '-',
                $jt1 ? $jt1->format('d/m/Y') : '-',
                $jt5 ? $jt5->format('d/m/Y') : '-',
                $utama && $utama->nominal !== null ? (float) $utama->nominal : '-',
                $utama && $utama->total_pajak !== null ? (float) $utama->total_pajak : '-',
                $statusPajak,
                $segeraHabis,
            ], null, "A{$rowNum}");

            if ($utama && $utama->nominal !== null) {
                $sheet->getStyle("P{$rowNum}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            }
            if ($utama && $utama->total_pajak !== null) {
                $sheet->getStyle("Q{$rowNum}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            }

            $this->embedFotoKendaraan($sheet, $k, $rowNum, 'T');

            $rowNum++;
        }

        foreach (range('A', 'S') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        // Kolom Dokumentasi (gambar) diberi lebar tetap agar tabel tetap terbaca.
        $sheet->getColumnDimension('T')->setWidth(18);

        $label = match ($mode) {
            'segera' => 'Segera-Habis-Pajak',
            'pilih' => 'Terpilih',
            default => 'Semua',
        };

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static fn () => $writer->save('php://output'),
            'Ekspor-Kendaraan-'.$label.'-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    /**
     * Tanggal jatuh tempo pajak tahunan (pajak_5_tahunan = false) dan lima
     * tahunan (true) dari riwayat pajak kendaraan.
     *
     * Untuk tiap jenis diambil record dengan `tanggal_berakhir` terbaru
     * (id_pajak sebagai penentu bila tanggal sama). Record ber-tanggal null
     * diabaikan. TIDAK ada perhitungan tanggal.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function pajakJatuhTempo(Kendaraan $kendaraan): array
    {
        $ambil = function (bool $limaTahunan) use ($kendaraan) {
            return $kendaraan->pajak
                ->filter(fn ($p) => (bool) $p->pajak_5_tahunan === $limaTahunan && $p->tanggal_berakhir !== null)
                ->sortByDesc(fn ($p) => [$p->tanggal_berakhir->getTimestamp(), (int) $p->id_pajak])
                ->first();
        };

        return [$ambil(false)?->tanggal_berakhir, $ambil(true)?->tanggal_berakhir];
    }

    /**
     * Record pajak "utama" untuk kolom Nominal dan Total Pajak:
     * prioritas pajak yang berstatus Aktif; bila tidak ada yang
     * aktif, record pajak terbaru (tanggal_berakhir terbesar). Null bila
     * kendaraan tidak punya pajak sama sekali.
     */
    private function pajakUtama(Kendaraan $kendaraan): ?PajakKendaraan
    {
        if ($kendaraan->pajakAktif) {
            return $kendaraan->pajakAktif;
        }

        return $kendaraan->pajak
            ->sortByDesc(fn ($p) => [$p->tanggal_berakhir?->getTimestamp() ?? PHP_INT_MIN, (int) $p->id_pajak])
            ->first();
    }

    /**
     * Menentukan jenis pajak yang mendekati jatuh tempo (rentang hari ini s.d. 30 hari ke depan).
     */
    private function jenisPajakSegeraHabis($jt1, $jt5, $today, $batas): string
    {
        $p1Segera = $jt1 && $jt1->greaterThanOrEqualTo($today) && $jt1->lessThanOrEqualTo($batas);
        $p5Segera = $jt5 && $jt5->greaterThanOrEqualTo($today) && $jt5->lessThanOrEqualTo($batas);

        return match (true) {
            $p1Segera && $p5Segera => 'Keduanya',
            $p1Segera => 'Pajak Tahunan',
            $p5Segera => 'Pajak Lima Tahunan',
            default => '-',
        };
    }

    /**
     * Teks kolom "Status Pajak" yang mengevaluasi pajak tahunan dan lima tahunan.
     */
    private function statusPajakEvaluasi($jt1, $jt5, $today, $batas): string
    {
        if (! $jt1 && ! $jt5) {
            return '-';
        }

        $terlambat1 = $jt1 && $jt1->lessThan($today);
        $terlambat5 = $jt5 && $jt5->lessThan($today);

        if ($terlambat1 && $terlambat5) {
            return 'Pajak Tahunan & 5 Tahunan Terlambat';
        }
        if ($terlambat1) {
            return 'Pajak Tahunan Terlambat';
        }
        if ($terlambat5) {
            return 'Pajak 5 Tahunan Terlambat';
        }

        $segera1 = $jt1 && $jt1->greaterThanOrEqualTo($today) && $jt1->lessThanOrEqualTo($batas);
        $segera5 = $jt5 && $jt5->greaterThanOrEqualTo($today) && $jt5->lessThanOrEqualTo($batas);

        if ($segera1 && $segera5) {
            return 'Pajak Tahunan & 5 Tahunan Segera Habis';
        }
        if ($segera1) {
            return 'Pajak Tahunan Segera Habis';
        }
        if ($segera5) {
            return 'Pajak 5 Tahunan Segera Habis';
        }

        return 'Aman';
    }

    /**
     * Sisipkan foto kendaraan (kolom `kendaraan.foto`, disk public) ke kolom
     * Dokumentasi. Rasio gambar dipertahankan; bila berkas tidak ada / bukan
     * gambar yang dikenali, sel dibiarkan kosong (tidak mengarang nilai).
     */
    private function embedFotoKendaraan($sheet, Kendaraan $kendaraan, int $rowNum, string $colCoord = 'T'): void
    {
        $foto = $kendaraan->foto;
        if (! $foto) {
            return;
        }

        try {
            if (! Storage::disk('public')->exists($foto)) {
                return;
            }

            $path = Storage::disk('public')->path($foto);
            if (! is_file($path) || @getimagesize($path) === false) {
                return;
            }

            $drawing = new Drawing;
            $drawing->setName('Foto Kendaraan '.$kendaraan->id_kendaraan);
            $drawing->setDescription('Foto kendaraan');
            $drawing->setPath($path);
            $drawing->setResizeProportional(true);
            $drawing->setHeight(50);
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(4);
            $drawing->setCoordinates($colCoord.$rowNum);
            $drawing->setWorksheet($sheet);

            $sheet->getRowDimension($rowNum)->setRowHeight(42);
        } catch (\Throwable $e) {
            // Gagal menyisipkan gambar tidak boleh menggagalkan seluruh ekspor.
        }
    }

    /**
     * Detail satu kendaraan: info aset + riwayat plat + pajak kendaraan.
     */
    public function show(Request $request, Kendaraan $kendaraan)
    {
        abort_if(
            ! auth()->user()->hasRole('Admin Aset'),
            403,
            'Hanya Admin Aset yang diperbolehkan mengakses halaman detail kendaraan.'
        );

        $aset = $kendaraan->aset;

        abort_if(
            ! $aset || ! $this->scope->bolehAksesAset($aset),
            403,
            $this->scope->pesanAkses('melihat detail kendaraan')
        );

        $kendaraan->load([
            'aset.barang.kategori',
            'aset.pemegangSaatIni.pegawai',
            'aset.penempatanAktif.ruangan',
            'aset.skpd',
            'aset.lokasi',
            'riwayatPlat' => fn ($q) => $q->orderByRaw("CASE WHEN status = 'Aktif' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->limit(3),
        ]);

        // Query riwayat transaksi pajak:
        // 1. Status Aktif selalu paling atas (CASE WHEN status = 'Aktif' THEN 0 ELSE 1 END)
        // 2. Status Tidak Aktif ditampilkan setelah Aktif
        // 3. Untuk status yang sama, urutkan tanggal bayar terbaru lalu id_pajak terbaru
        // 4. Pagination server-side maksimal 5 transaksi per halaman
        $pajakQuery = $kendaraan->pajak()
            ->orderByRaw("CASE WHEN status = 'Aktif' THEN 0 ELSE 1 END")
            ->orderByDesc('tanggal_bayar')
            ->orderByDesc('id_pajak');

        if ($request->filled('pajak_tahun')) {
            $pajakQuery->where('tahun', $request->pajak_tahun);
        }

        if ($request->filled('pajak_jenis')) {
            if ($request->pajak_jenis === '5-tahunan') {
                $pajakQuery->where('pajak_5_tahunan', true);
            } elseif ($request->pajak_jenis === 'tahunan') {
                $pajakQuery->where('pajak_5_tahunan', false);
            }
        }

        $riwayatPajak = $pajakQuery->paginate(5, ['*'], 'pajak_page')
            ->fragment('pajak-card')
            ->withQueryString();

        $kendaraan->setRelation('pajak', $riwayatPajak);

        $daftarTahunPajak = $kendaraan->pajak()
            ->select('tahun')
            ->distinct()
            ->whereNotNull('tahun')
            ->pluck('tahun')
            ->map(fn ($t) => (int) $t)
            ->unique()
            ->sortDesc()
            ->values();

        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';

        // Riwayat mutasi/ganti pemegang kendaraan ini (untuk tabel Riwayat Pemegang).
        $riwayatMutasi = DetailMutasiAset::with([
            'mutasi.userPenginput.pegawai', 'pegawaiLama', 'pegawaiBaru',
        ])
            ->where('id_aset', $kendaraan->id_aset)
            ->orderByDesc('id_detail')
            ->get();

        return view('kendaraan.show', compact('kendaraan', 'isAdminAset', 'riwayatMutasi', 'riwayatPajak', 'daftarTahunPajak'));
    }

    public function create()
    {
        return response()->json([
            'jenisList' => self::JENIS_KENDARAAN,
            'kondisiList' => self::KONDISI,
        ]);
    }

    /**
     * Simpan kendaraan baru -> membuat row aset (is_kendaraan=1) + row kendaraan.
     *
     * Pemegang TIDAK lagi diambil dari form: penempatan awal kendaraan
     * dilakukan terpisah (ikon "+" pada kolom Pemegang) dan pergantian
     * pemegang lewat Mutasi Pemegang. Karena itu `kendaraan.pemegang`
     * dibiarkan kosong sampai penempatan benar-benar dilakukan — tidak ada
     * penetapan pemegang/lokasi otomatis untuk kendaraan baru maupun lama.
     */
    public function store(Request $request)
    {
        $data = TanggalAset::samakanUntukSimpan($this->validateKendaraan($request));

        // Seluruh operasi tulis dijalankan atomik: bila salah satu langkah
        // (Kendaraan/RiwayatPlat/pajak/usulan) gagal, Aset yang sudah dibuat
        // tidak tertinggal separuh jalan.
        DB::transaction(function () use ($data, $request) {
            $aset = Aset::create([
                'nomor_kartu_barang' => $this->nomorKartuFallback($data['nomor_kartu_barang'] ?? null),
                'merk' => $data['merk'] ?? null,
            'tanggal_pengadaan' => $data['tanggal_pengadaan'] ?? null,
                'tanggal_habis_pakai' => $data['tanggal_habis_pakai'] ?? null,
                'nilai_perolehan' => $data['nilai_perolehan'] ?? null,
                'kondisi' => $data['kondisi'] ?? null,
                'status_aset' => $data['status_aset'] ?? 'aktif',
                'is_kendaraan' => true,
            ]);

            $kendaraan = Kendaraan::create([
                'id_aset' => $aset->id_aset,
                'nama_kendaraan' => trim((string) ($data['nama_kendaraan'] ?? '')) ?: null,
                'jenis_kendaraan' => $data['jenis_kendaraan'] ?? null,
                'nomor_rangka' => $data['nomor_rangka'] ?? null,
                'nomor_mesin' => $data['nomor_mesin'] ?? null,
                'merk' => $data['merk'] ?? null,
                'tipe' => $data['tipe'] ?? null,
                // Pemegang kosong: ditetapkan lewat Penempatan Awal / Mutasi.
                'pemegang' => null,
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            if (! empty($data['nomor_polisi'])) {
                RiwayatPlat::create([
                    'id_kendaraan' => $kendaraan->id_kendaraan,
                    'nomor_plat' => $data['nomor_polisi'],
                    'tanggal_berlaku' => null,
                    'ganti_plat' => false,
                    'status' => 'Aktif',
                ]);
            }

            $this->syncPajak($kendaraan, $request);

            // Pemicu otomatis: kendaraan baru berkondisi Rusak Berat langsung
            // masuk antrean usulan penghapusan.
            $this->usulan()->usulkanKarenaRusakBerat(
                $aset,
                'Otomatis: kendaraan dibuat dengan kondisi Rusak Berat.'
            );
        });

        return response()->json(['success' => true, 'message' => 'Kendaraan berhasil ditambahkan.']);
    }

    public function edit(Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'mengubah kendaraan');

        $kendaraan->load('aset', 'pajakAktif', 'platAktif');
        if ($kendaraan->aset) {
            $kendaraan->aset->load('penempatanAktif.ruangan', 'pemegangSaatIni.pegawai');
        }

        return response()->json([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'id_aset' => $kendaraan->id_aset,
            'nama_kendaraan' => $kendaraan->nama_kendaraan ?? '',
            'nomor_kartu_barang' => $kendaraan->aset?->nomor_kartu_barang,
            'jenis_kendaraan' => $kendaraan->jenis_kendaraan,
            'nomor_rangka' => $kendaraan->nomor_rangka,
            'nomor_mesin' => $kendaraan->nomor_mesin,
            'merk' => $kendaraan->aset?->merk,
            'tipe' => $kendaraan->tipe,
            'pemegang' => $kendaraan->pemegang,
            'keterangan' => $kendaraan->keterangan,
            'foto' => $kendaraan->foto,
            'tanggal_pengadaan' => $kendaraan->aset?->tanggal_pengadaan?->format('Y-m-d'),
            'tanggal_habis_pakai' => $kendaraan->aset?->tanggal_habis_pakai?->format('Y-m-d'),
            'nilai_perolehan' => $kendaraan->aset?->nilai_perolehan,
            'kondisi' => $kendaraan->aset?->kondisi,
            'status_aset' => $kendaraan->aset?->status_aset,
            'nomor_plat' => $kendaraan->platAktif?->nomor_plat ?? '',
            // penempatan
            'penempatan_aktif' => $kendaraan->aset?->penempatanAktif ? [
                'id_penempatan' => $kendaraan->aset->penempatanAktif->id_penempatan,
                'id_ruangan' => $kendaraan->aset->penempatanAktif->id_ruangan,
                'ruangan' => $kendaraan->aset->penempatanAktif->ruangan?->nama_ruangan,
                'is_finalized' => $kendaraan->aset->penempatanAktif->is_finalized,
            ] : null,
            'pemegang_aktif' => $kendaraan->aset?->pemegangSaatIni ? [
                'id_pegawai' => $kendaraan->aset->pemegangSaatIni->pegawai?->id_pegawai,
                'nama' => $kendaraan->aset->pemegangSaatIni->pegawai?->nama_pegawai,
            ] : null,
            // pajak aktif
            'pajak_aktif' => $kendaraan->pajakAktif?->id_pajak ?? null,
            'pajak_tanggal_berakhir' => $kendaraan->pajakAktif?->tanggal_berakhir?->format('Y-m-d'),
            'pajak_max_tahunan' => (bool) $kendaraan->pajakAktif?->pajak_5_tahunan,
            'pajak_nominal' => $kendaraan->pajakAktif?->nominal,
            'pajak_total' => $kendaraan->pajakAktif?->total_pajak,
        ]);
    }

    public function update(Request $request, Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'mengubah kendaraan');

        $data = TanggalAset::samakanUntukUpdate(
            $this->validateKendaraan($request, $kendaraan),
            $kendaraan->aset?->tanggal_pengadaan?->toDateString()
        );

        $aset = $kendaraan->aset;

        // Status aset SISTEM (`diusulkan_hapus`/`dihapuskan`) tidak boleh
        // diubah lewat form: nilai lama dipertahankan apa adanya sehingga
        // status sistem tidak pernah berubah OTOMATIS karena form disimpan.
        // Status user (aktif/nonaktif) tetap mengikuti pilihan admin.
        $statusSekarang = mb_strtolower(trim((string) ($aset->status_aset ?? '')));
        $statusAsetBaru = ($statusSekarang === '' || in_array($statusSekarang, self::STATUS_USER, true))
            ? $data['status_aset']
            : $aset->status_aset;

        // Seluruh penulisan dibungkus SATU transaksi: bila salah satu langkah
        // (aset/kendaraan/foto/plat/pajak) gagal, data master tidak pernah
        // tertinggal ter-update separuh jalan.
        DB::transaction(function () use ($data, $aset, $kendaraan, $request, $statusAsetBaru) {
            $asetData = [
                'nomor_kartu_barang' => $data['nomor_kartu_barang'] ?? $aset->nomor_kartu_barang,
                'merk' => $data['merk'] ?? null,
                'tanggal_habis_pakai' => $data['tanggal_habis_pakai'] ?? null,
                'nilai_perolehan' => $data['nilai_perolehan'] ?? null,
                'kondisi' => $data['kondisi'] ?? null,
                'status_aset' => $statusAsetBaru,
                'is_kendaraan' => true,
            ];

            if (array_key_exists('tanggal_pengadaan', $data)) {
                $asetData['tanggal_pengadaan'] = $data['tanggal_pengadaan'];
            }

            $aset->update($asetData);

            $kendaraan->update([
                'nama_kendaraan' => trim((string) ($data['nama_kendaraan'] ?? '')) ?: null,
                'jenis_kendaraan' => $data['jenis_kendaraan'] ?? null,
                'nomor_rangka' => $data['nomor_rangka'] ?? null,
                'nomor_mesin' => $data['nomor_mesin'] ?? null,
                'merk' => $data['merk'] ?? null,
                'tipe' => $data['tipe'] ?? null,
                // Pemegang sengaja TIDAK ikut diupdate dari request: pergantian
                // pemegang hanya sah melalui flow Mutasi (mutasiPemegang) atau
                // Penempatan Awal.
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            if ($request->hasFile('foto')) {
                if ($kendaraan->foto) {
                    Storage::disk('public')->delete($kendaraan->foto);
                }
                $kendaraan->update(['foto' => $this->storeFoto($request)]);
            }

            // Nomor polisi: bila diubah dari plat aktif, tercatat sebagai pergantian
            // pelat di RiwayatPlat (plat lama dinonaktifkan, riwayat tetap tersimpan).
            if (! empty($data['nomor_polisi'])) {
                $platTerbaru = trim(mb_strtoupper($data['nomor_polisi']));
                $platAktif = $kendaraan->platAktif;
                if (! $platAktif || trim(mb_strtoupper($platAktif->nomor_plat)) !== $platTerbaru) {
                    $tanggalBerlakuLama = $platAktif?->tanggal_berlaku;
                    if ($platAktif) {
                        $platAktif->update(['status' => 'Tidak Aktif']);
                    }
                    RiwayatPlat::create([
                        'id_kendaraan' => $kendaraan->id_kendaraan,
                        'nomor_plat' => $data['nomor_polisi'],
                        'tanggal_berlaku' => $tanggalBerlakuLama,
                        'ganti_plat' => false,
                        'status' => 'Aktif',
                    ]);
                }
            }

            $this->syncPajak($kendaraan, $request, $data);

            // Pemicu otomatis: mengubah kondisi kendaraan menjadi Rusak Berat
            // langsung mengusulkan penghapusan (anti-duplikat di service).
            $this->usulan()->usulkanKarenaRusakBerat(
                $aset,
                'Otomatis: kondisi kendaraan diubah menjadi Rusak Berat.'
            );
        });

        return response()->json(['success' => true, 'message' => 'Kendaraan berhasil diperbarui.']);
    }

    public function destroy(Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'mengusulkan penghapusan kendaraan');

        $aset = $kendaraan->aset;

        if (! $aset) {
            return response()->json([
                'success' => false,
                'message' => 'Data aset kendaraan tidak ditemukan.',
            ], 422);
        }

        if ($aset->status_aset !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya kendaraan berstatus aktif yang dapat diusulkan penghapusan.',
            ], 422);
        }

        if (UsulanPenghapusan::where('id_aset', $aset->id_aset)->where('status_usulan', 'diajukan')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Kendaraan sudah memiliki usulan penghapusan yang aktif.',
            ], 422);
        }

        // Bukan hard delete (SOP BMD): kendaraan & aset tetap tersimpan, hanya
        // status aset berubah sehingga hilang dari daftar aktif dan kondisi
        // ditandai Rusak Berat. Perubahan status + pencatatan usulan dibungkus
        // SATU transaksi supaya bila salah satu langkah gagal tidak pernah
        // tertinggal kondisi aset berubah tanpa usulan (atau sebaliknya).
        DB::transaction(function () use ($aset) {
            $aset->update([
                'status_aset' => 'diusulkan_hapus',
                'kondisi' => 'Rusak Berat',
            ]);

            UsulanPenghapusan::create([
                'id_aset' => $aset->id_aset,
                'id_pegawai_penghapus' => auth()->user()?->pegawai?->id_pegawai,
                'tanggal_usulan' => now()->toDateString(),
                'alasan_penghapusan' => 'Lainnya',
                'status_usulan' => 'diajukan',
                'keterangan' => 'Diusulkan melalui tombol hapus pada daftar kendaraan.',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Kendaraan diusulkan penghapusan dan dikeluarkan dari daftar aktif.',
        ]);
    }

    /**
     * Mutasi Pemegang Kendaraan: ganti pemegang kendaraan dinas ke pegawai lain.
     * Perbarui kolom `kendaraan.pemegang`, catat riwayat ke tabel
     * mutasi_aset + detail_mutasi_aset, lalu siapkan unduhan SPPKD & BAST.
     */
    public function mutasiPemegang(Request $request, Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'memutasi pemegang kendaraan');
        abort_unless(auth()->user()?->role?->nama_role === 'Admin Aset', 403);

        $data = $request->validate([
            'id_pegawai_baru' => ['required', 'exists:pegawai,id_pegawai'],
            'id_pegawai_lama' => ['nullable', 'integer'],
            'tanggal_mutasi' => ['nullable', 'date'],
            'nomor_surat' => ['nullable', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $expectedPemegangLamaId = $request->has('id_pegawai_lama') && $request->input('id_pegawai_lama') !== null && $request->input('id_pegawai_lama') !== ''
            ? (int) $request->input('id_pegawai_lama')
            : null;

        return DB::transaction(function () use ($kendaraan, $data, $expectedPemegangLamaId, $request) {
            // Urutan locking konsisten lintas modul:
            // 1. Entitas utama (Kendaraan -> Aset induk)
            $kendaraanLock = Kendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)
                ->lockForUpdate()
                ->firstOrFail();

            $aset = Aset::where('id_aset', $kendaraanLock->id_aset)
                ->lockForUpdate()
                ->first();

            if (! $aset || $aset->status_aset !== 'aktif') {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Mutasi pemegang hanya dapat dilakukan pada kendaraan berstatus aktif.',
                    ], 422);
                }

                return back()->with('error', 'Mutasi pemegang hanya dapat dilakukan pada kendaraan berstatus aktif.')->withInput();
            }

            // 2. Kunci relasi anak: PemegangAset aktif terlebih dahulu, kemudian PenempatanAset
            $pemegangAktif = PemegangAset::where('id_aset', $aset->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            $penempatanAktif = PenempatanAset::where('id_aset', $aset->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            if (! $penempatanAktif) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kendaraan belum memiliki penempatan aktif. Lakukan Penempatan Awal terlebih dahulu.',
                    ], 422);
                }

                return back()->with('error', 'Kendaraan belum memiliki penempatan aktif. Lakukan Penempatan Awal terlebih dahulu.')->withInput();
            }

            if (AsetPenempatan::kolomFinalisasiTersedia()) {
                if (is_null($penempatanAktif->is_finalized)) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Penempatan aktif berstatus Perlu Verifikasi. Lakukan Verifikasi & Finalisasi terlebih dahulu.',
                        ], 422);
                    }

                    return back()->with('error', 'Penempatan aktif berstatus Perlu Verifikasi. Lakukan Verifikasi & Finalisasi terlebih dahulu.')->withInput();
                }
                if ((int) $penempatanAktif->is_finalized === 0) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Penempatan aktif belum difinalisasi. Finalisasi terlebih dahulu agar dapat dimutasi.',
                        ], 422);
                    }

                    return back()->with('error', 'Penempatan aktif belum difinalisasi. Finalisasi terlebih dahulu agar dapat dimutasi.')->withInput();
                }
            }

            $pegawaiBaru = Pegawai::findOrFail($data['id_pegawai_baru']);

            // Sumber identitas pemegang lama: relasi PemegangAset AKTIF adalah
            // sumber utama. `kendaraan.pemegang` (teks bebas) hanya dipakai
            // sebagai fallback bila relasi aktif tidak tersedia (mis. data lama
            // yang belum tercatat di PemegangAset).
            $pegawaiLama = $pemegangAktif?->pegawai;
            if (! $pegawaiLama && $kendaraanLock->pemegang) {
                $pegawaiLama = Pegawai::where('nama_pegawai', $kendaraanLock->pemegang)->first();
            }

            // Validasi lost update: pastikan pemegang aktif saat transaksi dieksekusi
            // masih sama dengan pemegang yang ditampilkan saat formulir dibuka.
            $currentPemegangId = $pegawaiLama?->id_pegawai;
            if ($expectedPemegangLamaId !== null && (int) ($currentPemegangId ?? 0) !== $expectedPemegangLamaId) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data pemegang kendaraan telah berubah oleh proses lain. Silakan muat ulang halaman.',
                    ], 422);
                }

                return back()->with('error', 'Data pemegang kendaraan telah berubah oleh proses lain. Silakan muat ulang halaman.')->withInput();
            }

            if ($pegawaiLama && (int) $pegawaiLama->id_pegawai === (int) $pegawaiBaru->id_pegawai) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pemegang baru tidak boleh sama dengan pemegang lama.',
                        'errors' => ['id_pegawai_baru' => ['Pemegang baru tidak boleh sama dengan pemegang lama.']],
                    ], 422);
                }

                return back()->with('error', 'Pemegang baru tidak boleh sama dengan pemegang lama.')->withInput();
            }

            $nomorSurat = trim($data['nomor_surat'] ?? '');
            $catatan = trim($data['keterangan'] ?? '');
            $keterangan = 'Serah terima dari '.($kendaraanLock->pemegang ?: 'Pemegang lama');
            if ($nomorSurat !== '') {
                $keterangan .= '. Nomor Surat: '.$nomorSurat;
            }
            if ($catatan !== '') {
                $keterangan .= '. '.$catatan;
            }

            $mutasi = MutasiAset::create([
                'tanggal_mutasi' => $data['tanggal_mutasi'] ?? now()->toDateString(),
                'jenis_mutasi' => 'Ganti Pemegang',
                'keterangan' => $keterangan,
                'id_user_penginput' => auth()->id(),
                'status_mutasi' => 'selesai',
            ]);

            DetailMutasiAset::create([
                'id_mutasi' => $mutasi->id_mutasi,
                'id_aset' => $aset->id_aset,
                'pegawai_lama' => $pegawaiLama?->id_pegawai,
                'pegawai_baru' => $pegawaiBaru->id_pegawai,
                'ruangan_lama' => null,
                'ruangan_baru' => null,
            ]);

            $kendaraanLock->update(['pemegang' => $pegawaiBaru->nama_pegawai]);

            // Ganti pemegang pada relasi PemegangAset (sumber identitas pemegang):
            // tutup yang aktif, buka yang baru. Konsisten dengan mutatePemegang().
            $now = now()->toDateString();
            if ($pemegangAktif) {
                $pemegangAktif->update(['status' => 'tidak aktif', 'tanggal_selesai' => $now]);
            } else {
                PemegangAset::where('id_aset', $aset->id_aset)
                    ->where('status', 'aktif')
                    ->update(['status' => 'tidak aktif', 'tanggal_selesai' => $now]);
            }

            PemegangAset::create([
                'id_aset' => $aset->id_aset,
                'id_pegawai' => $pegawaiBaru->id_pegawai,
                'tanggal_mulai' => $now,
                'status' => 'aktif',
            ]);

            // Mutasi ganti pemegang: penempatan baru langsung difinalisasi (pola Aset Barang)
            if (AsetPenempatan::kolomFinalisasiTersedia()) {
                $ruanganEfektif = AsetPenempatan::ruanganKerjaPemegang($pegawaiBaru->id_pegawai) ?? $penempatanAktif->id_ruangan;
                // Sinkronkan aset.id_skpd/id_lokasi + pastikan difinalisasi
                AsetPenempatan::sinkronAset($aset, $pegawaiBaru->id_pegawai, $ruanganEfektif);
                $penempatanAktif->update(['status' => 'tidak aktif', 'tanggal_selesai' => now()->toDateString()]);
                $aset->placeAtRoom($ruanganEfektif, true);
            } else {
                // Fallback: tetap update pemegang
                if (method_exists($aset, 'placeAtRoom') && $penempatanAktif->id_ruangan) {
                    $aset->placeAtRoom($penempatanAktif->id_ruangan, true);
                }
            }

            // Simpan id mutasi untuk modal unduh dokumen di halaman index.
            session()->flash('mutasi_kendaraan_id', $mutasi->id_mutasi);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pemegang kendaraan berhasil diganti ke '.$pegawaiBaru->nama_pegawai.'.',
                    'download_bast_url' => route('kendaraan.bast.download', $mutasi->id_mutasi),
                    'id_mutasi' => $mutasi->id_mutasi,
                ]);
            }

            return back()->with('success', 'Pemegang kendaraan berhasil diganti ke '.$pegawaiBaru->nama_pegawai.'.');
        });
    }

    /**
     * Penempatan awal kendaraan yang belum memiliki penempatan aktif / pemegang aktif.
     */
    public function penempatanAwal(Request $request, Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'melakukan penempatan awal');
        abort_unless(auth()->user()?->role?->nama_role === 'Admin Aset', 403);

        $aset = $kendaraan->aset;
        if (! $aset || $aset->status_aset !== 'aktif') {
            throw ValidationException::withMessages([
                'id_aset' => 'Hanya kendaraan berstatus aktif yang dapat ditempatkan.',
            ]);
        }

        if ($aset->pemegangSaatIni()->exists()) {
            throw ValidationException::withMessages([
                'id_aset' => 'Kendaraan sudah memiliki pemegang aktif. Gunakan Mutasi untuk mengganti pemegang atau memindahkan ruangan.',
            ]);
        }

        if ($aset->penempatanAktif()->exists()) {
            throw ValidationException::withMessages([
                'id_aset' => 'Kendaraan sudah memiliki penempatan aktif.',
            ]);
        }

        $mode = $request->input('mode') === 'ruangan' ? 'ruangan' : 'pegawai';

        if ($mode === 'ruangan') {
            $validated = $request->validate([
                'id_ruangan' => ['required', 'exists:ruangan,id_ruangan'],
            ], [
                'id_ruangan.required' => 'Ruangan wajib dipilih.',
                'id_ruangan.exists' => 'Ruangan yang dipilih tidak ditemukan.',
            ]);
            $pegawai = null;
            $ruanganId = (int) $validated['id_ruangan'];
        } else {
            $validated = $request->validate([
                'id_pegawai' => ['required', 'exists:pegawai,id_pegawai'],
            ], [
                'id_pegawai.required' => 'Pemegang wajib dipilih.',
                'id_pegawai.exists' => 'Pegawai yang dipilih tidak ditemukan.',
            ]);
            $pegawai = Pegawai::findOrFail($validated['id_pegawai']);
            $ruanganId = AsetPenempatan::ruanganKerjaPemegang($pegawai->id_pegawai);
        }

        $result = DB::transaction(function () use ($aset, $pegawai, $ruanganId) {
            $asetLock = Aset::where('id_aset', $aset->id_aset)->lockForUpdate()->first();

            if (! $asetLock || $asetLock->status_aset !== 'aktif') {
                throw ValidationException::withMessages([
                    'id_aset' => 'Hanya kendaraan berstatus aktif yang dapat ditempatkan.',
                ]);
            }

            if ($asetLock->pemegangSaatIni()->exists()) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Kendaraan sudah memiliki pemegang aktif. Gunakan Mutasi untuk mengganti pemegang atau memindahkan ruangan.',
                ]);
            }

            if ($asetLock->penempatanAktif()->exists()) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Kendaraan sudah memiliki penempatan aktif.',
                ]);
            }

            if ($pegawai) {
                PemegangAset::create([
                    'id_aset' => $asetLock->id_aset,
                    'id_pegawai' => $pegawai->id_pegawai,
                    'tanggal_mulai' => now()->toDateString(),
                    'status' => 'aktif',
                ]);
            }

            if ($ruanganId) {
                // authorize ruangan
                $this->authorizeRuangan($ruanganId, 'melakukan penempatan awal');
                // place
                if (method_exists($asetLock, 'placeAtRoom')) {
                    $asetLock->placeAtRoom($ruanganId);
                } else {
                    PenempatanAset::where('id_aset', $asetLock->id_aset)
                        ->where('status', 'aktif')
                        ->update(['status' => 'tidak aktif', 'tanggal_selesai' => now()->toDateString()]);

                    PenempatanAset::create([
                        'id_aset' => $asetLock->id_aset,
                        'id_ruangan' => $ruanganId,
                        'tanggal_mulai' => now()->toDateString(),
                        'status' => 'aktif',
                    ]);
                }

                if (Schema::hasColumn('penempatan_aset', 'is_finalized')) {
                    PenempatanAset::where('id_aset', $asetLock->id_aset)
                        ->where('status', 'aktif')
                        ->update(['is_finalized' => 0]);
                }
            }

            AsetPenempatan::sinkronAset($asetLock, $pegawai?->id_pegawai, $ruanganId);

            if (isset($kendaraan)) {
                $kendaraan->update(['pemegang' => $pegawai?->nama_pegawai ?? $kendaraan->pemegang]);
            }

            return $asetLock->fresh();
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Penempatan awal berhasil disimpan.',
                'warning' => $ruanganId ? null : 'Pegawai belum punya ruangan. Kendaraan akan tampil tanpa ruangan sampai ruangan pegawai diisi.',
                'id_ruangan' => $ruanganId,
            ]);
        }

        return back()->with('success', 'Penempatan awal berhasil disimpan.');
    }

    private function authorizeRuangan(?int $idRuangan, string $aksi = 'menggunakan ruangan'): void
    {
        if ($idRuangan === null) {
            return;
        }

        abort_unless(
            Ruangan::where('id_ruangan', $idRuangan)->where('status', 'Aktif')->exists(),
            422,
            'Ruangan tujuan tidak ditemukan atau tidak aktif.'
        );

        abort_unless(
            $this->scope->bolehAksesRuangan($idRuangan),
            403,
            $this->scope->pesanAkses($aksi)
        );
    }

    /**
     * Finalisasi penempatan aktif untuk SATU kendaraan.
     *
     * Menjalankan DB::transaction() dengan lockForUpdate() pada PenempatanAset aktif
     * untuk mencegah race condition / pembaruan ganda.
     * Melempar ValidationException jika status tidak memenuhi syarat.
     */
    private function finalisasiPenempatanKendaraanSatu(Kendaraan $kendaraan): PenempatanAset
    {
        $aset = $kendaraan->aset;
        if (! $aset) {
            throw ValidationException::withMessages([
                'aset' => 'Data aset tidak ditemukan untuk kendaraan ini.',
            ]);
        }

        return DB::transaction(function () use ($aset) {
            $penempatan = PenempatanAset::where('id_aset', $aset->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            if (! $penempatan) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Tidak ada penempatan aktif.',
                ]);
            }

            if (is_null($penempatan->is_finalized)) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Perlu verifikasi terlebih dahulu.',
                ]);
            }

            if ((int) $penempatan->is_finalized === 1) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Penempatan sudah difinalisasi.',
                ]);
            }

            $penempatan->update([
                'is_finalized' => 1,
                'finalized_at' => now(),
                'finalized_by' => auth()->id(),
            ]);

            return $penempatan->fresh();
        });
    }

    public function finalisasiPenempatan(Request $request, Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'memfinalisasi penempatan');
        abort_unless(
            $this->scope->isAdminAset() || $this->scope->isAdminUpt(),
            403,
            'Hanya Admin Aset dan Admin UPT yang berwenang memfinalisasi penempatan kendaraan.'
        );

        $aset = $kendaraan->aset;
        if (! $aset) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Data aset tidak ditemukan.'], 422)
                : back()->with('error', 'Data aset tidak ditemukan.');
        }

        if (! AsetPenempatan::kolomFinalisasiTersedia()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Kolom finalisasi tidak tersedia.'], 422)
                : back()->with('error', 'Kolom finalisasi tidak tersedia.');
        }

        try {
            $this->finalisasiPenempatanKendaraanSatu($kendaraan);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Penempatan tidak dapat difinalisasi.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        } catch (\Throwable $e) {
            report($e);

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem saat memfinalisasi penempatan.'], 500)
                : back()->with('error', 'Terjadi kesalahan sistem saat memfinalisasi penempatan.');
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Penempatan berhasil difinalisasi.']);
        }

        return back()->with('success', 'Penempatan berhasil difinalisasi.');
    }

    public function verifikasiFinalisasiPenempatan(Request $request, Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'memverifikasi penempatan');
        abort_unless(
            $this->scope->isAdminAset() || $this->scope->isAdminUpt(),
            403,
            'Hanya Admin Aset dan Admin UPT yang berwenang memverifikasi penempatan kendaraan.'
        );

        $aset = $kendaraan->aset;
        if (! $aset) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Data aset tidak ditemukan.'], 422)
                : back()->with('error', 'Data aset tidak ditemukan.');
        }

        if (! AsetPenempatan::kolomFinalisasiTersedia()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Kolom finalisasi tidak tersedia.'], 422)
                : back()->with('error', 'Kolom finalisasi tidak tersedia.');
        }

        try {
            DB::transaction(function () use ($aset) {
                $penempatan = PenempatanAset::where('id_aset', $aset->id_aset)
                    ->where('status', 'aktif')
                    ->lockForUpdate()
                    ->first();

                if (! $penempatan) {
                    throw ValidationException::withMessages([
                        'penempatan' => 'Tidak ada penempatan aktif.',
                    ]);
                }

                if (! is_null($penempatan->is_finalized)) {
                    throw ValidationException::withMessages([
                        'penempatan' => 'Hanya penempatan berstatus Perlu Verifikasi yang dapat diverifikasi.',
                    ]);
                }

                $penempatan->update([
                    'is_finalized' => 0,
                ]);
            });
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Penempatan tidak dapat diverifikasi.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        } catch (\Throwable $e) {
            report($e);

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem saat memverifikasi penempatan.'], 500)
                : back()->with('error', 'Terjadi kesalahan sistem saat memverifikasi penempatan.');
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Penempatan dinyatakan perlu difinalisasi.']);
        }

        return back()->with('success', 'Penempatan dinyatakan perlu difinalisasi.');
    }

    public function finalisasiPenempatanMassal(Request $request)
    {
        abort_unless(
            $this->scope->isAdminAset() || $this->scope->isAdminUpt(),
            403,
            'Hanya Admin Aset dan Admin UPT yang berwenang memfinalisasi penempatan kendaraan.'
        );

        if (! AsetPenempatan::kolomFinalisasiTersedia()) {
            return response()->json(['success' => false, 'message' => 'Kolom finalisasi tidak tersedia.'], 422);
        }

        $rawIds = $request->input('ids', $request->input('id_kendaraan', []));
        if (! is_array($rawIds) || empty($rawIds)) {
            return response()->json(['success' => false, 'message' => 'Pilih minimal satu kendaraan untuk difinalisasi.'], 422);
        }

        if (count($rawIds) > 100) {
            return response()->json(['success' => false, 'message' => 'Maksimal 100 kendaraan dapat difinalisasi sekaligus.'], 422);
        }

        $berhasil = [];
        $gagal = [];
        $uniqueIds = [];
        $duplikatIds = [];

        foreach ($rawIds as $raw) {
            if (! is_numeric($raw) || (int) $raw <= 0) {
                $gagal[] = [
                    'id_kendaraan' => $raw,
                    'nama' => 'ID #'.$raw,
                    'alasan' => 'ID kendaraan tidak valid.',
                ];
                continue;
            }
            $id = (int) $raw;
            if (isset($uniqueIds[$id])) {
                $duplikatIds[] = $id;
                continue;
            }
            $uniqueIds[$id] = true;
        }

        foreach ($duplikatIds as $dupId) {
            $gagal[] = [
                'id_kendaraan' => $dupId,
                'nama' => 'Kendaraan #'.$dupId,
                'alasan' => 'ID kendaraan duplikat diabaikan.',
            ];
        }

        foreach (array_keys($uniqueIds) as $id) {
            $kendaraan = Kendaraan::with(['aset.penempatanAktif', 'platAktif'])->find($id);
            if (! $kendaraan || ! $kendaraan->aset) {
                $gagal[] = [
                    'id_kendaraan' => $id,
                    'nama' => 'Kendaraan #'.$id,
                    'alasan' => 'Data kendaraan atau aset tidak ditemukan.',
                ];
                continue;
            }

            $nama = $kendaraan->namaTampil() ?: ('Kendaraan #'.$id);
            if ($kendaraan->platAktif?->nomor_plat) {
                $nama .= ' ('.$kendaraan->platAktif->nomor_plat.')';
            }

            // Otorisasi cakupan: kendaraan di luar cakupan DITOLAK
            if (! $this->scope->bolehAksesAset($kendaraan->aset)) {
                $gagal[] = [
                    'id_kendaraan' => $id,
                    'nama' => $nama,
                    'alasan' => $this->scope->pesanAkses('memfinalisasi penempatan'),
                ];
                continue;
            }

            try {
                $penempatan = $this->finalisasiPenempatanKendaraanSatu($kendaraan);

                $berhasil[] = [
                    'id_kendaraan' => $id,
                    'nama' => $nama,
                    'finalized_at' => $penempatan->finalized_at?->toDateTimeString(),
                ];
            } catch (ValidationException $e) {
                $gagal[] = [
                    'id_kendaraan' => $id,
                    'nama' => $nama,
                    'alasan' => collect($e->errors())->flatten()->first() ?: 'Tidak memenuhi syarat finalisasi.',
                ];
            } catch (\Throwable $e) {
                report($e);
                $gagal[] = [
                    'id_kendaraan' => $id,
                    'nama' => $nama,
                    'alasan' => 'Terjadi kesalahan sistem saat memfinalisasi.',
                ];
            }
        }

        $countBerhasil = count($berhasil);
        $countGagal = count($gagal);

        $message = "Berhasil memfinalisasi {$countBerhasil} penempatan.";
        if ($countGagal > 0) {
            $message .= " Gagal/dilewati {$countGagal}.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'berhasil' => $countBerhasil,
            'gagal' => $countGagal,
            'finalized' => $countBerhasil,
            'skipped' => $countGagal,
            'diproses' => $countBerhasil,
            'processed' => $countBerhasil,
            'detail_berhasil' => $berhasil,
            'detail_gagal' => $gagal,
        ]);
    }

    /**
     * Edit pemegang penempatan aktif kendaraan (bukan mutasi).
     *
     * Memperbaiki pemegang (pemegang aktif pada penempatan aktif) kendaraan yang
     * masih Belum Difinalisasi (is_finalized = 0). Ruangan penempatan mengikuti
     * ruang kerja pemegain yang dipilih dan disinkronkan via `AsetPenempatan`.
     *
     * Penempatan lama (is_finalized NULL / Perlu Verifikasi) dan yang sudah
     * difinalisasi TIDAK dapat diedit.
     */
    public function editPemegangPenempatan(Request $request, Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan, 'mengedit penempatan kendaraan');
        abort_unless(
            $this->scope->isAdminAset() || $this->scope->isAdminUpt(),
            403,
            'Hanya Admin Aset dan Admin UPT yang berwenang mengedit penempatan kendaraan.'
        );

        $validated = $request->validate([
            'id_pegawai' => ['required', 'exists:pegawai,id_pegawai'],
        ], [
            'id_pegawai.required' => 'Pilih pemegang dari master Pegawai.',
            'id_pegawai.exists' => 'Pemegang yang dipilih tidak ditemukan.',
        ]);

        $aset = $kendaraan->aset;
        if (! $aset) {
            return response()->json([
                'success' => false,
                'message' => 'Data aset tidak ditemukan.',
            ], 422);
        }

        if (! AsetPenempatan::kolomFinalisasiTersedia()) {
            return response()->json(['success' => false, 'message' => 'Kolom finalisasi tidak tersedia.'], 422);
        }

        $pegawaiBaru = Pegawai::findOrFail($validated['id_pegawai']);

        // Untuk Admin UPT: pegawai baru wajib berada dalam cakupan UPT-nya
        // (berdasarkan lokasi fisik ruang kerja pegawai dan/atau id_skpd pegawai).
        // Pegawai dari Dinas atau UPT lain langsung ditolak dengan 403,
        // termasuk bila pegawai baru belum memiliki ruang kerja.
        if ($this->scope->isAdminUpt()) {
            $idLokasiCakupan = $this->scope->idLokasi() ?: [];
            $pegawaiLokasi = $pegawaiBaru->ruangan?->id_lokasi;
            $pegawaiSkpd = $pegawaiBaru->id_skpd;
            $dalamCakupan = ($pegawaiLokasi && in_array($pegawaiLokasi, $idLokasiCakupan, true))
                || ($pegawaiSkpd && (int) $pegawaiSkpd === (int) $this->scope->skpdId());
            abort_unless($dalamCakupan, 403, 'Pegawai ini berada di luar cakupan UPT Anda.');
        }

        $result = DB::transaction(function () use ($aset, $pegawaiBaru) {
            $asetLock = Aset::where('id_aset', $aset->id_aset)->lockForUpdate()->first();

            $penempatan = PenempatanAset::where('id_aset', $asetLock->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            if (! $penempatan) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Penempatan aktif tidak ditemukan untuk kendaraan ini.',
                ]);
            }

            if (is_null($penempatan->is_finalized)) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Penempatan berstatus Perlu Verifikasi dan tidak dapat diedit dari daftar ini. Gunakan Verifikasi & Finalisasi.',
                ]);
            }

            if ((int) $penempatan->is_finalized === 1) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Penempatan sudah difinalisasi dan tidak dapat diedit. Gunakan Mutasi bila ingin pindah pemegang.',
                ]);
            }

            $pemegangAktif = PemegangAset::where('id_aset', $asetLock->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            if (! $pemegangAktif) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Aset ini tidak memiliki pemegang aktif. Gunakan Penempatan Awal.',
                ]);
            }

            if ((int) $pemegangAktif->id_pegawai === (int) $pegawaiBaru->id_pegawai) {
                return [
                    'message' => 'Pemegang aset sudah '.$pegawaiBaru->nama_pegawai.'. Tidak ada perubahan.',
                    'warning' => null,
                ];
            }

            $ruanganBaru = AsetPenempatan::ruanganKerjaPemegang($pegawaiBaru->id_pegawai);
            if ($ruanganBaru) {
                abort_unless(
                    $this->scope->bolehAksesRuangan($ruanganBaru),
                    403,
                    $this->scope->pesanAkses('mengedit penempatan kendaraan')
                );
            }

            // Perbaikan data pemegang: record AKTIF yang sama diperbarui,
            // bukan membuat record baru (dan bukan mutasi).
            $pemegangAktif->update([
                'id_pegawai' => $pegawaiBaru->id_pegawai,
                'tanggal_mulai' => now()->toDateString(),
                'tanggal_selesai' => null,
                'status' => 'aktif',
            ]);

            $warning = null;
            $ruanganEfektif = $penempatan->id_ruangan;

            if ($ruanganBaru && (int) $penempatan->id_ruangan !== (int) $ruanganBaru) {
                $penempatan->update(['id_ruangan' => $ruanganBaru]);
                $ruanganEfektif = $ruanganBaru;
            }

            if (! $ruanganBaru) {
                $warning = 'Pemegang baru belum memiliki ruang kerja. Ruangan aset tidak diubah.';
            }

            // SKPD & Lokasi mengikuti master (pemegang baru + effective room).
            AsetPenempatan::sinkronAset($asetLock, $pegawaiBaru->id_pegawai, $ruanganEfektif);

            return [
                'message' => 'Pemegang kendaraan diperbaiki menjadi '.$pegawaiBaru->nama_pegawai.'.',
                'warning' => $warning,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'warning' => $result['warning'] ?? null,
        ]);
    }

    // ---------- Import ----------

    /**
     * Impor data kendaraan secara massal dari Excel.
     *
     * Aturan final (lihat handoff):
     * - Duplicate identity: nomor_rangka / nomor_mesin (bukan Nomor Polisi).
     * - Mode SKIP  -> tidak mengubah apa pun.
     * - Mode UPDATE -> hanya field master kendaraan; TIDAK boleh menyentuh
     *   PajakKendaraan dan RiwayatPlat. Pajak & plat kendaraan existing hanya
     *   berubah lewat flow Pajak Kendaraan (Pajak 5 Tahunan).
     * - Kendaraan BARU -> boleh membuat PajakKendaraan + Riwayat Plat awal.
     * - Satu row = satu DB::transaction; row gagal tidak meninggalkan sampah.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls', 'max:5120'],
            'mode' => ['required', 'in:skip,update'],
        ]);

        $mode = $request->input('mode');

        $errors = [];
        $warnings = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // Baris header = baris pertama yang dikenali minimal 3 header sah (cari hingga baris 15)
        $headerRow = null;
        $colMap = [];
        foreach ($rows as $index => $row) {
            if ($index > 15) {
                break;
            }
            $map = $this->buildHeaderMap($row);
            if (count($map) >= 3) {
                $headerRow = $index;
                $colMap = $map;
                break;
            }
        }

        if ($headerRow === null) {
            return back()->with('error', 'Header kolom tidak ditemukan pada 15 baris pertama. Pastikan file sesuai template import kendaraan.');
        }

        // Kumpulkan foto dari objek gambar tersisip, kelompokkan per baris data.
        $photosByRow = $this->collectPhotos($sheet);

        // Tracking duplikasi antarbaris di dalam file Excel yang sama
        $fileKartuMap = [];
        $fileRangkaMap = [];
        $fileMesinMap = [];

        foreach ($rows as $index => $row) {
            if ($index <= $headerRow) {
                continue;
            }

            // Lewati baris contoh bawaan template & baris kosong.
            if ($this->isContohRow($row) || ! $this->rowBerisi($row, $colMap)) {
                continue;
            }

            // Ambil data sel dari baris Excel
            $kartu = $this->cell($row, $colMap['kartu'] ?? '');
            $nama = $this->cell($row, $colMap['nama'] ?? '');
            $jenis = $this->cell($row, $colMap['jenis'] ?? '');
            $merk = $this->cell($row, $colMap['merk'] ?? '');
            $tipe = $this->cell($row, $colMap['tipe'] ?? '');
            $platRaw = $this->cell($row, $colMap['plat'] ?? '');
            $rangka = $this->cell($row, $colMap['rangka'] ?? '');
            $mesin = $this->cell($row, $colMap['mesin'] ?? '');
            $tglPengadaanRaw = $this->cell($row, $colMap['tgl_pengadaan'] ?? '');
            $tglHabisRaw = $this->cell($row, $colMap['tgl_habis_pakai'] ?? '');
            $nilaiRaw = $this->cell($row, $colMap['nilai'] ?? '');
            $kondisiRaw = $this->cell($row, $colMap['kondisi'] ?? '');
            $statusAsetRaw = $this->cell($row, $colMap['status_aset'] ?? '');
            $keterangan = $this->cell($row, $colMap['keterangan'] ?? '');

            // Kolom pajak (opsional)
            $jt1 = $this->cell($row, $colMap['pajak1'] ?? '');
            $jt5 = $this->cell($row, $colMap['pajak5'] ?? '');
            $nominal = $this->cell($row, $colMap['nominal'] ?? '');
            $total = $this->cell($row, $colMap['total'] ?? '');
            $flag5 = $this->parseYaTidak($this->cell($row, $colMap['tahunan'] ?? ''));

            $legacyPajak = $this->cell($row, $colMap['pajak'] ?? '');
            if ($legacyPajak) {
                if ($flag5 && ! $jt5) {
                    $jt5 = $legacyPajak;
                } elseif (! $flag5 && ! $jt1) {
                    $jt1 = $legacyPajak;
                }
            }

            // ==========================================
            // 1. VALIDASI 10 FIELD WAJIB
            // ==========================================

            // 1. Nomor Kartu Barang (Wajib, string, maks 255)
            if ($kartu === null) {
                $errors[] = "Baris {$index}: Nomor Kartu Barang wajib diisi.";
                continue;
            }
            if (mb_strlen($kartu) > 255) {
                $errors[] = "Baris {$index}: Nomor Kartu Barang melebihi 255 karakter.";
                continue;
            }

            // 2. Nama Kendaraan (Wajib, string, maks 150)
            if ($nama === null) {
                $errors[] = "Baris {$index}: Nama Kendaraan wajib diisi.";
                continue;
            }
            if (mb_strlen($nama) > 150) {
                $errors[] = "Baris {$index}: Nama Kendaraan melebihi 150 karakter.";
                continue;
            }

            // 3. Nomor Polisi (Wajib, string, maks 20)
            if ($platRaw === null) {
                $errors[] = "Baris {$index}: Nomor Polisi wajib diisi.";
                continue;
            }
            if (mb_strlen($platRaw) > 20) {
                $errors[] = "Baris {$index}: Nomor Polisi melebihi 20 karakter.";
                continue;
            }

            // 4. Nomor Rangka (Wajib, string, maks 100)
            if ($rangka === null) {
                $errors[] = "Baris {$index}: Nomor Rangka wajib diisi.";
                continue;
            }
            if (mb_strlen($rangka) > 100) {
                $errors[] = "Baris {$index}: Nomor Rangka melebihi 100 karakter.";
                continue;
            }

            // 5. Nomor Mesin (Wajib, string, maks 100)
            if ($mesin === null) {
                $errors[] = "Baris {$index}: Nomor Mesin wajib diisi.";
                continue;
            }
            if (mb_strlen($mesin) > 100) {
                $errors[] = "Baris {$index}: Nomor Mesin melebihi 100 karakter.";
                continue;
            }

            // 6. Tanggal Pengadaan (Wajib, tanggal valid)
            if ($tglPengadaanRaw === null) {
                $errors[] = "Baris {$index}: Tanggal Pengadaan wajib diisi.";
                continue;
            }
            $tglPengadaanDate = $this->parseDate($tglPengadaanRaw);
            if ($tglPengadaanDate === null) {
                $errors[] = "Baris {$index}: Tanggal Pengadaan ('{$tglPengadaanRaw}') tidak valid. Gunakan format DD/MM/YYYY.";
                continue;
            }

            // 7. Tanggal Habis Pakai (Wajib, tanggal valid, > tanggal pengadaan)
            if ($tglHabisRaw === null) {
                $errors[] = "Baris {$index}: Tanggal Habis Pakai wajib diisi.";
                continue;
            }
            $tglHabisDate = $this->parseDate($tglHabisRaw);
            if ($tglHabisDate === null) {
                $errors[] = "Baris {$index}: Tanggal Habis Pakai ('{$tglHabisRaw}') tidak valid. Gunakan format DD/MM/YYYY.";
                continue;
            }
            if ($tglHabisDate <= $tglPengadaanDate) {
                $errors[] = "Baris {$index}: Tanggal Habis Pakai ('{$tglHabisRaw}') harus lebih besar dari Tanggal Pengadaan ('{$tglPengadaanRaw}').";
                continue;
            }

            // 8. Nilai Perolehan (Wajib, numeric, > 0)
            if ($nilaiRaw === null) {
                $errors[] = "Baris {$index}: Nilai Perolehan wajib diisi.";
                continue;
            }
            $nilaiPerolehan = $this->parseNilaiPerolehan($nilaiRaw);
            if ($nilaiPerolehan === null) {
                $errors[] = "Baris {$index}: Nilai Perolehan ('{$nilaiRaw}') harus berupa angka yang valid.";
                continue;
            }
            if ($nilaiPerolehan <= 0) {
                $errors[] = "Baris {$index}: Nilai Perolehan harus lebih besar dari Rp0.";
                continue;
            }

            // 9. Kondisi (Wajib, in: Baik, Rusak Ringan, Rusak Berat)
            if ($kondisiRaw === null) {
                $errors[] = "Baris {$index}: Kondisi wajib diisi.";
                continue;
            }
            $kondisi = $this->normalizeKondisi($kondisiRaw);
            if ($kondisi === null) {
                $errors[] = "Baris {$index}: Kondisi ('{$kondisiRaw}') tidak valid. Pilih antara Baik, Rusak Ringan, atau Rusak Berat.";
                continue;
            }

            // 10. Status Aset (Wajib, in: aktif, nonaktif)
            if ($statusAsetRaw === null) {
                $errors[] = "Baris {$index}: Status Aset wajib diisi.";
                continue;
            }
            $statusClean = str_replace(['-', ' '], '', mb_strtolower(trim($statusAsetRaw)));
            if (! in_array($statusClean, self::STATUS_USER, true)) {
                $errors[] = "Baris {$index}: Status Aset ('{$statusAsetRaw}') tidak valid. Pilih antara aktif atau nonaktif.";
                continue;
            }
            $statusAset = $statusClean;

            // Validasi batas panjang kolom opsional
            if ($jenis !== null && mb_strlen($jenis) > 150) {
                $errors[] = "Baris {$index}: Jenis Kendaraan melebihi 150 karakter.";
                continue;
            }
            if ($merk !== null && mb_strlen($merk) > 100) {
                $errors[] = "Baris {$index}: Merk melebihi 100 karakter.";
                continue;
            }
            if ($tipe !== null && mb_strlen($tipe) > 50) {
                $errors[] = "Baris {$index}: Tipe melebihi 50 karakter.";
                continue;
            }
            if ($keterangan !== null && mb_strlen($keterangan) > 1000) {
                $errors[] = "Baris {$index}: Keterangan melebihi 1000 karakter.";
                continue;
            }

            // Validasi data pajak opsional
            $jt1Date = $jt1 ? $this->parseDate($jt1) : null;
            $jt5Date = $jt5 ? $this->parseDate($jt5) : null;
            if ($flag5 && ! $jt5Date) {
                $errors[] = "Baris {$index}: Pajak 5 Tahunan = Iya tetapi Jatuh Tempo Pajak 5 Tahun kosong.";
                continue;
            }
            $nominalVal = $nominal !== null ? $this->toNumber($nominal) : null;
            $totalVal = $total !== null ? $this->toNumber($total) : null;

            // ==========================================
            // 2. CEK DUPLIKASI ANTARBARIS DALAM FILE
            // ==========================================
            $kartuKey = mb_strtolower($kartu);
            if (isset($fileKartuMap[$kartuKey])) {
                $errors[] = "Baris {$index}: Duplikasi dalam file — Nomor Kartu Barang '{$kartu}' sudah digunakan pada baris {$fileKartuMap[$kartuKey]}.";
                continue;
            }
            $fileKartuMap[$kartuKey] = $index;

            $rangkaKey = mb_strtolower($rangka);
            if (isset($fileRangkaMap[$rangkaKey])) {
                $errors[] = "Baris {$index}: Duplikasi dalam file — Nomor Rangka '{$rangka}' sudah digunakan pada baris {$fileRangkaMap[$rangkaKey]}.";
                continue;
            }
            $fileRangkaMap[$rangkaKey] = $index;

            $mesinKey = mb_strtolower($mesin);
            if (isset($fileMesinMap[$mesinKey])) {
                $errors[] = "Baris {$index}: Duplikasi dalam file — Nomor Mesin '{$mesin}' sudah digunakan pada baris {$fileMesinMap[$mesinKey]}.";
                continue;
            }
            $fileMesinMap[$mesinKey] = $index;

            // ==========================================
            // 3. IDENTITAS DATABASE & MODE UPDATE / SKIP
            // Berdasarkan Keputusan Final: Nomor Kartu Barang adalah
            // IDENTITAS UTAMA untuk menentukan kendaraan yang diperbarui / dilewati.
            // ==========================================
            $existingAset = Aset::where('nomor_kartu_barang', $kartu)->first();

            if ($existingAset) {
                // Cari kendaraan terkait dari aset yang cocok
                $existingKendaraan = Kendaraan::where('id_aset', $existingAset->id_aset)->first();

                if (! $existingKendaraan) {
                    $errors[] = "Baris {$index}: Nomor Kartu Barang '{$kartu}' sudah terdaftar pada aset non-kendaraan di sistem.";
                    continue;
                }

                // Skenario 1: Mode LEWATI (skip)
                if ($mode === 'skip') {
                    $skipped++;
                    continue;
                }

                // Skenario 2: Mode UPDATE
                // Periksa apakah No. Rangka yang diinput sudah digunakan oleh kendaraan LAIN
                $konflikRangka = Kendaraan::where('nomor_rangka', $rangka)
                    ->where('id_kendaraan', '!=', $existingKendaraan->id_kendaraan)
                    ->first();
                if ($konflikRangka) {
                    $errors[] = "Baris {$index}: Konflik Nomor Rangka — '{$rangka}' sudah digunakan oleh kendaraan lain di sistem.";
                    continue;
                }

                // Periksa apakah No. Mesin yang diinput sudah digunakan oleh kendaraan LAIN
                $konflikMesin = Kendaraan::where('nomor_mesin', $mesin)
                    ->where('id_kendaraan', '!=', $existingKendaraan->id_kendaraan)
                    ->first();
                if ($konflikMesin) {
                    $errors[] = "Baris {$index}: Konflik Nomor Mesin — '{$mesin}' sudah digunakan oleh kendaraan lain di sistem.";
                    continue;
                }

                // Jalankan pembaruan master data secara atomik
                try {
                    DB::transaction(function () use (
                        $existingKendaraan, $existingAset, $kartu, $nama, $jenis, $merk, $tipe,
                        $platRaw, $rangka, $mesin, $tglPengadaanDate, $tglHabisDate,
                        $nilaiPerolehan, $kondisi, $statusAset, $keterangan,
                        $jt1Date, $jt5Date, $nominalVal, $totalVal, $flag5,
                        $photosByRow, $index
                    ) {
                        // Jangan ubah status aset sistem (diusulkan_hapus / dihapuskan) lewat import
                        $statusSekarang = mb_strtolower(trim((string) ($existingAset->status_aset ?? '')));
                        $statusSimpan = ($statusSekarang === '' || in_array($statusSekarang, self::STATUS_USER, true))
                            ? $statusAset
                            : $existingAset->status_aset;

                        $existingAset->update([
                            'tanggal_pengadaan' => $tglPengadaanDate,
                            'tanggal_habis_pakai' => $tglHabisDate,
                            'nilai_perolehan' => $nilaiPerolehan,
                            'merk' => $merk ?: $existingAset->merk,
                            'kondisi' => $kondisi,
                            'status_aset' => $statusSimpan,
                        ]);

                        $existingKendaraan->update([
                            'nama_kendaraan' => $nama,
                            'jenis_kendaraan' => $jenis ?: $existingKendaraan->jenis_kendaraan,
                            'nomor_rangka' => $rangka,
                            'nomor_mesin' => $mesin,
                            'merk' => $merk ?: $existingKendaraan->merk,
                            'tipe' => $tipe ?: $existingKendaraan->tipe,
                            'keterangan' => $keterangan ?: $existingKendaraan->keterangan,
                            // Pemegang dipertahankan apa adanya (tidak disentuh)
                        ]);

                        $photoPath = $photosByRow[$index] ?? null;
                        if ($photoPath && ! $existingKendaraan->foto) {
                            $existingKendaraan->update(['foto' => $photoPath]);
                        }

                        // Penanganan Riwayat Plat jika nomor polisi diisi
                        if (! empty($platRaw)) {
                            $platBaru = trim(mb_strtoupper($platRaw));
                            $platAktif = $existingKendaraan->platAktif;
                            $platLama = $platAktif ? trim(mb_strtoupper($platAktif->nomor_plat)) : null;

                            // Jika belum ada plat aktif atau plat baru berbeda, akhiri plat lama dan buat baru
                            if ($platAktif === null || $platLama !== $platBaru) {
                                if ($platAktif) {
                                    $platAktif->update(['status' => 'Tidak Aktif']);
                                }
                                RiwayatPlat::create([
                                    'id_kendaraan' => $existingKendaraan->id_kendaraan,
                                    'nomor_plat' => $platRaw,
                                    'tanggal_berlaku' => $jt5Date,
                                    'ganti_plat' => true,
                                    'status' => 'Aktif',
                                ]);
                            }
                        }

                        // Penanganan Pajak Kendaraan
                        if ($jt1Date || $jt5Date || $nominalVal !== null || $totalVal !== null) {
                            $pajakAktif = $existingKendaraan->pajakAktif;
                            if ($pajakAktif) {
                                $pajakAktif->update([
                                    'tanggal_berakhir' => $jt1Date ?: ($jt5Date ?: $pajakAktif->tanggal_berakhir),
                                    'tahun' => $jt1Date ? Carbon::parse($jt1Date)->year : ($jt5Date ? Carbon::parse($jt5Date)->year : $pajakAktif->tahun),
                                    'nominal' => $nominalVal ?? $pajakAktif->nominal,
                                    'total_pajak' => $totalVal ?? $pajakAktif->total_pajak,
                                    'pajak_5_tahunan' => (bool) $flag5,
                                ]);
                            } else {
                                if ($jt1Date) {
                                    PajakKendaraan::create([
                                        'id_kendaraan' => $existingKendaraan->id_kendaraan,
                                        'jenis_pajak' => 'PKB',
                                        'tahun' => Carbon::parse($jt1Date)->year,
                                        'tanggal_berakhir' => $jt1Date,
                                        'nominal' => $nominalVal,
                                        'total_pajak' => $totalVal,
                                        'pajak_5_tahunan' => false,
                                        'status' => 'Aktif',
                                    ]);
                                }
                                if ($jt5Date) {
                                    PajakKendaraan::create([
                                        'id_kendaraan' => $existingKendaraan->id_kendaraan,
                                        'jenis_pajak' => 'PKB',
                                        'tahun' => Carbon::parse($jt5Date)->year,
                                        'tanggal_berakhir' => $jt5Date,
                                        'nominal' => $jt1Date ? null : $nominalVal,
                                        'total_pajak' => $jt1Date ? null : $totalVal,
                                        'pajak_5_tahunan' => true,
                                        'status' => $jt1Date ? 'Tidak Aktif' : 'Aktif',
                                    ]);
                                }
                            }
                        }

                        // Integrasi Kondisi Rusak Berat & Usulan Penghapusan pada mode Update
                        if ($kondisi === 'Rusak Berat') {
                            $this->usulan()->usulkanKarenaRusakBerat(
                                $existingAset,
                                'Otomatis: kondisi kendaraan diubah menjadi Rusak Berat melalui import.'
                            );
                        }
                    });
                    $updated++;
                } catch (\Throwable $e) {
                    $errors[] = "Baris {$index}: {$e->getMessage()}";
                }
                continue;
            }

            // Skenario 3: Nomor Kartu Barang BELUM ADA di database
            // Diperlakukan sebagai KENDARAAN BARU (baik pada mode skip maupun update)

            // Cek apakah No. Rangka sudah terdaftar di sistem
            $rangkaExists = Kendaraan::where('nomor_rangka', $rangka)->first();
            if ($rangkaExists) {
                $errors[] = "Baris {$index}: Nomor Rangka '{$rangka}' sudah terdaftar pada kendaraan lain di sistem.";
                continue;
            }

            // Cek apakah No. Mesin sudah terdaftar di sistem
            $mesinExists = Kendaraan::where('nomor_mesin', $mesin)->first();
            if ($mesinExists) {
                $errors[] = "Baris {$index}: Nomor Mesin '{$mesin}' sudah terdaftar pada kendaraan lain di sistem.";
                continue;
            }

            // Buat kendaraan baru dalam transaksi atomik
            try {
                DB::transaction(function () use (
                    $kartu, $nama, $jenis, $merk, $tipe, $platRaw,
                    $rangka, $mesin, $tglPengadaanDate, $tglHabisDate,
                    $nilaiPerolehan, $kondisi, $statusAset, $keterangan,
                    $jt1Date, $jt5Date, $nominalVal, $totalVal, $flag5,
                    $photosByRow, $index
                ) {
                    $aset = Aset::create([
                        'nomor_kartu_barang' => $kartu,
                        'merk' => $merk,
                        'tanggal_pengadaan' => $tglPengadaanDate,
                        'tanggal_habis_pakai' => $tglHabisDate,
                        'nilai_perolehan' => $nilaiPerolehan,
                        'kondisi' => $kondisi,
                        'status_aset' => $statusAset,
                        'is_kendaraan' => true,
                    ]);

                    $kendaraan = Kendaraan::create([
                        'id_aset' => $aset->id_aset,
                        'nama_kendaraan' => $nama,
                        'jenis_kendaraan' => $jenis,
                        'nomor_rangka' => $rangka,
                        'nomor_mesin' => $mesin,
                        'merk' => $merk,
                        'tipe' => $tipe,
                        'pemegang' => null, // Selalu null saat import kendaraan baru
                        'keterangan' => $keterangan,
                    ]);

                    $photoPath = $photosByRow[$index] ?? null;
                    if ($photoPath) {
                        $kendaraan->update(['foto' => $photoPath]);
                    }

                    if ($platRaw) {
                        RiwayatPlat::create([
                            'id_kendaraan' => $kendaraan->id_kendaraan,
                            'nomor_plat' => $platRaw,
                            'tanggal_berlaku' => $jt5Date,
                            'ganti_plat' => (bool) $flag5,
                            'status' => 'Aktif',
                        ]);
                    }

                    if ($jt1Date) {
                        PajakKendaraan::create([
                            'id_kendaraan' => $kendaraan->id_kendaraan,
                            'jenis_pajak' => 'PKB',
                            'tahun' => Carbon::parse($jt1Date)->year,
                            'tanggal_berakhir' => $jt1Date,
                            'nominal' => $nominalVal,
                            'total_pajak' => $totalVal,
                            'pajak_5_tahunan' => false,
                            'status' => 'Aktif',
                        ]);
                    }

                    if ($jt5Date) {
                        PajakKendaraan::create([
                            'id_kendaraan' => $kendaraan->id_kendaraan,
                            'jenis_pajak' => 'PKB',
                            'tahun' => Carbon::parse($jt5Date)->year,
                            'tanggal_berakhir' => $jt5Date,
                            'nominal' => $jt1Date ? null : $nominalVal,
                            'total_pajak' => $jt1Date ? null : $totalVal,
                            'pajak_5_tahunan' => true,
                            'status' => $jt1Date ? 'Tidak Aktif' : 'Aktif',
                        ]);
                    }

                    // Pemicu otomatis: kendaraan baru berkondisi Rusak Berat langsung masuk antrean usulan penghapusan
                    if ($kondisi === 'Rusak Berat') {
                        $this->usulan()->usulkanKarenaRusakBerat(
                            $aset,
                            'Otomatis: kendaraan dibuat dengan kondisi Rusak Berat.'
                        );
                    }
                });
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Baris {$index}: {$e->getMessage()}";
            }
        }

        $photoWarnings = 0;
        if ($photosByRow && count($photosByRow) > ($created + $updated)) {
            $photoWarnings = count($photosByRow) - ($created + $updated);
        }

        $message = "Berhasil mengimpor {$created} kendaraan baru";
        if ($updated) {
            $message .= ", {$updated} diperbarui";
        }
        if ($skipped) {
            $message .= ", {$skipped} duplikat dilewati";
        }
        if ($photoWarnings) {
            $message .= ". {$photoWarnings} foto tidak dapat dipetakan ke baris data";
        }
        if ($errors) {
            $message .= '. '.count($errors).' baris gagal.';
        }
        if ($warnings) {
            $message .= '. Peringatan: '.implode(' | ', $warnings);
        }

        return back()->with(
            $errors ? 'error' : 'success',
            $errors ? $message.' -> '.implode(' | ', $errors) : $message
        );
    }

    /**
     * Unduh template Excel baku isian import kendaraan (1 worksheet + petunjuk pengisian di atas).
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import Kendaraan');

        // Petunjuk Pengisian di bagian atas sheet (Baris 1 - 7)
        $sheet->setCellValue('A1', 'PETUNJUK PENGISIAN IMPORT KENDARAAN DINAS');
        $sheet->setCellValue('A2', '1. Kolom Wajib (*): Nomor Kartu Barang, Nama Kendaraan, Nomor Polisi, No. Rangka, No. Mesin, Tanggal Pengadaan, Tanggal Habis Pakai, Nilai Perolehan, Kondisi, Status Aset.');
        $sheet->setCellValue('A3', '2. Tanggal Pengadaan & Habis Pakai: Gunakan format DD/MM/YYYY (contoh: 15/01/2024). Tanggal Habis Pakai harus lebih besar dari Tanggal Pengadaan.');
        $sheet->setCellValue('A4', '3. Nilai Perolehan: Diisi angka rupiah lebih besar dari 0 (contoh: 450000000). Jangan disamakan dengan Nominal Pajak STNK.');
        $sheet->setCellValue('A5', '4. Kondisi & Status: Kondisi hanya Baik, Rusak Ringan, atau Rusak Berat. Status Aset hanya aktif atau nonaktif.');
        $sheet->setCellValue('A6', '5. Keunikan: Nomor Kartu Barang, Nomor Rangka, dan Nomor Mesin harus unik di sistem dan tidak boleh duplikat antar-baris.');
        $sheet->setCellValue('A7', '6. Pemegang: Pemegang kendaraan TIDAK diisi di sini; ditetapkan terpisah melalui menu Penempatan Awal atau Mutasi.');

        // Styling Petunjuk
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('064E3B');
        $sheet->getStyle('A2:A7')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('334155');

        // Baris Header Tabel pada Baris 9
        $sheet->fromArray(self::KOLOM_TEMPLATE, null, 'A9');

        // Baris Contoh pada Baris 10 (Dilewati otomatis saat import karena mengandung penanda 'CONTOH')
        $sheet->fromArray([
            'CONTOH - KIB.02.01.001',
            'CONTOH - Toyota Fortuner 2.4 G',
            'Roda Empat (Mobil)',
            'Toyota',
            'Fortuner',
            'B 1234 ABC',
            'CONTOH - MHKMJ1A200012345',
            'CONTOH - 1GRFE1234567',
            '15/01/2024',
            '15/01/2031',
            450000000,
            'Baik',
            'aktif',
            'Kendaraan dinas operasional',
            '02/07/2027',
            '02/07/2029',
            2443500,
            2443500,
            'Tidak',
            null, // dokumentasi: gambar disisip, bukan teks
        ], null, 'A10');

        // Styling Header Tabel (A9:T9)
        $sheet->getStyle('A9:T9')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(9)->setRowHeight(28);
        $sheet->getRowDimension(10)->setRowHeight(20);

        foreach (range('A', 'T') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static fn () => $writer->save('php://output'),
            'Template-Import-Kendaraan.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    /**
     * Daftar kolom resmi template import kendaraan (20 kolom berurutan).
     */
    public const KOLOM_TEMPLATE = [
        'Nomor Kartu Barang',
        'Nama Kendaraan',
        'Jenis Kendaraan',
        'Merk',
        'Tipe',
        'Nomor Polisi',
        'No. Rangka',
        'No. Mesin',
        'Tanggal Pengadaan',
        'Tanggal Habis Pakai',
        'Nilai Perolehan',
        'Kondisi',
        'Status Aset',
        'Keterangan',
        'Jatuh Tempo Pajak 1 Tahun',
        'Jatuh Tempo Pajak 5 Tahun',
        'Nominal Pajak',
        'Total Pajak',
        'Pajak 5 Tahunan',
        'Dokumentasi',
    ];

    /**
     * Petakan nama header kolom -> huruf kolom, toleran terhadap urutan.
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $aliases = [
            'kartu' => ['nomor kartu barang', 'nomor kartu', 'no kartu barang', 'no. kartu barang', 'no kartu', 'kartu barang', 'kib'],
            'nama' => ['nama kendaraan', 'nama mobil', 'nama kend'],
            'jenis' => ['jenis kendaraan', 'kategori kendaraan', 'jenis'],
            'merk' => ['merk', 'merek', 'merk kendaraan', 'merek kendaraan'],
            'tipe' => ['tipe', 'type', 'tipe kendaraan'],
            'plat' => ['nomor polisi', 'no polisi', 'nopol', 'nomor plat', 'no. polisi', 'plat nomor', 'plat'],
            'rangka' => ['no rangka', 'nomor rangka', 'no. rangka', 'norangka', 'nomor rangka kendaraan'],
            'mesin' => ['no mesin', 'nomor mesin', 'no. mesin', 'nomesin', 'nomor mesin kendaraan'],
            'tgl_pengadaan' => ['tanggal pengadaan', 'tgl pengadaan', 'tgl. pengadaan', 'tanggal perolehan', 'tgl perolehan'],
            'tgl_habis_pakai' => ['tanggal habis pakai', 'tgl habis pakai', 'tgl. habis pakai', 'tanggal akhir masa manfaat', 'tgl akhir masa manfaat', 'habis pakai'],
            'nilai' => ['nilai perolehan', 'nilai perolehan (rp)', 'harga perolehan', 'nilai aset', 'harga perolehan (rp)'],
            'kondisi' => ['kondisi', 'kondisi aset', 'kondisi barang'],
            'status_aset' => ['status aset', 'status'],
            'keterangan' => ['keterangan', 'catatan'],
            'pajak1' => ['jatuh tempo pajak 1 tahun', 'jatuh tempo 1 tahun', 'pajak 1 tahun', 'jatuh tempo pajak tahunan', 'jatuh tempo tahunan'],
            'pajak5' => ['jatuh tempo pajak 5 tahun', 'jatuh tempo 5 tahun', 'pajak 5 tahun'],
            'pajak' => ['tanggal jatuh tempo pajak', 'tanggal jatuh tempo', 'jatuh tempo pajak', 'jatuh tempo', 'pajak jatuh tempo', 'pajak berakhir'],
            'nominal' => ['nominal pajak', 'nominal', 'nominal pkb'],
            'total' => ['total pajak', 'total', 'total pkb'],
            'tahunan' => ['pajak 5 tahunan', '5 tahunan', 'pajak lima tahunan'],
        ];

        $map = [];
        foreach ($headerRow as $colLetter => $value) {
            $label = $this->normalizeHeader($value);
            if ($label === '') {
                continue;
            }
            foreach ($aliases as $field => $candidates) {
                foreach ($candidates as $alias) {
                    if ($this->normalizeHeader($alias) === $label) {
                        $map[$field] = $colLetter;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Baris contoh bawaan template (mengandung penanda CONTOH) dilewati.
     */
    private function isContohRow(array $row): bool
    {
        foreach ($row as $value) {
            if (str_contains(mb_strtolower((string) ($value ?? '')), 'contoh')) {
                return true;
            }
        }

        return false;
    }

    /**
     * True bila row memuat set satu nilai data yang dikenali (bukan kosong).
     */
    private function rowBerisi(array $row, array $colMap): bool
    {
        foreach ($colMap as $field => $letter) {
            if ($this->cell($row, $letter) !== null) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader($value): string
    {
        $s = trim((string) ($value ?? ''));
        $s = mb_strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim($s);
    }

    /**
     * Kumpulkan gambar tersisip, petakan ke nomor baris data berdasarkan posisi.
     * Mengembalikan [nomorBaris => relativePathFoto].
     */
    private function collectPhotos($sheet): array
    {
        $result = [];
        foreach ($sheet->getDrawingCollection() as $drawing) {
            $coordinates = $drawing->getCoordinates(); // mis. 'B3'
            $row = (int) preg_replace('/[^0-9]/', '', $coordinates);
            if ($row < 1) {
                continue;
            }
            $path = $this->saveDrawing($drawing);
            if ($path) {
                $result[$row] = $path;
            }
        }

        return $result;
    }

    private function saveDrawing($drawing): ?string
    {
        try {
            $ext = 'png';
            $bytes = null;

            if ($drawing instanceof MemoryDrawing) {
                $image = $drawing->getImageResource();
                if (! $image) {
                    return null;
                }
                $ext = strtolower($drawing->getMimeType()) === 'image/png' ? 'png' : 'jpg';
                ob_start();
                if ($ext === 'png') {
                    imagepng($image);
                } else {
                    imagejpeg($image, null, 90);
                }
                $bytes = ob_get_clean();
            } elseif ($drawing instanceof Drawing && $drawing->getPath()) {
                $bytes = @file_get_contents($drawing->getPath());
                $ext = strtolower($drawing->getExtension()) ?: 'png';
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
            }

            if (! $bytes) {
                return null;
            }

            $name = 'kendaraan/'.Str::uuid()->toString().'.'.$ext;
            Storage::disk('public')->put($name, $bytes);

            return $name;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseYaTidak($value): bool
    {
        if ($value === null) {
            return false;
        }
        $s = mb_strtolower(trim((string) $value));

        return in_array($s, ['ya', 'y', 'iya', 'yes', 'true', '1'], true);
    }

    /**
     * Dua nama pemegang dianggap sama bila identik setelah spasi luar dibuang
     * dan huruf dinormalkan (lowercase). Perbedaan selain itu (kapitalisasi
     * internal, tanda baca, kata tambahan) dianggap BERBEDA.
     */
    private function pemegangSama(?string $a, ?string $b): bool
    {
        return mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
    }

    private function splitNomorPolisi(string $plat): array
    {
        $parts = preg_split('/[>\/|]+/', $plat);
        $list = [];
        foreach ($parts as $p) {
            $clean = trim(preg_replace('/\s+/', ' ', $p));
            if ($clean !== '') {
                $list[] = $clean;
            }
        }

        return $list;
    }

    // ---------- Helpers ----------

    private function syncPajak(Kendaraan $kendaraan, Request $request, ?array $data = null): void
    {
        $perpanjang = (bool) $request->input('pajak_perpanjang', $data['pajak_perpanjang'] ?? false);
        if (! $perpanjang) {
            return;
        }

        // Tanggal jatuh tempo SELALU input manual Admin sesuai bukti/STNK -
        // disimpan persis, tanpa hitung otomatis. Tanpa tanggal, perpanjangan dilewati.
        $tanggalInput = $request->input('pajak_tanggal_berakhir', $data['pajak_tanggal_berakhir'] ?? null);
        if (empty($tanggalInput)) {
            return;
        }

        $limaTahunan = (bool) $request->input('pajak_max_tahunan', $data['pajak_max_tahunan'] ?? false);
        $berakhir = \Carbon\Carbon::parse($tanggalInput);

        $pajakAktif = $kendaraan->pajakAktif;
        $payload = [
            'tanggal_berakhir' => $berakhir,
            'pajak_5_tahunan' => $limaTahunan,
            'nominal' => $request->input('pajak_nominal', $data['pajak_nominal'] ?? null),
            'total_pajak' => $request->input('pajak_total', $data['pajak_total'] ?? null),
        ];

        if ($pajakAktif) {
            $pajakAktif->update($payload);
        } else {
            PajakKendaraan::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'jenis_pajak' => 'PKB',
                ...$payload,
                'status' => 'Aktif',
            ]);
        }
    }

    private function storeFoto(Request $request): ?string
    {
        if (! $request->hasFile('foto')) {
            return null;
        }

        return $request->file('foto')->store('kendaraan', 'public');
    }

    private function nomorKartuFallback($value): string
    {
        $s = trim((string) ($value ?? ''));
        if ($s !== '') {
            return $s;
        }
        do {
            $candidate = 'KND-'.date('YmdHis').random_int(10, 99);
        } while (Aset::where('nomor_kartu_barang', $candidate)->exists());

        return $candidate;
    }

    /**
     * Validasi data master kendaraan (form Tambah & Edit).
     *
     * PEMEGANG BUKAN BAGIAN FORM INI. Field `pemegang`, `pemegang_awal`, dan
     * `id_pegawai_awal` sengaja TIDAK divalidasi lagi: crafted request tidak
     * boleh menulis pemegang lewat form. Penempatan awal dan pergantian
     * pemegang ditangani alur terpisah (Penempatan Awal / Mutasi Pemegang).
     *
     * Sepuluh field WAJIB sesuai kebutuhan proyek: nama kendaraan, nomor
     * kartu barang, nomor rangka, nomor mesin, nomor polisi, nilai perolehan,
     * tanggal pengadaan, tanggal habis pakai, kondisi, dan status aset.
     *
     * `nomor_rangka` & `nomor_mesin` harus UNIK. Saat edit, nilai milik
     * kendaraan yang sedang diedit dikecualikan (ignore) agar tidak
     * dianggap duplikat terhadap dirinya sendiri. `nomor_kartu_barang` juga
     * unik (kolom `aset` punya UNIQUE index) dengan pengecualian yang sama.
     *
     * Status aset SISTEM (`diusulkan_hapus`/`dihapuskan`) tidak boleh diubah
     * lewat form: pada kondisi itu `status_aset` tidak diwajibkan, dan nilainya
     * dipertahankan apa adanya oleh `update()`.
     */
    private function validateKendaraan(Request $request, ?Kendaraan $kendaraan = null): array
    {
        $aset = $kendaraan?->aset;
        $idKendaraan = $kendaraan?->id_kendaraan;
        $idAset = $aset?->id_aset;

        $statusSekarang = mb_strtolower(trim((string) ($aset?->status_aset ?? '')));
        $statusBolehDiubah = $statusSekarang === '' || in_array($statusSekarang, self::STATUS_USER, true);

        $data = $request->validate([
            'id_kendaraan' => ['nullable', 'integer'],
            'jenis_kendaraan' => ['nullable', 'string', 'max:150'],
            'nomor_rangka' => [
                'required', 'string', 'max:100',
                Rule::unique('kendaraan', 'nomor_rangka')->ignore($idKendaraan, 'id_kendaraan'),
            ],
            'nomor_mesin' => [
                'required', 'string', 'max:100',
                Rule::unique('kendaraan', 'nomor_mesin')->ignore($idKendaraan, 'id_kendaraan'),
            ],
            'merk' => ['nullable', 'string', 'max:100'],
            'tipe' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'nomor_polisi' => ['required', 'string', 'max:20'],
            'nomor_kartu_barang' => [
                'required', 'string', 'max:255',
                Rule::unique('aset', 'nomor_kartu_barang')->ignore($idAset, 'id_aset'),
            ],
            'tanggal_pengadaan' => ['required', 'date'],
            'tanggal_habis_pakai' => ['required', 'date'],
            'nilai_perolehan' => ['required', 'numeric', 'min:0'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'status_aset' => [$statusBolehDiubah ? 'required' : 'nullable', 'string', 'max:50'],
            'nama_kendaraan' => ['required', 'string', 'max:150'],
            'foto_hapus' => ['nullable'],
        ], [
            'nama_kendaraan.required' => 'Nama kendaraan wajib diisi.',
            'nomor_kartu_barang.required' => 'Nomor kartu barang wajib diisi.',
            'nomor_kartu_barang.unique' => 'Nomor kartu barang sudah dipakai aset lain.',
            'nomor_rangka.required' => 'Nomor rangka wajib diisi.',
            'nomor_rangka.unique' => 'Nomor rangka sudah dipakai kendaraan lain.',
            'nomor_mesin.required' => 'Nomor mesin wajib diisi.',
            'nomor_mesin.unique' => 'Nomor mesin sudah dipakai kendaraan lain.',
            'nomor_polisi.required' => 'Nomor polisi wajib diisi.',
            'nilai_perolehan.required' => 'Nilai perolehan wajib diisi.',
            'nilai_perolehan.numeric' => 'Nilai perolehan harus berupa angka yang valid.',
            'nilai_perolehan.min' => 'Nilai perolehan tidak boleh negatif.',
            'tanggal_pengadaan.required' => 'Tanggal pengadaan wajib diisi.',
            'tanggal_habis_pakai.required' => 'Tanggal habis pakai wajib diisi.',
            'kondisi.required' => 'Kondisi wajib diisi.',
            'status_aset.required' => 'Status aset wajib diisi.',
        ]);

        // Buang key turunan yang tetap saja dikirim crafted request.
        unset(
            $data['pemegang'],
            $data['pemegang_awal'],
            $data['id_pegawai_awal'],
            $data['id_skpd'],
            $data['id_lokasi']
        );

        $data['status_aset'] = $this->normalizeStatusAset($data['status_aset'] ?? null);

        return $data;
    }

    /**
     * Whitelist status aset: trim + lowercase, kosong menjadi "aktif", dan
     * nilai di luar {aktif, nonaktif} ditolak. Mencegah crafted request
     * menulis status sistem (diusulkan_hapus/dihapuskan) dan melewati
     * workflow penghapusan.
     */
    private function normalizeStatusAset(mixed $value): string
    {
        $status = mb_strtolower(trim((string) $value));

        if ($status === '') {
            return 'aktif';
        }

        if (! in_array($status, self::STATUS_USER, true)) {
            throw ValidationException::withMessages([
                'status_aset' => 'Status aset hanya boleh "aktif" atau "nonaktif".',
            ]);
        }

        return $status;
    }

    private function cell(array $row, string $key): ?string
    {
        $val = $row[$key] ?? null;
        if ($val === null) {
            return null;
        }
        $s = trim((string) $val);

        return $s === '' ? null : $s;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial >= 25569 && $serial <= 2958465) {
                try {
                    return SpreadsheetDate::excelToDateTimeObject($serial)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }
        $s = trim((string) $value);
        if ($s === '' || $s === '-') {
            return null;
        }

        // Format DD/MM/YYYY, DD-MM-YYYY, atau DD.MM.YYYY
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $s, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            return null;
        }

        // Format YYYY-MM-DD, YYYY/MM/DD, atau YYYY.MM.DD
        if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $s, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            return null;
        }

        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseNilaiPerolehan(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        // Hapus prefix Rp, rp, RP, spasi
        $s = preg_replace('/^(?:rp\.?|idr)\s*/i', '', $s);
        $s = trim($s);

        // Hanya digit, pemisah titik/koma, dan minus
        $s = preg_replace('/[^0-9,.\-]/', '', $s);
        if ($s === '' || $s === '-') {
            return null;
        }

        $hasDot = strpos($s, '.') !== false;
        $hasComma = strpos($s, ',') !== false;

        if ($hasDot && $hasComma) {
            if (strrpos($s, '.') > strrpos($s, ',')) {
                $s = str_replace(',', '', $s);
            } else {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            }
        } elseif ($hasComma) {
            $s = preg_match('/^-?\d{1,3}(,\d{3})+$/', $s)
                ? str_replace(',', '', $s)
                : str_replace(',', '.', $s);
        } elseif ($hasDot) {
            if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
                $s = str_replace('.', '', $s);
            }
        }

        if (! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    private function normalizeKondisi(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $val = mb_strtolower(trim($value));

        return match ($val) {
            'baik' => 'Baik',
            'rusak ringan' => 'Rusak Ringan',
            'rusak berat' => 'Rusak Berat',
            default => null,
        };
    }

    private function toNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Normalisasi format angka dari Excel. Sel numerik yang diformat
        // (mis. "#,##0") dihasilkan PhpSpreadsheet sebagai teks, sehingga
        // "2,443,500" dan "2.443.500" harus sama-sama dikenali. Hanya digit,
        // pemisah, dan tanda minus yang dipertahankan (otomatis membuang
        // "Rp" dan spasi).
        $s = preg_replace('/[^0-9,.\-]/', '', (string) $value);

        $hasDot = strpos($s, '.') !== false;
        $hasComma = strpos($s, ',') !== false;

        if ($hasDot && $hasComma) {
            // Pemisah terakhir = desimal; sisanya pemisah ribuan.
            if (strrpos($s, '.') > strrpos($s, ',')) {
                $s = str_replace(',', '', $s);
            } else {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            }
        } elseif ($hasComma) {
            $s = preg_match('/^-?\d{1,3}(,\d{3})+$/', $s)
                ? str_replace(',', '', $s)
                : str_replace(',', '.', $s);
        } elseif ($hasDot && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }
}
