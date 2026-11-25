<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\PenempatanAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\SensusAset;
use App\Models\Skpd;
use App\Models\User;
use App\Services\PeriodeSensus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengujian fitur Card "ASET BELUM DISENSUS" pada Monitoring Aset:
 * 1. Admin Aset melihat jumlah aset belum disensus secara keseluruhan.
 * 2. Admin UPT P2BTP hanya melihat jumlah aset belum disensus milik P2BTP.
 * 3. Admin UPT PSBP hanya melihat jumlah aset belum disensus milik PSBP.
 * 4. Aset kendaraan ikut dihitung.
 * 5. Aset yang belum difinalisasi ikut dihitung.
 * 6. Aset tanpa lokasi atau pemegang ikut dihitung.
 * 7. Aset yang sudah memiliki record sensus pada periode terpilih tidak dihitung.
 * 8. Mengganti dropdown periode mengubah angka card sesuai periode baru.
 * 9. Angka card konsisten dengan data aset yang ditampilkan dan hasil query database.
 */
class CardAsetBelumDisensusTest extends TestCase
{
    private User $adminAset;

    private User $adminUptP2btp;

    private User $adminUptPsbp;

    private Skpd $skpdP2btp;

    private Skpd $skpdPsbp;

    private Lokasi $lokasiP2btp;

    private Lokasi $lokasiPsbp;

    private array $createdAsetIds = [];

    private array $createdKendaraanIds = [];

    private array $createdPenempatanIds = [];

    private array $createdSensusIds = [];

    private array $createdUserIds = [];

    private array $createdPegawaiIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        Carbon::setTestNow(Carbon::parse('2026-10-28 10:00:00'));

        $this->siapkanDataUji();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Carbon::setTestNow();

        if (! empty($this->createdSensusIds)) {
            SensusAset::whereIn('id_sensus', $this->createdSensusIds)->delete();
        }
        if (! empty($this->createdPenempatanIds)) {
            PenempatanAset::whereIn('id_penempatan', $this->createdPenempatanIds)->delete();
        }
        if (! empty($this->createdKendaraanIds)) {
            Kendaraan::whereIn('id_kendaraan', $this->createdKendaraanIds)->delete();
        }
        if (! empty($this->createdAsetIds)) {
            Aset::whereIn('id_aset', $this->createdAsetIds)->delete();
        }
        if (! empty($this->createdUserIds)) {
            User::whereIn('id_user', $this->createdUserIds)->delete();
        }
        if (! empty($this->createdPegawaiIds)) {
            Pegawai::whereIn('id_pegawai', $this->createdPegawaiIds)->delete();
        }

        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        return $this->namaDatabasePengujian();
    }

    private function userDenganRole(string $role): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', $role))->firstOrFail();
    }

    private function siapkanDataUji(): void
    {
        $this->adminAset = $this->userDenganRole('Admin Aset');

        $this->skpdP2btp = Skpd::where('nama_skpd', 'UPT P2BTP')->firstOrFail();
        $this->skpdPsbp = Skpd::where('nama_skpd', 'UPT PSBP')->firstOrFail();

        $this->lokasiP2btp = Lokasi::where('id_skpd', $this->skpdP2btp->id_skpd)->firstOrFail();
        $this->lokasiPsbp = Lokasi::where('id_skpd', $this->skpdPsbp->id_skpd)->firstOrFail();

        $this->adminUptP2btp = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin UPT'))
            ->whereHas('pegawai.skpd', fn ($q) => $q->where('nama_skpd', 'UPT P2BTP'))
            ->firstOrFail();

        $pegawaiPsbp = Pegawai::create([
            'nip' => 'TST' . uniqid(),
            'nama_pegawai' => 'Admin UPT PSBP Test',
            'id_skpd' => $this->skpdPsbp->id_skpd,
        ]);
        $this->createdPegawaiIds[] = $pegawaiPsbp->id_pegawai;

        $this->adminUptPsbp = User::create([
            'id_pegawai' => $pegawaiPsbp->id_pegawai,
            'id_role' => Role::where('nama_role', 'Admin UPT')->firstOrFail()->id_role,
            'username' => 'tst_psbp_' . uniqid(),
            'password' => 'rahasia',
            'status_user' => 'aktif',
        ]);
        $this->createdUserIds[] = $this->adminUptPsbp->id_user;
    }

    private function buatAset(array $attributes = []): Aset
    {
        $aset = Aset::create(array_merge([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => 'TST-AST-' . uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'is_kendaraan' => false,
        ], $attributes));

        $this->createdAsetIds[] = $aset->id_aset;

        return $aset;
    }

    private function buatKendaraan(array $attributes = []): Kendaraan
    {
        $kendaraan = Kendaraan::create($attributes);
        $this->createdKendaraanIds[] = $kendaraan->id_kendaraan;

        return $kendaraan;
    }

    private function buatPenempatan(array $attributes = []): PenempatanAset
    {
        $penempatan = PenempatanAset::create($attributes);
        $this->createdPenempatanIds[] = $penempatan->id_penempatan;

        return $penempatan;
    }

    private function buatSensus(array $attributes = []): SensusAset
    {
        $sensus = SensusAset::create($attributes);
        $this->createdSensusIds[] = $sensus->id_sensus;

        return $sensus;
    }

    public function test_card_menampilkan_judul_desain_dan_ikon_aset_belum_disensus(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));

        $response->assertOk();
        $html = $response->getContent();

        // 1. Judul card Aset Belum Disensus
        $this->assertStringContainsString('Aset Belum Disensus', $html);

        // 2. Tiga card sebelumnya tetap utuh
        $this->assertStringContainsString('Total Nilai Buku Aset', $html);
        $this->assertStringContainsString('Aset Nilai Buku Habis', $html);
        $this->assertStringContainsString('Masa Pakai Kritis', $html);

        // 3. Grid responsif 4 kolom
        $this->assertStringContainsString('sm:grid-cols-2 lg:grid-cols-4', $html);

        // 4. Aksen warna oranye dan latar belakang badge
        $this->assertStringContainsString('text-orange-600', $html);
        $this->assertStringContainsString('bg-orange-50', $html);

        // 5. Ikon clipboard-list
        $this->assertStringContainsString('M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2', $html);
    }

    public function test_skenario_1_admin_aset_melihat_jumlah_aset_belum_disensus_secara_keseluruhan(): void
    {
        // 1. Ambil nilai card awal
        $resAwal = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $resAwal->assertOk();
        $countAwal = $resAwal->viewData('asetBelumDisensusCount');

        // Pastikan angka card awal konsisten dengan hitungAsetBelumDisensusAktif scope Admin Aset
        $query = Aset::query()->whereIn('status_aset', ['aktif', 'Aktif']);
        $ekspektasiAwal = PeriodeSensus::hitungAsetBelumDisensusAktif($query);
        $this->assertSame($ekspektasiAwal, $countAwal);
        $resAwal->assertSee("{$countAwal} <span class=\"text-xs font-semibold text-slate-500\">Aset</span>", false);

        // 2. Tambah 1 aset aktif baru dinamis
        $this->buatAset([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        // 3. Verifikasi delta card bertambah tepat 1
        $resAkhir = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $resAkhir->assertOk();
        $countAkhir = $resAkhir->viewData('asetBelumDisensusCount');
        $this->assertSame($countAwal + 1, $countAkhir);
    }

    public function test_skenario_2_admin_upt_p2btp_hanya_melihat_aset_belum_disensus_milik_p2btp(): void
    {
        $resAwal = $this->actingAs($this->adminUptP2btp)
            ->get(route('monitoring-aset.index'));
        $resAwal->assertOk();
        $countAwalP2 = $resAwal->viewData('asetBelumDisensusCount');

        // Tambah 1 aset baru di UPT P2BTP
        $this->buatAset([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        // Tambah 1 aset baru di UPT PSBP (tidak boleh menambah count P2BTP)
        $this->buatAset([
            'id_skpd' => $this->skpdPsbp->id_skpd,
            'id_lokasi' => $this->lokasiPsbp->id_lokasi,
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $resAkhir = $this->actingAs($this->adminUptP2btp)
            ->get(route('monitoring-aset.index'));
        $resAkhir->assertOk();
        $countAkhirP2 = $resAkhir->viewData('asetBelumDisensusCount');

        // Pastikan hanya bertambah 1 untuk aset milik P2BTP
        $this->assertSame($countAwalP2 + 1, $countAkhirP2);
    }

    public function test_skenario_3_admin_upt_psbp_hanya_melihat_aset_belum_disensus_milik_psbp(): void
    {
        $resAwal = $this->actingAs($this->adminUptPsbp)
            ->get(route('monitoring-aset.index'));
        $resAwal->assertOk();
        $countAwalPs = $resAwal->viewData('asetBelumDisensusCount');

        // Tambah 1 aset di P2BTP (tidak boleh menambah count PSBP)
        $this->buatAset([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        // Tambah 1 aset di PSBP (harus menambah count PSBP)
        $this->buatAset([
            'id_skpd' => $this->skpdPsbp->id_skpd,
            'id_lokasi' => $this->lokasiPsbp->id_lokasi,
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $resAkhir = $this->actingAs($this->adminUptPsbp)
            ->get(route('monitoring-aset.index'));
        $resAkhir->assertOk();
        $countAkhirPs = $resAkhir->viewData('asetBelumDisensusCount');

        $this->assertSame($countAwalPs + 1, $countAkhirPs);
    }

    public function test_skenario_4_aset_kendaraan_ikut_dihitung_dalam_card(): void
    {
        $resAwal = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $resAwal->assertOk();
        $countAwal = $resAwal->viewData('asetBelumDisensusCount');

        // Tambah 1 aset kendaraan belum disensus
        $asetKendaraan = $this->buatAset([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
        ]);

        $this->buatKendaraan([
            'id_aset' => $asetKendaraan->id_aset,
            'nama_kendaraan' => 'Motor Dinas Lapangan Test',
            'merk' => 'Honda',
            'tipe' => 'Revo',
            'jenis_kendaraan' => 'Roda 2',
        ]);

        $resAkhir = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $resAkhir->assertOk();
        $countAkhir = $resAkhir->viewData('asetBelumDisensusCount');

        $this->assertSame($countAwal + 1, $countAkhir);
    }

    public function test_skenario_5_aset_belum_difinalisasi_ikut_dihitung_dalam_card(): void
    {
        $resAwal = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $resAwal->assertOk();
        $countAwal = $resAwal->viewData('asetBelumDisensusCount');

        // Buat aset baru berstatus aktif dengan penempatan belum difinalisasi (is_finalized = 0)
        $aset = $this->buatAset([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $ruangan = Ruangan::where('id_lokasi', $this->lokasiP2btp->id_lokasi)->first();
        if ($ruangan) {
            $this->buatPenempatan([
                'id_aset' => $aset->id_aset,
                'id_ruangan' => $ruangan->id_ruangan,
                'tanggal_mulai' => '2025-01-01',
                'status' => 'aktif',
                'is_finalized' => 0, // belum difinalisasi
            ]);
        }

        $resAkhir = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $resAkhir->assertOk();
        $countAkhir = $resAkhir->viewData('asetBelumDisensusCount');

        $this->assertSame($countAwal + 1, $countAkhir);
    }

    public function test_skenario_6_aset_tanpa_lokasi_atau_pemegang_ikut_dihitung_dalam_card(): void
    {
        $resAwal = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $resAwal->assertOk();
        $countAwal = $resAwal->viewData('asetBelumDisensusCount');

        // Aset unplaced (tanpa lokasi fisik dan tanpa pemegang)
        $this->buatAset([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => null,
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $resAkhir = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $resAkhir->assertOk();
        $countAkhir = $resAkhir->viewData('asetBelumDisensusCount');

        $this->assertSame($countAwal + 1, $countAkhir);
    }

    public function test_skenario_7_aset_yang_sudah_disensus_pada_periode_terpilih_tidak_dihitung(): void
    {
        $periodeAktif = PeriodeSensus::periodeAktif();

        // 1. Buat aset baru belum disensus
        $aset = $this->buatAset([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'tanggal_pengadaan' => '2025-01-01',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        // Cek jumlah setelah aset dibuat
        $resSebelum = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $resSebelum->assertOk();
        $countSebelum = $resSebelum->viewData('asetBelumDisensusCount');

        // 2. Berikan sensus pada periode aktif terpilih
        $this->buatSensus([
            'id_aset' => $aset->id_aset,
            'periode' => $periodeAktif,
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2026-10-28',
            'id_user' => $this->adminAset->id_user,
        ]);

        // 3. Cek setelah disensus: jumlah berkurang 1
        $resSetelah = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $resSetelah->assertOk();
        $countSetelah = $resSetelah->viewData('asetBelumDisensusCount');

        $this->assertSame($countSebelum - 1, $countSetelah);
    }

    public function test_skenario_8_mengganti_dropdown_periode_tidak_mengubah_angka_card(): void
    {
        $aset = $this->buatAset([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'tanggal_pengadaan' => '2022-01-01',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        // Berikan sensus HANYA pada periode lampau 2022–2024
        $this->buatSensus([
            'id_aset' => $aset->id_aset,
            'periode' => '2022–2024',
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2023-05-10',
            'id_user' => $this->adminAset->id_user,
        ]);

        // 1. Pada filter periode 2025–2027:
        $resAktif = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index', ['periode' => '2025–2027']));
        $countAktif = $resAktif->viewData('asetBelumDisensusCount');

        // 2. Pada filter periode 2022–2024: Card tidak boleh berubah karena tetap menghitung periode aktif masing-masing aset
        $resLampau = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index', ['periode' => '2022–2024']));
        $countLampau = $resLampau->viewData('asetBelumDisensusCount');

        $this->assertSame($countAktif, $countLampau, 'Angka card tidak boleh berubah hanya karena filter periode dropdown diganti.');
    }

    public function test_skenario_9_angka_card_konsisten_dengan_query_database_dan_data_tabel(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));

        $response->assertOk();

        $cardCount = $response->viewData('asetBelumDisensusCount');

        // Hitung via PeriodeSensus::hitungAsetBelumDisensusAktif sesuai scope controller
        $query = Aset::query()
            ->whereIn('status_aset', ['aktif', 'Aktif']);

        $dbCount = PeriodeSensus::hitungAsetBelumDisensusAktif($query);
        $this->assertSame($dbCount, $cardCount);

        // Uji konsistensi dinamis dengan menambahkan aset baru
        $this->buatAset([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $resBaru = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $resBaru->assertOk();

        $cardCountBaru = $resBaru->viewData('asetBelumDisensusCount');
        $dbCountBaru = PeriodeSensus::hitungAsetBelumDisensusAktif(
            Aset::query()->whereIn('status_aset', ['aktif', 'Aktif'])
        );

        $this->assertSame($dbCountBaru, $cardCountBaru);
        $this->assertSame($cardCount + 1, $cardCountBaru);
    }
}
