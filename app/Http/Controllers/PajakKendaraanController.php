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
     * Object-level: kendaraan harus dalam cakupan user (route pajak sudah
     * `role:Admin Aset`, dicek ulang agar tetap aman bila role diperluas).
     */
    private function authorizeKendaraan(Kendaraan $kendaraan): void
    {
        abort_if(
            ! $kendaraan->aset || ! $this->scope->bolehAksesAset($kendaraan->aset),
            403,
            $this->scope->pesanAkses('mengelola pajak kendaraan')
        );
    }

    /**
     * Simpan pajak kendaraan baru.
     *
     * Tanggal jatuh tempo (`tanggal_berakhir`) adalah input manual Admin
     * sesuai bukti/STNK dan disimpan persis apa adanya - tanpa hitung otomatis.
     * Bila checkbox "Pajak 5 Tahunan / Ganti Plat" dicentang, nomor plat baru
     * wajib diisi; plat aktif lama di-demosi dan plat baru dibuat dalam satu
     * transaksi atomik (gagal = rollback total).
     */
    public function store(Request $request, Kendaraan $kendaraan)
    {
        $this->authorizeKendaraan($kendaraan);

        $data = $request->validate([
            'jenis_pajak' => ['required', 'string', 'max:50'],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'tanggal_bayar' => ['nullable', 'date'],
            'tanggal_berakhir' => ['required', 'date'],
            'nominal' => ['nullable', 'numeric', 'min:0'],
            'total_pajak' => ['nullable', 'numeric', 'min:0'],
            'pajak_5_tahunan' => ['nullable', 'boolean'],
            'nomor_plat_baru' => ['required_if:pajak_5_tahunan,1', 'nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['Aktif', 'Tidak Aktif'])],
            'bukti_pajak' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx', 'max:5120'],
        ]);

        $status = $data['status'] ?? 'Aktif';
        $limaTahunan = (bool) ($data['pajak_5_tahunan'] ?? false);
        $berakhir = Carbon::parse($data['tanggal_berakhir']);

        // Bukti disimpan lebih dulu di luar transaksi agar tidak ada berkas
        // yatim bila transaksi gagal (dibersihkan pada blok catch).
        $buktiPath = null;
        $buktiNama = null;
        $file = $request->file('bukti_pajak');
        if ($file) {
            $buktiPath = $file->store('pajak-kendaraan', 'public');
            $buktiNama = $file->getClientOriginalName();
        }

        try {
            DB::transaction(function () use ($kendaraan, $data, $status, $limaTahunan, $berakhir, $buktiPath, $buktiNama) {
                if ($status === 'Aktif') {
                    PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)
                        ->where('pajak_5_tahunan', $limaTahunan)
                        ->where('status', 'Aktif')
                        ->update(['status' => 'Tidak Aktif']);
                }

                PajakKendaraan::create([
                    'id_kendaraan' => $kendaraan->id_kendaraan,
                    'jenis_pajak' => $data['jenis_pajak'],
                    'tahun' => $data['tahun'] ?? $berakhir->year,
                    'tanggal_bayar' => ! empty($data['tanggal_bayar']) ? Carbon::parse($data['tanggal_bayar']) : null,
                    'tanggal_berakhir' => $berakhir,
                    'nominal' => $data['nominal'] ?? null,
                    'total_pajak' => $data['total_pajak'] ?? null,
                    'pajak_5_tahunan' => $limaTahunan,
                    'status' => $status,
                    'bukti_pajak_path' => $buktiPath,
                    'bukti_pajak_nama' => $buktiNama,
                ]);

                if ($limaTahunan && $status === 'Aktif') {
                    RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)
                        ->where('status', 'Aktif')
                        ->update(['status' => 'Tidak Aktif']);

                    RiwayatPlat::create([
                        'id_kendaraan' => $kendaraan->id_kendaraan,
                        'nomor_plat' => $data['nomor_plat_baru'],
                        'tanggal_berlaku' => $berakhir,
                        'ganti_plat' => true,
                        'status' => 'Aktif',
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
            'message' => 'Data pajak berhasil ditambahkan.',
            'tanggal_berakhir' => $berakhir->format('Y-m-d'),
        ]);
    }

    /**
     * Lengkapi/perbarui data pajak yang sudah ada tanpa membuat record baru.
     *
     * HANYA mengubah jenis pajak, tahun, tanggal bayar, tanggal jatuh tempo,
     * nominal, total pajak, dan bukti pembayaran. Status aktif, flag 5 tahunan,
     * dan Riwayat Plat TIDAK disentuh agar riwayat tetap utuh dan tidak ada
     * duplikasi nilai ke record lain.
     */
    public function update(Request $request, Kendaraan $kendaraan, PajakKendaraan $pajak)
    {
        if ($pajak->id_kendaraan !== $kendaraan->id_kendaraan) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid.'], 422);
        }

        $this->authorizeKendaraan($kendaraan);

        $data = $request->validate([
            'jenis_pajak' => ['required', 'string', 'max:50'],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'tanggal_bayar' => ['nullable', 'date'],
            'tanggal_berakhir' => ['required', 'date'],
            'nominal' => ['nullable', 'numeric', 'min:0'],
            'total_pajak' => ['nullable', 'numeric', 'min:0'],
            'bukti_pajak' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx', 'max:5120'],
        ]);

        // Tahun TIDAK diturunkan otomatis dari tanggal bayar/jatuh tempo; nilai
        // sesuai input admin. Bila dikosongkan, nilai tersimpan dipertahankan.
        $tahunBaru = ! empty($data['tahun']) ? (int) $data['tahun'] : $pajak->tahun;
        $berakhirBaru = Carbon::parse($data['tanggal_berakhir']);

        $buktiBaruPath = null;
        $buktiBaruNama = null;
        $file = $request->file('bukti_pajak');
        if ($file) {
            $buktiBaruPath = $file->store('pajak-kendaraan', 'public');
            $buktiBaruNama = $file->getClientOriginalName();
        }

        $buktiLama = $pajak->bukti_pajak_path;

        try {
            DB::transaction(function () use ($pajak, $data, $tahunBaru, $berakhirBaru, $buktiBaruPath, $buktiBaruNama) {
                $pajak->update([
                    'jenis_pajak' => $data['jenis_pajak'],
                    'tahun' => $tahunBaru,
                    'tanggal_bayar' => ! empty($data['tanggal_bayar']) ? Carbon::parse($data['tanggal_bayar']) : null,
                    'tanggal_berakhir' => $berakhirBaru,
                    'nominal' => $data['nominal'] ?? null,
                    'total_pajak' => $data['total_pajak'] ?? null,
                    'bukti_pajak_path' => $buktiBaruPath ?? $pajak->bukti_pajak_path,
                    'bukti_pajak_nama' => $buktiBaruNama ?? $pajak->bukti_pajak_nama,
                ]);
            });
        } catch (\Throwable $e) {
            if ($buktiBaruPath) {
                Storage::disk('public')->delete($buktiBaruPath);
            }
            throw $e;
        }

        // Berkas lama baru dihapus setelah transaksi sukses.
        if ($buktiBaruPath && $buktiLama && $buktiLama !== $buktiBaruPath) {
            Storage::disk('public')->delete($buktiLama);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data pajak berhasil diperbarui.',
        ]);
    }

    /**
     * Unduh/lihat bukti pembayaran pajak.
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
     * Sajikan bukti pembayaran secara INLINE untuk pratinjau di modal
     * (gambar/PDF). Otorisasi & kepemilikan kendaraan tetap dicek; path
     * penyimpanan tidak pernah dibocorkan ke client. Unduhan tetap lewat
     * route `kendaraan.pajak.bukti`.
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
     * Hapus pajak. Bila pajak ini adalah Pajak 5 Tahunan yang menciptakan plat
     * aktif saat ini, plat tersebut dihapus dan plat yang sebelumnya aktif
     * (didemosi oleh transaksi tersebut) dipulihkan — semuanya atomik. Tanpa
     * langkah ini, menghapus pajak akan meninggalkan plat yatim sehingga
     * transaksi 5 Tahunan berikutnya menciptakan plat ganda.
     *
     * Pemetaan pajak -> plat TIDAK ada FK-nya. Tanda stabil yang dipakai saat
     * `store` membuat plat adalah: ganti_plat = true, tanggal_berlaku sama
     * dengan tanggal_berakhir pajak, dan status Aktif. Syarat status Aktif
     * juga memastikan hanya transaksi 5 Tahunan TERKINI yang di-revert: pajak
     * lama yang platnya sudah tersisih tidak boleh menyentuh riwayat plat
     * (historinya harus tetap utuh).
     */
    public function destroy(Kendaraan $kendaraan, PajakKendaraan $pajak)
    {
        if ($pajak->id_kendaraan !== $kendaraan->id_kendaraan) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid.'], 422);
        }

        $this->authorizeKendaraan($kendaraan);

        $isLimaTahunan = (bool) $pajak->pajak_5_tahunan;
        $isAktif = strcasecmp((string) $pajak->status, 'Aktif') === 0;

        $platDibuat = null;
        $platSebelumnya = null;

        // A. Hanya Pajak 5 Tahunan berstatus AKTIF yang dapat memicu pemulihan plat lama.
        // Pajak 5 Tahunan historis/tidak aktif dan Pajak Tahunan TIDAK boleh memengaruhi Riwayat Plat.
        if ($isLimaTahunan && $isAktif) {
            $tgl = $pajak->tanggal_berakhir ? $pajak->tanggal_berakhir->format('Y-m-d') : null;

            if ($tgl) {
                $platDibuat = RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)
                    ->where('ganti_plat', true)
                    ->where('status', 'Aktif')
                    ->whereDate('tanggal_berlaku', $tgl)
                    ->first();
            }

            // Jika relasi plat aktif tidak dapat ditentukan dengan pasti, jangan gunakan fallback berisiko!
            // Batalkan proses penghapusan secara aman agar tidak merusak plat aktif kendaraan.
            if (! $platDibuat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Penghapusan pajak dibatalkan: Relasi riwayat plat aktif terkait tidak dapat ditentukan secara pasti.',
                ], 422);
            }

            // Cari plat yang sebelumnya aktif (didemosi oleh transaksi pajak ini)
            $platSebelumnya = RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)
                ->where('status', 'Tidak Aktif')
                ->where('id_plat', '<', $platDibuat->id_plat)
                ->orderByDesc('id_plat')
                ->first();
        }

        $buktiLama = $pajak->bukti_pajak_path;

        DB::transaction(function () use ($pajak, $platDibuat, $platSebelumnya) {
            // Hapus data pajak
            $pajak->delete();

            // Jika ada plat aktif terkait yang sah: hapus plat tersebut dan aktifkan kembali plat sebelumnya
            if ($platDibuat) {
                $platDibuat->delete();

                if ($platSebelumnya) {
                    $platSebelumnya->update(['status' => 'Aktif']);
                }
            }
        });

        // Bersihkan berkas bukti fisik setelah transaksi database berhasil commit
        if ($buktiLama && Storage::disk('public')->exists($buktiLama)) {
            Storage::disk('public')->delete($buktiLama);
        }

        return response()->json(['success' => true, 'message' => 'Data pajak berhasil dihapus.']);
    }
}
