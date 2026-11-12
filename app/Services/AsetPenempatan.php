<?php

namespace App\Services;

use App\Models\Aset;
use App\Models\PemegangAset;
use App\Models\Pegawai;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use Illuminate\Support\Facades\Schema;

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
 * 'Pindah Ruangan' (lihat `PegawaiController::update`).
 */
class AsetPenempatan
{
    /**
     * Cache ketersediaan kolom jejak finalisasi per request.
     */
    private static ?bool $kolomJejakFinalisasi = null;

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
     * Apakah tabel `penempatan_aset` sudah punya kolom finalisasi.
     *
     * Kolom `is_finalized` belum ada di database aktif sampai migration
     * `add_finalisasi_to_penempatan_aset_table` dijalankan. Semua pemeriksaan
     * yang bergantung pada kolom itu WAJIB melewati gerbang ini, supaya
     * query/SQL tidak gagal dengan "Unknown column".
     */
    public static function kolomFinalisasiTersedia(): bool
    {
        return Schema::hasColumn('penempatan_aset', 'is_finalized');
    }

    /**
     * Data finalisasi untuk penempatan aktif baru yang LANGSUNG difinalisasi.
     *
     * Aturan mutasi: setiap mutasi yang berhasil membuat penempatan aktif baru
     * yang langsung berstatus "Sudah Difinalisasi" — mutasi tidak memerlukan
     * finalisasi ulang. Jejak finalisasi (waktu + pelaku) dicatat saat itu
     * juga, bukan diwarisi dari penempatan lama.
     *
     * `$denganJejak = false` dipakai bila kolom `finalized_at`/`finalized_by`
     * belum tersedia di database (migration belum dijalankan).
     *
     * @return array{is_finalized:int, finalized_at?:mixed, finalized_by?:mixed}
     */
    public static function finalisasiLangsung(bool $denganJejak = true): array
    {
        $data = [
            'is_finalized' => 1,
        ];

        if ($denganJejak) {
            $data['finalized_at'] = now();
            $data['finalized_by'] = auth()->id();
        }

        return $data;
    }

    /**
     * Apakah kolom jejak finalisasi (`finalized_at` + `finalized_by`) tersedia.
     *
     * Dicek satu kali per request (di-cache) karena `finalisasiLangsung()`
     * bisa dipanggil berulang dalam satu loop perpindahan massal. Hasil
     * "tidak tersedia" tidak di-cache supaya pengecekan ulang tetap jujur
     * bila kondisi database berubah.
     */
    public static function kolomJejakFinalisasiTersedia(): bool
    {
        if (self::$kolomJejakFinalisasi === true) {
            return true;
        }

        self::$kolomJejakFinalisasi = Schema::hasColumns('penempatan_aset', ['finalized_at', 'finalized_by']);

        return self::$kolomJejakFinalisasi;
    }

    /**
     * Nilai finalisasi lama yang bisa DIWARISKAN ke penempatan aktif baru.
     *
     * Helper umum untuk alur yang memang memakai aset yang sama di ruangan
     * yang sama. Alur mutasi (Pindah Ruangan, Ganti Pemegang) TIDAK memakai
     * helper ini: penempatan baru hasil mutasi langsung difinalisasi lewat
     * `finalisasiLangsung()`.
     *
     * `Aset::placeAtRoom()` membuat penempatan aktif baru ber-status 0
     * (Belum Difinalisasi) secara default; nilai lama diwariskan supaya aset
     * yang sudah "Sudah Difinalisasi" tidak hilang dari SPPBI pemegang baru.
     * Penempatan lama NULL (Perlu Verifikasi) menghasilkan array kosong
     * (tidak mewariskan apa pun).
     *
     * @return array{is_finalized:int, finalized_at:mixed, finalized_by:mixed}|array{}
     */
    public static function finalisasiWarisan(?PenempatanAset $penempatanLama): array
    {
        if (! $penempatanLama || is_null($penempatanLama->is_finalized)) {
            return [];
        }

        return [
            'is_finalized' => (int) $penempatanLama->is_finalized,
            'finalized_at' => $penempatanLama->finalized_at,
            'finalized_by' => $penempatanLama->finalized_by,
        ];
    }

    /**
     * Penempatan aktif yang sudah final (Sudah Difinalisasi) dan valid.
     *
     * Syarat:
     *   1. record penempatan aktif ada,
     *   2. kolom finalisasi sudah tersedia (migration dijalankan),
     *   3. `is_finalized` bernilai tepat 1 (0 = Belum Difinalisasi,
     *      NULL = Perlu Verifikasi - keduanya BELUM memenuhi syarat),
     *   4. ruangannya ada dan berstatus Aktif.
     *
     * Pemegang TIDAK menjadi syarat: penempatan lewat ruangan boleh tanpa
     * pemegang.
     *
     * @param  bool|null  $kolomFinalisasiTersedia  hasil cache pemeriksaan kolom
     */
    public static function penempatanFinalValid(?PenempatanAset $penempatan, ?bool $kolomFinalisasiTersedia = null): bool
    {
        if (! $penempatan) {
            return false;
        }

        if ($kolomFinalisasiTersedia === null) {
            $kolomFinalisasiTersedia = self::kolomFinalisasiTersedia();
        }

        if (! $kolomFinalisasiTersedia) {
            return false;
        }

        if ((int) ($penempatan->is_finalized ?? 0) !== 1) {
            return false;
        }

        $ruangan = $penempatan->relationLoaded('ruangan')
            ? $penempatan->ruangan
            : $penempatan->ruangan()->first();

        return $ruangan !== null && $ruangan->status === 'Aktif';
    }

    /**
     * Apakah aset boleh dimutasi.
     *
     * Mutasi hanya bermakna bila aset sudah "berada" di somewhere: punya
     * pemegang aktif ATAU penempatan aktif. Aset yang belum punya keduanya
     * belum pernah ditempatkan, jadi harus lewat Penempatan Awal (tombol +)
     * — bukan Mutasi. Aturan ini jadi satu-satunya sumber kebenaran: dipakai
     * backend (penolakan request mutasi) DAN frontend (ikon Mutasi).
     */
    public static function bolehMutasi(?bool $adaPemegangAktif, ?bool $adaPenempatanAktif): bool
    {
        return (bool) $adaPemegangAktif || (bool) $adaPenempatanAktif;
    }

    /**
     * Syarat mutasi dari sisi status finalisasi penempatan.
     *
     * Mutasi hanya sah bila penempatan aktif SUDAH difinalisasi:
     *   - tidak ada penempatan aktif -> BOLEH. Aset belum pernah
     *     ditempatkan ke ruangan; tidak ada penempatan yang perlu
     *     difinalisasi. Ini tetap berlaku untuk aset yang hanya punya
     *     pemegang aktif (mis. pegawai belum punya ruang kerja).
     *   - ada penempatan aktif       -> WAJIB `is_finalized = 1`
     *     (Sudah Difinalisasi). Status 0 (Belum Difinalisasi) dan NULL
     *     (Perlu Verifikasi) sama-sama MENOLAK.
     *
     * Kolom finalisasi yang belum tersedia (migration belum dijalankan)
     * ditolak — fail closed, supaya tidak ada mutasi yang lolos tanpa
     * jejak status finalisasi.
     *
     * Sumber kebenaran tunggal: dipakai backend (penolakan request mutasi)
     * DAN frontend (ikon Mutasi) supaya keduanya tidak berbeda.
     */
    public static function bolehMutasiFinalisasi(?PenempatanAset $penempatan, ?bool $kolomFinalisasiTersedia = null): bool
    {
        if (! $penempatan) {
            return true;
        }

        if ($kolomFinalisasiTersedia === null) {
            $kolomFinalisasiTersedia = self::kolomFinalisasiTersedia();
        }

        if (! $kolomFinalisasiTersedia) {
            return false;
        }

        return (int) ($penempatan->is_finalized ?? 0) === 1;
    }

    /**
     * Pesan penolakan mutasi akibat penempatan belum difinalisasi.
     */
    public static function pesanMutasiBelumDifinalisasi(): string
    {
        return 'Penempatan aset belum difinalisasi. Finalisasi penempatan terlebih dahulu di menu Aset Barang sebelum melakukan mutasi.';
    }

    /**
     * Apakah penempatan aktif boleh DIEDIT dan/atau difinalisasi.
     *
     * Hanya `is_finalized = 0` (Belum Difinalisasi) yang boleh:
     *   - 0  -> penempatan baru, masih boleh diperbaiki lalu difinalisasi,
     *   - 1  -> sudah final, tidak boleh diedit/difinalisasi ulang,
     *   - NULL-> data lama (Perlu Verifikasi): TIDAK diperlakukan sebagai 0
     *           maupun 1, sehingga tidak menampilkan tombol seolah-olah
     *           statusnya sudah jelas. Nilainya tidak diubah otomatis.
     */
    public static function bolehEditFinalisasi(?PenempatanAset $penempatan, ?bool $kolomFinalisasiTersedia = null): bool
    {
        if (! $penempatan) {
            return false;
        }

        if ($kolomFinalisasiTersedia === null) {
            $kolomFinalisasiTersedia = self::kolomFinalisasiTersedia();
        }

        if (! $kolomFinalisasiTersedia) {
            return false;
        }

        $status = $penempatan->is_finalized;

        if ($status === null) {
            return false;
        }

        return (int) $status === 0;
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
     * Kunci pengurutan KIB dari `aset.nomor_kartu_barang`.
     *
     * Format KIB aplikasi: `11.01.35.00.020301.00000.00000.2025-1.3.2.10.01.02.001.000006`
     * dengan pemisah LOGIKA:
     *   - kode unit + tahun (sebelum `-`),
     *   - kode barang `1.3.2.10.01.02.001` (setelah `-`),
     *   - nomor urut register `000006` (segmen titik terakhir).
     *
     * Untuk mengurutkan "KIB terendah → tertinggi" dengan benar, semua segmen
     * numerik dibanding sebagai ANGKA (bukan string), agar `...000010` tidak
     * berada sebelum `...0002`.
     *
     * @return array<int, int|string>  tuple numerik untuk perbandingan `<=>`; kosong bila bukan KIB
     */
    public static function kunciKib(?string $nomor): array
    {
        if ($nomor === null || trim((string) $nomor) === '') {
            return [];
        }

        preg_match_all('/(\d+)/', (string) $nomor, $m);

        if (! isset($m[1]) || $m[1] === []) {
            return [];
        }

        return array_map('intval', $m[1]);
    }
}