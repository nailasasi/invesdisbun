<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Barang;
use App\Models\Kendaraan;
use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\SensusAset;
use App\Models\Skpd;
use App\Models\User;
use App\Services\PeriodeSensus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengujian 12 Skenario Wajib Perbaikan Dropdown Periode dan Siklus Sensus:
 * 1. Dropdown menampilkan periode tiga tahun yang valid.
 * 2. Periode empat tahun tidak muncul sebagai pilihan baru.
 * 3. Aset pengadaan 2019 memiliki periode aktif 2025–2027 pada tahun 2026.
 * 4. Aset pengadaan 2020 memiliki periode aktif 2026–2028 pada tahun 2026.
 * 5. Aset pengadaan 2023 memiliki periode aktif 2026–2028 pada tahun 2026.
 * 6. Aset pengadaan 2025 memiliki periode aktif 2025–2027 pada tahun 2026.
 * 7. Aset pengadaan 2026 memiliki periode aktif 2026–2028 pada tahun 2026.
 * 8. Aset pengadaan 2026 tidak lagi menampilkan Terkunci hanya karena dropdown memilih 2025–2027.
 * 9. Tombol Tambah/Edit selalu mengarah ke periode aktif aset masing-masing.
 * 10. Periode historis tetap dapat dilihat tetapi tidak dapat diedit.
 * 11. Card Aset Belum Disensus tetap menghitung periode aktif setiap aset dan tidak terpengaruh filter dropdown.
 * 12. Scope Admin Aset, Admin UPT, Admin Bidang, dan Pegawai tidak berubah.
 */
class PerbaikanDropdownDanSiklusSensusTest extends TestCase
{
    private User $adminAset;

    private User $adminUptP2btp;

    private User $adminBidang;

    private User $pegawai;

    private Skpd $skpdP2btp;

    private Lokasi $lokasiP2btp;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        Carbon::setTestNow(Carbon::parse('2026-10-28 10:00:00'));

        $this->siapkanPenggunaDanMaster();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        if (file_exists(base_path('.env'))) {
            foreach (file(base_path('.env')) as $line) {
                if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                    $nilai = trim(explode('=', $line, 2)[1]);

                    return $nilai === '' ? 'invesdisbun' : $nilai;
                }
            }
        }

        return 'invesdisbun';
    }

    private function siapkanPenggunaDanMaster(): void
    {
        $this->adminAset = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Aset'))->firstOrFail();
        $this->adminUptP2btp = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin UPT'))
            ->whereHas('pegawai.skpd', fn ($q) => $q->where('nama_skpd', 'UPT P2BTP'))
            ->firstOrFail();

        $this->adminBidang = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Bidang'))->firstOrFail();
        $this->pegawai = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Pegawai'))->firstOrFail();

        $this->skpdP2btp = Skpd::where('nama_skpd', 'UPT P2BTP')->firstOrFail();
        $this->lokasiP2btp = Lokasi::where('id_skpd', $this->skpdP2btp->id_skpd)->firstOrFail();
    }

    /**
     * Skenario 1: Dropdown menampilkan periode tiga tahun yang valid.
     */
    public function test_skenario_1_dropdown_menampilkan_periode_tiga_tahunan_valid(): void
    {
        $response = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index'));
        $response->assertOk();

        $daftarPeriode = $response->viewData('daftarPeriode');
        $this->assertNotEmpty($daftarPeriode);

        foreach ($daftarPeriode as $p) {
            [$mulai, $selesai] = PeriodeSensus::urai($p);
            $this->assertSame(2, $selesai - $mulai, "Periode {$p} harus mencakup tepat 3 tahun kalender inklusif (selisih 2).");
        }
    }

    /**
     * Skenario 2: Periode empat tahun tidak muncul sebagai pilihan baru.
     */
    public function test_skenario_2_periode_empat_tahun_tidak_muncul_dalam_dropdown(): void
    {
        $response = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index'));
        $response->assertOk();

        $daftarPeriode = $response->viewData('daftarPeriode');

        $this->assertNotContains('2022–2025', $daftarPeriode);
        $this->assertNotContains('2025–2028', $daftarPeriode);
        $this->assertNotContains('2022-2025', $daftarPeriode);
        $this->assertNotContains('2025-2028', $daftarPeriode);
    }

    /**
     * Skenario 3: Aset pengadaan 2019 memiliki periode aktif 2025–2027 pada tahun 2026.
     */
    public function test_skenario_3_aset_pengadaan_2019_aktif_2025_2027_pada_2026(): void
    {
        $aset2019 = new Aset(['tanggal_pengadaan' => '2019-04-10']);
        $this->assertSame('2025–2027', PeriodeSensus::periodeAktif(Carbon::parse('2026-10-28'), $aset2019));
    }

    /**
     * Skenario 4: Aset pengadaan 2020 memiliki periode aktif 2026–2028 pada tahun 2026.
     */
    public function test_skenario_4_aset_pengadaan_2020_aktif_2026_2028_pada_2026(): void
    {
        $aset2020 = new Aset(['tanggal_pengadaan' => '2020-07-15']);
        $this->assertSame('2026–2028', PeriodeSensus::periodeAktif(Carbon::parse('2026-10-28'), $aset2020));
    }

    /**
     * Skenario 5: Aset pengadaan 2023 memiliki periode aktif 2026–2028 pada tahun 2026.
     */
    public function test_skenario_5_aset_pengadaan_2023_aktif_2026_2028_pada_2026(): void
    {
        $aset2023 = new Aset(['tanggal_pengadaan' => '2023-01-20']);
        $this->assertSame('2026–2028', PeriodeSensus::periodeAktif(Carbon::parse('2026-10-28'), $aset2023));
    }

    /**
     * Skenario 6: Aset pengadaan 2025 memiliki periode aktif 2025–2027 pada tahun 2026.
     */
    public function test_skenario_6_aset_pengadaan_2025_aktif_2025_2027_pada_2026(): void
    {
        $aset2025 = new Aset(['tanggal_pengadaan' => '2025-05-12']);
        $this->assertSame('2025–2027', PeriodeSensus::periodeAktif(Carbon::parse('2026-10-28'), $aset2025));
    }

    /**
     * Skenario 7: Aset pengadaan 2026 memiliki periode aktif 2026–2028 pada tahun 2026.
     */
    public function test_skenario_7_aset_pengadaan_2026_aktif_2026_2028_pada_2026(): void
    {
        $aset2026 = new Aset(['tanggal_pengadaan' => '2026-02-14']);
        $this->assertSame('2026–2028', PeriodeSensus::periodeAktif(Carbon::parse('2026-10-28'), $aset2026));
    }

    /**
     * Skenario 8: Aset pengadaan 2026 tidak lagi menampilkan Terkunci hanya karena dropdown memilih 2025–2027.
     */
    public function test_skenario_8_aset_2026_tidak_terkunci_saat_dropdown_2025_2027(): void
    {
        $kib = 'TEST-KIB-2026-' . uniqid();
        $aset2026 = Aset::create([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => $kib,
            'tanggal_pengadaan' => '2026-03-01',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'is_kendaraan' => false,
        ]);

        $response = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index', [
            'periode' => '2025–2027',
            'search' => $kib,
        ]));

        $response->assertOk();
        $asetList = $response->viewData('asetList');
        $row = $asetList->first();

        $this->assertNotNull($row);
        $this->assertSame($aset2026->id_aset, $row->id_aset);
        $this->assertTrue($row->periode_berlangsung);
        $this->assertSame('2026–2028', $row->periode_sensus_aktif);

        // Di HTML tidak menampilkan teks "Terkunci" untuk baris aset ini, melainkan "Tambah Sensus"
        $response->assertSee('Tambah Sensus');
        $response->assertSee('data-periode-aktif="2026–2028"', false);
    }

    /**
     * Skenario 9: Tombol Tambah/Edit selalu mengarah ke periode aktif aset masing-masing.
     */
    public function test_skenario_9_tombol_tambah_edit_mengarah_ke_periode_aktif_masing_masing(): void
    {
        // 1. Aset pengadaan 2025 (periode aktif: 2025–2027)
        $kib2025 = 'TEST-KIB-2025-' . uniqid();
        $aset2025 = Aset::create([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => $kib2025,
            'tanggal_pengadaan' => '2025-01-10',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'is_kendaraan' => false,
        ]);

        // 2. Aset pengadaan 2026 (periode aktif: 2026–2028)
        $kib2026 = 'TEST-KIB-2026-' . uniqid();
        $aset2026 = Aset::create([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => $kib2026,
            'tanggal_pengadaan' => '2026-01-15',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'is_kendaraan' => false,
        ]);

        // Cek halaman monitoring dengan filter periode 2022–2024 (periode historis)
        $res = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index', [
            'periode' => '2022–2024',
        ]));
        $res->assertOk();

        // Cari row aset 2025
        $res2025 = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index', [
            'periode' => '2022–2024',
            'search' => $kib2025,
        ]));
        $row2025 = $res2025->viewData('asetList')->first();
        $this->assertSame('2025–2027', $row2025->periode_sensus_aktif);
        $res2025->assertSee('data-periode="2025–2027"', false);

        // Cari row aset 2026
        $res2026 = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index', [
            'periode' => '2022–2024',
            'search' => $kib2026,
        ]));
        $row2026 = $res2026->viewData('asetList')->first();
        $this->assertSame('2026–2028', $row2026->periode_sensus_aktif);
        $res2026->assertSee('data-periode="2026–2028"', false);
    }

    /**
     * Skenario 10: Periode historis tetap dapat dilihat tetapi tidak dapat diedit.
     */
    public function test_skenario_10_periode_historis_dapat_dilihat_tetapi_tidak_dapat_diedit(): void
    {
        $aset = Aset::create([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => 'TEST-HISTORIS-' . uniqid(),
            'tanggal_pengadaan' => '2019-01-01',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'is_kendaraan' => false,
        ]);

        // Catat sensus historis 2022–2024
        SensusAset::create([
            'id_aset' => $aset->id_aset,
            'periode' => '2022–2024',
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2023-04-10',
            'id_user' => $this->adminAset->id_user,
        ]);

        // 1. Riwayat sensus dapat dilihat
        $resRiwayat = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.sensus.riwayat', $aset->id_aset));
        $resRiwayat->assertOk();
        $resRiwayat->assertSee('2022–2024');

        // 2. Mengakses halaman form edit sensus untuk periode historis yang sudah berakhir ditolak (403)
        $resEdit = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.sensus.edit', ['aset' => $aset->id_aset, 'periode' => '2022–2024']));
        $resEdit->assertStatus(403);

        // 3. Mengirim pembaruan sensus untuk periode historis ditolak dengan pesan validasi periode terkunci
        $resUpdate = $this->actingAs($this->adminAset)
            ->from(route('monitoring-aset.index'))
            ->post(route('monitoring-aset.sensus.update', $aset->id_aset), [
                'periode' => '2022–2024',
                'hasil_sensus' => 'Rusak Ringan',
                'tanggal_sensus' => '2026-10-28',
            ]);
        $resUpdate->assertSessionHasErrors('periode');
    }

    /**
     * Skenario 11: Card Aset Belum Disensus tetap menghitung periode aktif setiap aset dan tidak terpengaruh filter dropdown.
     */
    public function test_skenario_11_card_aset_belum_disensus_tidak_terpengaruh_filter_dropdown(): void
    {
        $resDefault = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index'));
        $countDefault = $resDefault->viewData('asetBelumDisensusCount');

        $res2025 = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index', ['periode' => '2025–2027']));
        $count2025 = $res2025->viewData('asetBelumDisensusCount');

        $res2022 = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index', ['periode' => '2022–2024']));
        $count2022 = $res2022->viewData('asetBelumDisensusCount');

        $this->assertSame($countDefault, $count2025);
        $this->assertSame($countDefault, $count2022);
    }

    /**
     * Skenario 12: Scope Admin Aset, Admin UPT, Admin Bidang, dan Pegawai tidak berubah.
     */
    public function test_skenario_12_scope_otorisasi_tetap_terjaga(): void
    {
        // Admin Aset: 200 OK
        $resAdminAset = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index'));
        $resAdminAset->assertOk();

        // Admin UPT: 200 OK (terfilter UPT)
        $resAdminUpt = $this->actingAs($this->adminUptP2btp)->get(route('monitoring-aset.index'));
        $resAdminUpt->assertOk();

        // Admin Bidang: 403 Forbidden
        $resAdminBidang = $this->actingAs($this->adminBidang)->get(route('monitoring-aset.index'));
        $resAdminBidang->assertStatus(403);

        // Pegawai: 403 Forbidden
        $resPegawai = $this->actingAs($this->pegawai)->get(route('monitoring-aset.index'));
        $resPegawai->assertStatus(403);
    }
}
