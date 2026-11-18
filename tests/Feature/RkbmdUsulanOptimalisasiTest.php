<?php

namespace Tests\Feature;

use App\Models\MasterKegiatanRkbmd;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bagian opsional "Barang yang Dapat Dioptimalkan", jenis lama, dan alur
 * lanjutan (ajukan) pada modul Input Usulan RKBMD.
 *
 * Memakai koneksi MySQL nyata dalam transaksi rollback (pola project).
 */
class RkbmdUsulanOptimalisasiTest extends TestCase
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
        return $this->buatUser('Admin Aset', null, 'admin_usulan_aset2');
    }

    // ------------------------------------------------------------------
    // C. BAGIAN OPSIONAL (Barang yang Dapat Dioptimalkan)
    // ------------------------------------------------------------------

    public function test_opsional_dikosongkan_tidak_error_dan_barang_optimalisasi_null(): void
    {
        $this->actingAs($this->adminAset());

        $this->hantarPayload($this->payloadSukses([
            'opt_kode' => null,
            'opt_nama' => null,
            'opt_jumlah' => null,
            'opt_satuan' => null,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $data = DB::table('usulan_rkbmd')->where('nama_barang', 'Laptop Notebook Core i5')->first();
        $this->assertNotNull($data);
        $this->assertNull($data->barang_optimalisasi);
    }

    public function test_opsional_dikosongkan_total_tanpa_key_di_request(): void
    {
        $this->actingAs($this->adminAset());

        // Request tidak mengirim key opt_* sama sekali.
        $this->hantarPayload($this->payloadSukses(), ['opt_kode', 'opt_nama', 'opt_jumlah', 'opt_satuan'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $data = DB::table('usulan_rkbmd')->where('nama_barang', 'Laptop Notebook Core i5')->first();
        $this->assertNotNull($data);
        $this->assertNull($data->barang_optimalisasi);
    }

    public function test_opsional_diisi_dengan_nilai_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->adminAset());

        $this->hantarPayload($this->payloadSukses(['opt_jumlah' => -5]))
            ->assertSessionHasErrors('opt_jumlah');

        $this->hantarPayload($this->payloadSukses(['opt_jumlah' => 0]))
            ->assertSessionHasErrors('opt_jumlah');
    }

    public function test_opsional_diisi_dengan_nilai_valid_tersimpan_sebagai_json(): void
    {
        $this->actingAs($this->adminAset());

        $this->hantarPayload($this->payloadSukses([
            'opt_kode' => '3.02.01.01.001',
            'opt_nama' => 'Meja Kerja Kayu',
            'opt_jumlah' => 3,
            'opt_satuan' => 'Unit',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $data = DB::table('usulan_rkbmd')->where('nama_barang', 'Laptop Notebook Core i5')->first();
        $this->assertNotNull($data);
        $parsed = json_decode($data->barang_optimalisasi, true);
        $this->assertSame('3.02.01.01.001', $parsed['kode']);
        $this->assertSame('Meja Kerja Kayu', $parsed['nama']);
        $this->assertSame(3, $parsed['jumlah']);
        $this->assertSame('Unit', $parsed['satuan']);
    }

    // ------------------------------------------------------------------
    // D. JENIS LAMA TIDAK BERREGRESI & ALUR LANJUTAN TIDAK TERPENGARUH
    // ------------------------------------------------------------------

    public function test_jenis_lama_masih_tersimpan_dan_dapat_diajukan(): void
    {
        $skpd = $this->buatSkpd('Bidang Produksi Tanaman Tahunan', 'Bidang');
        $user = $this->buatUser('Admin Bidang', $skpd, 'admin_bidang_usulan');

        $this->actingAs($user);

        // Non-admin: payload TIDAK memuat id_skpd (diabaikan, memakai SKPD user).
        $this->hantarPayload($this->payloadSukses([
            'jenis_usulan' => 'Pengadaan',
            'id_skpd' => null,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $usulan = DB::table('usulan_rkbmd')->where('nama_barang', 'Laptop Notebook Core i5')->first();
        $this->assertNotNull($usulan);
        $this->assertSame('Pengadaan', $usulan->jenis_usulan);
        $this->assertSame((int) $skpd->id_skpd, (int) $usulan->id_skpd);
        $this->assertSame('Draft', $usulan->status_usulan);

        // Simulasi alur lanjutan: ajukan (Draft -> Diajukan).
        $this->post(route('rkbmd.submit', $usulan->id_usulan))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Diajukan', DB::table('usulan_rkbmd')->where('id_usulan', $usulan->id_usulan)->value('status_usulan'));
    }

    public function test_jenis_baru_dapat_diajukan_setelah_disimpan(): void
    {
        $this->actingAs($this->adminAset());

        $this->hantarPayload($this->payloadSukses(['jenis_usulan' => 'Pemindahtanganan']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $usulan = DB::table('usulan_rkbmd')->where('nama_barang', 'Laptop Notebook Core i5')->first();
        $this->assertNotNull($usulan);
        $this->assertSame('Pemindahtanganan', $usulan->jenis_usulan);
        $this->assertSame('Draft', $usulan->status_usulan);
    }

    public function test_ekspor_memfilter_jenis_pemanfaatan_tidak_lagi_mengarah_ke_penghapusan(): void
    {
        $this->actingAs($this->adminAset());

        $skpd = $this->buatSkpd('Bidang B', 'Bidang');
        $idSkpd = $skpd->id_skpd;

        DB::table('usulan_rkbmd')->insert([
            ['id_skpd' => $idSkpd, 'id_pegawai' => null, 'tahun_anggaran' => 2027, 'jenis_usulan' => 'Pemanfaatan', 'nama_barang' => 'B Pemanfaatan', 'jumlah' => 1, 'satuan' => 'Unit', 'status_usulan' => 'Diajukan', 'tanggal_usulan' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()],
            ['id_skpd' => $idSkpd, 'id_pegawai' => null, 'tahun_anggaran' => 2027, 'jenis_usulan' => 'Penghapusan', 'nama_barang' => 'B Penghapusan', 'jumlah' => 1, 'satuan' => 'Unit', 'status_usulan' => 'Diajukan', 'tanggal_usulan' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $resp = $this->get(route('rkbmd.export', ['jenis' => 'pemanfaatan', 'bidang' => 'all', 'status' => 'all']));

        // Respon XLSX (stream). Nama file harus memakai label "Pemanfaatan".
        $resp->assertSuccessful();
        $disposition = $resp->headers->get('Content-Disposition') ?: $resp->headers->get('content-disposition') ?: '';

        // Pastikan filter hanya mengembalikan data Pemanfaatan: cek nama file
        // tidak berlabel Penghapusan.
        $this->assertStringContainsString('Pemanfaatan', $disposition);
        $this->assertStringNotContainsString('Penghapusan', $disposition);
    }

    public function test_ekspor_setiap_jenis_hanya_memuat_baris_jenis_tersebut(): void
    {
        $this->actingAs($this->adminAset());

        $idSkpd = $this->buatSkpd('Bidang C', 'Bidang')->id_skpd;

        $baris = [];
        foreach (\App\Http\Controllers\RkbmdController::JENIS_KODE as $kode => $label) {
            $baris[] = ['id_skpd' => $idSkpd, 'id_pegawai' => null, 'tahun_anggaran' => 2027, 'jenis_usulan' => $label, 'nama_barang' => 'Barang '.$label, 'jumlah' => 1, 'satuan' => 'Unit', 'status_usulan' => 'Diajukan', 'tanggal_usulan' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('usulan_rkbmd')->insert($baris);

        foreach (\App\Http\Controllers\RkbmdController::JENIS_KODE as $kode => $label) {
            $resp = $this->get(route('rkbmd.export', ['jenis' => $kode, 'bidang' => [$idSkpd], 'status' => 'all']));
            $resp->assertSuccessful();

            $tmp = tempnam(sys_get_temp_dir(), 'rkbmdxlsx');
            file_put_contents($tmp, $resp->streamedContent());
            $isi = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet()->toArray(null, true, true, false);
            @unlink($tmp);

            $namaBarang = [];
            foreach (array_slice($isi, 10) as $r) {
                foreach ($r as $val) {
                    if (is_string($val) && str_starts_with($val, 'Barang ') && $val !== 'Barang yang dipelihara') {
                        $namaBarang[] = $val;
                    }
                }
            }
            $namaBarang = array_values(array_unique($namaBarang));

            $this->assertSame(['Barang '.$label], $namaBarang, 'Ekspor jenis '.$kode.' tidak memfilter dengan benar.');
        }
    }
}
