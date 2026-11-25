<?php

namespace Tests\Feature;

use App\Models\RetribusiTanah;
use App\Models\Role;
use App\Models\Tanah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\Support\MembuatFotoBukti;
use Tests\TestCase;

/**
 * Tahap A — jalur SUKSES/GAGAL password_otorisasi pada retribusi tanah.
 *
 * Butuh database. Di-SKIP otomatis bila pdo_sqlite tidak tersedia sehingga
 * tidak menambah kegagalan baseline (lihat TanahOtorisasiSuksesTest).
 *
 * Sejak Tahap 3, endpoint juga mewajibkan `foto_bukti_otentikasi`.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class RetribusiOtorisasiSuksesTest extends TestCase
{
    use MembuatFotoBukti;
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
    }

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

    private function tanah(): Tanah
    {
        return Tanah::create(['kib' => '01.01.01.02.001', 'video_tanah' => null]);
    }

    private function retribusi(Tanah $tanah): RetribusiTanah
    {
        return RetribusiTanah::create([
            'id_tanah' => $tanah->id_tanah,
            'tahun' => 2025,
            'PAD' => 100,
        ]);
    }

    public function test_store_retribusi_berhasil_tanpa_step_up_authentication(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();

        $response = $this->post(route('tanah.retribusi.store', $tanah), [
            'tahun' => 2025,
            'PAD' => 150,
        ]);

        $response->assertRedirect(route('tanah.show', $tanah));
        $this->assertDatabaseHas('retribusi_tanah', [
            'id_tanah' => $tanah->id_tanah,
            'tahun' => 2025,
            'PAD' => 150,
        ]);
    }

    public function test_update_retribusi_dengan_password_benar_berhasil(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();
        $retribusi = $this->retribusi($tanah);

        $response = $this->put(route('tanah.retribusi.update', $retribusi), [
            'tahun' => 2025,
            'PAD' => 275,
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertRedirect(route('tanah.show', $tanah));
        $this->assertDatabaseHas('retribusi_tanah', [
            'id_retribusi' => $retribusi->id_retribusi,
            'PAD' => 275,
        ]);
    }

    public function test_update_retribusi_dengan_password_salah_tidak_menyimpan(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();
        $retribusi = $this->retribusi($tanah);

        $response = $this->from(route('tanah.retribusi.edit', $retribusi))
            ->put(route('tanah.retribusi.update', $retribusi), [
                'tahun' => 2025,
                'PAD' => 300,
                'password_otorisasi' => 'password-salah',
                'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
            ]);

        $response->assertSessionHasErrors('password_otorisasi');
        $this->assertDatabaseHas('retribusi_tanah', [
            'id_retribusi' => $retribusi->id_retribusi,
            'PAD' => 100,
        ]);
    }
}
