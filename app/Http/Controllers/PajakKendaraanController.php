<?php

namespace App\Http\Controllers;

use App\Models\Kendaraan;
use App\Models\PajakKendaraan;
use App\Models\RiwayatPlat;
use App\Services\AsetScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PajakKendaraanController extends Controller
{
    public function __construct(private readonly AsetScope $scope)
    {
    }

    /**
     * Object-level: kendaraan harus dalam cakupan user dan user harus Admin Aset.
     */
    private function authorizeKendaraan(Kendaraan $kendaraan): void
    {
        abort_if(
            ! auth()->user()->hasRole('Admin Aset'),
            403,
            'Hanya Admin Aset yang diperbolehkan mengelola pajak kendaraan.'
        );

        abort_if(
            ! $kendaraan->aset || ! $this->scope->bolehAksesAset($kendaraan->aset),
            403,
            $this->scope->pesanAkses('mengelola pajak kendaraan')
        );
    }

    /**
     * Simpan transaksi pajak kendaraan baru (Tahunan atau 5 Tahunan).
     *
     * Tanggal jatuh tempo adalah input manual Admin sesuai bukti/STNK resmi Samsat.
     * Tidak menggunakan kalkulasi penambahan tanggal otomatis.
     */
    public function store(Request $request, Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan);

        $limaTahunan = (bool) $request->input('pajak_5_tahunan', false);

        $rules = [
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tanggal_bayar' => ['required', 'date'],
            'tanggal_berakhir' => ['required', 'date'], // Tanggal Jatuh Tempo PKB Berikutnya
            'nominal' => ['required', 'numeric', 'min:0'], // Nominal Total Pembayaran
            'pajak_5_tahunan' => ['nullable', 'boolean'],
            'bukti_pajak' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx', 'max:5120'],
        ];

        if ($limaTahunan) {
            $rules['tanggal_berakhir_stnk'] = ['required', 'date'];
            $rules['nomor_plat_baru'] = ['required', 'string', 'max:20'];
        }

        $data = $request->validate($rules, [
            'tahun.required' => 'Tahun pajak wajib diisi.',
            'tanggal_bayar.required' => 'Tanggal pembayaran wajib diisi.',
            'tanggal_berakhir.required' => 'Tanggal jatuh tempo PKB wajib diisi.',
            'nominal.required' => 'Nominal total pembayaran wajib diisi.',
            'tanggal_berakhir_stnk.required' => 'Tanggal berakhir STNK (5 tahunan) wajib diisi untuk Pajak 5 Tahunan.',
            'nomor_plat_baru.required' => 'Nomor plat setelah pembayaran wajib diisi untuk Pajak 5 Tahunan.',
            'bukti_pajak.required' => 'Bukti pembayaran pajak wajib diunggah.',
            'bukti_pajak.max' => 'Ukuran berkas bukti pembayaran maksimal 5 MB.',
            'bukti_pajak.mimes' => 'Format berkas bukti pembayaran harus JPG, PNG, PDF, DOC/DOCX, atau XLS/XLSX.',
        ]);

        $berakhirPkb = Carbon::parse($data['tanggal_berakhir']);
        $berakhirStnk = $limaTahunan && ! empty($data['tanggal_berakhir_stnk'])
            ? Carbon::parse($data['tanggal_berakhir_stnk'])
            : null;
        $tanggalBayar = Carbon::parse($data['tanggal_bayar']);
        $nominalTotal = $data['nominal'];

        $buktiPath = null;
        $buktiNama = null;
        $file = $request->file('bukti_pajak');
        if ($file) {
            $buktiPath = $file->store('pajak-kendaraan', 'public');
            $buktiNama = $file->getClientOriginalName();
        }

        try {
            DB::transaction(function () use (
                $kendaraan,
                $data,
                $limaTahunan,
                $berakhirPkb,
                $berakhirStnk,
                $tanggalBayar,
                $nominalTotal,
                $buktiPath,
                $buktiNama
            ) {
                if ($limaTahunan) {
                    // Pajak 5 Tahunan:
                    // 1. Menonaktifkan transaksi 5 Tahunan aktif sebelumnya.
                    PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)
                        ->where('pajak_5_tahunan', true)
                        ->where('status', 'Aktif')
                        ->update(['status' => 'Tidak Aktif']);

                    // 2. Menonaktifkan transaksi Tahunan aktif sebelumnya.
                    // Pajak 5 Tahunan menjadi sumber informasi terbaru untuk jatuh tempo PKB dan STNK.
                    PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)
                        ->where('pajak_5_tahunan', false)
                        ->where('status', 'Aktif')
                        ->update(['status' => 'Tidak Aktif']);

                    // 3. Simpan record Pajak 5 Tahunan baru.
                    PajakKendaraan::create([
                        'id_kendaraan' => $kendaraan->id_kendaraan,
                        'jenis_pajak' => '5 Tahunan',
                        'tahun' => (int) $data['tahun'],
                        'tanggal_bayar' => $tanggalBayar,
                        'tanggal_berakhir' => $berakhirPkb,
                        'tanggal_berakhir_stnk' => $berakhirStnk,
                        'nominal' => $nominalTotal,
                        'total_pajak' => $nominalTotal,
                        'pajak_5_tahunan' => true,
                        'status' => 'Aktif',
                        'bukti_pajak_path' => $buktiPath,
                        'bukti_pajak_nama' => $buktiNama,
                    ]);

                    // 4. Update data plat aktif yang sudah ada (in-place) tanpa membuat baris baru/duplikat.
                    $nomorPlatBaru = trim($data['nomor_plat_baru']);
                    $platAktif = RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)
                        ->where('status', 'Aktif')
                        ->first();

                    if ($platAktif) {
                        $platAktif->update([
                            'nomor_plat' => $nomorPlatBaru,
                            'tanggal_berlaku' => $berakhirStnk,
                            'status' => 'Aktif',
                        ]);
                    } else {
                        RiwayatPlat::create([
                            'id_kendaraan' => $kendaraan->id_kendaraan,
                            'nomor_plat' => $nomorPlatBaru,
                            'tanggal_berlaku' => $berakhirStnk,
                            'status' => 'Aktif',
                            'ganti_plat' => false,
                        ]);
                    }
                } else {
                    // Pajak Tahunan:
                    // 1. Menonaktifkan transaksi Tahunan aktif sebelumnya.
                    PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)
                        ->where('pajak_5_tahunan', false)
                        ->where('status', 'Aktif')
                        ->update(['status' => 'Tidak Aktif']);

                    // 2. Simpan record Pajak Tahunan baru.
                    PajakKendaraan::create([
                        'id_kendaraan' => $kendaraan->id_kendaraan,
                        'jenis_pajak' => 'Tahunan',
                        'tahun' => (int) $data['tahun'],
                        'tanggal_bayar' => $tanggalBayar,
                        'tanggal_berakhir' => $berakhirPkb,
                        'tanggal_berakhir_stnk' => null,
                        'nominal' => $nominalTotal,
                        'total_pajak' => $nominalTotal,
                        'pajak_5_tahunan' => false,
                        'status' => 'Aktif',
                        'bukti_pajak_path' => $buktiPath,
                        'bukti_pajak_nama' => $buktiNama,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            if ($buktiPath) {
                Storage::disk('public')->delete($buktiPath);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaksi pajak berhasil ditambahkan.',
        ]);
    }

    /**
     * Edit transaksi pajak yang berstatus Aktif.
     *
     * Transaksi Tidak Aktif ditolak dari pengeditan.
     * Jenis transaksi tidak boleh diubah.
     */
    public function update(Request $request, Kendaraan $kendaraan, PajakKendaraan $pajak)
    {
        if ($pajak->id_kendaraan !== $kendaraan->id_kendaraan) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid.'], 422);
        }

        $this->authorizeKendaraan($kendaraan);

        // Edit hanya diperbolehkan untuk transaksi dengan status Aktif
        if ($pajak->status !== 'Aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya transaksi dengan status Aktif yang dapat diedit.',
            ], 422);
        }

        $isLimaTahunan = (bool) $pajak->pajak_5_tahunan;

        $rules = [
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tanggal_bayar' => ['required', 'date'],
            'tanggal_berakhir' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'bukti_pajak' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx', 'max:5120'],
        ];

        if ($isLimaTahunan) {
            $rules['tanggal_berakhir_stnk'] = ['required', 'date'];
            $rules['nomor_plat_baru'] = ['required', 'string', 'max:20'];
        }

        $data = $request->validate($rules, [
            'tahun.required' => 'Tahun pajak wajib diisi.',
            'tanggal_bayar.required' => 'Tanggal pembayaran wajib diisi.',
            'tanggal_berakhir.required' => 'Tanggal jatuh tempo PKB wajib diisi.',
            'nominal.required' => 'Nominal total pembayaran wajib diisi.',
            'tanggal_berakhir_stnk.required' => 'Tanggal berakhir STNK wajib diisi untuk Pajak 5 Tahunan.',
            'nomor_plat_baru.required' => 'Nomor plat aktif wajib diisi untuk Pajak 5 Tahunan.',
            'bukti_pajak.max' => 'Ukuran berkas bukti pembayaran maksimal 5 MB.',
            'bukti_pajak.mimes' => 'Format berkas bukti pembayaran harus JPG, PNG, PDF, DOC/DOCX, atau XLS/XLSX.',
        ]);

        $berakhirPkb = Carbon::parse($data['tanggal_berakhir']);
        $berakhirStnk = $isLimaTahunan && ! empty($data['tanggal_berakhir_stnk'])
            ? Carbon::parse($data['tanggal_berakhir_stnk'])
            : null;
        $tanggalBayar = Carbon::parse($data['tanggal_bayar']);
        $nominalTotal = $data['nominal'];

        $buktiBaruPath = null;
        $buktiBaruNama = null;
        $file = $request->file('bukti_pajak');
        if ($file) {
            $buktiBaruPath = $file->store('pajak-kendaraan', 'public');
            $buktiBaruNama = $file->getClientOriginalName();
        }

        $buktiLama = $pajak->bukti_pajak_path;

        try {
            DB::transaction(function () use (
                $kendaraan,
                $pajak,
                $data,
                $isLimaTahunan,
                $berakhirPkb,
                $berakhirStnk,
                $tanggalBayar,
                $nominalTotal,
                $buktiBaruPath,
                $buktiBaruNama
            ) {
                $payload = [
                    'tahun' => (int) $data['tahun'],
                    'tanggal_bayar' => $tanggalBayar,
                    'tanggal_berakhir' => $berakhirPkb,
                    'nominal' => $nominalTotal,
                    'total_pajak' => $nominalTotal,
                ];

                if ($buktiBaruPath) {
                    $payload['bukti_pajak_path'] = $buktiBaruPath;
                    $payload['bukti_pajak_nama'] = $buktiBaruNama;
                }

                if ($isLimaTahunan) {
                    $payload['tanggal_berakhir_stnk'] = $berakhirStnk;

                    // Sinkronisasi data plat aktif in-place
                    $nomorPlatBaru = trim($data['nomor_plat_baru']);
                    $platAktif = RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)
                        ->where('status', 'Aktif')
                        ->first();

                    if ($platAktif) {
                        $platAktif->update([
                            'nomor_plat' => $nomorPlatBaru,
                            'tanggal_berlaku' => $berakhirStnk,
                            'status' => 'Aktif',
                        ]);
                    } else {
                        RiwayatPlat::create([
                            'id_kendaraan' => $kendaraan->id_kendaraan,
                            'nomor_plat' => $nomorPlatBaru,
                            'tanggal_berlaku' => $berakhirStnk,
                            'status' => 'Aktif',
                            'ganti_plat' => false,
                        ]);
                    }
                }

                $pajak->update($payload);
            });
        } catch (\Throwable $e) {
            if ($buktiBaruPath) {
                Storage::disk('public')->delete($buktiBaruPath);
            }
            throw $e;
        }

        // Hapus file bukti lama setelah file baru berhasil disimpan dan transaksi commit
        if ($buktiBaruPath && $buktiLama && $buktiLama !== $buktiBaruPath) {
            Storage::disk('public')->delete($buktiLama);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data transaksi pajak berhasil diperbarui.',
        ]);
    }

    /**
     * Unduh bukti pembayaran pajak.
     */
    public function downloadBukti(Kendaraan $kendaraan, PajakKendaraan $pajak)
    {
        if ($pajak->id_kendaraan !== $kendaraan->id_kendaraan) {
            abort(404);
        }

        $this->authorizeKendaraan($kendaraan);

        if (! $pajak->bukti_pajak_path || ! Storage::disk('public')->exists($pajak->bukti_pajak_path)) {
            abort(404, 'Bukti pembayaran belum tersedia.');
        }

        return Storage::disk('public')->download(
            $pajak->bukti_pajak_path,
            $pajak->bukti_pajak_nama ?? ('bukti-pajak-'.$pajak->id_pajak)
        );
    }

    /**
     * Sajikan bukti pembayaran secara INLINE untuk pratinjau di modal (gambar/PDF).
     */
    public function previewBukti(Kendaraan $kendaraan, PajakKendaraan $pajak)
    {
        if ($pajak->id_kendaraan !== $kendaraan->id_kendaraan) {
            abort(404);
        }

        $this->authorizeKendaraan($kendaraan);

        if (! $pajak->bukti_pajak_path || ! Storage::disk('public')->exists($pajak->bukti_pajak_path)) {
            abort(404, 'Bukti pembayaran belum tersedia.');
        }

        return Storage::disk('public')->response(
            $pajak->bukti_pajak_path,
            $pajak->bukti_pajak_nama ?? ('bukti-pajak-'.$pajak->id_pajak)
        );
    }

    /**
     * Penghapusan transaksi pajak dinonaktifkan sepenuhnya sesuai aturan bisnis.
     * Permintaan DELETE langsung melalui URL atau API wajib ditolak (403 Forbidden).
     */
    public function destroy(Kendaraan $kendaraan, PajakKendaraan $pajak)
    {
        abort(403, 'Penghapusan transaksi pajak tidak diperbolehkan.');
    }
}
