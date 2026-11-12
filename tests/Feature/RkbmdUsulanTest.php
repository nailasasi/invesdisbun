<?php

namespace Tests\Feature;

use App\Http\Controllers\RkbmdController;
use App\Models\MasterKegiatanRkbmd;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Revisi modul Input Usulan RKBMD:
 * A) Lima pilihan jenis usulan (dua baru: Pemindahtanganan, Pemanfaatan).
 * B) Seluruh field wajib kecuali "Barang yang Dapat Dioptimalkan" (opsional).
 *
 * Memakai koneksi MySQL nyata dalam transaksi rollback (pola project),
 * sehingga tidak ada perubahan permanen pada database.
 */
class RkbmdUsulanTest extends TestCase
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

    private function buatUser(string $roleNama, ?Skpd $skpd, string $username): User
    {
        $role = $this->buatRole($roleNama);

        $pegawai = null;
        if ($skpd) {
            $pegawai = Pegawai::create([
                'nip' => '8'.random_int(10000000, 99999999),
                'nama_pegawai' => 'User '.$username,
                'jabatan' => 'Pegawai',
                'id_skpd' => $skpd->id_skpd,
            ]);
        }

        return User::create([
            'id_pegawai' => $pegawai?->id_pegawai,
            'id_role' => $role->id_role,
            'username' => $username,
            'password' => self::PASSWORD,
            'status_user' => 'aktif',
        ]);
    }

    private function payloadSukses(array $override = []): array
    {
        $kegiatan = MasterKegiatanRkbmd::first();

        return array_merge([
            'id_skpd' => $this->buatSkpd('Bidang A', 'Bidang')->id_skpd,
            'tahun_anggaran' => 2027,
            'jenis_usulan' => 'Pengadaan',
            'id_kegiatan' => $kegiatan?->id_kegiatan ?? 1,
            'program_kegiatan' => 'Program Penunjang Urusan Pemerintahan',
            'kode_barang' => '2.01.01.01.001',
            'nama_barang' => 'Laptop Notebook Core i5',
            'spesifikasi' => 'RAM 8 GB, SSD 256 GB',
            'jumlah' => 2,
            'satuan' => 'Unit',
            'kebutuhan_maksimum' => 5,
            'kebutuhan_riil' => 2,
            'alasan_kebutuhan' => 'Pegawai belum memiliki laptop untuk administrasi perkantoran.',
        ], $override);
    }

    private function hantarPayload(array $payload, array $omit = [])
    {
        foreach ($omit as $f) {
            unset($payload[$f]);
        }

        return $this->post(route('rkbmd.store'), $payload);
    }

    private function adminAset(): User
    {
        return $this->buatUser('Admin Aset', null, 'admin_usulan_aset');
    }

    // ------------------------------------------------------------------
    // A. DUA JENIS BARU DITERIMA BACKEND & LIMA PILIHAN TERSEDIA
    // ------------------------------------------------------------------

    public function test_menyimpan_usulan_pemindahtanganan(): void
    {
        $this->actingAs($this->adminAset());

        $this->hantarPayload($this->payloadSukses(['jenis_usulan' => 'Pemindahtanganan']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usulan_rkbmd', [
            'jenis_usulan' => 'Pemindahtanganan',
            'nama_barang' => 'Laptop Notebook Core i5',
        ]);
    }

    public function test_menyimpan_usulan_pemanfaatan(): void
    {
        $this->actingAs($this->adminAset());

        $this->hantarPayload($this->payloadSukses(['jenis_usulan' => 'Pemanfaatan']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usulan_rkbmd', [
            'jenis_usulan' => 'Pemanfaatan',
            'nama_barang' => 'Laptop Notebook Core i5',
        ]);
    }

    public function test_mengirim_jenis_usulan_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->adminAset());

        $this->hantarPayload($this->payloadSukses(['jenis_usulan' => 'Sewa']))
            ->assertSessionHasErrors('jenis_usulan');
    }

    public function test_kelima_jenis_tersedia_pada_dropdown_modal_input(): void
    {
        $this->actingAs($this->adminAset());

        $html = $this->get(route('rkbmd.index'))->assertOk()->getContent();

        foreach (RkbmdController::JENIS as $jenis) {
            $this->assertStringContainsString(
                '<option value="'.$jenis.'"',
                $html,
                'Opsi jenis '.$jenis.' tidak ditemukan pada modal Input Usulan.'
            );
        }
    }

    // ------------------------------------------------------------------
    // B. SELURUH FIELD WAJIB (selain Barang yang Dapat Dioptimalkan)
    // ------------------------------------------------------------------

    public function test_usulan_tersimpan_saat_semua_field_wajib_terisi(): void
    {
        $this->actingAs($this->adminAset());

        $payload = $this->payloadSukses();
        $kegiatan = MasterKegiatanRkbmd::find($payload['id_kegiatan']);

        $this->hantarPayload($payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usulan_rkbmd', [
            'status_usulan' => 'Draft',
            'jenis_usulan' => 'Pengadaan',
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'program_kegiatan' => "{$kegiatan->bidang_urusan} - {$kegiatan->nama_program} - {$kegiatan->nama_sub_kegiatan}",
            'kode_barang' => '2.01.01.01.001',
            'nama_barang' => 'Laptop Notebook Core i5',
            'kebutuhan_maksimum' => 5,
            'kebutuhan_riil' => 2,
        ]);
    }

    public function test_field_wajib_kosong_ditolak_backend(): void
    {
        $this->actingAs($this->adminAset());

        foreach ([
            'tahun_anggaran', 'jenis_usulan', 'id_kegiatan', 'nama_barang',
            'kode_barang', 'jumlah', 'satuan', 'kebutuhan_maksimum',
            'kebutuhan_riil', 'alasan_kebutuhan',
        ] as $field) {
            $this->hantarPayload($this->payloadSukses(), [$field])
                ->assertSessionHasErrors($field);
        }
    }

    public function test_nilai_angka_mengikuti_aturan_jumlah_dan_kebutuhan(): void
    {
        $this->actingAs($this->adminAset());

        $this->hantarPayload($this->payloadSukses(['jumlah' => 0]))
            ->assertSessionHasErrors('jumlah');
        $this->hantarPayload($this->payloadSukses(['kebutuhan_maksimum' => -1]))
            ->assertSessionHasErrors('kebutuhan_maksimum');
        $this->hantarPayload($this->payloadSukses(['kebutuhan_riil' => 'abc']))
            ->assertSessionHasErrors('kebutuhan_riil');
        $this->hantarPayload($this->payloadSukses(['tahun_anggaran' => 2999]))
            ->assertSessionHasErrors('tahun_anggaran');
    }
}
