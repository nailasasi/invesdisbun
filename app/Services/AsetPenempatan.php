<?php

namespace App\Services;

use App\Models\Aset;
use App\Models\PemegangAset;
use App\Models\Pegawai;
use App\Models\Ruangan;

/**
 * Aturan penempatan aset.
 *
 * Prinsip penting:
 *  - LOKASI FISIK aset mengikuti RUANGAN tempat aset berada, dan HANYA
 *    berubah melalui proses penempatan/mutasi aset.
 *  - UNIT PENANGGUNG JAWAB aset (skpd) ditunjuk eksplisit (form aset) —
 *    TIDAK ikut berubah hanya karena ruang atau pemegangnya berpindah.
 *  - Perubahan role / skpd / ruangan pegawai TIDAK PERNAH memindahkan
 *    aset. Perpindahan fisik selalu lewat mutasi.
 *
 * Jadi aset milik Bidang yang ditaruh di ruang UPT tetap milik Bidang,
 * dan user UPT tetap bisa mengelolanya karena akses berbasis lokasi
 * fisik (lihat AsetScope).
 */
class AsetPenempatan
{
    /**
     * Lokasi fisik untuk ruang tertentu.
     */
    public static function lokasiUntukRuangan(?int $ruanganId): ?int
    {
        if (! $ruanganId) {
            return null;
        }

        return Ruangan::where('id_ruangan', $ruanganId)->value('id_lokasi');
    }

    /**
     * Unit penanggung jawab default aset yang dipegang pegawai tertentu,
     * diturunkan dari skpd pegawai tersebut.
     */
    public static function skpdUntukPemegang(?int $pegawaiId): ?int
    {
        if (! $pegawaiId) {
            return null;
        }

        return Pegawai::where('id_pegawai', $pegawaiId)->value('id_skpd');
    }

    /**
     * Terapkan unit penanggung jawab + lokasi saat aset dibuat atau
     * ditempatkan ulang.
     *
     * @param  int|null  $skpdPJ  skpd penanggung jawab yang dipilih eksplisit di form
     */
    public static function terapkan(Aset $aset, ?int $pegawaiId, ?int $ruanganId, ?int $skpdPJ = null): void
    {
        $update = [];

        $skpd = $skpdPJ ?: self::skpdUntukPemegang($pegawaiId);

        if ($skpd) {
            $update['id_skpd'] = $skpd;
        }

        $lokasi = self::lokasiUntukRuangan($ruanganId);

        if ($lokasi) {
            $update['id_lokasi'] = $lokasi;
        }

        if ($update) {
            $aset->update($update);
        }
    }

    /**
     * Sinkronkan `aset.id_lokasi` dengan lokasi ruangan tujuan.
     *
     * WAJIB dipanggil setiap kali penempatan aset berubah (create/
     * mutasi/pindah ruang), karena `id_lokasi` dipakai AsetScope untuk
     * menegakkan hak akses. Lokasi fisik HANYA berubah lewat penempatan
     * aset — bukan karena role/unit/ruangan pegawai berubah, dan bukan
     * karena pemegangnya berganti.
     *
     * @return int jumlah aset yang ikut tersinkron
     */
    public static function sinkronLokasiAset(Aset $aset, ?int $ruanganId): int
    {
        $lokasi = self::lokasiUntukRuangan($ruanganId);

        if (! $lokasi || (int) $aset->id_lokasi === (int) $lokasi) {
            return 0;
        }

        $aset->forceFill(['id_lokasi' => $lokasi])->save();

        return 1;
    }

    /**
     * Peringatan eksplisit: perubahan data pegawai TIDAK boleh memindahkan
     * aset. Dipanggil dari User Management sebagai penjaga agar tidak ada
     * jalur tersembunyi yang menggeser lokasi fisik aset.
     *
     * Aset yang benar-benar harus berpindah dilakukan lewat proses
     * mutasi/penempatan, bukan lewat perubahan pegawai.
     */
    public static function asetMilik(int $pegawaiId): int
    {
        return PemegangAset::where('id_pegawai', $pegawaiId)
            ->where('status', 'aktif')
            ->distinct()
            ->count('id_aset');
    }
}