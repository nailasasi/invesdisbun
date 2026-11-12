<?php

namespace App\Support\Word;

/**
 * Kontrak placeholder template SPPBI yang aktif di website.
 *
 * Template SPPBI memakai placeholder BERSPASI (bukan gaya underscore seperti
 * template BAST/KIR/SPPKD), serta memuat satu blok "Spesfikasi Teknis" per
 * barang di dalam paragraf biasa (bukan baris tabel).
 *
 * Kontrak ini dipisah dari controller agar:
 *   - pemetaan placeholder -> sumber data terdokumentasi di satu tempat,
 *   - dapat diuji (semua placeholder template aktif wajib dikenali).
 */
class SppbiTemplate
{
    /**
     * Penanda blok spesifikasi barang (paragraf pertama & terakhir blok).
     *
     * `BLOCK_END` sengaja memakai penutup kurung "(... rupiah)" karena
     * terbilangnya harga satuan boleh terbelah ke paragraf berikutnya.
     */
    public const BLOCK_START = 'Spesfikasi Teknis';

    public const BLOCK_END = 'rupiah)';

    /**
     * Placeholder blok spesifikasi barang => key data pada baris.
     *
     * Sumber data (lihat App\Http\Controllers\DokumenSppbiController):
     *   jenis           -> master_barang.nama_barang
     *   merk            -> aset.merk
     *   type            -> master_barang.kode_barang (kosong di master saat ini)
     *   tanggal/bulan/tahun -> aset.tanggal_pengadaan
     *   kode            -> aset.nomor_kartu_barang
     *   tahun_pengadaan -> tahun aset.tanggal_pengadaan
     *   jumlah          -> 1 (satu blok per aset)
     *   register        -> aset.nomor_kartu_barang
     *   harga           -> aset.nilai_perolehan (angka tanpa pemisah ribuan)
     */
    public const BLOCK_TOKENS = [
        'Jenis Barang' => 'jenis',
        'Merk Barang' => 'merk',
        'Type Barang' => 'type',
        'Tanggal' => 'tanggal',
        'Bulan' => 'bulan',
        'Tahun' => 'tahun',
        'Kode Barang' => 'kode',
        'Tahun Pengadaan' => 'tahun_pengadaan',
        'Jumlah' => 'jumlah',
        'Nomor Register' => 'register',
        'Harga Satuan' => 'harga',
    ];

    /**
     * Token nominal yang juga diisi terbilangnya dalam kurung.
     */
    public const CURRENCY_TOKEN = 'Harga Satuan';

    /**
     * Placeholder identitas ASN & tanggal surat (di luar blok barang).
     *
     * Dipakai pada halaman 1 (identitas ASN) dan halaman Pakta Integritas
     * (identitas ASN + tanggal + tanda tangan). Varian kapital
     * `{NAMA ASN}` dan `{Nama ASN}` keduanya ada di template.
     */
    public const VALUE_TOKENS = [
        '{Nama ASN}' => 'nama_asn',
        '{NAMA ASN}' => 'nama_asn',
        '{NIP ASN}' => 'nip_asn',
        '{Jabatan ASN}' => 'jabatan_asn',
        '{Tanggal, Bulan Tahun}' => 'tanggal_surat',
    ];

    /**
     * Seluruh token yang wajib dikenali generator SPPBI.
     *
     * @return string[]
     */
    public static function semuaToken(): array
    {
        $tokens = array_map(
            fn (string $token) => '{'.$token.'}',
            array_keys(self::BLOCK_TOKENS)
        );

        return array_merge($tokens, array_keys(self::VALUE_TOKENS));
    }
}
