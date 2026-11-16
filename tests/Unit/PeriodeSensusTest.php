<?php

namespace Tests\Unit;

use App\Models\Aset;
use App\Services\PeriodeSensus;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PeriodeSensusTest extends TestCase
{
    public function test_periode_aktif_tahun_2026_adalah_2025_2027(): void
    {
        $date = Carbon::parse('2026-10-28');
        $this->assertSame('2025–2027', PeriodeSensus::periodeAktif($date));
    }

    public function test_periode_aktif_pada_batas_tahun_siklus_tiga_tahunan_inklusif(): void
    {
        // Tahun 2025 sampai 2027: siklus 2025–2027 (tepat 3 tahun kalender: 2025, 2026, 2027)
        $this->assertSame('2025–2027', PeriodeSensus::periodeAktif(Carbon::parse('2025-01-01')));
        $this->assertSame('2025–2027', PeriodeSensus::periodeAktif(Carbon::parse('2026-06-15')));
        $this->assertSame('2025–2027', PeriodeSensus::periodeAktif(Carbon::parse('2027-12-31')));

        // Tahun 2028 sampai 2030: siklus berikutnya 2028–2030
        $this->assertSame('2028–2030', PeriodeSensus::periodeAktif(Carbon::parse('2028-01-01')));
        $this->assertSame('2028–2030', PeriodeSensus::periodeAktif(Carbon::parse('2029-06-15')));
        $this->assertSame('2028–2030', PeriodeSensus::periodeAktif(Carbon::parse('2030-12-31')));

        // Tahun 2031: siklus 2031–2033
        $this->assertSame('2031–2033', PeriodeSensus::periodeAktif(Carbon::parse('2031-01-01')));
    }

    public function test_perhitungan_periode_berdasarkan_tahun_pengadaan_aset_berbeda(): void
    {
        $refDate = Carbon::parse('2026-10-28');

        // 1. Aset pengadaan 2025 -> siklus 2025–2027
        $aset2025 = new Aset(['tanggal_pengadaan' => '2025-04-12']);
        $this->assertSame('2025–2027', PeriodeSensus::periodeAktif($refDate, $aset2025));

        // 2. Aset pengadaan 2023 -> siklus 1: 2023–2025, siklus 2: 2026–2028 (aktif pada 2026)
        $aset2023 = new Aset(['tanggal_pengadaan' => '2023-08-20']);
        $this->assertSame('2026–2028', PeriodeSensus::periodeAktif($refDate, $aset2023));

        // 3. Aset pengadaan 2019 -> siklus: 2019–2021, 2022–2024, 2025–2027 (aktif pada 2026)
        $aset2019 = new Aset(['tanggal_pengadaan' => '2019-01-15']);
        $this->assertSame('2025–2027', PeriodeSensus::periodeAktif($refDate, $aset2019));

        // 4. Aset pengadaan 2020 -> siklus: 2020–2022, 2023–2025, 2026–2028 (aktif pada 2026)
        $aset2020 = new Aset(['tanggal_pengadaan' => '2020-05-10']);
        $this->assertSame('2026–2028', PeriodeSensus::periodeAktif($refDate, $aset2020));

        // 5. Aset tanpa tanggal pengadaan (null) -> aman menggunakan fallback tahun dasar dinas 2025
        $asetNull = new Aset(['tanggal_pengadaan' => null]);
        $this->assertSame('2025–2027', PeriodeSensus::periodeAktif($refDate, $asetNull));
    }

    public function test_daftar_periode_untuk_aset_tidak_memuat_periode_masa_depan(): void
    {
        $refDate = Carbon::parse('2026-10-28');

        // Aset 2023: harus menghasilkan 2023–2025 dan 2026–2028 (tidak memuat masa depan 2029–2031)
        $aset2023 = new Aset(['tanggal_pengadaan' => '2023-08-20']);
        $daftar2023 = PeriodeSensus::daftarPeriodeUntukAset($aset2023, $refDate);

        $this->assertContains('2023–2025', $daftar2023);
        $this->assertContains('2026–2028', $daftar2023);
        $this->assertNotContains('2029–2031', $daftar2023);

        // Aset 2025: harus menghasilkan 2025–2027 saja (tidak memuat masa depan 2028–2030)
        $aset2025 = new Aset(['tanggal_pengadaan' => '2025-01-01']);
        $daftar2025 = PeriodeSensus::daftarPeriodeUntukAset($aset2025, $refDate);

        $this->assertContains('2025–2027', $daftar2025);
        $this->assertNotContains('2028–2030', $daftar2025);
    }

    public function test_normalisasi_periode_menghasilkan_format_standar(): void
    {
        $this->assertSame('2025–2027', PeriodeSensus::normalisasi('2025-2027'));
        $this->assertSame('2025–2027', PeriodeSensus::normalisasi('2025–2027'));
        $this->assertSame('2025–2027', PeriodeSensus::normalisasi(' 2025 - 2027 '));
        $this->assertSame('2028–2030', PeriodeSensus::normalisasi('2028-2030'));
    }

    public function test_validasi_periode(): void
    {
        // Standar baru siklus 3 tahun kalender inklusif (selisih 2)
        $this->assertTrue(PeriodeSensus::valid('2025–2027'));
        $this->assertTrue(PeriodeSensus::valid('2025-2027'));
        $this->assertTrue(PeriodeSensus::valid('2028–2030'));
        $this->assertTrue(PeriodeSensus::valid('2031–2033'));
        $this->assertTrue(PeriodeSensus::valid('2022–2024'));

        // Kompatibilitas data legacy (selisih 3) tetap diakui valid
        $this->assertTrue(PeriodeSensus::valid('2025–2028'));
        $this->assertTrue(PeriodeSensus::valid('2022–2025'));

        // Tidak valid: selisih bukan 3 tahun kalender (bukan 2 dan bukan 3)
        $this->assertFalse(PeriodeSensus::valid('2025–2029'));
        $this->assertFalse(PeriodeSensus::valid('2025–2026'));
        $this->assertFalse(PeriodeSensus::valid('2025'));
        $this->assertFalse(PeriodeSensus::valid('invalid-string'));
    }

    public function test_aturan_penguncian_periode_sensus_aktif_lampau_dan_masa_depan(): void
    {
        $date2026 = Carbon::parse('2026-10-28');

        // Pada tahun 2026:
        // Periode berjalan (2025–2027): sedang berlangsung dan boleh diisi
        $this->assertTrue(PeriodeSensus::periodeSedangBerlangsung('2025–2027', $date2026));
        $this->assertTrue(PeriodeSensus::periodeSedangBerlangsung('2025–2028', $date2026)); // kompatibilitas format legacy
        $this->assertFalse(PeriodeSensus::periodeSudahBerakhir('2025–2027', $date2026));
        $this->assertFalse(PeriodeSensus::periodeBelumDimulai('2025–2027', $date2026));
        $this->assertTrue(PeriodeSensus::bolehDiisi('2025–2027', $date2026));

        // Periode lampau (2022–2024): telah berakhir, terkunci, dan TIDAK boleh diisi lagi
        $this->assertFalse(PeriodeSensus::periodeSedangBerlangsung('2022–2024', $date2026));
        $this->assertTrue(PeriodeSensus::periodeSudahBerakhir('2022–2024', $date2026));
        $this->assertTrue(PeriodeSensus::periodeSudahBerakhir('2022–2025', $date2026));
        $this->assertFalse(PeriodeSensus::periodeBelumDimulai('2022–2024', $date2026));
        $this->assertFalse(PeriodeSensus::bolehDiisi('2022–2024', $date2026));

        // Periode masa depan (2028–2030, 2031–2033): belum dimulai dan TIDAK boleh diisi
        $this->assertFalse(PeriodeSensus::periodeSedangBerlangsung('2028–2030', $date2026));
        $this->assertFalse(PeriodeSensus::periodeSudahBerakhir('2028–2030', $date2026));
        $this->assertTrue(PeriodeSensus::periodeBelumDimulai('2028–2030', $date2026));
        $this->assertTrue(PeriodeSensus::periodeBelumDimulai('2031–2033', $date2026));
        $this->assertFalse(PeriodeSensus::bolehDiisi('2028–2030', $date2026));
    }

    public function test_penguncian_periode_berjalan_secara_dinamis_antar_tahun(): void
    {
        // 1. Tahun 2028: Periode 2025–2027 otomatis terkunci, 2028–2030 aktif
        $date2028 = Carbon::parse('2028-01-15');
        $this->assertTrue(PeriodeSensus::periodeSudahBerakhir('2025–2027', $date2028));
        $this->assertFalse(PeriodeSensus::bolehDiisi('2025–2027', $date2028));

        $this->assertTrue(PeriodeSensus::periodeSedangBerlangsung('2028–2030', $date2028));
        $this->assertTrue(PeriodeSensus::bolehDiisi('2028–2030', $date2028));

        $this->assertTrue(PeriodeSensus::periodeBelumDimulai('2031–2033', $date2028));
        $this->assertFalse(PeriodeSensus::bolehDiisi('2031–2033', $date2028));

        // 2. Tahun 2031: Periode 2028–2030 otomatis terkunci, 2031–2033 aktif
        $date2031 = Carbon::parse('2031-06-01');
        $this->assertTrue(PeriodeSensus::periodeSudahBerakhir('2028–2030', $date2031));
        $this->assertFalse(PeriodeSensus::bolehDiisi('2028–2030', $date2031));

        $this->assertTrue(PeriodeSensus::periodeSedangBerlangsung('2031–2033', $date2031));
        $this->assertTrue(PeriodeSensus::bolehDiisi('2031–2033', $date2031));
    }
}
