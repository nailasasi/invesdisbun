<?php

namespace Tests\Unit;

use App\Services\PeriodeSensus;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class PeriodeSensusTest extends TestCase
{
    public function test_periode_aktif_tahun_2026_adalah_2025_2028(): void
    {
        $date = Carbon::parse('2026-10-28');
        $this->assertSame('2025–2028', PeriodeSensus::periodeAktif($date));
    }

    public function test_periode_aktif_pada_batas_tahun_siklus(): void
    {
        // Tahun 2025: awal siklus 2025–2028
        $this->assertSame('2025–2028', PeriodeSensus::periodeAktif(Carbon::parse('2025-01-01')));
        $this->assertSame('2025–2028', PeriodeSensus::periodeAktif(Carbon::parse('2027-12-31')));

        // Tahun 2028: awal siklus 2028–2031
        $this->assertSame('2028–2031', PeriodeSensus::periodeAktif(Carbon::parse('2028-01-01')));
        $this->assertSame('2028–2031', PeriodeSensus::periodeAktif(Carbon::parse('2029-06-15')));
        $this->assertSame('2028–2031', PeriodeSensus::periodeAktif(Carbon::parse('2030-12-31')));

        // Tahun 2031: awal siklus 2031–2034
        $this->assertSame('2031–2034', PeriodeSensus::periodeAktif(Carbon::parse('2031-01-01')));
    }

    public function test_normalisasi_periode_menghasilkan_format_standar(): void
    {
        $this->assertSame('2025–2028', PeriodeSensus::normalisasi('2025-2028'));
        $this->assertSame('2025–2028', PeriodeSensus::normalisasi('2025–2028'));
        $this->assertSame('2025–2028', PeriodeSensus::normalisasi(' 2025 - 2028 '));
        $this->assertSame('2028–2031', PeriodeSensus::normalisasi('2028-2031'));
    }

    public function test_validasi_periode(): void
    {
        $this->assertTrue(PeriodeSensus::valid('2025–2028'));
        $this->assertTrue(PeriodeSensus::valid('2025-2028'));
        $this->assertTrue(PeriodeSensus::valid('2028–2031'));
        $this->assertTrue(PeriodeSensus::valid('2031–2034'));
        $this->assertTrue(PeriodeSensus::valid('2022–2025'));

        // Tidak valid: selisih bukan 3 tahun
        $this->assertFalse(PeriodeSensus::valid('2025–2029'));
        $this->assertFalse(PeriodeSensus::valid('2025–2026'));

        // Tidak valid: bukan kelipatan 3 dari tahun dasar 2025
        $this->assertFalse(PeriodeSensus::valid('2026–2029'));
        $this->assertFalse(PeriodeSensus::valid('2024–2027'));

        // Tidak valid: format salah
        $this->assertFalse(PeriodeSensus::valid('2025'));
        $this->assertFalse(PeriodeSensus::valid('invalid-string'));
    }

    public function test_daftar_periode_menyertakan_periode_aktif(): void
    {
        $daftar = PeriodeSensus::daftarPeriode(1, false);
        $this->assertContains('2025–2028', $daftar);
        $this->assertContains('2022–2025', $daftar);
    }

    public function test_periode_masa_depan_tidak_boleh_diisi_sebelum_waktunya(): void
    {
        $date = Carbon::parse('2026-10-28');

        // Periode aktif boleh diisi
        $this->assertTrue(PeriodeSensus::bolehDiisi('2025–2028', $date));

        // Periode masa lalu boleh diisi / dicatat historisnya
        $this->assertTrue(PeriodeSensus::bolehDiisi('2022–2025', $date));

        // Periode masa depan DITOLAK (belum boleh diisi sebelum waktunya)
        $this->assertFalse(PeriodeSensus::bolehDiisi('2028–2031', $date));
        $this->assertFalse(PeriodeSensus::bolehDiisi('2031–2034', $date));
    }
}

