<?php

namespace Tests\Feature;

use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PegawaiRuanganKerjaTest extends TestCase
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

    private function getAdminAset(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Aset'))->firstOrFail();
    }

    private function getAdminUptP2btp(): User
    {
        // Admin UPT pada SKPD 6 (UPT P2BTP, lokasi 2)
        $user = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin UPT'))
            ->whereHas('pegawai', fn ($q) => $q->where('id_skpd', 6))
            ->first();

        if ($user) {
            return $user;
        }

        $roleAdminUpt = Role::where('nama_role', 'Admin UPT')->firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => '198811112015011006',
            'nama_pegawai' => 'Admin UPT P2BTP Fixture',
            'jabatan' => 'Kepala Subbag TU',
            'id_skpd' => 6,
            'id_ruangan' => Ruangan::where('id_skpd', 6)->value('id_ruangan') ?? Ruangan::where('id_lokasi', 2)->value('id_ruangan'),
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'admin_p2btp_fixture_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_role' => $roleAdminUpt->id_role,
            'status_user' => 'aktif',
        ]);
    }

    // =========================================================================
    // 1. PENGUJIAN TAMBAH PEGAWAI
    // =========================================================================

    public function test_tambah_pegawai_tanpa_ruangan_ditolak_422(): void
    {
        $this->actingAs($this->getAdminAset());

        $res = $this->postJson(route('master-data.pegawai.store'), [
            'nip' => '199001012026011901',
            'nama_pegawai' => 'Pegawai Tanpa Ruangan',
            'jabatan' => 'Staf Pengujian',
            'id_skpd' => 1, // Sekretariat (lokasi 1)
            // id_ruangan sengaja tidak dikirim
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['id_ruangan']);
        $this->assertStringContainsString('Ruangan kerja wajib dipilih', json_encode($res->json()));
        $this->assertDatabaseMissing('pegawai', ['nip' => '199001012026011901']);
    }

    public function test_tambah_pegawai_dengan_ruangan_kosong_ditolak_422(): void
    {
        $this->actingAs($this->getAdminAset());

        $res = $this->postJson(route('master-data.pegawai.store'), [
            'nip' => '199001012026011902',
            'nama_pegawai' => 'Pegawai Ruangan Kosong',
            'jabatan' => 'Staf Pengujian',
            'id_skpd' => 1,
            'id_ruangan' => '',
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['id_ruangan']);
        $this->assertStringContainsString('Ruangan kerja wajib dipilih', json_encode($res->json()));
    }

    public function test_tambah_pegawai_dengan_ruangan_sesuai_lokasi_skpd_berhasil(): void
    {
        $this->actingAs($this->getAdminAset());

        // SKPD 1 (Sekretariat) berlokasi di lokasi 1 (Kantor Dinas)
        $ruangSekretariat = Ruangan::where('id_skpd', 1)->firstOrFail();

        $res = $this->postJson(route('master-data.pegawai.store'), [
            'nip' => '199001012026011903',
            'nama_pegawai' => 'Pegawai Sukses Tambah',
            'jabatan' => 'Staf Perencanaan',
            'id_skpd' => 1,
            'id_ruangan' => $ruangSekretariat->id_ruangan,
        ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertDatabaseHas('pegawai', [
            'nip' => '199001012026011903',
            'nama_pegawai' => 'Pegawai Sukses Tambah',
            'id_skpd' => 1,
            'id_ruangan' => $ruangSekretariat->id_ruangan,
        ]);
    }

    public function test_tambah_pegawai_dengan_ruangan_bersama_di_lokasi_sama_berhasil(): void
    {
        $this->actingAs($this->getAdminAset());

        // Ruang bersama di lokasi 1 (Kantor Dinas, id_skpd NULL)
        $ruangBersama = Ruangan::whereNull('id_skpd')->where('id_lokasi', 1)->firstOrFail();

        $res = $this->postJson(route('master-data.pegawai.store'), [
            'nip' => '199001012026011904',
            'nama_pegawai' => 'Pegawai Ruang Bersama',
            'jabatan' => 'Staf Dinas',
            'id_skpd' => 1, // Sekretariat (lokasi 1)
            'id_ruangan' => $ruangBersama->id_ruangan,
        ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertDatabaseHas('pegawai', [
            'nip' => '199001012026011904',
            'id_ruangan' => $ruangBersama->id_ruangan,
        ]);
    }

    public function test_tambah_pegawai_dengan_ruangan_dari_lokasi_berbeda_ditolak_422(): void
    {
        $this->actingAs($this->getAdminAset());

        // SKPD 1 (Sekretariat, lokasi 1), tapi memilih ruangan di UPT P2BTP (lokasi 2)
        $ruangUpt = Ruangan::where('id_lokasi', 2)->firstOrFail();

        $res = $this->postJson(route('master-data.pegawai.store'), [
            'nip' => '199001012026011905',
            'nama_pegawai' => 'Pegawai Manipulasi Ruang Asing',
            'jabatan' => 'Staf Hacker',
            'id_skpd' => 1, // Lokasi 1
            'id_ruangan' => $ruangUpt->id_ruangan, // Lokasi 2
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['id_ruangan']);
        $this->assertStringContainsString('Ruangan yang dipilih tidak sesuai dengan lokasi kerja SKPD', json_encode($res->json()));
        $this->assertDatabaseMissing('pegawai', ['nip' => '199001012026011905']);
    }

    // =========================================================================
    // 2. PENGUJIAN EDIT PEGAWAI
    // =========================================================================

    public function test_edit_pegawai_menampilkan_ruangan_eksisting_jika_sesuai(): void
    {
        $this->actingAs($this->getAdminAset());

        $ruang = Ruangan::where('id_skpd', 1)->firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => '199001012026011906',
            'nama_pegawai' => 'Pegawai Uji Edit',
            'jabatan' => 'Staf TU',
            'id_skpd' => 1,
            'id_ruangan' => $ruang->id_ruangan,
        ]);

        $res = $this->getJson(route('master-data.pegawai.edit', $pegawai));
        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'pegawai' => [
                'id_pegawai' => $pegawai->id_pegawai,
                'id_skpd' => 1,
                'id_ruangan' => $ruang->id_ruangan,
            ],
        ]);
    }

    public function test_edit_pegawai_tanpa_ruangan_ditolak_422(): void
    {
        $this->actingAs($this->getAdminAset());

        $ruang = Ruangan::where('id_skpd', 1)->firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => '199001012026011907',
            'nama_pegawai' => 'Pegawai Edit Kosong Ruang',
            'jabatan' => 'Staf TU',
            'id_skpd' => 1,
            'id_ruangan' => $ruang->id_ruangan,
        ]);

        $res = $this->putJson(route('master-data.pegawai.update', $pegawai), [
            'nip' => $pegawai->nip,
            'nama_pegawai' => 'Pegawai Edit Kosong Ruang',
            'jabatan' => 'Staf TU',
            'id_skpd' => 1,
            'id_ruangan' => '',
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['id_ruangan']);
        $this->assertStringContainsString('Ruangan kerja wajib dipilih', json_encode($res->json()));

        $pegawai->refresh();
        $this->assertSame($ruang->id_ruangan, $pegawai->id_ruangan, 'Ruangan lama tidak boleh berubah saat validasi gagal.');
    }

    public function test_edit_pegawai_dengan_ruangan_sesuai_lokasi_berhasil(): void
    {
        $this->actingAs($this->getAdminAset());

        $ruang1 = Ruangan::where('id_skpd', 1)->firstOrFail();
        // Ruangan lain di lokasi 1 (misal ruang bersama atau ruang bidang lain)
        $ruang2 = Ruangan::where('id_lokasi', 1)->where('id_ruangan', '!=', $ruang1->id_ruangan)->firstOrFail();

        $pegawai = Pegawai::create([
            'nip' => '199001012026011908',
            'nama_pegawai' => 'Pegawai Pindah Ruang Sesuai Lokasi',
            'jabatan' => 'Staf TU',
            'id_skpd' => 1,
            'id_ruangan' => $ruang1->id_ruangan,
        ]);

        $res = $this->putJson(route('master-data.pegawai.update', $pegawai), [
            'nip' => $pegawai->nip,
            'nama_pegawai' => 'Pegawai Pindah Ruang Sesuai Lokasi',
            'jabatan' => 'Staf TU',
            'id_skpd' => 1,
            'id_ruangan' => $ruang2->id_ruangan,
        ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $pegawai->refresh();
        $this->assertSame($ruang2->id_ruangan, $pegawai->id_ruangan);
    }

    public function test_edit_pegawai_dengan_ruangan_dari_lokasi_berbeda_ditolak_422(): void
    {
        $this->actingAs($this->getAdminAset());

        $ruangDinas = Ruangan::where('id_skpd', 1)->firstOrFail();
        $ruangUptPsbp = Ruangan::where('id_lokasi', 3)->firstOrFail(); // Lokasi 3

        $pegawai = Pegawai::create([
            'nip' => '199001012026011909',
            'nama_pegawai' => 'Pegawai Edit Ruang Beda Lokasi',
            'jabatan' => 'Staf TU',
            'id_skpd' => 1, // Lokasi 1
            'id_ruangan' => $ruangDinas->id_ruangan,
        ]);

        $res = $this->putJson(route('master-data.pegawai.update', $pegawai), [
            'nip' => $pegawai->nip,
            'nama_pegawai' => 'Pegawai Edit Ruang Beda Lokasi',
            'id_skpd' => 1,
            'id_ruangan' => $ruangUptPsbp->id_ruangan, // Lokasi 3
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['id_ruangan']);
        $this->assertStringContainsString('Ruangan yang dipilih tidak sesuai dengan lokasi kerja SKPD', json_encode($res->json()));
    }

    public function test_edit_pegawai_historis_tanpa_ruangan_wajib_memilih_ruangan(): void
    {
        $this->actingAs($this->getAdminAset());

        // Pegawai lama dengan id_ruangan = NULL
        $pegawaiLama = Pegawai::create([
            'nip' => '199001012026011910',
            'nama_pegawai' => 'Pegawai Historis Null Ruangan',
            'jabatan' => 'Staf Lama',
            'id_skpd' => 1,
            'id_ruangan' => null,
        ]);

        // 1. Coba update tanpa memilih ruangan -> gagal 422
        $resFail = $this->putJson(route('master-data.pegawai.update', $pegawaiLama), [
            'nip' => $pegawaiLama->nip,
            'nama_pegawai' => 'Pegawai Historis Update Nama',
            'id_skpd' => 1,
            'id_ruangan' => '',
        ]);
        $resFail->assertStatus(422);
        $resFail->assertJsonValidationErrors(['id_ruangan']);

        // 2. Update dengan memilih ruangan valid -> berhasil
        $ruangValid = Ruangan::where('id_skpd', 1)->firstOrFail();
        $resOk = $this->putJson(route('master-data.pegawai.update', $pegawaiLama), [
            'nip' => $pegawaiLama->nip,
            'nama_pegawai' => 'Pegawai Historis Lengkap',
            'id_skpd' => 1,
            'id_ruangan' => $ruangValid->id_ruangan,
        ]);
        $resOk->assertStatus(200);

        $pegawaiLama->refresh();
        $this->assertSame($ruangValid->id_ruangan, $pegawaiLama->id_ruangan);
    }

    // =========================================================================
    // 3. PENGUJIAN HAK AKSES ADMIN UPT
    // =========================================================================

    public function test_admin_upt_dapat_tambah_pegawai_dengan_ruangan_upt_nya(): void
    {
        $adminUpt = $this->getAdminUptP2btp(); // SKPD 6, Lokasi 2
        $this->actingAs($adminUpt);

        $ruangUpt = Ruangan::where('id_lokasi', 2)->firstOrFail();

        $res = $this->postJson(route('master-data.pegawai.store'), [
            'nip' => '199001012026011911',
            'nama_pegawai' => 'Pegawai Baru UPT P2BTP',
            'jabatan' => 'Teknisi Kebun',
            'id_skpd' => 6, // SKPD UPT sendiri
            'id_ruangan' => $ruangUpt->id_ruangan, // Ruangan UPT sendiri
        ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertDatabaseHas('pegawai', [
            'nip' => '199001012026011911',
            'id_skpd' => 6,
            'id_ruangan' => $ruangUpt->id_ruangan,
        ]);
    }

    public function test_admin_upt_tidak_bisa_memilih_ruangan_dinas_atau_upt_lain(): void
    {
        $adminUpt = $this->getAdminUptP2btp(); // SKPD 6, Lokasi 2
        $this->actingAs($adminUpt);

        // Coba pakai ruangan Dinas Surabaya (lokasi 1)
        $ruangDinas = Ruangan::where('id_lokasi', 1)->firstOrFail();

        $res = $this->postJson(route('master-data.pegawai.store'), [
            'nip' => '199001012026011912',
            'nama_pegawai' => 'Coba Injeksi Ruang Dinas',
            'jabatan' => 'Staf',
            'id_skpd' => 6,
            'id_ruangan' => $ruangDinas->id_ruangan,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['id_ruangan']);
        $this->assertStringContainsString('Ruangan yang dipilih tidak sesuai dengan lokasi kerja SKPD', json_encode($res->json()));
    }

    public function test_admin_upt_tidak_bisa_memilih_skpd_dinas(): void
    {
        $adminUpt = $this->getAdminUptP2btp();
        $this->actingAs($adminUpt);

        $ruangDinas = Ruangan::where('id_lokasi', 1)->firstOrFail();

        $res = $this->postJson(route('master-data.pegawai.store'), [
            'nip' => '199001012026011913',
            'nama_pegawai' => 'Coba Injeksi SKPD Dinas',
            'jabatan' => 'Staf',
            'id_skpd' => 1, // Bukan UPT-nya
            'id_ruangan' => $ruangDinas->id_ruangan,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['id_skpd']);
        $this->assertStringContainsString('Anda tidak berwenang memilih SKPD tersebut', json_encode($res->json()));
    }

    // =========================================================================
    // 4. PENGUJIAN TAMPILAN VIEW & BLADE
    // =========================================================================

    public function test_view_form_memiliki_atribut_required_dan_tanda_bintang(): void
    {
        $this->actingAs($this->getAdminAset());

        $res = $this->get(route('master-data.pegawai.index'));
        $res->assertStatus(200);

        // Cek label bertanda bintang merah pada modal tambah dan edit
        $content = $res->getContent();
        $this->assertStringContainsString('tambah_id_ruangan', $content);
        $this->assertStringContainsString('edit_id_ruangan', $content);
        $this->assertStringContainsString('Ruangan Kerja <span class="text-red-500">*</span>', $content);

        // Cek select memiliki atribut required
        $this->assertMatchesRegularExpression('/<select[^>]*id="tambah_id_ruangan"[^>]*required/', $content);
        $this->assertMatchesRegularExpression('/<select[^>]*id="edit_id_ruangan"[^>]*required/', $content);
    }
}
