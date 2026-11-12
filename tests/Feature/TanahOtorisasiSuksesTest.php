<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tanah;
use App\Models\TanahHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\Support\MembuatFotoBukti;
use Tests\TestCase;

/**
 * Tahap A — jalur SUKSES password_otorisasi pada modul Tanah.
 *
 * Butuh database (tabel users/role/tanah/tanah_histories). Bila ekstensi
 * pdo_sqlite tidak tersedia (mis. lingkungan ini), seluruh kelas otomatis
 * di-SKIP — bukan error — sehingga tidak menambah kegagalan baseline.
 *
 * Sejak Tahap 3, endpoint juga mewajibkan `foto_bukti_otentikasi`
 * (data URL kamera); disediakan lewat trait MembuatFotoBukti.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class TanahOtorisasiSuksesTest extends TestCase
{
    use MembuatFotoBukti;
    use RefreshDatabase;

    private const PASSWORD = 'rahasia123';

    private function adminAset(): User
    {
        $role = Role::create(['nama_role' => 'Admin Aset']);

        return User::create([
            'id_role' => $role->id_role,
            'username' => 'admin_aset',
            'password' => self::PASSWORD,
            'status_user' => 'aktif',
        ]);
    }

    public function test_store_tanah_dengan_password_benar_berhasil(): void
    {
        $this->actingAs($this->adminAset());

        $response = $this->post(route('tanah.store'), [
            'kib' => '01.01.01.01.001',
            'video_url' => null,
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertRedirect(route('tanah.index'));
        $this->assertDatabaseHas('tanah', ['kib' => '01.01.01.01.001']);
    }

    public function test_store_tanah_dengan_password_salah_tidak_menyimpan_data(): void
    {
        $this->actingAs($this->adminAset());

        $response = $this->from(route('tanah.create'))->post(route('tanah.store'), [
            'kib' => '01.01.01.01.002',
            'video_url' => null,
            'password_otorisasi' => 'password-salah',
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertSessionHasErrors('password_otorisasi');
        $this->assertDatabaseMissing('tanah', ['kib' => '01.01.01.01.002']);
    }

    public function test_update_tanah_dengan_password_benar_berhasil_dan_password_tidak_masuk_riwayat(): void
    {
        $this->actingAs($this->adminAset());

        $tanah = Tanah::create(['kib' => '01.01.01.01.100', 'video_tanah' => null]);

        $response = $this->put(route('tanah.update', $tanah), [
            'kib' => '01.01.01.01.101',
            'video_url' => null,
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertRedirect(route('tanah.index'));
        $this->assertDatabaseHas('tanah', [
            'id_tanah' => $tanah->id_tanah,
            'kib' => '01.01.01.01.101',
        ]);

        $history = TanahHistory::where('id_tanah', $tanah->id_tanah)->first();
        $this->assertNotNull($history);
        $this->assertArrayNotHasKey('password_otorisasi', $history->data_lama);
        $this->assertArrayNotHasKey('password_otorisasi', $history->data_baru);
    }

    public function test_update_tanah_dengan_password_salah_tidak_mengubah_data(): void
    {
        $this->actingAs($this->adminAset());

        $tanah = Tanah::create(['kib' => '01.01.01.01.200', 'video_tanah' => null]);

        $response = $this->from(route('tanah.edit', $tanah))->put(route('tanah.update', $tanah), [
            'kib' => '01.01.01.01.201',
            'video_url' => null,
            'password_otorisasi' => 'password-salah',
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertSessionHasErrors('password_otorisasi');
        $this->assertDatabaseHas('tanah', [
            'id_tanah' => $tanah->id_tanah,
            'kib' => '01.01.01.01.200',
        ]);
    }
}
