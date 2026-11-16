<?php

namespace App\Http\Controllers;

use App\Models\IzinKendaraan;
use App\Models\Kendaraan;
use App\Models\Pegawai;
use App\Models\TemplateDokumen;
use App\Models\User;
use App\Services\AsetScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\TemplateProcessor;

class IzinKendaraanController extends Controller
{
    public function __construct(private readonly AsetScope $scope)
    {
    }

    /**
     * Daftar pengajuan izin kendaraan (halaman layanan).
     *
     * - Admin Aset : melihat seluruh pengajuan.
     * - Role lain   : hanya melihat pengajuan miliknya.
     */
    public function index()
    {
        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';

        $izinList = IzinKendaraan::with([
            'kendaraan.aset.barang',
            'kendaraan.platAktif',
            'pengaju',
            'penyerah',
        ])
            ->when(! $isAdminAset, fn ($q) => $q->where('id_pegawai_pengaju', auth()->user()->pegawai?->id_pegawai))
            ->orderByDesc('id_izin')
            ->get();

        // Opsi kendaraan: jika pemohon kembali dari error validasi dengan input jadwal yang sah,
        // muat kendaraan yang sesuai dengan jadwal tersebut. Jika belum ada jadwal, kosongkan
        // agar pemohon menentukan jadwal pemakaian terlebih dahulu.
        $kendaraanOptions = [];
        $oldMulaiTgl = old('tanggal_berangkat');
        $oldMulaiJam = old('waktu_berangkat');
        $oldSelesaiTgl = old('tanggal_kembali');
        $oldSelesaiJam = old('waktu_kembali');

        if ($oldMulaiTgl && $oldMulaiJam && $oldSelesaiTgl && $oldSelesaiJam) {
            try {
                $start = Carbon::parse($oldMulaiTgl . ' ' . $oldMulaiJam);
                $end = Carbon::parse($oldSelesaiTgl . ' ' . $oldSelesaiJam);
                if ($end->greaterThan($start) && $start->greaterThanOrEqualTo(now()->startOfMinute())) {
                    $kendaraanOptions = $this->queryKendaraanTersedia($start, $end)->mapWithKeys(function (Kendaraan $k) {
                        $nama = $k->namaTampil();
                        $plat = $k->platAktif?->nomor_plat;
                        return [$k->id_kendaraan => trim($nama . ($plat ? " ({$plat})" : ''))];
                    })->all();
                }
            } catch (\Exception $e) {
                $kendaraanOptions = [];
            }
        }

        // Pengurus Barang (Penyerah): HANYA pegawai yang ditempatkan di "Ruang Aset".
        $pengurusBarangList = Pegawai::whereHas('ruangan', fn ($q) => $q->where('nama_ruangan', 'Ruang Aset'))
            ->orderBy('nama_pegawai')
            ->get();

        $pengurusOptions = $pengurusBarangList->mapWithKeys(function (Pegawai $p) {
            return [$p->id_pegawai => $p->nama_pegawai];
        })->all();

        return view('layanan.izin-kendaraan.index', compact(
            'izinList', 'pengurusBarangList', 'kendaraanOptions',
            'pengurusOptions', 'isAdminAset'
        ));
    }

    /**
     * Endpoint ketersediaan kendaraan secara dinamis berdasarkan rentang jadwal yang dipilih.
     */
    public function ketersediaan(Request $request)
    {
        $validator = validator($request->all(), [
            'tanggal_berangkat' => ['required', 'date', 'after_or_equal:today'],
            'waktu_berangkat' => ['required', 'string'],
            'tanggal_kembali' => ['required', 'date', 'after_or_equal:tanggal_berangkat'],
            'waktu_kembali' => ['required', 'string'],
        ], [
            'tanggal_berangkat.after_or_equal' => 'Tanggal mulai pemakaian tidak boleh lebih awal dari hari ini.',
            'tanggal_kembali.after_or_equal' => 'Tanggal selesai pemakaian tidak boleh lebih awal dari tanggal mulai.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'kendaraan' => [],
            ], 422);
        }

        try {
            $timeStart = Carbon::parse($request->waktu_berangkat)->format('H:i:s');
            $timeEnd = Carbon::parse($request->waktu_kembali)->format('H:i:s');
            $start = Carbon::parse($request->tanggal_berangkat . ' ' . $timeStart);
            $end = Carbon::parse($request->tanggal_kembali . ' ' . $timeEnd);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Format tanggal atau jam pemakaian tidak valid.',
                'kendaraan' => [],
            ], 422);
        }

        if ($start->lessThan(now()->startOfMinute())) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal mulai pemakaian tidak boleh sudah berlalu.',
                'kendaraan' => [],
            ], 422);
        }

        if ($end->lessThanOrEqualTo($start)) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal dan jam selesai harus lebih besar daripada waktu mulai.',
                'kendaraan' => [],
            ], 422);
        }

        $kendaraanList = $this->queryKendaraanTersedia($start, $end);

        return response()->json([
            'success' => true,
            'durasi' => self::hitungDurasi($start, $end),
            'kendaraan' => $kendaraanList->map(function (Kendaraan $k) {
                $nama = $k->namaTampil();
                $plat = $k->platAktif?->nomor_plat;
                return [
                    'id' => $k->id_kendaraan,
                    'text' => trim($nama . ($plat ? " ({$plat})" : '')),
                ];
            })->values(),
        ]);
    }

    /**
     * Query kendaraan dinas yang memenuhi seluruh syarat ketersediaan jadwal:
     * - Aset berstatus aktif.
     * - Penempatan aset sudah difinalisasi (penempatanAktif.is_finalized = 1).
     * - Berada dalam cakupan lokasi pengguna (AsetScope).
     * - Tidak memiliki pengajuan berstatus "Menunggu" atau "Disetujui" yang jadwalnya bertabrakan.
     * - Tidak sedang digunakan secara aktual pada waktu yang dipilih.
     */
    public function queryKendaraanTersedia(Carbon $start, Carbon $end)
    {
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        $driver = DB::connection()->getDriverName();
        $startExpr = $driver === 'sqlite'
            ? "datetime(tanggal_berangkat || ' ' || coalesce(waktu_berangkat, '00:00:00'))"
            : "TIMESTAMP(tanggal_berangkat, COALESCE(waktu_berangkat, '00:00:00'))";
        $endExpr = $driver === 'sqlite'
            ? "datetime(coalesce(tanggal_kembali, tanggal_berangkat) || ' ' || coalesce(waktu_kembali, '23:59:59'))"
            : "TIMESTAMP(COALESCE(tanggal_kembali, tanggal_berangkat), COALESCE(waktu_kembali, '23:59:59'))";

        return Kendaraan::with(['aset.barang', 'platAktif', 'aset.penempatanAktif'])
            ->whereHas('aset', function ($a) {
                $a->where('status_aset', 'aktif')
                    ->whereHas('penempatanAktif', function ($p) {
                        $p->where('is_finalized', 1);
                    })
                    ->tap(fn ($q) => $this->scope->terapkan($q));
            })
            ->whereDoesntHave('izin', function ($q) use ($startStr, $endStr, $startExpr, $endExpr) {
                $q->whereIn('status_approval', ['Menunggu', 'Disetujui'])
                    ->whereRaw("{$startExpr} < ?", [$endStr])
                    ->whereRaw("{$endExpr} > ?", [$startStr]);
            })
            ->when($start->lessThanOrEqualTo(now()) && $end->greaterThanOrEqualTo(now()), function ($q) {
                $q->where('status_penggunaan', '!=', 'Digunakan');
            })
            ->orderBy('jenis_kendaraan')
            ->get();
    }

    /**
     * Pengecekan benturan jadwal terhadap pengajuan lain.
     */
    public function cekBenturanJadwal(int $idKendaraan, string $startStr, string $endStr, ?int $exceptIzinId = null): bool
    {
        $driver = DB::connection()->getDriverName();
        $startExpr = $driver === 'sqlite'
            ? "datetime(tanggal_berangkat || ' ' || coalesce(waktu_berangkat, '00:00:00'))"
            : "TIMESTAMP(tanggal_berangkat, COALESCE(waktu_berangkat, '00:00:00'))";
        $endExpr = $driver === 'sqlite'
            ? "datetime(coalesce(tanggal_kembali, tanggal_berangkat) || ' ' || coalesce(waktu_kembali, '23:59:59'))"
            : "TIMESTAMP(COALESCE(tanggal_kembali, tanggal_berangkat), COALESCE(waktu_kembali, '23:59:59'))";

        return IzinKendaraan::where('id_kendaraan', $idKendaraan)
            ->whereIn('status_approval', ['Menunggu', 'Disetujui'])
            ->when($exceptIzinId, fn ($q) => $q->where('id_izin', '!=', $exceptIzinId))
            ->whereRaw("{$startExpr} < ?", [$endStr])
            ->whereRaw("{$endExpr} > ?", [$startStr])
            ->exists();
    }

    /**
     * Hitung durasi otomatis dari tanggal dan jam mulai sampai selesai.
     */
    public static function hitungDurasi(Carbon $start, Carbon $end): string
    {
        $totalMenit = (int) $start->diffInMinutes($end);

        if ($totalMenit <= 300) {
            $label = 'Setengah Hari';
        } elseif ($totalMenit <= 1440) {
            $label = '1 Hari';
        } else {
            $hariPenuh = intdiv($totalMenit, 1440);
            $sisaMenit = $totalMenit % 1440;

            if ($sisaMenit === 0) {
                $label = "{$hariPenuh} Hari";
            } elseif ($sisaMenit <= 300) {
                $label = "{$hariPenuh} Hari + Setengah Hari";
            } else {
                $label = ($hariPenuh + 1) . ' Hari';
            }
        }

        if ($start->isSameDay($end)) {
            $rentang = $start->format('H:i') . ' - ' . $end->format('H:i');
        } elseif ($start->year === $end->year) {
            $rentang = $start->translatedFormat('H:i, d M') . ' - ' . $end->translatedFormat('H:i, d M Y');
        } else {
            $rentang = $start->translatedFormat('H:i, d M Y') . ' - ' . $end->translatedFormat('H:i, d M Y');
        }

        return "{$label} ({$rentang})";
    }

    /**
     * Simpan pengajuan izin kendaraan dari halaman layanan.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! $user->pegawai) {
            throw ValidationException::withMessages([
                'id_pegawai_pengaju' => 'Akun Anda belum terhubung dengan data pegawai. Silakan hubungi Admin Aset.',
            ]);
        }

        $data = $request->validate([
            'id_kendaraan' => ['required', 'exists:kendaraan,id_kendaraan'],
            'nama_pengemudi' => ['required', 'string', 'max:100'],
            'id_pengurus_barang' => [
                'required',
                Rule::exists('pegawai', 'id_pegawai')->where(function ($query) {
                    $query->whereIn('id_ruangan', function ($subQuery) {
                        $subQuery->select('id_ruangan')
                            ->from('ruangan')
                            ->where('nama_ruangan', 'Ruang Aset');
                    });
                }),
            ],
            'tujuan' => ['required', 'string', 'max:500'],
            'tanggal_berangkat' => ['required', 'date', 'after_or_equal:today'],
            'waktu_berangkat' => ['required', 'string'],
            'tanggal_kembali' => ['required', 'date', 'after_or_equal:tanggal_berangkat'],
            'waktu_kembali' => ['required', 'string'],
            'durasi' => ['nullable', 'string', 'max:100'],
        ], [
            'id_pengurus_barang.exists' => 'Pengurus Barang harus merupakan pegawai yang bertugas di Ruang Aset.',
            'tanggal_berangkat.after_or_equal' => 'Tanggal mulai pemakaian tidak boleh lebih awal dari hari ini.',
            'tanggal_kembali.after_or_equal' => 'Tanggal selesai pemakaian tidak boleh lebih awal dari tanggal mulai.',
        ]);

        try {
            $timeStart = Carbon::parse($data['waktu_berangkat'])->format('H:i:s');
            $timeEnd = Carbon::parse($data['waktu_kembali'])->format('H:i:s');
            $startDateTime = Carbon::parse($data['tanggal_berangkat'] . ' ' . $timeStart);
            $endDateTime = Carbon::parse($data['tanggal_kembali'] . ' ' . $timeEnd);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'waktu_berangkat' => 'Format tanggal atau jam pemakaian tidak valid.',
            ]);
        }

        if ($startDateTime->lessThan(now()->startOfMinute())) {
            throw ValidationException::withMessages([
                'waktu_berangkat' => 'Jadwal mulai pemakaian tidak boleh sudah berlalu.',
            ]);
        }

        if ($endDateTime->lessThanOrEqualTo($startDateTime)) {
            throw ValidationException::withMessages([
                'tanggal_kembali' => 'Tanggal dan jam selesai pemakaian harus lebih besar daripada waktu mulai.',
            ]);
        }

        $durasi = self::hitungDurasi($startDateTime, $endDateTime);

        // Gunakan transaksi dengan row-level lock untuk mencegah race condition (dua pemohon bersamaan)
        return DB::transaction(function () use ($data, $startDateTime, $endDateTime, $timeStart, $timeEnd, $durasi) {
            $kendaraan = Kendaraan::with(['aset.penempatanAktif'])->lockForUpdate()->find($data['id_kendaraan']);

            abort_unless($kendaraan, 404, 'Kendaraan tidak ditemukan.');

            // Object-level: kendaraan yang dipilih harus dalam cakupan user.
            abort_unless(
                $this->scope->bolehAksesAset($kendaraan->aset),
                403,
                $this->scope->pesanAkses('mengajukan izin kendaraan')
            );

            // Syarat aset aktif
            if ($kendaraan->aset?->status_aset !== 'aktif') {
                throw ValidationException::withMessages([
                    'id_kendaraan' => 'Kendaraan yang dipilih tidak berstatus aktif.',
                ]);
            }

            // Syarat finalisasi: penempatan aktif harus is_finalized = 1
            $penempatanAktif = $kendaraan->aset?->penempatanAktif;
            if (! $penempatanAktif || (int) ($penempatanAktif->is_finalized ?? 0) !== 1) {
                throw ValidationException::withMessages([
                    'id_kendaraan' => 'Kendaraan dinas belum difinalisasi penempatannya.',
                ]);
            }

            // Pastikan kendaraan tidak sedang digunakan secara aktual pada waktu yang dipilih
            if ($startDateTime->lessThanOrEqualTo(now()) && $endDateTime->greaterThanOrEqualTo(now()) && $kendaraan->status_penggunaan === 'Digunakan') {
                throw ValidationException::withMessages([
                    'id_kendaraan' => 'Kendaraan saat ini sedang digunakan secara aktual.',
                ]);
            }

            // Pengecekan benturan jadwal terhadap pengajuan lain (Menunggu & Disetujui)
            $bentrok = $this->cekBenturanJadwal(
                (int) $kendaraan->id_kendaraan,
                $startDateTime->format('Y-m-d H:i:s'),
                $endDateTime->format('Y-m-d H:i:s')
            );

            if ($bentrok) {
                throw ValidationException::withMessages([
                    'id_kendaraan' => 'Kendaraan tersebut sudah memiliki jadwal peminjaman lain pada rentang waktu yang dipilih.',
                ]);
            }

            IzinKendaraan::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'id_pegawai_pengaju' => auth()->user()?->pegawai?->id_pegawai,
                'id_pengurus_barang' => $data['id_pengurus_barang'],
                'nama_pengemudi' => $data['nama_pengemudi'],
                'tujuan' => $data['tujuan'],
                'durasi' => $durasi,
                'tanggal_berangkat' => $data['tanggal_berangkat'],
                'waktu_berangkat' => $timeStart,
                'tanggal_kembali' => $data['tanggal_kembali'],
                'waktu_kembali' => $timeEnd,
                'status_approval' => 'Menunggu',
            ]);

            return redirect()->route('layanan.izin-kendaraan.index')
                ->with('success', 'Pengajuan izin berhasil dikirim dan menunggu verifikasi pengurus barang.');
        });
    }

    /**
     * Verifikasi cepat pengajuan (khusus Admin Aset) dari halaman layanan.
     *
     * Penolakan wajib disertai alasan yang disimpan ke kolom
     * `alasan_penolakan` agar bisa dibaca kembali oleh pemohon & admin.
     */
    public function updateStatus(Request $request, IzinKendaraan $izin)
    {
        abort_unless(auth()->user()?->role?->nama_role === 'Admin Aset', 403);

        if ($izin->status_approval !== 'Menunggu') {
            if ($request->expectsJson() || $request->wantsJson()) {
                abort(422, 'Pengajuan ini sudah pernah diproses atau status sudah final.');
            }
            return redirect()->route('layanan.izin-kendaraan.index')
                ->with('error', 'Pengajuan ini sudah pernah diproses atau status sudah final.');
        }

        $rules = [
            'status_approval' => ['required', Rule::in(['Disetujui', 'Ditolak'])],
            'alasan_penolakan' => ['nullable', 'string', 'max:1000'],
        ];

        if (($request->input('status_approval') ?? '') === 'Ditolak') {
            $rules['alasan_penolakan'][] = 'required';
        }

        $data = $request->validate($rules);

        return DB::transaction(function () use ($data, $izin, $request) {
            // Kunci baris kendaraan dengan lockForUpdate untuk mencegah race condition (approval konkuren)
            $kendaraan = $izin->id_kendaraan ? Kendaraan::lockForUpdate()->find($izin->id_kendaraan) : null;
            abort_unless($kendaraan, 404, 'Kendaraan tidak ditemukan.');

            // Baca ulang permohonan terkini setelah lock diperoleh
            $izin = IzinKendaraan::lockForUpdate()->find($izin->id_izin);
            abort_unless($izin, 404, 'Permohonan izin tidak ditemukan.');

            if ($izin->status_approval !== 'Menunggu') {
                if ($request->expectsJson() || $request->wantsJson()) {
                    abort(422, 'Pengajuan ini sudah pernah diproses atau status sudah final.');
                }
                return redirect()->route('layanan.izin-kendaraan.index')
                    ->with('error', 'Pengajuan ini sudah pernah diproses atau status sudah final.');
            }

            if ($data['status_approval'] === 'Disetujui') {
                $tglBerangkat = $izin->tanggal_berangkat ? Carbon::parse($izin->tanggal_berangkat)->format('Y-m-d') : null;
                $wktBerangkat = $izin->waktu_berangkat ? Carbon::parse($izin->waktu_berangkat)->format('H:i:s') : '00:00:00';
                $tglKembali = $izin->tanggal_kembali ? Carbon::parse($izin->tanggal_kembali)->format('Y-m-d') : $tglBerangkat;
                $wktKembali = $izin->waktu_kembali ? Carbon::parse($izin->waktu_kembali)->format('H:i:s') : '23:59:59';

                $start = $tglBerangkat ? Carbon::parse($tglBerangkat . ' ' . $wktBerangkat) : null;
                $end = $tglKembali ? Carbon::parse($tglKembali . ' ' . $wktKembali) : null;

                $startStr = $start ? $start->format('Y-m-d H:i:s') : null;
                $endStr = $end ? $end->format('Y-m-d H:i:s') : null;

                // Periksa apakah ada permohonan LAIN untuk kendaraan ini yang sudah berstatus 'Disetujui' dan jadwalnya berbenturan
                $adaBentrok = false;
                if ($startStr && $endStr) {
                    $driver = DB::connection()->getDriverName();
                    $startExpr = $driver === 'sqlite'
                        ? "datetime(tanggal_berangkat || ' ' || coalesce(waktu_berangkat, '00:00:00'))"
                        : "TIMESTAMP(tanggal_berangkat, COALESCE(waktu_berangkat, '00:00:00'))";
                    $endExpr = $driver === 'sqlite'
                        ? "datetime(coalesce(tanggal_kembali, tanggal_berangkat) || ' ' || coalesce(waktu_kembali, '23:59:59'))"
                        : "TIMESTAMP(COALESCE(tanggal_kembali, tanggal_berangkat), COALESCE(waktu_kembali, '23:59:59'))";

                    $adaBentrok = IzinKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)
                        ->where('id_izin', '!=', $izin->id_izin)
                        ->where('status_approval', 'Disetujui')
                        ->whereRaw("{$startExpr} < ?", [$endStr])
                        ->whereRaw("{$endExpr} > ?", [$startStr])
                        ->exists();
                }

                // Jika ditemukan benturan dengan izin yang sudah Disetujui:
                if ($adaBentrok) {
                    // Otomatis ubah permohonan ini menjadi Ditolak dengan alasan 'Kendaraan digunakan'
                    $izin->update([
                        'status_approval' => 'Ditolak',
                        'alasan_penolakan' => 'Kendaraan digunakan',
                    ]);

                    // Jangan ubah permohonan pertama yang sudah disetujui, jangan ubah status kendaraan
                    return redirect()->route('layanan.izin-kendaraan.index')
                        ->with('error', 'Permohonan izin otomatis ditolak karena kendaraan digunakan.');
                }

                // Jika tidak ada benturan, setujui permohonan
                $izin->update([
                    'status_approval' => 'Disetujui',
                    'alasan_penolakan' => null,
                ]);

                // Update status_penggunaan kendaraan jika jadwalnya aktif saat ini
                if ($start && $end && $start->lessThanOrEqualTo(now()) && $end->greaterThanOrEqualTo(now())) {
                    $kendaraan->update(['status_penggunaan' => 'Digunakan']);
                } elseif (! $kendaraan->sedangDigunakanAktual()) {
                    $kendaraan->update(['status_penggunaan' => 'Tersedia']);
                }

                return redirect()->route('layanan.izin-kendaraan.index')
                    ->with('success', 'Pengajuan izin kendaraan disetujui.');
            }

            // Alur penolakan manual oleh Admin Aset
            $izin->update([
                'status_approval' => 'Ditolak',
                'alasan_penolakan' => $data['alasan_penolakan'],
            ]);

            // Saat ditolak manual, bebaskan kendaraan jika tidak ada izin lain yang sedang aktif sekarang
            if (! $kendaraan->sedangDigunakanAktual()) {
                $kendaraan->update(['status_penggunaan' => 'Tersedia']);
            }

            return redirect()->route('layanan.izin-kendaraan.index')
                ->with('success', 'Pengajuan izin kendaraan ditolak.');
        });
    }

    /**
     * Batalkan pengajuan izin kendaraan oleh pemohon (pengaju).
     *
     * Ketentuan:
     * - Hanya pengaju/pemilik pengajuan yang dapat membatalkan.
     * - Admin Aset tidak boleh membatalkan pengajuan milik orang lain.
     * - Hanya pengajuan berstatus "Menunggu" yang dapat dibatalkan.
     * - Status diubah menjadi "Dibatalkan" dan riwayat tetap tersimpan.
     */
    public function batal(IzinKendaraan $izin)
    {
        $userPegawaiId = auth()->user()?->pegawai?->id_pegawai;

        // Validasi kepemilikan: hanya pengaju yang boleh membatalkan
        abort_unless(
            $userPegawaiId && (int) $izin->id_pegawai_pengaju === (int) $userPegawaiId,
            403,
            'Anda tidak memiliki wewenang untuk membatalkan pengajuan ini.'
        );

        // Validasi status: hanya status Menunggu yang boleh dibatalkan
        abort_unless(
            $izin->status_approval === 'Menunggu',
            403,
            'Pengajuan yang sudah diproses atau dibatalkan tidak dapat dibatalkan kembali.'
        );

        $izin->update([
            'status_approval' => 'Dibatalkan',
        ]);

        return redirect()->route('layanan.izin-kendaraan.index')
            ->with('success', 'Pengajuan izin kendaraan berhasil dibatalkan.');
    }

    /**
     * Konfirmasi pengembalian kendaraan oleh pengaju.
     *
     * Berisi catatan kondisi kendaraan, foto bukti (opsional), dan waktu
     * pengembalian riil. Status pengajuan menjadi "Selesai" dan kendaraan
     * otomatis kembali tersedia untuk dipesan pegawai lain.
     */
    public function returnKendaraan(Request $request, IzinKendaraan $izin)
    {
        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';
        $isPengaju = $izin->id_pegawai_pengaju === auth()->user()?->pegawai?->id_pegawai;

        abort_unless($isAdminAset || $isPengaju, 403);
        abort_unless($izin->status_approval === 'Disetujui', 422);

        $data = $request->validate([
            'catatan_kondisi' => ['nullable', 'string', 'max:1000'],
            'foto_pengembalian' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'waktu_pengembalian' => ['nullable', 'date'],
        ]);

        $fotoPath = null;
        if ($request->hasFile('foto_pengembalian')) {
            $fotoPath = $request->file('foto_pengembalian')->store('pengembalian', 'public');
        }

        $izin->update([
            'status_approval' => 'Selesai',
            'catatan_pengembalian' => $data['catatan_kondisi'] ?? null,
            'foto_pengembalian' => $fotoPath,
            'waktu_pengembalian' => $data['waktu_pengembalian'] ?? now(),
        ]);

        // Kendaraan hanya dikembalikan ke Tersedia bila tidak ada lagi
        // pengajuan lain atas kendaraan tersebut yang sedang aktif dipakai sekarang.
        if ($izin->kendaraan && ! $izin->kendaraan->sedangDigunakanAktual()) {
            $izin->kendaraan->update(['status_penggunaan' => 'Tersedia']);
        }

        return redirect()->route('layanan.izin-kendaraan.index')
            ->with('success', 'Pengembalian kendaraan dikonfirmasi. Status pengajuan menjadi "Selesai".');
    }

    /**
     * Unduh surat izin (.docx) untuk pengajuan yang telah disetujui.
     *
     * Menggunakan file template "Surat Izin / Tugas Kendaraan Dinas" yang
     * diunggah Admin Aset lewat menu Pengaturan Template Dokumen
     * (kode_template: surat_izin_kendaraan). Placeholder yang didukung:
     *   {{ nomor_surat }}, {{ nama_pemohon }}, {{ nip_pemohon }},
     *   {{ jabatan_pemohon }}, {{ nama_pengemudi }}, {{ nama_kendaraan }},
     *   {{ jenis_kendaraan }}, {{ merk_kendaraan }}, {{ tipe_kendaraan }},
     *   {{ plat_nomor }}, {{ tujuan }}, {{ tanggal_berangkat }}, {{ durasi }},
     *   {{ nama_pengurus_barang }}, {{ nip_pengurus_barang }},
     *   {{ nama_pejabat }}, {{ nip_pejabat }}, {{ kota_surat }}, {{ tanggal_surat }}.
     *
     * Jika template belum diunggah, digunakan mekanisme generate otomatis
     * agar fitur unduh tetap berfungsi.
     */
    public function downloadSurat(IzinKendaraan $izin)
    {
        abort_unless($izin->status_approval === 'Disetujui', 403);

        // Object-level: hanya pengaju sendiri, atau user yang cakupannya
        // mencakup kendaraan tersebut (mis. Admin Aset / pengurus barang).
        // Menutup bocor surat izin lintas unit via URL langsung.
        $isPengaju = $izin->id_pegawai_pengaju === auth()->user()?->pegawai?->id_pegawai;

        abort_unless(
            $isPengaju || $this->scope->bolehAksesAset($izin->kendaraan?->aset),
            403,
            $this->scope->pesanAkses('mengunduh surat izin kendaraan')
        );

        $izin->load(['kendaraan.aset.barang', 'kendaraan.platAktif', 'pengaju', 'penyerah']);

        $template = TemplateDokumen::where('kode_template', 'surat_izin_kendaraan')
            ->whereNotNull('file_path')
            ->first();

        if ($template && $template->fileExists()) {
            return $this->generateFromTemplate($izin, $template);
        }

        return $this->generateManual($izin);
    }

    /**
     * Isi placeholder pada file template .docx yang diunggah Admin Aset,
     * lalu unduh hasilnya sebagai surat izin siap cetak.
     */
    protected function generateFromTemplate(IzinKendaraan $izin, TemplateDokumen $template)
    {
        $kendaraan = $izin->kendaraan;
        $plat = $kendaraan?->platAktif?->nomor_plat ?? '-';
        $tanggal = Carbon::parse($izin->tanggal_berangkat)->locale('id');
        $hariIni = Carbon::now()->locale('id');

        $namaKendaraan = $kendaraan?->namaTampil() ?? '-';

        $values = [
            'nomor_surat' => sprintf('%03d', $izin->id_izin).'/IZINK-DISBUN/'.$tanggal->format('Y'),
            'nama_pemohon' => $izin->pengaju?->nama_pegawai ?? '-',
            'nip_pemohon' => $izin->pengaju?->nip ?? '-',
            'jabatan_pemohon' => $izin->pengaju?->jabatan ?? '-',
            'nama_pengemudi' => $izin->nama_pengemudi ?? '-',
            'nama_kendaraan' => $namaKendaraan,
            'jenis_kendaraan' => $kendaraan?->jenis_kendaraan ?? '-',
            'merk_kendaraan' => $kendaraan?->merk ?? '-',
            'tipe_kendaraan' => $kendaraan?->tipe ?? '-',
            'plat_nomor' => $plat,
            'tujuan' => $izin->tujuan ?? '-',
            'tanggal_berangkat' => $tanggal->translatedFormat('d F Y'),
            'durasi' => $izin->durasi ?? '-',
            'nama_pengurus_barang' => $izin->penyerah?->nama_pegawai ?? '-',
            'nip_pengurus_barang' => $izin->penyerah?->nip ?? '-',
            'nama_pejabat' => $izin->penyerah?->nama_pegawai ?? '-',
            'nip_pejabat' => $izin->penyerah?->nip ?? '-',
            'jabatan_pejabat' => $izin->penyerah?->jabatan ?? '-',
            'nama_pengurus' => $izin->penyerah?->nama_pegawai ?? '-',
            'nip_pengurus' => $izin->penyerah?->nip ?? '-',
            'waktu' => $izin->durasi ?? '-',
            'kota_surat' => 'Surabaya',
            'tanggal_surat' => $hariIni->translatedFormat('d F Y'),
        ];

        $tmpZip = sys_get_temp_dir().'/Surat_Izin_TPL_'.uniqid().'.docx';
        copy($template->realPath(), $tmpZip);

        $zip = new \ZipArchive;

        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);

            return back()->with('error', 'Berkas template surat izin tidak dapat dibaca.');
        }

        $xml = $zip->getFromName('word/document.xml');

        if ($xml !== false) {
            // 1) Ubah notasi {{ key }} menjadi ${key} agar dikenali PHPWord
            //    TemplateProcessor (setValue/ensureMacroCompleted).
            $xml = preg_replace('/\{\{\s*/', '\\${', $xml);
            $xml = preg_replace('/\s*\}\}/', '}', $xml);

            // 2) Rapikan token ${...} yang terpecah antar run menjadi satu run
            //    utuh (pertahankan format run pertama). Cakupan dibatasi per
            //    paragraf -(?!</w:p>).- agar teks antar paragraf TIDAK pernah
            //    disambung/ditumpuk.
            $xml = preg_replace_callback('/(<w:p\b[^>]*>(?:(?!<\/w:p>).)*?<\/w:p>)/s', function ($pm) {
                return preg_replace_callback(
                    '/(<w:r\b[^>]*>(?:<w:rPr>.*?<\/w:rPr>)?<w:t(?: [^>]*)?>)\$\{(?:(?!<\/w:p>).)*?\}<\/w:t><\/w:r>/s',
                    function ($m) {
                        [$full, $openTag] = $m;
                        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $full, $tt);
                        $text = html_entity_decode(implode('', $tt[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                        $text = preg_replace('/\s+/u', ' ', trim($text));

                        return $openTag.$text.'</w:t></w:r>';
                    },
                    $pm[1]
                );
            }, $xml);

            // 3) Rapatkan spasi sebelum '}' pada token yang penutupnya berada
            //    di run/rata yang sama (mis. "${nama_pengemudi }" -> "${nama_pengemudi}").
            $xml = preg_replace('/(\$\{[^}]*?)\s+\}/', '$1}', $xml);

            // 4) Hapus run yang isinya HANYA '}' (artefak pemisahan penutup token
            //    oleh grammar-check Word). Pola dibatasi tag-per-tag agar tidak
            //    pernah menyambung/menghapus teks lain.
            $xml = preg_replace('/<w:r\b[^>]*>(?:<w:rPr>(?:<[^>]*>)*<\/w:rPr>)?<w:t(?: [^>]*)?>\s*\}\s*<\/w:t><\/w:r>/', '', $xml);

            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $xml);
        }

        $zip->close();

        // 5) Isi placeholder satu per satu dengan setValue (per variabel tunggal)
        //    agar hanya token yang diganti, teks label di sekitarnya tetap utuh.
        $templateProcessor = new TemplateProcessor($tmpZip);

        foreach ($values as $key => $value) {
            $templateProcessor->setValue($key, $value);
        }

        $tmpOutput = sys_get_temp_dir().'/Surat_Izin_Kendaraan_'.uniqid().'.docx';
        $templateProcessor->saveAs($tmpOutput);
        @unlink($tmpZip);

        $fileName = 'Surat_Izin_Kendaraan_'.str_replace(' ', '_', $plat).'_'.$tanggal->format('Ymd').'.docx';

        return response()->download($tmpOutput, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Fallback: generate surat izin langsung dari data (tanpa template upload).
     */
    protected function generateManual(IzinKendaraan $izin)
    {
        $kendaraan = $izin->kendaraan;
        $plat = $kendaraan?->platAktif?->nomor_plat ?? '-';
        $tanggal = Carbon::parse($izin->tanggal_berangkat)->locale('id');

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginTop' => 720,
            'marginBottom' => 720,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ]);

        $center = ['alignment' => Jc::CENTER];
        $bold = ['bold' => true];

        // Kop surat
        $section->addText('PEMERINTAH PROVINSI JAWA TIMUR', $bold, $center);
        $section->addText('DINAS PERKEBUNAN', $bold, $center);
        $section->addText('SURAT IZIN PEMAKAIAN KENDARAAN DINAS', ['bold' => true, 'size' => 11], $center);
        $section->addText('Nomor: ......../IZINK/....', ['size' => 11], $center);

        $section->addTextBreak(1);
        $section->addText('Yang bertanda tangan di bawah ini memberikan izin pemakaian kendaraan dinas kepada:');

        // Detail tabel
        $table = $section->addTable([
            'borderSize' => 4,
            'borderColor' => '000000',
            'cellMargin' => 60,
        ]);
        $detail = [
            'Nama Pengemudi' => $izin->nama_pengemudi ?? '-',
            'Unit / Pegawai Pemohon' => $izin->pengaju?->nama_pegawai ?? '-',
            'Kendaraan Dinas' => ($kendaraan?->jenis_kendaraan ?? '-').' ('.$plat.')',
            'Merk / Tipe' => $kendaraan?->merk && $kendaraan?->tipe
                ? $kendaraan->merk.' / '.$kendaraan->tipe
                : ($kendaraan?->merk ?: ($kendaraan?->namaTampil() ?: '-')),
            'Tanggal Berangkat' => $tanggal->translatedFormat('d F Y'),
            'Waktu / Durasi' => $izin->durasi ?? '-',
            'Tujuan' => $izin->tujuan ?? '-',
            'Pengurus Barang (Penyerah)' => $izin->penyerah?->nama_pegawai ?? '-',
        ];

        foreach ($detail as $label => $value) {
            $table->addRow();
            $table->addCell(4200, ['gridSpan' => 1])->addText($label, ['bold' => true, 'size' => 11]);
            $table->addCell(7200)->addText($value, ['size' => 11]);
        }

        $section->addTextBreak(1);
        $section->addText('Demikian surat izin ini dibuat agar digunakan sebagaimana mestinya, dan kendaraan dijaga serta dirawat dengan baik selama dipergunakan.');

        $section->addTextBreak(2);

        // Blok tanda tangan
        $sigTable = $section->addTable(['cellMargin' => 60]);
        $sigTable->addRow();
        $col1 = $sigTable->addCell(5400);
        $col2 = $sigTable->addCell(5400);

        $left = [
            'Pemohon,',
            '',
            '',
            $izin->pengaju?->nama_pegawai ?? '........................',
            'NIP. '.($izin->pengaju?->nip ?? '........................'),
        ];
        $right = [
            'Surabaya, '.$tanggal->translatedFormat('d F Y'),
            'Pengurus Barang,',
            '',
            '',
            $izin->penyerah?->nama_pegawai ?? 'Achmar Adrian Ramadhan, A.Md.',
            'NIP. '.($izin->penyerah?->nip ?? '19991223 202504 1 006'),
        ];

        foreach ($left as $line) {
            $col1->addText($line, ['size' => 11], ['alignment' => Jc::CENTER]);
        }
        foreach ($right as $line) {
            $col2->addText($line, ['size' => 11], ['alignment' => Jc::CENTER]);
        }

        $filePath = sys_get_temp_dir().'/Surat_Izin_Kendaraan_'.uniqid().'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($filePath);

        $fileName = 'Surat_Izin_Kendaraan_'.str_replace(' ', '_', $plat).'_'.$tanggal->format('Ymd').'.docx';

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }
}
