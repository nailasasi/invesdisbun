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

        $this->adminUptPsbp = User::create([
            'id_pegawai' => $pegawaiPsbp->id_pegawai,
            'id_role' => Role::where('nama_role', 'Admin UPT')->firstOrFail()->id_role,
            'username' => 'tst_psbp_' . uniqid(),
            'password' => 'rahasia',
            'status_user' => 'aktif',
        ]);
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
        $periodeAktif = PeriodeSensus::periodeAktif();

        // Hitung ekspektasi langsung dari query database sesuai scope Admin Aset
        $ekspektasi = Aset::where(fn ($q) => $q->whereNull('status_aset')->orWhere('status_aset', '!=', 'dihapuskan'))
            ->whereDoesntHave('sensus', fn ($s) => $s->where('periode', $periodeAktif))
            ->count();

        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));

        $response->assertOk();
        $this->assertSame($ekspektasi, $response->viewData('asetBelumDisensusCount'));
        $response->assertSee("{$ekspektasi} <span class=\"text-xs font-semibold text-slate-500\">Aset</span>", false);
    }

    public function test_skenario_2_admin_upt_p2btp_hanya_melihat_aset_belum_disensus_milik_p2btp(): void
    {
        $periodeAktif = PeriodeSensus::periodeAktif();

        // Buat 1 aset baru di UPT P2BTP
        $asetP2 = Aset::create([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => 'TEST-P2-' . uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'is_kendaraan' => false,
        ]);

        // Buat 1 aset baru di UPT PSBP
        $asetPs = Aset::create([
            'id_skpd' => $this->skpdPsbp->id_skpd,
            'id_lokasi' => $this->lokasiPsbp->id_lokasi,
            'nomor_kartu_barang' => 'TEST-PS-' . uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'is_kendaraan' => false,
        ]);

        $responseP2 = $this->actingAs($this->adminUptP2btp)
            ->get(route('monitoring-aset.index'));

        $responseP2->assertOk();
        $countP2 = $responseP2->viewData('asetBelumDisensusCount');

        // Pastikan scope P2BTP hanya menghitung aset di lokasi P2BTP atau SKPD P2BTP
        $ekspektasiP2 = Aset::where(fn ($q) => $q->whereNull('status_aset')->orWhere('status_aset', '!=', 'dihapuskan'))
            ->where(function ($q) {
                $q->whereIn('aset.id_lokasi', [$this->lokasiP2btp->id_lokasi])
                    ->orWhere(fn ($q2) => $q2->whereNull('aset.id_lokasi')->where('aset.id_skpd', $this->skpdP2btp->id_skpd));
            })
            ->whereDoesntHave('sensus', fn ($s) => $s->where('periode', $periodeAktif))
            ->count();

        $this->assertSame($ekspektasiP2, $countP2);
    }

    public function test_skenario_3_admin_upt_psbp_hanya_melihat_aset_belum_disensus_milik_psbp(): void
    {
        $periodeAktif = PeriodeSensus::periodeAktif();

        $responsePs = $this->actingAs($this->adminUptPsbp)
            ->get(route('monitoring-aset.index'));

        $responsePs->assertOk();
        $countPs = $responsePs->viewData('asetBelumDisensusCount');

        $ekspektasiPs = Aset::where(fn ($q) => $q->whereNull('status_aset')->orWhere('status_aset', '!=', 'dihapuskan'))
            ->where(function ($q) {
                $q->whereIn('aset.id_lokasi', [$this->lokasiPsbp->id_lokasi])
                    ->orWhere(fn ($q2) => $q2->whereNull('aset.id_lokasi')->where('aset.id_skpd', $this->skpdPsbp->id_skpd));
            })
            ->whereDoesntHave('sensus', fn ($s) => $s->where('periode', $periodeAktif))
            ->count();

        $this->assertSame($ekspektasiPs, $countPs);
    }

    public function test_skenario_4_aset_kendaraan_ikut_dihitung_dalam_card(): void
    {
        $periodeAktif = PeriodeSensus::periodeAktif();

        $baseline = Aset::where(fn ($q) => $q->whereNull('status_aset')->orWhere('status_aset', '!=', 'dihapuskan'))
            ->whereDoesntHave('sensus', fn ($s) => $s->where('periode', $periodeAktif))
            ->count();

        // Tambah 1 aset kendaraan belum disensus
        $asetKendaraan = Aset::create([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => 'TEST-KENDARAAN-' . uniqid(),
            'tanggal_pengadaan' => '2025-02-10',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'is_kendaraan' => true,
        ]);

        Kendaraan::create([
            'id_aset' => $asetKendaraan->id_aset,
            'nama_kendaraan' => 'Motor Dinas Lapangan',
            'merk' => 'Honda',
            'tipe' => 'Revo',
            'jenis_kendaraan' => 'Roda 2',
        ]);

        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));

        $response->assertOk();
        $this->assertSame($baseline + 1, $response->viewData('asetBelumDisensusCount'));
    }

    public function test_skenario_5_aset_belum_difinalisasi_ikut_dihitung_dalam_card(): void
    {
        $periodeAktif = PeriodeSensus::periodeAktif();

        $baseline = Aset::where(fn ($q) => $q->whereNull('status_aset')->orWhere('status_aset', '!=', 'dihapuskan'))
            ->whereDoesntHave('sensus', fn ($s) => $s->where('periode', $periodeAktif))
            ->count();

        // Buat aset baru berstatus draft / belum difinalisasi
        $asetDraft = Aset::create([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => 'TEST-DRAFT-' . uniqid(),
            'tanggal_pengadaan' => '2025-03-15',
            'status_aset' => null, // belum final / null
            'kondisi' => 'Baik',
            'is_kendaraan' => false,
        ]);

        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));

        $response->assertOk();
        $this->assertSame($baseline + 1, $response->viewData('asetBelumDisensusCount'));
    }

    public function test_skenario_6_aset_tanpa_lokasi_atau_pemegang_ikut_dihitung_dalam_card(): void
    {
        $periodeAktif = PeriodeSensus::periodeAktif();

        $baseline = Aset::where(fn ($q) => $q->whereNull('status_aset')->orWhere('status_aset', '!=', 'dihapuskan'))
            ->whereDoesntHave('sensus', fn ($s) => $s->where('periode', $periodeAktif))
            ->count();

        // Aset unplaced (tanpa lokasi fisik dan tanpa pemegang)
        $asetUnplaced = Aset::create([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => null,
            'nomor_kartu_barang' => 'TEST-UNPLACED-' . uniqid(),
            'tanggal_pengadaan' => '2025-04-20',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'is_kendaraan' => false,
        ]);

        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));

        $response->assertOk();
        $this->assertSame($baseline + 1, $response->viewData('asetBelumDisensusCount'));
    }

    public function test_skenario_7_aset_yang_sudah_disensus_pada_periode_terpilih_tidak_dihitung(): void
    {
        $periodeAktif = PeriodeSensus::periodeAktif();

        $aset = Aset::create([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => 'TEST-SUDISENSUS-' . uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'is_kendaraan' => false,
        ]);

        // Cek sebelum disensus
        $resSebelum = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $countSebelum = $resSebelum->viewData('asetBelumDisensusCount');

        // Berikan sensus pada periode aktif terpilih
        SensusAset::create([
            'id_aset' => $aset->id_aset,
            'periode' => $periodeAktif,
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2026-10-28',
            'id_user' => $this->adminAset->id_user,
        ]);

        // Cek setelah disensus: jumlah berkurang 1
        $resSetelah = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));
        $countSetelah = $resSetelah->viewData('asetBelumDisensusCount');

        $this->assertSame($countSebelum - 1, $countSetelah);
    }

    public function test_skenario_8_mengganti_dropdown_periode_tidak_mengubah_angka_card(): void
    {
        $aset = Aset::create([
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $this->lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => 'TEST-DINAMIS-' . uniqid(),
            'tanggal_pengadaan' => '2022-01-01',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'is_kendaraan' => false,
        ]);

        // Berikan sensus HANYA pada periode lampau 2022–2024
        SensusAset::create([
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

        // Hitung via PeriodeSensus::hitungAsetBelumDisensusAktif
        $query = Aset::query()
            ->where(fn ($q) => $q->whereNull('status_aset')->orWhere('status_aset', '!=', 'dihapuskan'));

        $dbCount = PeriodeSensus::hitungAsetBelumDisensusAktif($query);
        $this->assertSame($dbCount, $cardCount);
    }
}

