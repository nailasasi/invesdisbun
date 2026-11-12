<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use App\Services\MasaPakaiAset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tahap B - konsistensi masa pakai, nilai buku, KPI, filter, dan badge.
 *
 * Memakai koneksi MySQL nyata dibungkus transaksi yang di-rollback.
 * Tanggal hari ini dikunci agar batas 1/30/31 hari dan ulang tahun aset
 * dapat diuji tanpa bergantung pada tanggal server.
 */
class MonitoringAsetMasaPakaiTest extends TestCase
{
    private User $admin;

    private Lokasi $lokasi;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00'));

        $this->siapkanLingkunganUjiTerisolasi();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::rollBack();
        parent::tearDown();
    }

    private function siapkanLingkunganUjiTerisolasi(): void
    {
        $skpd = Skpd::create([
            'nama_skpd' => 'UPT Uji '.uniqid(),
            'jenis_skpd' => 'UPT',
        ]);

        $this->lokasi = Lokasi::create([
            'id_skpd' => $skpd->id_skpd,
            'nama_lokasi' => 'Lokasi UPT Uji '.uniqid(),
            'jenis_lokasi' => 'UPT',
            'status' => 'Aktif',
        ]);

        $pegawai = Pegawai::create([
            'nip' => 'TST'.uniqid(),
            'nama_pegawai' => 'Admin UPT Test',
            'id_skpd' => $skpd->id_skpd,
        ]);

        $this->admin = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => Role::where('nama_role', 'Admin UPT')->firstOrFail()->id_role,
            'username' => 'tst_upt_'.uniqid(),
            'password' => 'rahasia',
            'status_user' => 'aktif',
        ]);
    }

    private function namaDatabase(): string
    {
        foreach (file(base_path('.env')) as $line) {
            if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                $nilai = trim(explode('=', $line, 2)[1]);

                return $nilai === '' ? 'invesdisbun' : $nilai;
            }
        }

        return 'invesdisbun';
    }

    private function kartuUnik(string $prefix): string
    {
        return $prefix.'-'.uniqid();
    }

    private function buatAset(array $atribut): Aset
    {
        return Aset::create(array_merge([
            'id_lokasi' => $this->lokasi->id_lokasi,
            'nomor_kartu_barang' => $this->kartuUnik('TST-MP'),
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ], $atribut));
    }

    private function index(array $query = [])
    {
        return $this->actingAs($this->admin)
            ->get(route('monitoring-aset.index', $query));
    }

    private function angkaKpi(string $html, string $label): int
    {
        $pola = '~'.preg_quote($label, '~').'</p>\s*<h3[^>]*>\s*(\d+)~s';

        if (preg_match($pola, $html, $cocok) !== 1) {
            return -1;
        }

        return (int) $cocok[1];
    }

    private function nilaiKpi(string $html, string $label): int
    {
        $pola = '~'.preg_quote($label, '~').'</p>\s*<h3[^>]*>\s*Rp\s*([\d.]+)~s';

        if (preg_match($pola, $html, $cocok) !== 1) {
            return -1;
        }

        return (int) str_replace('.', '', $cocok[1]);
    }

    public function test_filter_tahun_hanya_memakai_tanggal_pengadaan(): void
    {
        $kartu2025 = $this->kartuUnik('TAHUN-2025');
        $kartu2026 = $this->kartuUnik('TAHUN-2026');

        $this->buatAset([
            'nomor_kartu_barang' => $kartu2025,
            'tanggal_pengadaan' => '2025-06-01',
            'tanggal_habis_pakai' => '2030-06-01',
            'nilai_perolehan' => 1000000,
        ]);
        $this->buatAset([
            'nomor_kartu_barang' => $kartu2026,
            'tanggal_pengadaan' => '2026-06-01',
            'tanggal_habis_pakai' => '2031-06-01',
            'nilai_perolehan' => 2000000,
        ]);

        $semua = $this->index()->assertOk();
        $semua->assertSee($kartu2025)
            ->assertSee($kartu2026)
            ->assertSee('value="2025"', false)
            ->assertSee('value="2026"', false);

        $tahun2025 = $this->index(['tahun' => 2025])->assertOk();
        $tahun2025->assertSee($kartu2025)
            ->assertDontSee($kartu2026)
            ->assertSee('value="2025"', false)
            ->assertSee('value="2026"', false);

        $tahun2026 = $this->index(['tahun' => 2026])->assertOk();
        $tahun2026->assertSee($kartu2026)
            ->assertDontSee($kartu2025)
            ->assertSee('value="2025"', false)
            ->assertSee('value="2026"', false);
    }

    public function test_nilai_buku_memakai_tanggal_hari_ini_bukan_tahun_filter(): void
    {
        $kartu = $this->kartuUnik('BUKU-FILTER');

        $this->buatAset([
            'nomor_kartu_barang' => $kartu,
            'tanggal_pengadaan' => '2026-11-10',
            'tanggal_habis_pakai' => '2031-11-10',
            'nilai_perolehan' => 5000000,
        ]);

        Carbon::setTestNow(Carbon::parse('2029-11-10 12:00:00'));

        $response = $this->index(['tahun' => 2026])->assertOk();

        $response->assertSee($kartu);
        $this->assertSame(2000000, $this->nilaiKpi($response->getContent(), 'Total Nilai Buku Aset'));
    }

    public function test_nilai_buku_berubah_tepat_pada_ulang_tahun_dan_tidak_negatif(): void
    {
        $kartu = $this->kartuUnik('ULANG');
        $this->buatAset([
            'nomor_kartu_barang' => $kartu,
            'tanggal_pengadaan' => '2024-11-10',
            'tanggal_habis_pakai' => '2029-11-10',
            'nilai_perolehan' => 5000000,
        ]);

        $harapan = [
            '2027-11-09' => 3000000,
            '2027-11-10' => 2000000,
            '2028-11-10' => 1000000,
            '2029-11-10' => 0,
            '2029-11-11' => 0,
        ];

        foreach ($harapan as $tanggal => $nilai) {
            Carbon::setTestNow(Carbon::parse($tanggal.' 12:00:00'));

            $response = $this->index()->assertOk();

            $this->assertSame(
                $nilai,
                $this->nilaiKpi($response->getContent(), 'Total Nilai Buku Aset'),
                "Nilai buku pada {$tanggal} tidak sesuai."
            );

            if ($nilai === 0) {
                $response->assertSee('>Habis</span>', false);
            }
        }

        Carbon::setTestNow(Carbon::parse('2027-11-09 23:59:59'));
        $this->assertSame(
            3000000,
            $this->nilaiKpi($this->index()->assertOk()->getContent(), 'Total Nilai Buku Aset'),
            'Nilai buku berubah sebelum pergantian hari pada tanggal ulang tahun.'
        );

        Carbon::setTestNow(Carbon::parse('2027-11-10 00:00:01'));
        $this->assertSame(
            2000000,
            $this->nilaiKpi($this->index()->assertOk()->getContent(), 'Total Nilai Buku Aset'),
            'Nilai buku tidak berubah tepat setelah pergantian hari pada tanggal ulang tahun.'
        );
    }

    public function test_ulang_tahun_akhir_bulan_tetap_pada_tanggal_yang_sama(): void
    {
        $kartu = $this->kartuUnik('AKHIR-BULAN');
        $this->buatAset([
            'nomor_kartu_barang' => $kartu,
            'tanggal_pengadaan' => '2026-01-31',
            'tanggal_habis_pakai' => '2029-01-31',
            'nilai_perolehan' => 12000,
        ]);

        Carbon::setTestNow(Carbon::parse('2027-01-30 12:00:00'));
        $this->assertSame(
            12000,
            $this->nilaiKpi($this->index()->assertOk()->getContent(), 'Total Nilai Buku Aset')
        );

        Carbon::setTestNow(Carbon::parse('2027-01-31 12:00:00'));
        $this->assertSame(
            8000,
            $this->nilaiKpi($this->index()->assertOk()->getContent(), 'Total Nilai Buku Aset')
        );
    }

    public function test_ulang_tahun_akhir_bulan_februari_tetap_pada_tanggal_yang_sama(): void
    {
        $kartu = $this->kartuUnik('AKHIR-FEB');
        $this->buatAset([
            'nomor_kartu_barang' => $kartu,
            'tanggal_pengadaan' => '2026-02-28',
            'tanggal_habis_pakai' => '2029-02-28',
            'nilai_perolehan' => 12000,
        ]);

        Carbon::setTestNow(Carbon::parse('2027-02-27 12:00:00'));
        $this->assertSame(
            12000,
            $this->nilaiKpi($this->index()->assertOk()->getContent(), 'Total Nilai Buku Aset')
        );

        Carbon::setTestNow(Carbon::parse('2027-02-28 12:00:00'));
        $this->assertSame(
            8000,
            $this->nilaiKpi($this->index()->assertOk()->getContent(), 'Total Nilai Buku Aset')
        );
    }

    public function test_ulang_tahun_29_februari_memakai_1_maret_pada_tahun_non_kabisat(): void
    {
        $kartu = $this->kartuUnik('LEAP');
        $this->buatAset([
            'nomor_kartu_barang' => $kartu,
            'tanggal_pengadaan' => '2024-02-29',
            'tanggal_habis_pakai' => '2027-02-28',
            'nilai_perolehan' => 1200,
        ]);

        foreach (['2025-02-28' => 1200, '2025-03-01' => 800] as $tanggal => $nilai) {
            Carbon::setTestNow(Carbon::parse($tanggal.' 12:00:00'));

            $this->assertSame(
                $nilai,
                $this->nilaiKpi($this->index()->assertOk()->getContent(), 'Total Nilai Buku Aset'),
                "Nilai buku pada {$tanggal} tidak sesuai."
            );
        }
    }

    public function test_kpi_filter_dan_badge_memakai_klasifikasi_yang_sama(): void
    {
        $kartuHabis0 = $this->kartuUnik('HABIS-0');
        $kartuHabisLewat = $this->kartuUnik('HABIS-LEWAT');
        $kartuNilaiNol = $this->kartuUnik('HABIS-NILAI-0');
        $kartuKritis1 = $this->kartuUnik('KRITIS-1');
        $kartuKritis30 = $this->kartuUnik('KRITIS-30');
        $kartuNormal31 = $this->kartuUnik('NORMAL-31');

        $dasar = [
            'tanggal_pengadaan' => '2026-01-01',
            
            'nilai_perolehan' => 1000000,
        ];

        $this->buatAset($dasar + [
            'nomor_kartu_barang' => $kartuHabis0,
            'tanggal_habis_pakai' => '2026-11-10',
        ]);
        $this->buatAset($dasar + [
            'nomor_kartu_barang' => $kartuHabisLewat,
            'tanggal_habis_pakai' => '2026-11-09',
        ]);
        $this->buatAset([
            'tanggal_pengadaan' => '2026-01-01',
            
            'nilai_perolehan' => 0,
            'nomor_kartu_barang' => $kartuNilaiNol,
            'tanggal_habis_pakai' => '2030-01-01',
        ]);
        $this->buatAset($dasar + [
            'nomor_kartu_barang' => $kartuKritis1,
            'tanggal_habis_pakai' => '2026-11-11',
            'status_aset' => null,
        ]);
        $this->buatAset($dasar + [
            'nomor_kartu_barang' => $kartuKritis30,
            'tanggal_habis_pakai' => '2026-12-10',
        ]);
        $this->buatAset($dasar + [
            'nomor_kartu_barang' => $kartuNormal31,
            'tanggal_habis_pakai' => '2026-12-11',
        ]);

        $semua = $this->index()->assertOk();
        $html = $semua->getContent();

        $this->assertSame(3, $this->angkaKpi($html, 'Aset Nilai Buku Habis'));
        $this->assertSame(2, $this->angkaKpi($html, 'Masa Pakai Kritis'));
        $this->assertSame(3000000, $this->nilaiKpi($html, 'Total Nilai Buku Aset'));
        $this->assertSame(3, substr_count($html, '>Habis</span>'));
        $semua->assertSee('1 Hari')
            ->assertSee('30 Hari')
            ->assertSee('31 Hari')
            ->assertSee($kartuKritis1);

        $habis = $this->index(['status' => 'habis'])->assertOk();
        $habis->assertSee($kartuHabis0)
            ->assertSee($kartuHabisLewat)
            ->assertSee($kartuNilaiNol)
            ->assertDontSee($kartuKritis1)
            ->assertDontSee($kartuKritis30)
            ->assertDontSee($kartuNormal31);
        $this->assertSame(3, $this->angkaKpi($habis->getContent(), 'Aset Nilai Buku Habis'));
        $this->assertSame(0, $this->angkaKpi($habis->getContent(), 'Masa Pakai Kritis'));

        $kritis = $this->index(['status' => 'kritis'])->assertOk();
        $kritis->assertSee($kartuKritis1)
            ->assertSee($kartuKritis30)
            ->assertDontSee($kartuHabis0)
            ->assertDontSee($kartuHabisLewat)
            ->assertDontSee($kartuNilaiNol)
            ->assertDontSee($kartuNormal31);
        $this->assertSame(2, $this->angkaKpi($kritis->getContent(), 'Masa Pakai Kritis'));

        $normal = $this->index(['status' => 'normal'])->assertOk();
        $normal->assertSee($kartuNormal31)
            ->assertDontSee($kartuKritis1)
            ->assertDontSee($kartuKritis30)
            ->assertDontSee($kartuHabis0)
            ->assertDontSee($kartuHabisLewat)
            ->assertDontSee($kartuNilaiNol);
        $this->assertSame(0, $this->angkaKpi($normal->getContent(), 'Masa Pakai Kritis'));
    }

    public function test_data_tidak_lengkap_tidak_ditampilkan_sebagai_nol_dan_tidak_masuk_filter_status(): void
    {
        $kartuNormal = $this->kartuUnik('LENGKAP-NORMAL');
        $kartuTanpaNilai = $this->kartuUnik('TANPA-NILAI');
        $kartuTanpaNilaiHabis = $this->kartuUnik('TANPA-NILAI-HABIS');
        $kartuTanpaHabis = $this->kartuUnik('TANPA-HABIS');

        $dasar = [
            'tanggal_pengadaan' => '2026-01-01',
            
            'nilai_perolehan' => 1000000,
        ];

        $this->buatAset($dasar + [
            'nomor_kartu_barang' => $kartuNormal,
            'tanggal_habis_pakai' => '2031-01-01',
        ]);

        $asetTanpaNilai = $this->buatAset([
            'nomor_kartu_barang' => $kartuTanpaNilai,
            'tanggal_pengadaan' => null,
            
            'tanggal_habis_pakai' => null,
            'nilai_perolehan' => null,
        ]);

        $asetTanpaNilaiHabis = $this->buatAset([
            'nomor_kartu_barang' => $kartuTanpaNilaiHabis,
            'tanggal_pengadaan' => '2026-01-01',
            
            'tanggal_habis_pakai' => '2026-11-09',
            'nilai_perolehan' => null,
        ]);

        $asetTanpaHabis = $this->buatAset([
            'nomor_kartu_barang' => $kartuTanpaHabis,
            'tanggal_pengadaan' => '2026-01-01',
            
            'tanggal_habis_pakai' => null,
            'nilai_perolehan' => 2000000,
        ]);

        $service = app(MasaPakaiAset::class);

        $evaluasiTanpaNilai = $service->evaluasi($asetTanpaNilai);
        $this->assertNull($evaluasiTanpaNilai['nilai_buku']);
        $this->assertSame('nan', $evaluasiTanpaNilai['masa_status']);

        $evaluasiTanpaNilaiHabis = $service->evaluasi($asetTanpaNilaiHabis);
        $this->assertNull($evaluasiTanpaNilaiHabis['nilai_buku']);
        $this->assertSame('habis', $evaluasiTanpaNilaiHabis['masa_status']);

        $evaluasiTanpaHabis = $service->evaluasi($asetTanpaHabis);
        $this->assertSame(2000000.0, $evaluasiTanpaHabis['nilai_buku']);
        $this->assertSame('nan', $evaluasiTanpaHabis['masa_status']);

        $semua = $this->index()->assertOk();
        $html = $semua->getContent();

        $this->assertSame(3000000, $this->nilaiKpi($html, 'Total Nilai Buku Aset'));
        $this->assertSame(1, $this->angkaKpi($html, 'Aset Nilai Buku Habis'));
        $this->assertSame(0, $this->angkaKpi($html, 'Masa Pakai Kritis'));
        $semua->assertSee($kartuNormal)
            ->assertSee($kartuTanpaNilai)
            ->assertSee($kartuTanpaNilaiHabis)
            ->assertSee($kartuTanpaHabis);

        $habis = $this->index(['status' => 'habis'])->assertOk();
        $habis->assertSee($kartuTanpaNilaiHabis)
            ->assertDontSee($kartuNormal)
            ->assertDontSee($kartuTanpaNilai)
            ->assertDontSee($kartuTanpaHabis);
        $this->assertSame(1, $this->angkaKpi($habis->getContent(), 'Aset Nilai Buku Habis'));

        $kritis = $this->index(['status' => 'kritis'])->assertOk();
        $kritis->assertDontSee($kartuNormal)
            ->assertDontSee($kartuTanpaNilai)
            ->assertDontSee($kartuTanpaNilaiHabis)
            ->assertDontSee($kartuTanpaHabis);

        $normal = $this->index(['status' => 'normal'])->assertOk();
        $normal->assertSee($kartuNormal)
            ->assertDontSee($kartuTanpaNilai)
            ->assertDontSee($kartuTanpaNilaiHabis)
            ->assertDontSee($kartuTanpaHabis);
        $this->assertSame(1000000, $this->nilaiKpi($normal->getContent(), 'Total Nilai Buku Aset'));
    }

    public function test_nilai_buku_habis_sebelum_tanggal_habis_pakai_masuk_filter_habis(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-01-01 12:00:00'));

        $kartu = $this->kartuUnik('BUKU-NOL-SEBELUM-HABIS');
        $aset = $this->buatAset([
            'nomor_kartu_barang' => $kartu,
            'tanggal_pengadaan' => '2026-01-01',
            
            'tanggal_habis_pakai' => '2030-01-02',
            'nilai_perolehan' => 1,
        ]);

        $evaluasi = app(MasaPakaiAset::class)->evaluasi($aset);
        $this->assertSame(1, $evaluasi['sisa_hari']);
        $this->assertSame(0.0, $evaluasi['nilai_buku']);
        $this->assertSame('habis', $evaluasi['masa_status']);

        $semua = $this->index()->assertOk();
        $this->assertSame(0, $this->nilaiKpi($semua->getContent(), 'Total Nilai Buku Aset'));
        $this->assertSame(1, $this->angkaKpi($semua->getContent(), 'Aset Nilai Buku Habis'));
        $this->assertSame(0, $this->angkaKpi($semua->getContent(), 'Masa Pakai Kritis'));

        $this->index(['status' => 'habis'])->assertOk()->assertSee($kartu);
        $this->index(['status' => 'kritis'])->assertOk()->assertDontSee($kartu);
        $this->index(['status' => 'normal'])->assertOk()->assertDontSee($kartu);
    }

    public function test_tanggal_perolehan_sama_dengan_habis_pakai_diklasifikasikan_habis(): void
    {
        $kartu = $this->kartuUnik('PEROLEHAN-SAMA-HABIS');
        $aset = $this->buatAset([
            'nomor_kartu_barang' => $kartu,
            'tanggal_pengadaan' => '2026-11-10',
            'tanggal_perolehan' => '2026-11-10',
            'tanggal_habis_pakai' => '2026-11-10',
            'nilai_perolehan' => 5000000,
        ]);

        $evaluasi = app(MasaPakaiAset::class)->evaluasi($aset);
        $this->assertSame(0, $evaluasi['sisa_hari']);
        $this->assertSame(0.0, $evaluasi['nilai_buku']);
        $this->assertSame('habis', $evaluasi['masa_status']);

        $semua = $this->index()->assertOk();
        $this->assertSame(0, $this->nilaiKpi($semua->getContent(), 'Total Nilai Buku Aset'));
        $this->assertSame(1, $this->angkaKpi($semua->getContent(), 'Aset Nilai Buku Habis'));

        $this->index(['status' => 'habis'])->assertOk()->assertSee($kartu);
        $this->index(['status' => 'kritis'])->assertOk()->assertDontSee($kartu);
        $this->index(['status' => 'normal'])->assertOk()->assertDontSee($kartu);
    }
}
