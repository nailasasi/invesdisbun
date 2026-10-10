<?php

namespace App\Services;

use App\Models\Aset;
use App\Models\PemegangAset;
use App\Models\Pegawai;
use App\Models\Ruangan;

/**
 * Aturan penempatan aset.
 *
 * `id_skpd` dan `id_lokasi` aset TIDAK PERNAH menjadi input admin. Keduanya
 * selalu diturunkan dari master struktur organisasi yang dikelola di User
 * Management (tabel `pegawai`) dan Ruangan (tabel `ruangan`):
 *
 *   - ada PEMEGANG  -> effective room = `pegawai.id_ruangan`
 *                      aset.id_skpd   = `pegawai.id_skpd`
 *   - tanpa PEMEGANG -> effective room = ruangan yang dipilih admin
 *                      aset.id_skpd   = `ruangan.id_skpd` (NULL bila ruang bersama)
 *   - tanpa keduanya -> effective room = NULL
 *                      aset.id_skpd   = NULL
 *
 * `aset.id_lokasi` SELALU = `ruangan.id_lokasi` dari effective room. Bila
 * effective room NULL atau ruangnya belum dipetakan ke lokasi, hasilnya NULL
 * — nilai lama tidak pernah dipertahankan.
 *
 * Perubahan role/SKPD pegawai tidak memindahkan aset; perubahan RUANGAN KERJA
 * pegawai memindahkan aset yang dipegangnya dan dicatat sebagai mutasi
 * 'Pindah Ruangan' (lihat `UserController::update`).
 */
class AsetPenempatan
{
    /**
     * Ruangan kerja seorang pegawai (sumber: User Management).
     */
    public static function ruanganKerjaPemegang(?int $pegawaiId): ?int
    {
        if (! $pegawaiId) {
            return null;
        }

        $id = Pegawai::where('id_pegawai', $pegawaiId)->value('id_ruangan');

        return $id ? (int) $id : null;
    }

    /**
     * Lokasi fisik untuk ruang tertentu.
     */
    public static function lokasiUntukRuangan(?int $ruanganId): ?int
    {
        if (! $ruanganId) {
            return null;
        }

        $id = Ruangan::where('id_ruangan', $ruanganId)->value('id_lokasi');

        return $id ? (int) $id : null;
    }

    /**
     * SKPD aset berdasarkan effective source (bukan input admin):
     *   - ada pemegang  -> `pegawai.id_skpd`
     *   - tanpa pemegang-> `ruangan.id_skpd` (NULL untuk ruang bersama)
     *   - tanpa keduanya-> NULL
     */
    public static function skpdUntuk(?int $pegawaiId, ?int $ruanganId): ?int
    {
        if ($pegawaiId) {
            $skpd = Pegawai::where('id_pegawai', $pegawaiId)->value('id_skpd');

            return $skpd ? (int) $skpd : null;
        }

        if ($ruanganId) {
            $skpd = Ruangan::where('id_ruangan', $ruanganId)->value('id_skpd');

            return $skpd ? (int) $skpd : null;
        }

        return null;
    }

    /**
     * Sinkronkan `aset.id_lokasi` dengan lokasi effective room.
     *
     * WAJIB dipanggil setiap kali effective room aset berubah (create/
     * edit/mutasi/pindah ruang), karena `id_lokasi` dipakai AsetScope untuk
     * menegakkan hak akses.
     *
     * Bila effective room NULL (atau ruangnya belum dipetakan ke lokasi),
     * lokasi aset menjadi NULL — nilai lama TIDAK dipertahankan.
     *
     * @return int jumlah aset yang ikut tersinkron
     */
    public static function sinkronLokasiAset(Aset $aset, ?int $ruanganId): int
    {
        $lokasi = self::lokasiUntukRuangan($ruanganId);
        $sekarang = $aset->id_lokasi;

        $sama = $lokasi === null && $sekarang === null
            || ($lokasi !== null && $sekarang !== null && (int) $sekarang === (int) $lokasi);

        if ($sama) {
            return 0;
        }

        $aset->forceFill(['id_lokasi' => $lokasi])->save();

        return 1;
    }

    /**
     * Sinkronkan `aset.id_skpd` dari effective source (Pemegang → Pegawai,
     * tanpa Pemegang → Ruangan, tanpa keduanya → NULL).
     *
     * @return int jumlah aset yang ikut tersinkron
     */
    public static function sinkronSkpdAset(Aset $aset, ?int $pegawaiId, ?int $ruanganId): int
    {
        $skpd = self::skpdUntuk($pegawaiId, $ruanganId);

        if ((int) $aset->id_skpd === (int) $skpd) {
            return 0;
        }

        $aset->forceFill(['id_skpd' => $skpd])->save();

        return 1;
    }

    /**
     * Sinkronkan KEDUA kolom turunan sekaligus (SKPD + Lokasi Fisik).
     *
     * Pemanggil hanya cukup memberikan pemegang & effective room; nilai
     * `id_skpd` dan `id_lokasi` dari request tidak pernah dipakai.
     */
    public static function sinkronAset(Aset $aset, ?int $pegawaiId, ?int $ruanganId): void
    {
        self::sinkronSkpdAset($aset, $pegawaiId, $ruanganId);
        self::sinkronLokasiAset($aset, $ruanganId);
    }

    /**
     * Jumlah aset yang saat ini dipegang pegawai tertentu (aktif).
     *
     * Dipakai User Management sebagai penjaga: aset milik pegawai hanya
     * berpindah ruangan ketika RUANGAN KERJA pegawai itu berubah (dicatat
     * sebagai mutasi 'Pindah Ruangan'), dan berganti pemegang hanya lewat
     * Mutasi → Ganti Pemegang.
     */
    public static function asetMilik(int $pegawaiId): int
    {
        return PemegangAset::where('id_pegawai', $pegawaiId)
            ->where('status', 'aktif')
            ->distinct()
            ->count('id_aset');
    }
}