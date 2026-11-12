<?php

namespace App\Services;

use App\Models\Aset;
use App\Models\InventarisRuangan;
use App\Models\UsulanPenghapusan;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya sumber logika "aset masuk antrean usulan penghapusan".
 *
 * Kondisi "Rusak Berat" adalah PEMICU OTOMATIS: begitu sebuah aset ditandai
 * Rusak Berat (create/update), aset keluar dari daftar aktif
 * (status_aset = diusulkan_hapus) dan baris usulan dibuat supaya muncul di
 * menu Penghapusan Aset. Aturan yang ditegakkan:
 *
 *   - hanya aset berstatus pengguna (aktif/nonaktif) yang diproses — aset
 *     yang sudah di antrean (diusulkan_hapus) / sudah dihapuskan diabaikan;
 *   - anti-duplikat: maksimal SATU usulan aktif per aset;
 *   - atomik: perubahan status + pencatatan usulan dibungkus satu transaksi
 *     agar tidak pernah tertinggal status berubah tanpa usulan (atau
 *     sebaliknya);
 *   - TIDAK menembus otorisasi: pemanggil WAJIB sudah menjalankan
 *     authorize*() sebelum memanggil service ini.
 */
class UsulanPenghapusanService
{
    public const KONDISI_RUSAK_BERAT = 'Rusak Berat';

    /**
     * Status aset yang boleh diusulkan (status "pengguna").
     */
    public const STATUS_PENGGUNA = ['aktif', 'nonaktif'];

    /**
     * Status sistem setelah masuk antrean penghapusan.
     */
    public const STATUS_ANTREAN = 'diusulkan_hapus';

    /**
     * Usulkan penghapusan sebuah aset (idempoten, atomik).
     *
     * @return bool true bila usulan BARU dibuat; false bila dilewati
     *              (status bukan pengguna, atau sudah ada usulan aktif).
     */
    public function usulkan(
        Aset $aset,
        string $alasan,
        ?string $keterangan = null,
        bool $jadikanRusakBerat = false
    ): bool {
        return DB::transaction(function () use ($aset, $alasan, $keterangan, $jadikanRusakBerat) {
            // Kunci baris supaya dua request bersamaan tidak membuat usulan
            // ganda (lockForUpdate diabaikan pada driver yang tidak
            // mendukung, mis. sqlite — tidak masalah untuk dev/test).
            $fresh = Aset::whereKey($aset->getKey())->lockForUpdate()->first();

            if (! $fresh || ! in_array($fresh->status_aset, self::STATUS_PENGGUNA, true)) {
                return false;
            }

            $sudahAda = UsulanPenghapusan::where('id_aset', $fresh->getKey())
                ->where('status_usulan', 'diajukan')
                ->lockForUpdate()
                ->exists();

            if ($sudahAda) {
                // Sudah di antrean: cukup konsistenkan status/kondisi tanpa
                // membuat usulan kedua.
                $this->pindahKeAntrean($fresh, $jadikanRusakBerat);

                return false;
            }

            $this->pindahKeAntrean($fresh, $jadikanRusakBerat);

            UsulanPenghapusan::create([
                'id_aset' => $fresh->getKey(),
                'id_pegawai_penghapus' => auth()->user()?->pegawai?->id_pegawai,
                'tanggal_usulan' => now()->toDateString(),
                'alasan_penghapusan' => $alasan,
                'status_usulan' => 'diajukan',
                'keterangan' => $keterangan,
            ]);

            return true;
        });
    }

    /**
     * Pemicu otomatis: hanya jalan bila kondisi aset SUDAH "Rusak Berat".
     *
     * Dipanggil SETELAH kondisi tersimpan (create/update) sehingga `$aset`
     * sudah membawa nilai kondisi terbaru.
     */
    public function usulkanKarenaRusakBerat(Aset $aset, ?string $keterangan = null): bool
    {
        if (($aset->kondisi ?? null) !== self::KONDISI_RUSAK_BERAT) {
            return false;
        }

        return $this->usulkan(
            $aset,
            alasan: self::KONDISI_RUSAK_BERAT,
            keterangan: $keterangan ?? 'Otomatis: kondisi aset Rusak Berat.',
            jadikanRusakBerat: true,
        );
    }

    /**
     * Usulkan penghapusan sebuah inventaris lokal ruangan (atomik, idempoten).
     *
     * @return bool true bila usulan baru dibuat; false bila dilewati.
     */
    public function usulkanInventaris(
        InventarisRuangan $inventaris,
        string $alasan = self::KONDISI_RUSAK_BERAT,
        ?string $keterangan = null
    ): bool {
        return DB::transaction(function () use ($inventaris, $alasan, $keterangan) {
            $fresh = InventarisRuangan::whereKey($inventaris->getKey())->lockForUpdate()->first();

            if (! $fresh || $fresh->status !== InventarisRuangan::STATUS_AKTIF) {
                return false;
            }

            $sudahAda = UsulanPenghapusan::where('id_inventaris', $fresh->getKey())
                ->where('status_usulan', 'diajukan')
                ->lockForUpdate()
                ->exists();

            if ($sudahAda) {
                $fresh->update([
                    'status' => InventarisRuangan::STATUS_DIUSULKAN_HAPUS,
                    'kondisi' => self::KONDISI_RUSAK_BERAT,
                ]);

                return false;
            }

            $fresh->update([
                'status' => InventarisRuangan::STATUS_DIUSULKAN_HAPUS,
                'kondisi' => self::KONDISI_RUSAK_BERAT,
            ]);

            UsulanPenghapusan::create([
                'id_aset' => null,
                'id_inventaris' => $fresh->getKey(),
                'id_pegawai_penghapus' => auth()->user()?->pegawai?->id_pegawai,
                'tanggal_usulan' => now()->toDateString(),
                'alasan_penghapusan' => $alasan,
                'status_usulan' => 'diajukan',
                'keterangan' => $keterangan,
            ]);

            return true;
        });
    }

    /**
     * Pindahkan aset ke antrean usulan (status + opsional kondisi).
     */
    private function pindahKeAntrean(Aset $aset, bool $jadikanRusakBerat): void
    {
        $aset->update([
            'status_aset' => self::STATUS_ANTREAN,
            'kondisi' => $jadikanRusakBerat ? self::KONDISI_RUSAK_BERAT : $aset->kondisi,
        ]);
    }
}
