<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Lokasi;
use App\Models\MasterBarang;
use App\Models\Pegawai;
use App\Models\RiwayatPenghapusan;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use App\Services\AsetScope;
use App\Services\BuktiOtorisasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MembuatFotoBukti;
use Tests\TestCase;

class PenghapusanAsetTest extends TestCase
{
    use MembuatFotoBukti;

    private const PASSWORD = 'password123';

    private ?User $adminAset = null;
    private ?User $adminUptP2btp = null;
    private ?User $adminUptPsbp = null;
    private ?User $adminBidang = null;
    private ?User $pegawai = null;

    private ?Skpd $skpdDinas = null;
    private ?Skpd $skpdP2btp = null;
    private ?Skpd $skpdPsbp = null;
    private ?Skpd $skpdBidang = null;

    private ?Lokasi $lokasiDinas = null;
    private ?Lokasi $lokasiP2btp = null;
    private ?Lokasi $lokasiPsbp = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        $this->skpdDinas = Skpd::where('jenis_skpd', 'Sekretariat')->first()
            ?? Skpd::create(['nama_skpd' => 'Sekretariat Test', 'jenis_skpd' => 'Sekretariat']);
        $this->skpdP2btp = Skpd::where('jenis_skpd', 'UPT')->where('nama_skpd', 'like', '%P2BTP%')->first()
            ?? Skpd::create(['nama_skpd' => 'UPT P2BTP Test', 'jenis_skpd' => 'UPT']);
        $this->skpdPsbp = Skpd::where('jenis_skpd', 'UPT')->where('nama_skpd', 'like', '%PSBP%')->first()
            ?? Skpd::create(['nama_skpd' => 'UPT PSBP Test', 'jenis_skpd' => 'UPT']);
        $this->skpdBidang = Skpd::where('jenis_skpd', 'Bidang')->first()
            ?? Skpd::create(['nama_skpd' => 'Bidang Produksi Test', 'jenis_skpd' => 'Bidang']);

        $this->lokasiDinas = Lokasi::where('jenis_lokasi', 'Dinas')->first()
            ?? Lokasi::create(['nama_lokasi' => 'Kantor Dinas Test', 'jenis_lokasi' => 'Dinas', 'id_skpd' => $this->skpdDinas->id_skpd, 'status' => 'Aktif']);
        $this->lokasiP2btp = Lokasi::where('jenis_lokasi', 'UPT')->where('nama_lokasi', 'like', '%P2BTP%')->first()
            ?? Lokasi::create(['nama_lokasi' => 'Kantor UPT P2BTP Test', 'jenis_lokasi' => 'UPT', 'id_skpd' => $this->skpdP2btp->id_skpd, 'status' => 'Aktif']);
        $this->lokasiPsbp = Lokasi::where('jenis_lokasi', 'UPT')->where('nama_lokasi', 'like', '%PSBP%')->first()
            ?? Lokasi::create(['nama_lokasi' => 'Kantor UPT PSBP Test', 'jenis_lokasi' => 'UPT', 'id_skpd' => $this->skpdPsbp->id_skpd, 'status' => 'Aktif']);

        $this->adminAset = $this->buatUserDenganRole('Admin Aset', $this->skpdDinas);
        $this->adminUptP2btp = $this->buatUserDenganRole('Admin UPT', $this->skpdP2btp);
        $this->adminUptPsbp = $this->buatUserDenganRole('Admin UPT', $this->skpdPsbp);
        $this->adminBidang = $this->buatUserDenganRole('Admin Bidang', $this->skpdBidang);
        $this->pegawai = $this->buatUserDenganRole('Pegawai', $this->skpdDinas);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
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

    private function loginSebagai(User $user): void
    {
        app()->forgetInstance(AsetScope::class);
        $this->actingAs($user);
    }

    private function ambilRole(string $namaRole): Role
    {
        return Role::firstOrCreate(
            ['nama_role' => $namaRole],
            ['deskripsi' => 'Role ' . $namaRole]
        );
    }

    private function buatUserDenganRole(string $namaRole, Skpd $skpd, ?Ruangan $ruangan = null): User
    {
        $role = $this->ambilRole($namaRole);

        $pegawai = Pegawai::create([
            'nama_pegawai' => $namaRole . ' ' . $skpd->id_skpd . ' ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan?->id_ruangan,
            'jabatan' => 'Staf ' . $namaRole,
        ]);

        return User::create([
            'username' => 'test_' . strtolower(str_replace(' ', '_', $namaRole)) . '_' . uniqid(),
            'password' => bcrypt(self::PASSWORD),
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $role->id_role,
            'status_user' => 'aktif',
        ]);
    }

    private function buatAset(string $status = 'aktif', string $kondisi = 'Baik'): Aset
    {
        return $this->buatAsetDenganLokasi($this->skpdDinas, $this->lokasiDinas, $status, $kondisi);
    }

    private function buatAsetDenganLokasi(
        Skpd $skpd,
        Lokasi $lokasi,
        string $status = 'aktif',
        string $kondisi = 'Baik',
        ?string $namaBarang = null
    ): Aset {
        $barang = MasterBarang::first() ?? MasterBarang::create([
            'kode_barang' => '02.03.01.TEST.' . uniqid(),
            'nama_barang' => $namaBarang ?? ('Laptop Lenovo Test ' . uniqid()),
        ]);

        return Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'NKB-' . uniqid(),
            'merk' => 'Lenovo',
            'tanggal_pengadaan' => '2024-01-15',
            'nilai_perolehan' => 15000000,
            'kondisi' => $kondisi,
            'status_aset' => $status,
            'is_kendaraan' => false,
        ]);
    }

    private function buatUsulan(Aset $aset, ?User $pembuat = null, string $alasan = 'Rusak Berat'): UsulanPenghapusan
    {
        return UsulanPenghapusan::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai_penghapus' => $pembuat?->pegawai?->id_pegawai ?? $pembuat?->id_pegawai,
            'tanggal_usulan' => now()->toDateString(),
            'alasan_penghapusan' => $alasan,
            'status_usulan' => 'diajukan',
        ]);
    }

    // =========================================================================
    // 17 SKENARIO PENGUJIAN WAJIB TAHAP 1
    // =========================================================================

    /** 1. Admin Aset dapat melihat antrean global */
    public function test_1_admin_aset_dapat_melihat_antrean_global(): void
    {
        $asetP2btp = $this->buatAsetDenganLokasi($this->skpdP2btp, $this->lokasiP2btp, 'diusulkan_hapus');
        $asetPsbp = $this->buatAsetDenganLokasi($this->skpdPsbp, $this->lokasiPsbp, 'diusulkan_hapus');
        $asetDinas = $this->buatAsetDenganLokasi($this->skpdDinas, $this->lokasiDinas, 'diusulkan_hapus');

        $this->buatUsulan($asetP2btp, $this->adminUptP2btp);
        $this->buatUsulan($asetPsbp, $this->adminUptPsbp);
        $this->buatUsulan($asetDinas, $this->adminAset);

        $this->loginSebagai($this->adminAset);
        $response = $this->get(route('penghapusan.index'));

        $response->assertOk();
        $response->assertSee($asetP2btp->nomor_kartu_barang);
        $response->assertSee($asetPsbp->nomor_kartu_barang);
        $response->assertSee($asetDinas->nomor_kartu_barang);
    }

    /** 2. Admin Aset dapat mengajukan aset dari berbagai unit */
    public function test_2_admin_aset_dapat_mengajukan_aset_dari_berbagai_unit(): void
    {
        $asetP2btp = $this->buatAsetDenganLokasi($this->skpdP2btp, $this->lokasiP2btp, 'aktif');
        $asetPsbp = $this->buatAsetDenganLokasi($this->skpdPsbp, $this->lokasiPsbp, 'aktif');

        $this->loginSebagai($this->adminAset);

        // Usulkan aset P2BTP
        $res1 = $this->post(route('penghapusan.store'), [
            'id_aset' => $asetP2btp->id_aset,
            'alasan_penghapusan' => 'Rusak Berat',
        ]);
        $res1->assertRedirect();
        $res1->assertSessionHas('success');
        $this->assertSame('diusulkan_hapus', $asetP2btp->fresh()->status_aset);

        // Usulkan aset PSBP
        $res2 = $this->post(route('penghapusan.store'), [
            'id_aset' => $asetPsbp->id_aset,
            'alasan_penghapusan' => 'Rusak Berat',
        ]);
        $res2->assertRedirect();
        $res2->assertSessionHas('success');
        $this->assertSame('diusulkan_hapus', $asetPsbp->fresh()->status_aset);
    }

    /** 3. Admin UPT P2BTP dapat melihat antrean P2BTP */
    public function test_3_admin_upt_p2btp_dapat_melihat_antrean_p2btp(): void
    {
        $asetP2btp = $this->buatAsetDenganLokasi($this->skpdP2btp, $this->lokasiP2btp, 'diusulkan_hapus');
        $this->buatUsulan($asetP2btp, $this->adminUptP2btp);

        $this->loginSebagai($this->adminUptP2btp);
        $response = $this->get(route('penghapusan.index'));

        $response->assertOk();
        $response->assertSee($asetP2btp->nomor_kartu_barang);
    }

    /** 4. Admin UPT PSBP dapat melihat antrean PSBP */
    public function test_4_admin_upt_psbp_dapat_melihat_antrean_psbp(): void
    {
        $asetPsbp = $this->buatAsetDenganLokasi($this->skpdPsbp, $this->lokasiPsbp, 'diusulkan_hapus');
        $this->buatUsulan($asetPsbp, $this->adminUptPsbp);

        $this->loginSebagai($this->adminUptPsbp);
        $response = $this->get(route('penghapusan.index'));

        $response->assertOk();
        $response->assertSee($asetPsbp->nomor_kartu_barang);
    }

    /** 5. P2BTP tidak dapat melihat antrean PSBP */
    public function test_5_p2btp_tidak_dapat_melihat_antrean_psbp(): void
    {
        $asetPsbp = $this->buatAsetDenganLokasi($this->skpdPsbp, $this->lokasiPsbp, 'diusulkan_hapus');
        $this->buatUsulan($asetPsbp, $this->adminUptPsbp);

        $this->loginSebagai($this->adminUptP2btp);
        $response = $this->get(route('penghapusan.index'));

        $response->assertOk();
        $response->assertDontSee($asetPsbp->nomor_kartu_barang);
    }

    /** 6. PSBP tidak dapat melihat antrean P2BTP */
    public function test_6_psbp_tidak_dapat_melihat_antrean_p2btp(): void
    {
        $asetP2btp = $this->buatAsetDenganLokasi($this->skpdP2btp, $this->lokasiP2btp, 'diusulkan_hapus');
        $this->buatUsulan($asetP2btp, $this->adminUptP2btp);

        $this->loginSebagai($this->adminUptPsbp);
        $response = $this->get(route('penghapusan.index'));

        $response->assertOk();
        $response->assertDontSee($asetP2btp->nomor_kartu_barang);
    }

    /** 7. Admin UPT tidak dapat melihat data Dinas di luar scope */
    public function test_7_admin_upt_tidak_dapat_melihat_data_dinas_di_luar_scope(): void
    {
        $asetDinas = $this->buatAsetDenganLokasi($this->skpdDinas, $this->lokasiDinas, 'diusulkan_hapus');
        $this->buatUsulan($asetDinas, $this->adminAset);

        // UPT P2BTP
        $this->loginSebagai($this->adminUptP2btp);
        $resP2btp = $this->get(route('penghapusan.index'));
        $resP2btp->assertOk();
        $resP2btp->assertDontSee($asetDinas->nomor_kartu_barang);

        // UPT PSBP
        $this->loginSebagai($this->adminUptPsbp);
        $resPsbp = $this->get(route('penghapusan.index'));
        $resPsbp->assertOk();
        $resPsbp->assertDontSee($asetDinas->nomor_kartu_barang);
    }

    /** 8. Admin UPT P2BTP tidak dapat mengajukan aset PSBP */
    public function test_8_admin_upt_p2btp_tidak_dapat_mengajukan_aset_psbp(): void
    {
        $asetPsbp = $this->buatAsetDenganLokasi($this->skpdPsbp, $this->lokasiPsbp, 'aktif');

        $this->loginSebagai($this->adminUptP2btp);
        $response = $this->post(route('penghapusan.store'), [
            'id_aset' => $asetPsbp->id_aset,
            'alasan_penghapusan' => 'Rusak Berat',
        ]);

        $response->assertForbidden();
        $this->assertSame('aktif', $asetPsbp->fresh()->status_aset);
    }

    /** 9. Admin UPT PSBP tidak dapat mengajukan aset P2BTP */
    public function test_9_admin_upt_psbp_tidak_dapat_mengajukan_aset_p2btp(): void
    {
        $asetP2btp = $this->buatAsetDenganLokasi($this->skpdP2btp, $this->lokasiP2btp, 'aktif');

        $this->loginSebagai($this->adminUptPsbp);
        $response = $this->post(route('penghapusan.store'), [
            'id_aset' => $asetP2btp->id_aset,
            'alasan_penghapusan' => 'Rusak Berat',
        ]);

        $response->assertForbidden();
        $this->assertSame('aktif', $asetP2btp->fresh()->status_aset);
    }

    /** 10. Admin UPT tidak dapat mengajukan aset Dinas di luar scope */
    public function test_10_admin_upt_tidak_dapat_mengajukan_aset_dinas_di_luar_scope(): void
    {
        $asetDinas = $this->buatAsetDenganLokasi($this->skpdDinas, $this->lokasiDinas, 'aktif');

        $this->loginSebagai($this->adminUptP2btp);
        $responseP2btp = $this->post(route('penghapusan.store'), [
            'id_aset' => $asetDinas->id_aset,
            'alasan_penghapusan' => 'Rusak Berat',
        ]);
        $responseP2btp->assertForbidden();

        $this->loginSebagai($this->adminUptPsbp);
        $responsePsbp = $this->post(route('penghapusan.store'), [
            'id_aset' => $asetDinas->id_aset,
            'alasan_penghapusan' => 'Rusak Berat',
        ]);
        $responsePsbp->assertForbidden();

        $this->assertSame('aktif', $asetDinas->fresh()->status_aset);
    }

    /** 11. Admin UPT dapat mengajukan aset dalam scope sendiri */
    public function test_11_admin_upt_dapat_mengajukan_aset_dalam_scope_sendiri(): void
    {
        $asetP2btp = $this->buatAsetDenganLokasi($this->skpdP2btp, $this->lokasiP2btp, 'aktif');

        $this->loginSebagai($this->adminUptP2btp);
        $response = $this->post(route('penghapusan.store'), [
            'id_aset' => $asetP2btp->id_aset,
            'alasan_penghapusan' => 'Rusak Berat',
            'keterangan' => 'Kerusakan fisik parah di kebun P2BTP.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $asetP2btp->refresh();
        $this->assertSame('diusulkan_hapus', $asetP2btp->status_aset);
        $this->assertSame('Rusak Berat', $asetP2btp->kondisi);

        $this->assertDatabaseHas('usulan_penghapusan', [
            'id_aset' => $asetP2btp->id_aset,
            'status_usulan' => 'diajukan',
            'alasan_penghapusan' => 'Rusak Berat',
        ]);
    }

    /** 12. Admin Bidang tidak dapat mengajukan usulan */
    public function test_12_admin_bidang_tidak_dapat_mengajukan(): void
    {
        $asetDinas = $this->buatAsetDenganLokasi($this->skpdBidang, $this->lokasiDinas, 'aktif');

        $this->loginSebagai($this->adminBidang);
        $response = $this->post(route('penghapusan.store'), [
            'id_aset' => $asetDinas->id_aset,
            'alasan_penghapusan' => 'Rusak Berat',
        ]);

        $response->assertForbidden();
        $this->assertSame('aktif', $asetDinas->fresh()->status_aset);
    }

    /** 13. Admin Bidang tidak dapat membuka antrean dan riwayat */
    public function test_13_admin_bidang_tidak_dapat_membuka_antrean_dan_riwayat(): void
    {
        $this->loginSebagai($this->adminBidang);

        $resIndex = $this->get(route('penghapusan.index'));
        $resIndex->assertForbidden();

        $resRiwayat = $this->get(route('penghapusan.riwayat'));
        $resRiwayat->assertForbidden();
    }

    /** 14. Pegawai tidak dapat mengakses modul penghapusan */
    public function test_14_pegawai_tidak_dapat_mengakses_modul(): void
    {
        $this->loginSebagai($this->pegawai);

        $resIndex = $this->get(route('penghapusan.index'));
        $resIndex->assertForbidden();

        $resStore = $this->post(route('penghapusan.store'), [
            'id_aset' => 1,
            'alasan_penghapusan' => 'Rusak Berat',
        ]);
        $resStore->assertForbidden();

        $resRiwayat = $this->get(route('penghapusan.riwayat'));
        $resRiwayat->assertForbidden();
    }

    /** 15. Manipulasi ID aset tidak dapat melewati scope backend */
    public function test_15_manipulasi_id_aset_tidak_dapat_melewati_scope_backend(): void
    {
        $asetAsing = $this->buatAsetDenganLokasi($this->skpdPsbp, $this->lokasiPsbp, 'aktif');

        // Admin UPT P2BTP mencoba membypass dengan menembak ID aset PSBP langsung via POST
        $this->loginSebagai($this->adminUptP2btp);
        $response = $this->post(route('penghapusan.store'), [
            'id_aset' => $asetAsing->id_aset,
            'alasan_penghapusan' => 'Masa Pakai Habis',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('usulan_penghapusan', [
            'id_aset' => $asetAsing->id_aset,
            'alasan_penghapusan' => 'Masa Pakai Habis',
        ]);
        $this->assertSame('aktif', $asetAsing->fresh()->status_aset);
    }

    /** 16. Pagination dan pencarian tetap menghormati scope */
    public function test_16_pagination_dan_pencarian_tetap_menghormati_scope(): void
    {
        // Buat aset di PSBP dengan nama unik
        $asetPsbp = $this->buatAsetDenganLokasi(
            $this->skpdPsbp,
            $this->lokasiPsbp,
            'diusulkan_hapus',
            'Rusak Berat',
            'Barang Rahasia Milik PSBP'
        );
        $this->buatUsulan($asetPsbp, $this->adminUptPsbp);

        // Admin UPT P2BTP mencari kata 'Rahasia'
        $this->loginSebagai($this->adminUptP2btp);
        $response = $this->get(route('penghapusan.index', ['search' => 'Rahasia']));

        $response->assertOk();
        $response->assertDontSee('Barang Rahasia Milik PSBP');
        $this->assertEquals(0, $response->viewData('usulanList')->total());
    }

    /** 17. Eksekusi penghapusan permanen tetap hanya untuk Admin Aset */
    public function test_17_eksekusi_penghapusan_permanen_tetap_hanya_untuk_admin_aset(): void
    {
        $aset = $this->buatAsetDenganLokasi($this->skpdP2btp, $this->lokasiP2btp, 'diusulkan_hapus');
        $usulan = $this->buatUsulan($aset, $this->adminUptP2btp);

        $payload = [
            'no_sk' => '030/SK/2026',
            'tanggal_sk' => now()->toDateString(),
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ];

        // Admin UPT P2BTP ditolak
        $this->loginSebagai($this->adminUptP2btp);
        $this->delete(route('penghapusan.eksekusi', $usulan->id_usulan_hapus), $payload)->assertForbidden();

        // Admin UPT PSBP ditolak
        $this->loginSebagai($this->adminUptPsbp);
        $this->delete(route('penghapusan.eksekusi', $usulan->id_usulan_hapus), $payload)->assertForbidden();

        // Admin Bidang ditolak
        $this->loginSebagai($this->adminBidang);
        $this->delete(route('penghapusan.eksekusi', $usulan->id_usulan_hapus), $payload)->assertForbidden();

        // Pegawai ditolak
        $this->loginSebagai($this->pegawai);
        $this->delete(route('penghapusan.eksekusi', $usulan->id_usulan_hapus), $payload)->assertForbidden();
    }

    // =========================================================================
    // REGRESI PENGUJIAN OTORISASI SK & FOTO (KHUSUS ADMIN ASET)
    // =========================================================================

    /** Hapus permanen tanpa Nomor SK ditolak */
    public function test_hapus_permanen_tanpa_nomor_sk_ditolak(): void
    {
        $aset = $this->buatAset('diusulkan_hapus', 'Rusak Berat');
        $usulan = $this->buatUsulan($aset, $this->adminAset);

        $this->loginSebagai($this->adminAset);
        $response = $this->delete(route('penghapusan.eksekusi', $usulan->id_usulan_hapus), [
            'no_sk' => '',
            'tanggal_sk' => now()->toDateString(),
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertSessionHasErrors('no_sk');
        $this->assertSame('diusulkan_hapus', $aset->fresh()->status_aset);
    }

    /** Hapus permanen tanpa Tanggal SK ditolak */
    public function test_hapus_permanen_tanpa_tanggal_sk_ditolak(): void
    {
        $aset = $this->buatAset('diusulkan_hapus', 'Rusak Berat');
        $usulan = $this->buatUsulan($aset, $this->adminAset);

        $this->loginSebagai($this->adminAset);
        $response = $this->delete(route('penghapusan.eksekusi', $usulan->id_usulan_hapus), [
            'no_sk' => '030/SK-HAPUS/2026',
            'tanggal_sk' => '',
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertSessionHasErrors('tanggal_sk');
        $this->assertSame('diusulkan_hapus', $aset->fresh()->status_aset);
    }

    /** Tanggal SK di masa depan ditolak */
    public function test_hapus_permanen_dengan_tanggal_sk_di_masa_depan_ditolak(): void
    {
        $aset = $this->buatAset('diusulkan_hapus', 'Rusak Berat');
        $usulan = $this->buatUsulan($aset, $this->adminAset);

        $futureDate = now()->addDays(5)->toDateString();

        $this->loginSebagai($this->adminAset);
        $response = $this->delete(route('penghapusan.eksekusi', $usulan->id_usulan_hapus), [
            'no_sk' => '030/SK-HAPUS/2026',
            'tanggal_sk' => $futureDate,
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertSessionHasErrors('tanggal_sk');
        $this->assertSame('diusulkan_hapus', $aset->fresh()->status_aset);
    }

    /** Password otorisasi salah ditolak */
    public function test_hapus_permanen_dengan_password_salah_ditolak(): void
    {
        $aset = $this->buatAset('diusulkan_hapus', 'Rusak Berat');
        $usulan = $this->buatUsulan($aset, $this->adminAset);

        $this->loginSebagai($this->adminAset);
        $response = $this->delete(route('penghapusan.eksekusi', $usulan->id_usulan_hapus), [
            'no_sk' => '030/SK-HAPUS/2026',
            'tanggal_sk' => now()->toDateString(),
            'password_otorisasi' => 'password-yang-salah',
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertSessionHasErrors('password_otorisasi');
        $this->assertSame('diusulkan_hapus', $aset->fresh()->status_aset);
    }

    /** Foto otorisasi tidak valid ditolak */
    public function test_hapus_permanen_dengan_foto_tidak_valid_ditolak(): void
    {
        $aset = $this->buatAset('diusulkan_hapus', 'Rusak Berat');
        $usulan = $this->buatUsulan($aset, $this->adminAset);

        $this->loginSebagai($this->adminAset);
        $response = $this->delete(route('penghapusan.eksekusi', $usulan->id_usulan_hapus), [
            'no_sk' => '030/SK-HAPUS/2026',
            'tanggal_sk' => now()->toDateString(),
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => 'bukan-data-url-foto',
        ]);

        $response->assertSessionHasErrors('foto_bukti_otentikasi');
        $this->assertSame('diusulkan_hapus', $aset->fresh()->status_aset);
    }

    /** Eksekusi ganda pada usulan yang sudah disetujui dicegah */
    public function test_eksekusi_usulan_yang_sudah_disetujui_dicegah(): void
    {
        $aset = $this->buatAset('dihapuskan');

        $usulan = UsulanPenghapusan::create([
            'id_aset' => $aset->id_aset,
            'tanggal_usulan' => now()->toDateString(),
            'alasan_penghapusan' => 'Rusak Berat',
            'status_usulan' => 'disetujui',
            'no_sk' => '030/SK-HAPUS/2026',
            'tanggal_sk' => now()->toDateString(),
        ]);

        $this->loginSebagai($this->adminAset);
        $response = $this->delete(route('penghapusan.eksekusi', $usulan->id_usulan_hapus), [
            'no_sk' => '030/SK-HAPUS/2026',
            'tanggal_sk' => now()->toDateString(),
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /** Model RiwayatPenghapusan memformat nomor SK, tanggal SK, dan waktu eksekusi secara tepat */
    public function test_model_riwayat_penghapusan_format_accessor_sk(): void
    {
        $waktuEksekusi = Carbon::parse('2026-11-02 14:30:00');

        $riwayat = new RiwayatPenghapusan([
            'id_aset' => 999,
            'no_sk' => '030/SK-HAPUS/2026',
            'tanggal_sk' => '2026-01-15',
            'nama_pelaksana' => 'Admin Aset',
            'waktu_penghapusan' => $waktuEksekusi,
            'alasan_penghapusan' => 'Rusak Berat',
            'foto_bukti' => 'bukti-otorisasi/test.jpg',
            'snapshot_aset' => [
                'id_aset' => 999,
                'nomor_kartu_barang' => 'NKB-UNIT-01',
                'nama_aset' => 'Mobil Toyota Hilux',
                'is_kendaraan' => true,
                'nilai_perolehan' => 350000000,
                'no_sk' => '030/SK-HAPUS/2026',
                'tanggal_sk' => '2026-01-15',
            ],
        ]);

        $this->assertSame('030/SK-HAPUS/2026', $riwayat->no_sk_tampil);
        $this->assertSame('15 Jan 2026', $riwayat->tanggal_sk_tampil);
        $this->assertSame('Mobil Toyota Hilux', $riwayat->nama_aset_tampil);
        $this->assertSame('NKB-UNIT-01', $riwayat->nomor_kartu_barang_tampil);
        $this->assertSame('Kendaraan', $riwayat->jenis_aset_tampil);
        $this->assertSame('Rp 350.000.000', $riwayat->nilai_perolehan_tampil);
        $this->assertSame('2026-11-02 14:30:00', $riwayat->waktu_penghapusan->format('Y-m-d H:i:s'));
    }
}
