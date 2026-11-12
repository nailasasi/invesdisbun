<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Pengelola aturan periode Sensus Aset.
 *
 * Aturan bisnis:
 * - Sensus berlangsung setiap 3 tahun sekali.
 * - Tahun dasar (anchor): 2025.
 * - Siklus: 2025–2028, 2028–2031, 2031–2034, dst.
 * - Tahun 2026 berada dalam periode aktif 2025–2028.
 */
class PeriodeSensus
{
    public const TAHUN_DASAR = 2025;

    public const INTERVAL_TAHUN = 3;

    /**
     * Tentukan periode aktif berdasarkan tanggal acuan (default: saat ini).
     */
    public static function periodeAktif(?CarbonInterface $date = null): string
    {
        $date = $date ?? Carbon::now();
        $tahun = (int) $date->format('Y');

        $offset = $tahun - self::TAHUN_DASAR;
        $k = (int) floor($offset / self::INTERVAL_TAHUN);

        $mulai = self::TAHUN_DASAR + ($k * self::INTERVAL_TAHUN);
        $selesai = $mulai + self::INTERVAL_TAHUN;

        return self::format($mulai, $selesai);
    }

    /**
     * Format string standar periode menggunakan en-dash.
     */
    public static function format(int $mulai, int $selesai): string
    {
        return "{$mulai}–{$selesai}";
    }

    /**
     * Normalisasi string periode dari format variatif (mis. '2025-2028' atau '2025 – 2028')
     * menjadi format standar '2025–2028'.
     */
    public static function normalisasi(?string $periode): string
    {
        if ($periode === null) {
            return self::periodeAktif();
        }

        $clean = trim($periode);
        if (preg_match('/^(\d{4})\s*[\-–—]\s*(\d{4})$/u', $clean, $matches)) {
            return self::format((int) $matches[1], (int) $matches[2]);
        }

        return $clean;
    }

    /**
     * Validasi apakah sebuah periode sesuai dengan siklus 3 tahunan dari tahun dasar.
     */
    public static function valid(string $periode): bool
    {
        $clean = trim($periode);
        if (! preg_match('/^(\d{4})\s*[\-–—]\s*(\d{4})$/u', $clean, $matches)) {
            return false;
        }

        $mulai = (int) $matches[1];
        $selesai = (int) $matches[2];

        if ($selesai - $mulai !== self::INTERVAL_TAHUN) {
            return false;
        }

        if (($mulai - self::TAHUN_DASAR) % self::INTERVAL_TAHUN !== 0) {
            return false;
        }

        return true;
    }

    /**
     * Pastikan periode tidak berada di masa depan (tidak boleh diisi sebelum waktunya).
     */
    public static function bolehDiisi(string $periode, ?CarbonInterface $date = null): bool
    {
        if (! self::valid($periode)) {
            return false;
        }

        [$mulai] = self::urai($periode);
        [$aktifMulai] = self::urai(self::periodeAktif($date));

        // Periode masa depan (tahun mulai lebih besar dari periode aktif) belum boleh diisi
        return $mulai <= $aktifMulai;
    }

    /**
     * Daftar pilihan periode untuk dropdown filter dan form input.
     *
     * @param int $jumlahSebelumnya Jumlah siklus sebelum periode aktif
     * @param bool $sertakanMasaDepan Apakah menyertakan siklus masa depan
     * @return string[]
     */
    public static function daftarPeriode(int $jumlahSebelumnya = 1, bool $sertakanMasaDepan = false): array
    {
        $aktif = self::periodeAktif();
        [$aktifMulai] = self::urai($aktif);

        $daftar = [];

        for ($i = -$jumlahSebelumnya; $i <= 0; $i++) {
            $mulai = $aktifMulai + ($i * self::INTERVAL_TAHUN);
            $selesai = $mulai + self::INTERVAL_TAHUN;
            $daftar[] = self::format($mulai, $selesai);
        }

        if ($sertakanMasaDepan) {
            $mulaiMasaDepan = $aktifMulai + self::INTERVAL_TAHUN;
            $daftar[] = self::format($mulaiMasaDepan, $mulaiMasaDepan + self::INTERVAL_TAHUN);
        }

        return $daftar;
    }

    /**
     * Urai periode menjadi pasangan tahun [mulai, selesai].
     *
     * @return array{int, int}
     */
    public static function urai(string $periode): array
    {
        $clean = self::normalisasi($periode);
        if (preg_match('/^(\d{4})–(\d{4})$/u', $clean, $matches)) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        $tahun = (int) date('Y');

        return [$tahun, $tahun + self::INTERVAL_TAHUN];
    }
}

