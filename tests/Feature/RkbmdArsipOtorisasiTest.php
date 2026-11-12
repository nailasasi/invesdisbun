<?php

namespace Tests\Feature;

use App\Http\Controllers\RkbmdController;
use App\Models\ArsipRkbmd;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Keamanan akses arsip RKBMD: pratinjau/unduh harus melewati otorisasi
 * role + cakupan SKPD di backend, bukan sekadar menyembunyikan tautan.
 *
 * - Admin Aset        -> semua arsip lintas unit.
 * - Admin Bidang      -> HANYA arsip SKPD-nya sendiri (id_skpd sama).
 * - Admin UPT P2BTP   -> HANYA arsip SKPD UPT P2BTP (miliknya).
 * - Admin UPT PSBP    -> HANYA arsip SKPD UPT PSBP (miliknya).
 * - Tanpa relasi pegawai/SKPD valid -> ditolak (fail closed).
 *
 * Mengikuti pola MutasiRuanganTest: memakai koneksi MySQL nyata (schema
 * produksi) di dalam transaksi yang di-rollback, sehingga tidak ada perubahan
 * permanen dan tidak bergantung pada schema `:memory:` sqlite yang rusak pada
 * migration template_dokumen. File fixture dibuat di disk fake dan otomatis
 * dibersihkan.
 */
class RkbmdArsipOtorisasiTest extends TestCase
{
    private const PASSWORD = 'rahasia123';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        Storage::fake(RkbmdController::DISK_ARSIP);
        Storage::fake(RkbmdController::DISK_ARSIP_LAMA);
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

    private function buatRole(string $nama): Role
    {
        return Role::firstOrCreate(['nama_role' => $nama]);
    }

    private function buatSkpd(string $nama, string $jenis): Skpd
    {
        return Skpd::firstOrCreate(['nama_skpd' => $nama], ['jenis_skpd' => $jenis]);
    }

    /**
     * User dengan role + penempatan SKPD tertentu (null = tanpa pegawai/SKPD).
     */
    private function buatUser(string $roleNama, ?Skpd $skpd, string $username): User
    {
        $role = $this->buatRole($roleNama);

        $pegawai = Pegawai::create([
            'nip' => '9'.random_int(10000000, 99999999),
            'nama_pegawai' => 'User '.$username,
            'jabatan' => 'Pegawai',
            'id_skpd' => $skpd?->id_skpd,
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $role->id_role,
            'username' => $username,
            'password' => self::PASSWORD,
            'status_user' => 'aktif',
        ]);
    }

    /**
     * Rekam arsip + letakkan file PDF di kedua disk bila path diberikan.
     */
    private function buatArsip(Skpd $skpd, ?string $path = null): ArsipRkbmd
    {
        $arsip = ArsipRkbmd::create([
            'id_skpd' => $skpd->id_skpd,
            'tahun_anggaran' => 2026,
            'nama_dokumen' => 'SK Pengesahan '.$skpd->nama_skpd,
            'file_path' => $path ?: 'arsip-rkbmd/berkas-'.$skpd->id_skpd.'.pdf',
        ]);

        if ($path !== null) {
            foreach ([RkbmdController::DISK_ARSIP, RkbmdController::DISK_ARSIP_LAMA] as $disk) {
                Storage::disk($disk)->put($path, '%PDF-1.4 fake isi arsip');
            }
        }

        return $arsip;
    }

    // ----------------------------------------------------------------------
    // AKSES DIIZINKAN
    // ----------------------------------------------------------------------

    public function test_admin_aset_dapat_mengakses_arsip_dari_beberapa_skpd(): void
    {
        $skpdA = $this->buatSkpd('Bidang A', 'Bidang');
        $skpdB = $this->buatSkpd('UPT P2BTP', 'UPT');
        $arsipA = $this->buatArsip($skpdA, 'arsip-rkbmd/a.pdf');
        $arsipB = $this->buatArsip($skpdB, 'arsip-rkbmd/b.pdf');

        $admin = $this->buatUser('Admin Aset', null, 'admin_aset');

        $this->actingAs($admin);

        $this->get(route('rkbmd.arsip.preview', $arsipA))->assertOk();
        $this->get(route('rkbmd.arsip.download', $arsipA))->assertOk();
        $this->get(route('rkbmd.arsip.preview', $arsipB))->assertOk();
        $this->get(route('rkbmd.arsip.download', $arsipB))->assertOk();
    }

    public function test_admin_bidang_dapat_mengakses_arsip_skpd_nya_sendiri(): void
    {
        $skpd = $this->buatSkpd('Bidang Produksi Tanaman Tahunan', 'Bidang');
        $arsip = $this->buatArsip($skpd, 'arsip-rkbmd/bidang.pdf');

        $user = $this->buatUser('Admin Bidang', $skpd, 'admin_bidang_tt');

        $this->actingAs($user);

        $this->get(route('rkbmd.arsip.preview', $arsip))->assertOk();
        $this->get(route('rkbmd.arsip.download', $arsip))->assertOk();
    }

    // ----------------------------------------------------------------------
    // AKSES DITOLAK (lintas unit)
    // ----------------------------------------------------------------------

    public function test_admin_bidang_ditolak_saat_mengakses_arsip_bidang_lain(): void
    {
        $skpdSaya = $this->buatSkpd('Bidang Produksi Tanaman Tahunan', 'Bidang');
        $skpdLain = $this->buatSkpd('Bidang Perlindungan Perkebunan', 'Bidang');
        $arsipLain = $this->buatArsip($skpdLain, 'arsip-rkbmd/bidang-lain.pdf');

        $user = $this->buatUser('Admin Bidang', $skpdSaya, 'admin_bidang');

        $this->actingAs($user);

        $this->get(route('rkbmd.arsip.preview', $arsipLain))->assertForbidden();
        $this->get(route('rkbmd.arsip.download', $arsipLain))->assertForbidden();
    }

    public function test_admin_upt_p2btp_ditolak_saat_mengakses_arsip_upt_psbp(): void
    {
        $skpdP2btp = $this->buatSkpd('UPT P2BTP', 'UPT');
        $skpdPsbp = $this->buatSkpd('UPT PSBP', 'UPT');
        $arsipPsbp = $this->buatArsip($skpdPsbp, 'arsip-rkbmd/psbp.pdf');

        $user = $this->buatUser('Admin UPT', $skpdP2btp, 'admin_p2btp');

        $this->actingAs($user);

        $this->get(route('rkbmd.arsip.preview', $arsipPsbp))->assertForbidden();
        $this->get(route('rkbmd.arsip.download', $arsipPsbp))->assertForbidden();
    }

    public function test_admin_upt_psbp_ditolak_saat_mengakses_arsip_upt_p2btp(): void
    {
        $skpdPsbp = $this->buatSkpd('UPT PSBP', 'UPT');
        $skpdP2btp = $this->buatSkpd('UPT P2BTP', 'UPT');
        $arsipP2btp = $this->buatArsip($skpdP2btp, 'arsip-rkbmd/p2btp.pdf');

        $user = $this->buatUser('Admin UPT', $skpdPsbp, 'admin_psbp');

        $this->actingAs($user);

        $this->get(route('rkbmd.arsip.preview', $arsipP2btp))->assertForbidden();
        $this->get(route('rkbmd.arsip.download', $arsipP2btp))->assertForbidden();
    }

    public function test_admin_bidang_ditolak_saat_mengakses_arsip_upt(): void
    {
        $skpdBidang = $this->buatSkpd('Bidang Pengelolahan dan Pemasaran Hasil', 'Bidang');
        $skpdUpt = $this->buatSkpd('UPT P2BTP', 'UPT');
        $arsipUpt = $this->buatArsip($skpdUpt, 'arsip-rkbmd/upt.pdf');

        $user = $this->buatUser('Admin Bidang', $skpdBidang, 'admin_bidang_2');

        $this->actingAs($user);

        $this->get(route('rkbmd.arsip.preview', $arsipUpt))->assertForbidden();
    }

    // ----------------------------------------------------------------------
    // TANPA IDENTITAS VALID -> FAIL CLOSED
    // ----------------------------------------------------------------------

    public function test_user_tanpa_relasi_pegawai_ditolak(): void
    {
        $skpd = $this->buatSkpd('UPT P2BTP', 'UPT');
        $arsip = $this->buatArsip($skpd, 'arsip-rkbmd/tanpa-pegawai.pdf');

        // Role sah, tetapi tidak ada id_pegawai / id_skpd -> tidak boleh.
        $user = $this->buatUser('Admin UPT', null, 'upt_tanpa_skpd');

        $this->actingAs($user);

        $this->get(route('rkbmd.arsip.preview', $arsip))->assertForbidden();
        $this->get(route('rkbmd.arsip.download', $arsip))->assertForbidden();
    }

    public function test_semua_arsip_ditolak_saat_user_kehilangan_skpd(): void
    {
        $skpdSaya = $this->buatSkpd('UPT P2BTP', 'UPT');
        $arsip = $this->buatArsip($skpdSaya, 'arsip-rkbmd/upt-sendiri.pdf');

        // Pegawai ada tetapi id_skpd null.
        $role = $this->buatRole('Admin UPT');
        $pegawai = Pegawai::create([
            'nip' => '7'.random_int(10000000, 99999999),
            'nama_pegawai' => 'Tanpa Unit',
            'jabatan' => 'Pegawai',
        ]);
        $user = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $role->id_role,
            'username' => 'admin_no_skpd',
            'password' => self::PASSWORD,
            'status_user' => 'aktif',
        ]);

        $this->actingAs($user);

        $this->get(route('rkbmd.arsip.preview', $arsip))->assertForbidden();
    }

    // ----------------------------------------------------------------------
    // AUTENTIKASI
    // ----------------------------------------------------------------------

    public function test_tanpa_autentikasi_dihentikan_middleware_auth(): void
    {
        $skpd = $this->buatSkpd('UPT P2BTP', 'UPT');
        $arsip = $this->buatArsip($skpd, 'arsip-rkbmd/anon.pdf');

        $this->get(route('rkbmd.arsip.preview', $arsip))->assertRedirect(route('login'));
        $this->get(route('rkbmd.arsip.download', $arsip))->assertRedirect(route('login'));
    }

    // ----------------------------------------------------------------------
    // RECORD TIDAK DITEMUKAN
    // ----------------------------------------------------------------------

    public function test_record_arsip_tidak_ditemukan_menghasilkan_404(): void
    {
        $admin = $this->buatUser('Admin Aset', null, 'admin_aset_404');

        $this->actingAs($admin);

        $this->get(route('rkbmd.arsip.preview', 999999))->assertNotFound();
        $this->get(route('rkbmd.arsip.download', 999999))->assertNotFound();
    }

    // ----------------------------------------------------------------------
    // PATH / ID TIDAK DAPAT DIMANIPULASI
    // ----------------------------------------------------------------------

    public function test_path_di_request_tidak_mengubah_file_yang_disajikan(): void
    {
        $skpd = $this->buatSkpd('UPT P2BTP', 'UPT');
        $arsip = $this->buatArsip($skpd, 'arsip-rkbmd/aman.pdf');
        Storage::disk(RkbmdController::DISK_ARSIP_LAMA)->put('arsip-rkbmd/lain.pdf', '%PDF-1.4 file lain rahasia');

        $admin = $this->buatUser('Admin Aset', null, 'admin_aset_path');

        $this->actingAs($admin);

        // Tambahan query path/file tidak boleh mengganti file yang dibuka.
        $this->get(route('rkbmd.arsip.preview', $arsip).'?path=arsip-rkbmd/lain.pdf')->assertOk();

        $respons = $this->get(route('rkbmd.arsip.download', $arsip).'?file=arsip-rkbmd/lain.pdf');
        $respons->assertOk();
        $this->assertStringNotContainsString('rahasia', $respons->streamedContent() ?: '');
    }

    public function test_ganti_id_arsip_tidak_membuka_arsip_unit_lain_untuk_admin_bidang(): void
    {
        $skpdSaya = $this->buatSkpd('Bidang Produksi Tanaman Semusim', 'Bidang');
        $skpdLain = $this->buatSkpd('UPT PSBP', 'UPT');
        $arsipSaya = $this->buatArsip($skpdSaya, 'arsip-rkbmd/saya.pdf');
        $arsipLain = $this->buatArsip($skpdLain, 'arsip-rkbmd/lain2.pdf');

        $user = $this->buatUser('Admin Bidang', $skpdSaya, 'admin_bidang_semusim');

        $this->actingAs($user);

        $this->get(route('rkbmd.arsip.preview', $arsipSaya))->assertOk();
        $this->get(route('rkbmd.arsip.preview', $arsipLain))->assertForbidden();
    }

    // ----------------------------------------------------------------------
    // UI TIDAK MEMAKAI URL PUBLIK LANGSUNG
    // ----------------------------------------------------------------------

    public function test_view_arsip_memakai_endpoint_terproteksi_bukan_url_publik(): void
    {
        $skpd = $this->buatSkpd('UPT P2BTP', 'UPT');
        $arsip = $this->buatArsip($skpd, 'arsip-rkbmd/ui.pdf');

        $admin = $this->buatUser('Admin Aset', null, 'admin_aset_ui');

        $this->actingAs($admin);

        $html = $this->get(route('rkbmd.index'))->assertOk()->getContent();

        // Tautan pratinjau & unduh mengarah ke endpoint controller.
        $this->assertStringContainsString(route('rkbmd.arsip.preview', $arsip), $html);
        $this->assertStringContainsString(route('rkbmd.arsip.download', $arsip), $html);

        // Tidak boleh ada URL langsung ke file arsip (disk publik / symlink).
        $this->assertStringNotContainsString('/storage/arsip-rkbmd', $html);
        $this->assertStringNotContainsString(basename($arsip->file_path), $html);
    }

    // ----------------------------------------------------------------------
    // FILE TIDAK ADA DI PENYIMPANAN
    // ----------------------------------------------------------------------

    public function test_file_arsip_tidak_ada_di_penyimpanan_menghasilkan_404(): void
    {
        $skpd = $this->buatSkpd('UPT P2BTP', 'UPT');
        // Rekam tanpa meletakkan file fisik di disk mana pun.
        $arsip = $this->buatArsip($skpd);

        $admin = $this->buatUser('Admin Aset', null, 'admin_aset_missing');

        $this->actingAs($admin);

        $this->get(route('rkbmd.arsip.preview', $arsip))->assertNotFound();
        $this->get(route('rkbmd.arsip.download', $arsip))->assertNotFound();
    }

    // ----------------------------------------------------------------------
    // ATURAN BISNIS: PENGHAPUSAN ARSIP DINONAKTIFKAN UNTUK SEMUA ROLE
    // ----------------------------------------------------------------------

    /**
     * Seluruh pengguna (Super Admin, Admin Aset, Admin Bidang, Admin UPT)
     * DITOLAK (HTTP 403) saat mencoba menghapus arsip dokumen RKBMD.
     */
    public function test_semua_role_ditolak_saat_mencoba_menghapus_arsip(): void
    {
        $skpd = $this->buatSkpd('UPT P2BTP', 'UPT');
        $arsip = $this->buatArsip($skpd, 'arsip-rkbmd/dokumen-permanen.pdf');

        $superAdmin = $this->buatUser('Super Admin', null, 'super_no_del_arsip');
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_no_del_arsip');
        $adminBidang = $this->buatUser('Admin Bidang', $skpd, 'bidang_no_del_arsip');
        $adminUpt = $this->buatUser('Admin UPT', $skpd, 'upt_no_del_arsip');

        // 1. Super Admin ditolak 403
        $this->actingAs($superAdmin)
            ->delete(route('rkbmd.arsip.destroy', $arsip))
            ->assertForbidden();

        // 2. Admin Aset ditolak 403
        $this->actingAs($adminAset)
            ->delete(route('rkbmd.arsip.destroy', $arsip))
            ->assertForbidden();

        // 3. Admin Bidang ditolak 403
        $this->actingAs($adminBidang)
            ->delete(route('rkbmd.arsip.destroy', $arsip))
            ->assertForbidden();

        // 4. Admin UPT ditolak 403
        $this->actingAs($adminUpt)
            ->delete(route('rkbmd.arsip.destroy', $arsip))
            ->assertForbidden();

        // 5. Arsip dan file fisik tetap aman di database dan storage
        $this->assertDatabaseHas('arsip_rkbmd', ['id_arsip' => $arsip->id_arsip]);
        $this->assertTrue(Storage::disk(RkbmdController::DISK_ARSIP)->exists('arsip-rkbmd/dokumen-permanen.pdf'));
    }

    /**
     * Tombol Hapus arsip tidak muncul pada halaman antarmuka (UI).
     */
    public function test_tombol_hapus_arsip_tidak_muncul_pada_ui(): void
    {
        $skpd = $this->buatSkpd('UPT PSBP', 'UPT');
        $arsip = $this->buatArsip($skpd, 'arsip-rkbmd/ui-nodelete.pdf');

        $adminAset = $this->buatUser('Admin Aset', null, 'admin_aset_ui_check');
        $this->actingAs($adminAset);

        $html = $this->get(route('rkbmd.index'))->assertOk()->getContent();

        // Tombol / form DELETE arsip tidak boleh muncul di UI
        $this->assertStringNotContainsString('action="' . route('rkbmd.arsip.destroy', $arsip) . '"', $html);
        $this->assertStringNotContainsString('Hapus arsip dokumen ini', $html);
        $this->assertStringNotContainsString('title="Hapus"', $html);

        // Namun tombol Pratinjau dan Unduh tetap muncul
        $this->assertStringContainsString(route('rkbmd.arsip.preview', $arsip), $html);
        $this->assertStringContainsString(route('rkbmd.arsip.download', $arsip), $html);
    }

    // ----------------------------------------------------------------------
    // HAK AKSES UPLOAD ARSIP DOKUMEN SAH RKBMD
    // ----------------------------------------------------------------------

    /**
     * 1. Admin Aset dapat upload arsip untuk berbagai SKPD.
     */
    public function test_admin_aset_dapat_mengunggah_arsip_untuk_berbagai_skpd(): void
    {
        $skpdA = $this->buatSkpd('Bidang Upload A', 'Bidang');
        $skpdB = $this->buatSkpd('UPT Upload B', 'UPT');
        $adminAset = $this->buatUser('Admin Aset', null, 'admin_aset_uploader');

        $this->actingAs($adminAset);

        $fileA = UploadedFile::fake()->create('skpd_a.pdf', 500, 'application/pdf');
        $resA = $this->post(route('rkbmd.arsip.store'), [
            'id_skpd' => $skpdA->id_skpd,
            'tahun_anggaran' => 2027,
            'jenis_dokumen' => ArsipRkbmd::JENIS_DOKUMEN[0],
            'nama_dokumen' => 'Dokumen SKPD A',
            'file_dokumen' => $fileA,
        ]);
        $resA->assertRedirect();
        $this->assertDatabaseHas('arsip_rkbmd', [
            'id_skpd' => $skpdA->id_skpd,
            'nama_dokumen' => 'Dokumen SKPD A',
            'uploaded_by' => $adminAset->id_user,
        ]);

        $fileB = UploadedFile::fake()->create('skpd_b.pdf', 500, 'application/pdf');
        $resB = $this->post(route('rkbmd.arsip.store'), [
            'id_skpd' => $skpdB->id_skpd,
            'tahun_anggaran' => 2027,
            'jenis_dokumen' => ArsipRkbmd::JENIS_DOKUMEN[1],
            'nama_dokumen' => 'Dokumen SKPD B',
            'file_dokumen' => $fileB,
        ]);
        $resB->assertRedirect();
        $this->assertDatabaseHas('arsip_rkbmd', [
            'id_skpd' => $skpdB->id_skpd,
            'nama_dokumen' => 'Dokumen SKPD B',
            'uploaded_by' => $adminAset->id_user,
        ]);
    }

    /**
     * 2. Admin Bidang dapat upload arsip untuk SKPD-nya sendiri.
     */
    public function test_admin_bidang_dapat_mengunggah_arsip_untuk_skpd_sendiri(): void
    {
        $skpdBidang = $this->buatSkpd('Bidang Upload Mandiri', 'Bidang');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'admin_bidang_uploader');

        $this->actingAs($adminBidang);

        $file = UploadedFile::fake()->create('arsip_bidang.pdf', 500, 'application/pdf');
        $response = $this->post(route('rkbmd.arsip.store'), [
            'id_skpd' => $skpdBidang->id_skpd,
            'tahun_anggaran' => 2027,
            'jenis_dokumen' => ArsipRkbmd::JENIS_DOKUMEN[0],
            'nama_dokumen' => 'SK Penetapan Bidang Mandiri',
            'file_dokumen' => $file,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('arsip_rkbmd', [
            'id_skpd' => $skpdBidang->id_skpd,
            'nama_dokumen' => 'SK Penetapan Bidang Mandiri',
            'uploaded_by' => $adminBidang->id_user,
        ]);
    }

    /**
     * 3. Admin Bidang ditolak HTTP 403 saat mencoba manipulasi id_skpd ke unit lain.
     */
    public function test_admin_bidang_ditolak_saat_mencoba_upload_ke_skpd_lain(): void
    {
        $skpdSaya = $this->buatSkpd('Bidang Asal', 'Bidang');
        $skpdLain = $this->buatSkpd('Bidang Target Manipulasi', 'Bidang');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdSaya, 'admin_bidang_tamper');

        $this->actingAs($adminBidang);

        $file = UploadedFile::fake()->create('arsip_tamper.pdf', 500, 'application/pdf');
        $response = $this->post(route('rkbmd.arsip.store'), [
            'id_skpd' => $skpdLain->id_skpd, // Manipulasi request ke SKPD lain
            'tahun_anggaran' => 2027,
            'jenis_dokumen' => ArsipRkbmd::JENIS_DOKUMEN[0],
            'nama_dokumen' => 'SK Manipulasi Target',
            'file_dokumen' => $file,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('arsip_rkbmd', [
            'nama_dokumen' => 'SK Manipulasi Target',
        ]);
    }

    /**
     * 4. Admin UPT P2BTP dapat upload arsip untuk UPT P2BTP.
     */
    public function test_admin_upt_p2btp_dapat_mengunggah_arsip_untuk_p2btp(): void
    {
        $skpdP2btp = $this->buatSkpd('UPT P2BTP Upload', 'UPT');
        $adminP2btp = $this->buatUser('Admin UPT', $skpdP2btp, 'admin_p2btp_uploader');

        $this->actingAs($adminP2btp);

        $file = UploadedFile::fake()->create('arsip_p2btp.pdf', 500, 'application/pdf');
        $response = $this->post(route('rkbmd.arsip.store'), [
            'id_skpd' => $skpdP2btp->id_skpd,
            'tahun_anggaran' => 2027,
            'jenis_dokumen' => ArsipRkbmd::JENIS_DOKUMEN[0],
            'nama_dokumen' => 'SK Usulan P2BTP Sah',
            'file_dokumen' => $file,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('arsip_rkbmd', [
            'id_skpd' => $skpdP2btp->id_skpd,
            'nama_dokumen' => 'SK Usulan P2BTP Sah',
            'uploaded_by' => $adminP2btp->id_user,
        ]);
    }

    /**
     * 5. Admin UPT PSBP dapat upload arsip untuk UPT PSBP.
     */
    public function test_admin_upt_psbp_dapat_mengunggah_arsip_untuk_psbp(): void
    {
        $skpdPsbp = $this->buatSkpd('UPT PSBP Upload', 'UPT');
        $adminPsbp = $this->buatUser('Admin UPT', $skpdPsbp, 'admin_psbp_uploader');

        $this->actingAs($adminPsbp);

        $file = UploadedFile::fake()->create('arsip_psbp.pdf', 500, 'application/pdf');
        $response = $this->post(route('rkbmd.arsip.store'), [
            'id_skpd' => $skpdPsbp->id_skpd,
            'tahun_anggaran' => 2027,
            'jenis_dokumen' => ArsipRkbmd::JENIS_DOKUMEN[0],
            'nama_dokumen' => 'SK Usulan PSBP Sah',
            'file_dokumen' => $file,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('arsip_rkbmd', [
            'id_skpd' => $skpdPsbp->id_skpd,
            'nama_dokumen' => 'SK Usulan PSBP Sah',
            'uploaded_by' => $adminPsbp->id_user,
        ]);
    }

    /**
     * 6. Admin UPT ditolak HTTP 403 saat mencoba upload ke UPT lain.
     */
    public function test_admin_upt_ditolak_saat_mencoba_upload_ke_upt_lain(): void
    {
        $skpdP2btp = $this->buatSkpd('UPT P2BTP Tamper', 'UPT');
        $skpdPsbp = $this->buatSkpd('UPT PSBP Tamper Target', 'UPT');
        $adminP2btp = $this->buatUser('Admin UPT', $skpdP2btp, 'admin_p2btp_tamper');

        $this->actingAs($adminP2btp);

        $file = UploadedFile::fake()->create('arsip_cross_upt.pdf', 500, 'application/pdf');
        $response = $this->post(route('rkbmd.arsip.store'), [
            'id_skpd' => $skpdPsbp->id_skpd, // Manipulasi antar UPT
            'tahun_anggaran' => 2027,
            'jenis_dokumen' => ArsipRkbmd::JENIS_DOKUMEN[0],
            'nama_dokumen' => 'SK Cross UPT Ilegal',
            'file_dokumen' => $file,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('arsip_rkbmd', [
            'nama_dokumen' => 'SK Cross UPT Ilegal',
        ]);
    }

    /**
     * 7. Pegawai biasa ditolak HTTP 403 saat mencoba mengunggah arsip.
     */
    public function test_pegawai_biasa_ditolak_saat_mencoba_mengunggah_arsip(): void
    {
        $skpd = $this->buatSkpd('Bidang Pegawai Test', 'Bidang');
        $pegawai = $this->buatUser('Pegawai', $skpd, 'pegawai_biasa_upload');

        $this->actingAs($pegawai);

        $file = UploadedFile::fake()->create('arsip_pegawai.pdf', 500, 'application/pdf');
        $response = $this->post(route('rkbmd.arsip.store'), [
            'id_skpd' => $skpd->id_skpd,
            'tahun_anggaran' => 2027,
            'jenis_dokumen' => ArsipRkbmd::JENIS_DOKUMEN[0],
            'nama_dokumen' => 'SK Dari Pegawai Biasa',
            'file_dokumen' => $file,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('arsip_rkbmd', [
            'nama_dokumen' => 'SK Dari Pegawai Biasa',
        ]);
    }

    /**
     * 8. Visibilitas tombol upload dan antarmuka pemilihan unit kerja.
     */
    public function test_visibilitas_tombol_upload_dan_antarmuka_skpd(): void
    {
        $skpdBidang = $this->buatSkpd('Bidang UI Upload', 'Bidang');
        $adminAset = $this->buatUser('Admin Aset', null, 'admin_aset_ui_btn');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'admin_bidang_ui_btn');
        $pegawai = $this->buatUser('Pegawai', $skpdBidang, 'pegawai_ui_nobtn');

        // Admin Aset melihat tombol upload dan dropdown select SKPD
        $resAset = $this->actingAs($adminAset)->get(route('rkbmd.index'));
        $resAset->assertOk();
        $resAset->assertSee('data-open-modal="modalArsipUpload"', false);
        $resAset->assertSee('id="arsip-skpd"', false);

        // Admin Bidang melihat tombol upload, tetapi unit kerjanya read-only (bukan select)
        $resBidang = $this->actingAs($adminBidang)->get(route('rkbmd.index'));
        $resBidang->assertOk();
        $resBidang->assertSee('data-open-modal="modalArsipUpload"', false);
        $resBidang->assertSee($skpdBidang->nama_skpd);
        $resBidang->assertSee('Unit kerja ditentukan secara otomatis sesuai akun Anda.');

        // Pegawai biasa ditolak 403 saat membuka rkbmd.index (tidak melihat halaman maupun tombol)
        $resPegawai = $this->actingAs($pegawai)->get(route('rkbmd.index'));
        $resPegawai->assertForbidden();
    }
}
