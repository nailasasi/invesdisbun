<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Tahap A — akses Monitoring Aset:
 * Admin Aset & Admin UPT boleh masuk; Admin Bidang & Pegawai ditolak.
 * Admin UPT hanya melihat data lokasi UPT miliknya, termasuk pada KPI,
 * daftar tahun, dan kedua endpoint ekspor.
 *
 * Memakai koneksi MySQL nyata dibungkus transaksi yang di-rollback.
 */
class MonitoringAsetAksesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
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

    private function userDenganRole(string $role): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', $role))->firstOrFail();
    }

    private function adminUptP2btp(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin UPT'))
            ->whereHas('pegawai.skpd', fn ($q) => $q->where('nama_skpd', 'UPT P2BTP'))
            ->firstOrFail();
    }

    private function adminUptPsbp(): User
    {
        $skpd = Skpd::where('nama_skpd', 'UPT PSBP')->firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => 'TST'.uniqid(),
            'nama_pegawai' => 'Admin UPT PSBP Test',
            'id_skpd' => $skpd->id_skpd,
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => Role::where('nama_role', 'Admin UPT')->firstOrFail()->id_role,
            'username' => 'tst_upt_psbp_'.uniqid(),
            'password' => 'rahasia',
            'status_user' => 'aktif',
        ]);
    }

    private function pegawaiUpt(string $namaSkpd): Pegawai
    {
        $skpd = Skpd::where('nama_skpd', $namaSkpd)->firstOrFail();

        return Pegawai::create([
            'nip' => 'TST'.uniqid(),
            'nama_pegawai' => 'Pegawai '.$namaSkpd,
            'id_skpd' => $skpd->id_skpd,
        ]);
    }

    private function lokasiUpt(string $namaSkpd): Lokasi
    {
        $skpd = Skpd::where('nama_skpd', $namaSkpd)->firstOrFail();

        return Lokasi::where('id_skpd', $skpd->id_skpd)->firstOrFail();
    }

    private function buatAset(
        int $idLokasi,
        string $kartu,
        ?string $tanggal = null,
        ?float $nilai = null,
        string $merk = 'Aset Uji',
        ?string $habis = null
    ): Aset {
        return Aset::create([
            'id_lokasi' => $idLokasi,
            'nomor_kartu_barang' => $kartu,
            'merk' => $merk,
            'tanggal_pengadaan' => $tanggal,
            'tanggal_habis_pakai' => $habis,
            'nilai_perolehan' => $nilai,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);
    }

    private function kartuUnik(string $prefix): string
    {
        return $prefix.'-'.uniqid();
    }

    /**
     * @return array<int, mixed>
     */
    private function isiWorkbook(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'monxlsx');

        try {
            file_put_contents($path, $content);
            $sheet = IOFactory::load($path)->getActiveSheet();

            return $sheet->toArray(null, true, true, false);
        } finally {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    private function teksWorkbook(string $content): string
    {
        $teks = '';

        foreach ($this->isiWorkbook($content) as $baris) {
            $teks .= implode(' | ', array_map(fn ($sel) => (string) $sel, $baris))."\n";
        }

        return $teks;
    }

    public function test_admin_aset_dan_admin_upt_boleh_mengakses_monitoring(): void
    {
        foreach ([
            $this->userDenganRole('Admin Aset'),
            $this->adminUptP2btp(),
            $this->adminUptPsbp(),
        ] as $user) {
            $this->actingAs($user)
                ->get(route('monitoring-aset.index'))
                ->assertOk();
        }
    }

    public function test_admin_bidang_dan_pegawai_ditolak_pada_semua_endpoint(): void
    {
        foreach ([
            $this->userDenganRole('Admin Bidang'),
            $this->userDenganRole('Pegawai'),
        ] as $user) {
            foreach ([
                route('monitoring-aset.index'),
                route('monitoring-aset.export'),
                route('monitoring-aset.export-per-pengguna'),
            ] as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }
    }

    /**
     * @return array{kartuP2btp: string, kartuPsbp: string, kartuDinas: string}
     */
    private function tigaAsetLintasLokasi(): array
    {
        $lokasiP2btp = $this->lokasiUpt('UPT P2BTP');
        $lokasiPsbp = $this->lokasiUpt('UPT PSBP');
        $lokasiDinas = Lokasi::where('jenis_lokasi', 'Dinas')->firstOrFail();

        $kartuP2btp = $this->kartuUnik('TST-P2BTP');
        $kartuPsbp = $this->kartuUnik('TST-PSBP');
        $kartuDinas = $this->kartuUnik('TST-DINAS');

        $this->buatAset($lokasiP2btp->id_lokasi, $kartuP2btp, '1991-05-05');
        $this->buatAset($lokasiPsbp->id_lokasi, $kartuPsbp, '1992-05-05');
        $this->buatAset($lokasiDinas->id_lokasi, $kartuDinas, '1993-05-05');

        return compact('kartuP2btp', 'kartuPsbp', 'kartuDinas');
    }

    public function test_admin_upt_p2btp_hanya_melihat_aset_dan_tahun_dalam_lingkupnya(): void
    {
        ['kartuP2btp' => $kartuP2btp, 'kartuPsbp' => $kartuPsbp, 'kartuDinas' => $kartuDinas]
            = $this->tigaAsetLintasLokasi();

        $this->actingAs($this->adminUptP2btp())
            ->get(route('monitoring-aset.index'))
            ->assertOk()
            ->assertSee($kartuP2btp)
            ->assertDontSee($kartuPsbp)
            ->assertDontSee($kartuDinas)
            ->assertSee('value="1991"', false)
            ->assertDontSee('value="1992"', false)
            ->assertDontSee('value="1993"', false)
            ->assertDontSee('Usulkan Hapus');
    }

    public function test_admin_upt_psbp_hanya_melihat_aset_dan_tahun_dalam_lingkupnya(): void
    {
        ['kartuP2btp' => $kartuP2btp, 'kartuPsbp' => $kartuPsbp, 'kartuDinas' => $kartuDinas]
            = $this->tigaAsetLintasLokasi();

        $this->actingAs($this->adminUptPsbp())
            ->get(route('monitoring-aset.index'))
            ->assertOk()
            ->assertSee($kartuPsbp)
            ->assertDontSee($kartuP2btp)
            ->assertDontSee($kartuDinas)
            ->assertSee('value="1992"', false)
            ->assertDontSee('value="1991"', false)
            ->assertDontSee('value="1993"', false)
            ->assertDontSee('Usulkan Hapus');
    }

    public function test_admin_aset_melihat_aset_dan_tahun_semua_lokasi(): void
    {
        ['kartuP2btp' => $kartuP2btp, 'kartuPsbp' => $kartuPsbp, 'kartuDinas' => $kartuDinas]
            = $this->tigaAsetLintasLokasi();

        $this->actingAs($this->userDenganRole('Admin Aset'))
            ->get(route('monitoring-aset.index'))
            ->assertOk()
            ->assertSee($kartuP2btp)
            ->assertSee($kartuPsbp)
            ->assertSee($kartuDinas)
            ->assertSee('value="1991"', false)
            ->assertSee('value="1992"', false)
            ->assertSee('value="1993"', false)
            ->assertSee('Tambah Sensus');
    }

    public function test_kpi_admin_upt_p2btp_hanya_menghitung_lingkupnya(): void
    {
        $this->buatAset($this->lokasiUpt('UPT P2BTP')->id_lokasi, $this->kartuUnik('TST-KPI-P2'), null, 1234567);
        $this->buatAset($this->lokasiUpt('UPT PSBP')->id_lokasi, $this->kartuUnik('TST-KPI-PS'), null, 7654321);

        $this->actingAs($this->adminUptP2btp())
            ->get(route('monitoring-aset.index'))
            ->assertOk()
            ->assertSee('Rp 1.234.567')
            ->assertDontSee('Rp 7.654.321');
    }

    public function test_kpi_admin_upt_psbp_hanya_menghitung_lingkupnya(): void
    {
        $this->buatAset($this->lokasiUpt('UPT P2BTP')->id_lokasi, $this->kartuUnik('TST-KPI-P2'), null, 1234567);
        $this->buatAset($this->lokasiUpt('UPT PSBP')->id_lokasi, $this->kartuUnik('TST-KPI-PS'), null, 7654321);

        $this->actingAs($this->adminUptPsbp())
            ->get(route('monitoring-aset.index'))
            ->assertOk()
            ->assertSee('Rp 7.654.321')
            ->assertDontSee('Rp 1.234.567');
    }

    /**
     * @return array{kartuP2btp: string, kartuPsbp: string, merkP2btp: string, merkPsbp: string}
     */
    private function asetEksporDuaUpt(): array
    {
        $kartuP2btp = $this->kartuUnik('TST-EKS-P2');
        $kartuPsbp = $this->kartuUnik('TST-EKS-PS');
        $merkP2btp = 'MERK-P2BTP-'.uniqid();
        $merkPsbp = 'MERK-PSBP-'.uniqid();

        $asetP2btp = $this->buatAset($this->lokasiUpt('UPT P2BTP')->id_lokasi, $kartuP2btp, null, 1000, $merkP2btp);
        $asetPsbp = $this->buatAset($this->lokasiUpt('UPT PSBP')->id_lokasi, $kartuPsbp, null, 2000, $merkPsbp);

        PemegangAset::create([
            'id_aset' => $asetP2btp->id_aset,
            'id_pegawai' => $this->pegawaiUpt('UPT P2BTP')->id_pegawai,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        PemegangAset::create([
            'id_aset' => $asetPsbp->id_aset,
            'id_pegawai' => $this->pegawaiUpt('UPT PSBP')->id_pegawai,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        return compact('kartuP2btp', 'kartuPsbp', 'merkP2btp', 'merkPsbp');
    }

    public function test_ekspor_admin_upt_p2btp_mengikuti_lingkupnya(): void
    {
        ['kartuP2btp' => $kartuP2btp, 'kartuPsbp' => $kartuPsbp, 'merkP2btp' => $merkP2btp, 'merkPsbp' => $merkPsbp]
            = $this->asetEksporDuaUpt();

        $rekap = $this->actingAs($this->adminUptP2btp())
            ->get(route('monitoring-aset.export'))
            ->assertOk();
        $teksRekap = $this->teksWorkbook($rekap->streamedContent());
        $this->assertStringContainsString($kartuP2btp, $teksRekap);
        $this->assertStringNotContainsString($kartuPsbp, $teksRekap);

        $perPengguna = $this->actingAs($this->adminUptP2btp())
            ->get(route('monitoring-aset.export-per-pengguna'))
            ->assertOk();
        $teksPerPengguna = $this->teksWorkbook($perPengguna->streamedContent());
        $this->assertStringContainsString($merkP2btp, $teksPerPengguna);
        $this->assertStringNotContainsString($merkPsbp, $teksPerPengguna);
    }

    public function test_ekspor_admin_upt_psbp_mengikuti_lingkupnya(): void
    {
        ['kartuP2btp' => $kartuP2btp, 'kartuPsbp' => $kartuPsbp, 'merkP2btp' => $merkP2btp, 'merkPsbp' => $merkPsbp]
            = $this->asetEksporDuaUpt();

        $rekap = $this->actingAs($this->adminUptPsbp())
            ->get(route('monitoring-aset.export'))
            ->assertOk();
        $teksRekap = $this->teksWorkbook($rekap->streamedContent());
        $this->assertStringContainsString($kartuPsbp, $teksRekap);
        $this->assertStringNotContainsString($kartuP2btp, $teksRekap);

        $perPengguna = $this->actingAs($this->adminUptPsbp())
            ->get(route('monitoring-aset.export-per-pengguna'))
            ->assertOk();
        $teksPerPengguna = $this->teksWorkbook($perPengguna->streamedContent());
        $this->assertStringContainsString($merkPsbp, $teksPerPengguna);
        $this->assertStringNotContainsString($merkP2btp, $teksPerPengguna);
    }

    public function test_role_tidak_dikenal_ditolak_pada_seluruh_endpoint_monitoring(): void
    {
        $roleTakDikenal = Role::create([
            'nama_role' => 'RoleTakDikenal_'.uniqid(),
            'deskripsi' => 'Role uji tanpa izin monitoring',
        ]);

        $pegawai = Pegawai::create([
            'nip' => 'TST'.uniqid(),
            'nama_pegawai' => 'User Tak Dikenal',
            'id_skpd' => Skpd::first()->id_skpd,
        ]);

        $userTakDikenal = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $roleTakDikenal->id_role,
            'username' => 'user_tak_dikenal_'.uniqid(),
            'password' => 'rahasia',
            'status_user' => 'aktif',
        ]);

        foreach ([
            route('monitoring-aset.index'),
            route('monitoring-aset.export'),
            route('monitoring-aset.export-per-pengguna'),
        ] as $url) {
            $this->actingAs($userTakDikenal)->get($url)->assertForbidden();
        }
    }

    private function ambilSidebarHtml(string $fullHtml): string
    {
        if (preg_match('/<aside id="sidebar".*?<\/aside>/s', $fullHtml, $matches)) {
            return $matches[0];
        }

        return $fullHtml;
    }

    public function test_sidebar_menampilkan_menu_monitoring_untuk_admin_aset_dan_admin_upt_tetapi_tidak_untuk_lainnya(): void
    {
        // 1. Admin Aset melihat tepat 1 menu Monitoring Aset di sidebar di bawah Pengelolaan Aset
        $resAset = $this->actingAs($this->userDenganRole('Admin Aset'))->get(route('dashboard'));
        $resAset->assertOk()
            ->assertSee('Monitoring Aset')
            ->assertSee('Mutasi Aset')
            ->assertSee('Penghapusan Aset');
        $sidebarAset = $this->ambilSidebarHtml($resAset->getContent());
        $this->assertSame(1, substr_count($sidebarAset, 'Monitoring Aset'));
        $this->assertTrue(strpos($sidebarAset, 'Monitoring Aset') > strpos($sidebarAset, 'Pengelolaan Aset'));

        // 2. Admin UPT P2BTP melihat tepat 1 menu Monitoring Aset di sidebar (di bawah Pengelolaan Aset, bukan di Inventaris Aset)
        $resP2btp = $this->actingAs($this->adminUptP2btp())->get(route('dashboard'));
        $resP2btp->assertOk()
            ->assertSee('Monitoring Aset')
            ->assertDontSee('Mutasi Aset')
            ->assertDontSee('Penghapusan Aset');
        $sidebarP2btp = $this->ambilSidebarHtml($resP2btp->getContent());
        $this->assertSame(1, substr_count($sidebarP2btp, 'Monitoring Aset'));
        $this->assertTrue(strpos($sidebarP2btp, 'Monitoring Aset') > strpos($sidebarP2btp, 'Pengelolaan Aset'));

        // 3. Admin UPT PSBP melihat tepat 1 menu Monitoring Aset di sidebar (di bawah Pengelolaan Aset)
        $resPsbp = $this->actingAs($this->adminUptPsbp())->get(route('dashboard'));
        $resPsbp->assertOk()
            ->assertSee('Monitoring Aset')
            ->assertDontSee('Mutasi Aset')
            ->assertDontSee('Penghapusan Aset');
        $sidebarPsbp = $this->ambilSidebarHtml($resPsbp->getContent());
        $this->assertSame(1, substr_count($sidebarPsbp, 'Monitoring Aset'));
        $this->assertTrue(strpos($sidebarPsbp, 'Monitoring Aset') > strpos($sidebarPsbp, 'Pengelolaan Aset'));

        // 4. Admin Bidang TIDAK melihat Monitoring Aset di sidebar
        $resBidang = $this->actingAs($this->userDenganRole('Admin Bidang'))->get(route('dashboard'));
        $resBidang->assertOk()
            ->assertDontSee('Monitoring Aset');
        $sidebarBidang = $this->ambilSidebarHtml($resBidang->getContent());
        $this->assertSame(0, substr_count($sidebarBidang, 'Monitoring Aset'));

        // 5. Pegawai TIDAK melihat Monitoring Aset di sidebar
        $resPegawai = $this->actingAs($this->userDenganRole('Pegawai'))->get(route('dashboard'));
        $resPegawai->assertOk()
            ->assertDontSee('Monitoring Aset');
        $sidebarPegawai = $this->ambilSidebarHtml($resPegawai->getContent());
        $this->assertSame(0, substr_count($sidebarPegawai, 'Monitoring Aset'));
    }

    public function test_filter_status_masa_pakai_terisolasi_antara_upt_p2btp_dan_psbp(): void
    {
        $kartuP2btp = $this->kartuUnik('NOL-P2BTP');
        $kartuPsbp = $this->kartuUnik('NOL-PSBP');

        // Aset di P2BTP: bernilai perolehan 1, pengadaan 2026-01-01, habis 2030-01-02
        // Nilai buku habis Rp0 pada 2030-01-01 (masuk idNilaiBukuNolSebelumHabis)
        $this->buatAset(
            $this->lokasiUpt('UPT P2BTP')->id_lokasi,
            $kartuP2btp,
            '2026-01-01',
            1,
            'Aset Uji',
            '2030-01-02'
        );

        // Aset di PSBP: bernilai perolehan 1, pengadaan 2026-01-01, habis 2030-01-02
        $this->buatAset(
            $this->lokasiUpt('UPT PSBP')->id_lokasi,
            $kartuPsbp,
            '2026-01-01',
            1,
            'Aset Uji',
            '2030-01-02'
        );

        // Uji pada tanggal 2030-01-01
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2030-01-01 12:00:00'));

        try {
            // Admin UPT P2BTP memfilter status=habis: HANYA melihat aset P2BTP
            $this->actingAs($this->adminUptP2btp())
                ->get(route('monitoring-aset.index', ['status' => 'habis']))
                ->assertOk()
                ->assertSee($kartuP2btp)
                ->assertDontSee($kartuPsbp);

            // Admin UPT PSBP memfilter status=habis: HANYA melihat aset PSBP
            $this->actingAs($this->adminUptPsbp())
                ->get(route('monitoring-aset.index', ['status' => 'habis']))
                ->assertOk()
                ->assertSee($kartuPsbp)
                ->assertDontSee($kartuP2btp);

            // Admin Aset memfilter status=habis: melihat KEDUA aset secara global
            $this->actingAs($this->userDenganRole('Admin Aset'))
                ->get(route('monitoring-aset.index', ['status' => 'habis']))
                ->assertOk()
                ->assertSee($kartuP2btp)
                ->assertSee($kartuPsbp);
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }
}
