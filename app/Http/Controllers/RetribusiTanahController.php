<?php

namespace App\Http\Controllers;

use App\Models\RetribusiTanah;
use App\Models\Tanah;
use App\Rules\FotoKameraValid;
use App\Services\BuktiOtorisasi;
use App\Services\RiwayatTanah;
use App\Services\TanahScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RetribusiTanahController extends Controller
{
    /**
     * Gerbang modul Tanah: Admin Aset atau Admin UPT P2BTP saja
     * (deny by default untuk seluruh method).
     */
    public function __construct(
        private readonly TanahScope $tanahScope,
        private readonly BuktiOtorisasi $bukti,
        private readonly RiwayatTanah $riwayat,
    ) {
        // Guard auth()->check() — lihat TanahController (CLI/route:list).
        if (auth()->check()) {
            abort_unless(
                $this->tanahScope->bolehAkses(),
                403,
                'Anda tidak memiliki hak akses untuk modul data tanah.'
            );
        }
    }

    /**
     * Menampilkan form tambah retribusi.
     */
    public function create(Tanah $tanah)
    {
        return view('tanah.retribusi.create', compact('tanah'));
    }

    /**
     * Menyimpan data retribusi.
     */
    public function store(Request $request, Tanah $tanah)
    {
        $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],

            'status_pemanfaatan' => [
                'nullable',
                'string',
                'max:50',
            ],

            'biaya_pengurusan' => ['nullable', 'numeric', 'min:0'],
            'PAD' => ['nullable', 'numeric', 'min:0'],
            'tarif_retribusi' => ['nullable', 'numeric', 'min:0'],
            'total_tarif_sewa' => ['nullable', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $validated['id_tanah'] = $tanah->id_tanah;

        DB::transaction(function () use ($validated, $tanah) {
            $retribusi = RetribusiTanah::create($validated);
            $this->syncPenerimaanPad($tanah);

            $this->riwayat->catat(
                $tanah,
                'CREATE',
                'retribusi',
                $retribusi->id_retribusi,
                null,
                $retribusi->fresh()->toArray(),
                null
            );
        });

        return redirect()
            ->route('tanah.show', $tanah)
            ->with('success', 'Data retribusi berhasil ditambahkan.');
    }

    /**
     * Sinkronisasi akumulasi penerimaan PAD ke kolom pada tanah induk.
     */
    private function syncPenerimaanPad(Tanah $tanah): void
    {
        $tanah->forceFill([
            'penerimaan_pad' => (float) $tanah->retribusi()->sum('PAD'),
        ])->save();
    }

    /**
     * Menampilkan form edit retribusi.
     */
    public function edit(RetribusiTanah $retribusi)
    {
        $tanah = $retribusi->tanah;

        return view('tanah.retribusi.edit', compact(
            'retribusi',
            'tanah'
        ));
    }

    /**
     * Memperbarui data retribusi.
     */
    public function update(
        Request $request,
        RetribusiTanah $retribusi
    ) {
        $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],

            'status_pemanfaatan' => [
                'nullable',
                'string',
                'max:50',
            ],

            'biaya_pengurusan' => ['nullable', 'numeric', 'min:0'],
            'PAD' => ['nullable', 'numeric', 'min:0'],
            'tarif_retribusi' => ['nullable', 'numeric', 'min:0'],
            'total_tarif_sewa' => ['nullable', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],

            // Konfirmasi ulang password akun yang sedang login (step-up
            // otorisasi). Diverifikasi server-side, tidak pernah disimpan.
            'password_otorisasi' => ['required', 'current_password:web'],

            // Foto bukti kamera langsung (data URL). Wajib; validasi isi file.
            'foto_bukti_otentikasi' => ['required', 'string', new FotoKameraValid],
        ], [
            'password_otorisasi.required' => 'Password otorisasi wajib diisi.',
            'password_otorisasi.current_password' => 'Password otorisasi tidak cocok dengan akun Anda.',
            'foto_bukti_otentikasi.required' => 'Silakan ambil foto konfirmasi terlebih dahulu sebelum menyimpan.',
        ]);

        unset($validated['password_otorisasi'], $validated['foto_bukti_otentikasi']);

        $tanah = $retribusi->tanah;
        $dataLama = $retribusi->toArray();

        try {
            $fotoBukti = $this->bukti->simpan($request->input('foto_bukti_otentikasi'));
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->except(['password_otorisasi', 'foto_bukti_otentikasi']))
                ->withErrors(['foto_bukti_otentikasi' => 'Foto bukti gagal disimpan. Silakan ambil foto ulang.']);
        }

        try {
            DB::transaction(function () use ($retribusi, $validated, $tanah, $dataLama, $fotoBukti) {
                $retribusi->update($validated);
                $this->syncPenerimaanPad($tanah);

                $this->riwayat->catat(
                    $tanah,
                    'UPDATE',
                    'retribusi',
                    $retribusi->id_retribusi,
                    $dataLama,
                    $retribusi->fresh()->toArray(),
                    $fotoBukti
                );
            });
        } catch (\Throwable $e) {
            $this->bukti->hapus($fotoBukti);

            return back()
                ->withInput($request->except(['password_otorisasi', 'foto_bukti_otentikasi']))
                ->withErrors(['foto_bukti_otentikasi' => 'Data gagal disimpan sehingga foto bukti dibatalkan. Silakan coba lagi.']);
        }

        return redirect()
            ->route('tanah.show', $tanah)
            ->with('success', 'Data retribusi berhasil diperbarui.');
    }
}
