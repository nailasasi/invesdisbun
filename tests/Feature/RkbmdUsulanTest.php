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

        $pegawai = Pegawai::create([
            'nip' => '8'.random_int(10000000, 99999999),
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
            'tahun_anggaran', 'jenis_usulan', 'id_kegiatan',
        ] as $field) {
            $this->hantarPayload($this->payloadSukses(), [$field])
                ->assertSessionHasErrors($field);
        }
    }

    public function test_usulan_dapat_disimpan_tanpa_kebutuhan_maksimum(): void
    {
        $this->actingAs($this->adminAset());

        $payload = $this->payloadSukses();
        unset($payload['kebutuhan_maksimum']);

        $this->hantarPayload($payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usulan_rkbmd', [
            'status_usulan' => 'Draft',
            'kebutuhan_maksimum' => null,
            'kebutuhan_riil' => 2,
        ]);
    }

    public function test_usulan_dapat_disimpan_tanpa_kebutuhan_riil(): void
    {
        $this->actingAs($this->adminAset());

        $payload = $this->payloadSukses();
        unset($payload['kebutuhan_riil']);

        $this->hantarPayload($payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usulan_rkbmd', [
            'status_usulan' => 'Draft',
            'kebutuhan_maksimum' => 5,
            'kebutuhan_riil' => null,
        ]);
    }

    public function test_usulan_dapat_disimpan_tanpa_kebutuhan_maksimum_dan_riil(): void
    {
        $this->actingAs($this->adminAset());

        $payload = $this->payloadSukses();
        unset($payload['kebutuhan_maksimum'], $payload['kebutuhan_riil']);

        $this->hantarPayload($payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usulan_rkbmd', [
            'status_usulan' => 'Draft',
            'kebutuhan_maksimum' => null,
            'kebutuhan_riil' => null,
        ]);
    }

    public function test_halaman_index_dan_detail_dapat_menampilkan_usulan_dengan_kebutuhan_null(): void
    {
        $admin = $this->adminAset();
        $this->actingAs($admin);

        $payload = $this->payloadSukses([
            'nama_barang' => 'Server Khusus Testing Null Kebutuhan',
            'kebutuhan_maksimum' => null,
            'kebutuhan_riil' => null,
        ]);

        $this->hantarPayload($payload)->assertRedirect()->assertSessionHasNoErrors();

        $usulan = \App\Models\UsulanRkbmd::where('nama_barang', 'Server Khusus Testing Null Kebutuhan')->firstOrFail();

        // 1. Index page
        $resIndex = $this->get(route('rkbmd.index'))->assertOk();
        $resIndex->assertSee('Server Khusus Testing Null Kebutuhan');

        // 2. Show page (detail)
        $resShow = $this->get(route('rkbmd.show', $usulan->id_usulan))->assertOk();
        $resShow->assertSee('Server Khusus Testing Null Kebutuhan');
        $resShow->assertSee('Kebutuhan Maksimum');
        $resShow->assertSee('Kebutuhan Riil');
        $resShow->assertSee('-');
    }

    public function test_export_excel_tidak_error_saat_kebutuhan_kosong(): void
    {
        $admin = $this->adminAset();
        $this->actingAs($admin);

        // Buat usulan berstatus Diajukan dengan kebutuhan null
        $payload = $this->payloadSukses([
            'nama_barang' => 'Komputer Export Test Null Kebutuhan',
            'kebutuhan_maksimum' => null,
            'kebutuhan_riil' => null,
        ]);

        $this->hantarPayload($payload)->assertRedirect()->assertSessionHasNoErrors();
        $usulan = \App\Models\UsulanRkbmd::where('nama_barang', 'Komputer Export Test Null Kebutuhan')->firstOrFail();
        $usulan->update(['status_usulan' => 'Diajukan']);

        $resp = $this->get(route('rkbmd.export', ['jenis' => 'all', 'bidang' => 'all', 'status' => 'all']));
        $resp->assertSuccessful();

        $tmp = tempnam(sys_get_temp_dir(), 'rkbmdnulltest');
        file_put_contents($tmp, $resp->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        @unlink($tmp);

        // Cari baris yang memuat usulan kita
        $matchedRow = null;
        foreach ($rows as $r) {
            if (($r['D'] ?? '') === 'Komputer Export Test Null Kebutuhan' || ($r['N'] ?? '') === 'Komputer Export Test Null Kebutuhan') {
                $matchedRow = $r;
                break;
            }
        }

        $this->assertNotNull($matchedRow, 'Baris usulan harus ditemukan dalam hasil ekspor');
        $valMaks = $matchedRow['G'] ?? $matchedRow['R'] ?? '';
        $valRiil = $matchedRow['M'] ?? $matchedRow['S'] ?? '';
        $this->assertTrue(in_array($valMaks, ['NIHIL', '-'], true), 'Kolom Kebutuhan Maksimum harus bernilai "NIHIL" atau "-"');
        $this->assertTrue(in_array($valRiil, ['NIHIL', '-'], true), 'Kolom Kebutuhan Riil harus bernilai "NIHIL" atau "-"');
    }

    public function test_optimalisasi_tetap_valid_saat_data_kebutuhan_kosong(): void
    {
        $this->actingAs($this->adminAset());

        $payload = $this->payloadSukses([
            'kebutuhan_maksimum' => null,
            'kebutuhan_riil' => null,
            'opt_kode' => '1.3.2.10.02.001',
            'opt_nama' => 'Printer Lama Rusak Ringan',
            'opt_jumlah' => 2,
            'opt_satuan' => 'Unit',
        ]);

        $this->hantarPayload($payload)->assertRedirect()->assertSessionHasNoErrors();

        $usulan = \App\Models\UsulanRkbmd::where('nama_barang', 'Laptop Notebook Core i5')->latest('id_usulan')->firstOrFail();
        $this->assertNull($usulan->kebutuhan_maksimum);
        $this->assertNull($usulan->kebutuhan_riil);
        $this->assertIsArray($usulan->barang_optimalisasi);
        $this->assertSame('Printer Lama Rusak Ringan', $usulan->barang_optimalisasi['nama']);
        $this->assertSame(2, $usulan->barang_optimalisasi['jumlah']);
    }

    public function test_usulan_dapat_disimpan_tanpa_master_barang_dan_tanpa_kode_nama_barang(): void
    {
        $this->actingAs($this->adminAset());

        $payload = $this->payloadSukses();
        unset($payload['kode_barang'], $payload['nama_barang']);

        $this->hantarPayload($payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usulan_rkbmd', [
            'status_usulan' => 'Draft',
            'kode_barang' => null,
            'nama_barang' => null,
            'kebutuhan_maksimum' => 5,
            'kebutuhan_riil' => 2,
        ]);
    }

    public function test_usulan_dapat_disimpan_tanpa_jumlah_dan_satuan(): void
    {
        $this->actingAs($this->adminAset());

        $payload = $this->payloadSukses();
        unset($payload['jumlah'], $payload['satuan']);

        $this->hantarPayload($payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usulan_rkbmd', [
            'status_usulan' => 'Draft',
            'jumlah' => null,
            'satuan' => null,
            'kebutuhan_maksimum' => 5,
            'kebutuhan_riil' => 2,
        ]);
    }

    public function test_alasan_kebutuhan_bersifat_opsional_dan_usulan_dapat_disimpan_tanpa_alasan(): void
    {
        $this->actingAs($this->adminAset());

        $payload = $this->payloadSukses();
        unset($payload['alasan_kebutuhan']);

        $this->hantarPayload($payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usulan_rkbmd', [
            'status_usulan' => 'Draft',
            'kode_barang' => '2.01.01.01.001',
            'alasan_kebutuhan' => null,
        ]);
    }

    public function test_usulan_dapat_disimpan_tanpa_mengisi_barang_yang_dapat_dioptimalkan(): void
    {
        $this->actingAs($this->adminAset());

        $payload = $this->payloadSukses([
            'opt_kode' => '',
            'opt_nama' => '',
            'opt_jumlah' => '',
            'opt_satuan' => '',
        ]);

        $this->hantarPayload($payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usulan_rkbmd', [
            'status_usulan' => 'Draft',
            'kode_barang' => '2.01.01.01.001',
            'barang_optimalisasi' => null,
        ]);
    }

    public function test_pilihan_satuan_dan_satuan_optimalisasi_menyediakan_empat_pilihan_standar(): void
    {
        $this->actingAs($this->adminAset());

        $html = $this->get(route('rkbmd.index'))->assertOk()->getContent();

        // Verifikasi pilihan satuan utama dan satuan optimalisasi
        foreach (['Unit', 'Paket', 'Set', 'Buah'] as $satuan) {
            $this->assertStringContainsString(
                '<option value="'.$satuan.'"',
                $html,
                'Opsi satuan '.$satuan.' harus tersedia pada dropdown form modal.'
            );
        }
    }

    public function test_data_usulan_dapat_diedit_kembali_tanpa_kehilangan_nilai_satuan(): void
    {
        $this->actingAs($this->adminAset());

        // Simpan usulan awal dengan satuan 'Paket'
        $payload = $this->payloadSukses([
            'satuan' => 'Paket',
            'alasan_kebutuhan' => null,
        ]);

        $this->hantarPayload($payload)->assertRedirect()->assertSessionHasNoErrors();

        $usulan = \App\Models\UsulanRkbmd::where('satuan', 'Paket')->latest('id_usulan')->firstOrFail();
        $this->assertSame('Paket', $usulan->satuan);

        // Update usulan menjadi satuan 'Set'
        $response = $this->patch(route('rkbmd.update', $usulan->id_usulan), array_merge($payload, [
            'satuan' => 'Set',
            'alasan_kebutuhan' => 'Justifikasi pembaruan',
        ]));

        $response->assertRedirect()->assertSessionHasNoErrors();
        $usulan->refresh();

        $this->assertSame('Set', $usulan->satuan);
        $this->assertSame('Justifikasi pembaruan', $usulan->alasan_kebutuhan);
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
