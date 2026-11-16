<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\SensusAset;
use App\Services\ActivityLogger;
use App\Services\AsetScope;
use App\Services\PeriodeSensus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Controller untuk mengelola pencatatan & riwayat Sensus Aset.
 *
 * Aturan bisnis:
 * - Siklus 3 tahunan dari tahun dasar 2025.
 * - Nilai hasil sensus: Baik, Rusak Ringan, Rusak Berat.
 * - Rusak Berat tidak otomatis membuat usulan penghapusan.
 * - Satu catatan per aset per periode, tidak menimpa periode historis.
 * - Otorisasi AsetScope: Admin Aset penuh, Admin UPT dibatasi UPT-nya.
 */
class SensusAsetController extends Controller
{
    public const HASIL_SENSUS = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

    private readonly ActivityLogger $logger;

    public function __construct(
        private readonly AsetScope $scope,
        ?ActivityLogger $logger = null,
    ) {
        $this->logger = $logger ?? app(ActivityLogger::class);
    }

    /**
     * Pastikan aset dapat diakses oleh user saat ini sesuai AsetScope.
     */
    private function authorizeAset(Aset $aset, string $aksi = 'mengakses sensus aset'): void
    {
        abort_unless(
            $this->scope->bolehAksesAset($aset),
            403,
            $this->scope->pesanAkses($aksi)
        );
    }

    /**
     * Halaman form tambah / edit sensus beserta riwayat seluruh periode.
     */
    public function edit(Request $request, Aset $aset)
    {
        $this->authorizeAset($aset, 'mengelola sensus aset');

        $aset->loadMissing([
            'barang',
            'kendaraan',
            'penempatanAktif.ruangan',
            'pemegangSaatIni.pegawai',
            'skpd',
            'lokasi',
        ]);

        $periodeAktif = PeriodeSensus::periodeAktif(null, $aset);
        $periodeDipilih = PeriodeSensus::normalisasi($request->query('periode', $periodeAktif));

        if (PeriodeSensus::periodeSudahBerakhir($periodeDipilih, null, $aset)) {
            abort(403, "Periode sensus {$periodeDipilih} telah berakhir dan terkunci. Pengubahan data sensus hanya dapat dilakukan pada periode yang sedang berlangsung.");
        }

        if (PeriodeSensus::periodeBelumDimulai($periodeDipilih, null, $aset)) {
            abort(403, "Periode sensus {$periodeDipilih} belum dimulai. Sensus hanya dapat dicatat pada periode yang sedang berlangsung.");
        }

        $sensus = SensusAset::where('id_aset', $aset->id_aset)
            ->where('periode', $periodeDipilih)
            ->first();

        $riwayatSensus = SensusAset::where('id_aset', $aset->id_aset)
            ->with(['user.pegawai'])
            ->orderByDesc('periode')
            ->orderByDesc('tanggal_sensus')
            ->get();

        $daftarPeriode = [$periodeAktif];

        return view('monitoring-aset.sensus', compact(
            'aset',
            'sensus',
            'riwayatSensus',
            'periodeAktif',
            'periodeDipilih',
            'daftarPeriode'
        ));
    }

    /**
     * Simpan / perbarui hasil sensus aset pada periode tertentu.
     */
    public function update(Request $request, Aset $aset)
    {
        $this->authorizeAset($aset, 'menyimpan sensus aset');

        $validated = $request->validate([
            'periode' => ['required', 'string'],
            'hasil_sensus' => ['required', 'in:Baik,Rusak Ringan,Rusak Berat'],
            'tanggal_sensus' => ['required', 'date'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], [
            'periode.required' => 'Periode sensus wajib ditentukan.',
            'hasil_sensus.required' => 'Hasil sensus wajib dipilih.',
            'hasil_sensus.in' => 'Hasil sensus hanya boleh: Baik, Rusak Ringan, atau Rusak Berat.',
            'tanggal_sensus.required' => 'Tanggal pelaksanaan sensus wajib diisi.',
            'tanggal_sensus.date' => 'Format tanggal sensus tidak valid.',
        ]);

        $periode = PeriodeSensus::normalisasi($validated['periode']);

        if (! PeriodeSensus::valid($periode, $aset)) {
            throw ValidationException::withMessages([
                'periode' => 'Format periode tidak valid atau tidak sesuai dengan siklus 3 tahunan aset ini (contoh: ' . PeriodeSensus::periodeAktif(null, $aset) . ').',
            ]);
        }

        if (PeriodeSensus::periodeSudahBerakhir($periode, null, $aset)) {
            throw ValidationException::withMessages([
                'periode' => "Periode sensus {$periode} telah berakhir dan terkunci. Pengubahan atau penambahan data hanya dapat dilakukan pada periode yang sedang berlangsung.",
            ]);
        }

        if (PeriodeSensus::periodeBelumDimulai($periode, null, $aset)) {
            throw ValidationException::withMessages([
                'periode' => "Periode sensus {$periode} berada di masa depan dan belum dapat diisi sebelum waktunya.",
            ]);
        }

        $existing = SensusAset::where('id_aset', $aset->id_aset)
            ->where('periode', $periode)
            ->first();

        $oldValues = $existing ? [
            'hasil_sensus' => $existing->hasil_sensus,
            'tanggal_sensus' => (string) ($existing->tanggal_sensus instanceof \DateTimeInterface ? $existing->tanggal_sensus->format('Y-m-d') : $existing->tanggal_sensus),
            'catatan' => $existing->catatan,
        ] : [];

        $newValues = [
            'hasil_sensus' => $validated['hasil_sensus'],
            'tanggal_sensus' => $validated['tanggal_sensus'],
            'catatan' => $validated['catatan'] ?? null,
        ];

        $sensus = DB::transaction(function () use ($aset, $periode, $validated) {
            $record = SensusAset::updateOrCreate(
                [
                    'id_aset' => $aset->id_aset,
                    'periode' => $periode,
                ],
                [
                    'hasil_sensus' => $validated['hasil_sensus'],
                    'tanggal_sensus' => $validated['tanggal_sensus'],
                    'catatan' => $validated['catatan'] ?? null,
                    'id_user' => auth()->id(),
                ]
            );

            // Perbarui kondisi pada tabel master aset
            $aset->update([
                'kondisi' => $validated['hasil_sensus'],
            ]);

            return $record;
        });

        $action = $existing ? 'Memperbarui' : 'Mencatat';
        $logType = $existing ? 'sensus_aset.updated' : 'sensus_aset.created';

        try {
            $this->logger->log(
                $logType,
                $sensus,
                $oldValues,
                $newValues,
                sprintf(
                    '%s hasil sensus %s untuk periode %s pada aset %s.',
                    $action,
                    $validated['hasil_sensus'],
                    $periode,
                    $aset->nomor_kartu_barang ?? (string) $aset->id_aset
                )
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat log aktivitas sensus aset: ' . $e->getMessage(), [
                'id_aset' => $aset->id_aset,
                'periode' => $periode,
            ]);
        }

        if ($request->input('redirect_to') === 'monitoring') {
            return redirect()
                ->route('monitoring-aset.index', ['periode' => $periode])
                ->with('success', "Hasil sensus aset periode {$periode} berhasil disimpan.");
        }

        return redirect()
            ->route('monitoring-aset.sensus.edit', ['aset' => $aset->id_aset, 'periode' => $periode])
            ->with('success', "Hasil sensus aset periode {$periode} berhasil disimpan.");
    }

    /**
     * Halaman khusus tampilan riwayat sensus seluruh periode.
     */
    public function riwayat(Aset $aset)
    {
        $this->authorizeAset($aset, 'melihat riwayat sensus aset');

        $aset->loadMissing([
            'barang',
            'kendaraan',
            'penempatanAktif.ruangan',
            'pemegangSaatIni.pegawai',
            'skpd',
            'lokasi',
        ]);

        $riwayatSensus = SensusAset::where('id_aset', $aset->id_aset)
            ->with(['user.pegawai'])
            ->orderByDesc('periode')
            ->orderByDesc('tanggal_sensus')
            ->get();

        $periodeAktif = PeriodeSensus::periodeAktif(null, $aset);

        return view('monitoring-aset.riwayat', compact(
            'aset',
            'riwayatSensus',
            'periodeAktif'
        ));
    }
}

