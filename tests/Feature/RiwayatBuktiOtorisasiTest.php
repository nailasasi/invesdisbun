<?php

namespace Tests\Feature;

use App\Models\RetribusiTanah;
use App\Models\Role;
use App\Models\Tanah;
use App\Models\TanahHistory;
use App\Models\User;
use App\Services\BuktiOtorisasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\Support\MembuatFotoBukti;
use Tests\TestCase;

/**
 * Tahap 3 — integrasi backend: bukti otorisasi (foto kamera) + riwayat
 * aktivitas untuk Tambah/Edit Tanah dan Tambah/Edit/Hapus Retribusi.
 *
 * Butuh database. Di-SKIP otomatis bila ekstensi pdo_sqlite tidak tersedia
 * (lingkungan ini), sehingga tidak menambah kegagalan baseline.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class RiwayatBuktiOtorisasiTest extends TestCase
{
    use MembuatFotoBukti;
    use RefreshDatabase;

    private const PASSWORD = 'rahasia123';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(BuktiOtorisasi::DISK);
    }

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
        return Tanah::create(['kib' => '01.01.01.03.001', 'video_tanah' => null]);
    }

    private function retribusi(Tanah $tanah): RetribusiTanah
    {
        return RetribusiTanah::create([
            'id_tanah' => $tanah->id_tanah,
            'tahun' => 2025,
            'PAD' => 100,
        ]);
    }

    // ------------------------------------------------------------------
    // TAMBAH RETRIBUSI
    // ------------------------------------------------------------------

    public function test_store_retribusi_mencatat_riwayat_dengan_bukti(): void
    {
        $user = $this->adminAset();
        $this->actingAs($user);
        $tanah = $this->tanah();

        $response = $this->post(route('tanah.retribusi.store', $tanah), [
            'tahun' => 2025,
            'PAD' => 150,
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertRedirect(route('tanah.show', $tanah));

        $history = TanahHistory::where('objek', 'retribusi')->where('aksi', 'CREATE')->first();

        $this->assertNotNull($history);
        $this->assertNotNull($history->foto_bukti);
        $this->assertSame('retribusi', $history->objek);
        $this->assertSame($user->id_user, $history->id_user);
        $this->assertSame('admin_aset', $history->nama_aktor);
        $this->assertNotNull($history->id_retribusi);
        Storage::disk(BuktiOtorisasi::DISK)->assertExists($history->foto_bukti);
    }

    public function test_store_retribusi_tanpa_foto_ditolak(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();

        $response = $this->from(route('tanah.retribusi.create', $tanah))
            ->post(route('tanah.retribusi.store', $tanah), [
                'tahun' => 2025,
                'PAD' => 150,
                'password_otorisasi' => self::PASSWORD,
            ]);

        $response->assertSessionHasErrors('foto_bukti_otentikasi');
        $this->assertDatabaseMissing('retribusi_tanah', ['id_tanah' => $tanah->id_tanah]);
        $this->assertSame(0, TanahHistory::where('objek', 'retribusi')->count());
    }

    public function test_store_retribusi_foto_rusak_ditolak(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();

        $response = $this->from(route('tanah.retribusi.create', $tanah))
            ->post(route('tanah.retribusi.store', $tanah), [
                'tahun' => 2025,
                'PAD' => 150,
                'password_otorisasi' => self::PASSWORD,
                'foto_bukti_otentikasi' => $this->dataUrlRusak(),
            ]);

        $response->assertSessionHasErrors('foto_bukti_otentikasi');
        $this->assertDatabaseMissing('retribusi_tanah', ['id_tanah' => $tanah->id_tanah]);
    }

    // ------------------------------------------------------------------
    // EDIT RETRIBUSI
    // ------------------------------------------------------------------

    public function test_update_retribusi_mencatat_riwayat_dengan_bukti(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();
        $retribusi = $this->retribusi($tanah);

        $response = $this->put(route('tanah.retribusi.update', $retribusi), [
            'tahun' => 2025,
            'PAD' => 275,
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlPngValid(),
        ]);

        $response->assertRedirect(route('tanah.show', $tanah));

        $history = TanahHistory::where('objek', 'retribusi')->where('aksi', 'UPDATE')->first();
        $this->assertNotNull($history);
        $this->assertNotNull($history->foto_bukti);
        Storage::disk(BuktiOtorisasi::DISK)->assertExists($history->foto_bukti);
    }

    // ------------------------------------------------------------------
    // HAPUS RETRIBUSI — bukti dipertahankan setelah baris hilang
    // ------------------------------------------------------------------

    public function test_destroy_retribusi_menyimpan_bukti_setelah_baris_hilang(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();
        $retribusi = $this->retribusi($tanah);
        $idRetribusi = $retribusi->id_retribusi;

        $response = $this->delete(route('tanah.retribusi.destroy', $retribusi), [
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertRedirect(route('tanah.show', $tanah));
        $this->assertDatabaseMissing('retribusi_tanah', ['id_retribusi' => $idRetribusi]);

        $history = TanahHistory::where('objek', 'retribusi')->where('aksi', 'DELETE')->first();
        $this->assertNotNull($history);
        $this->assertSame($idRetribusi, (int) $history->id_retribusi);
        $this->assertNotNull($history->foto_bukti);
        Storage::disk(BuktiOtorisasi::DISK)->assertExists($history->foto_bukti);
    }

    public function test_destroy_retribusi_tanpa_foto_tidak_menghapus(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();
        $retribusi = $this->retribusi($tanah);

        $response = $this->delete(route('tanah.retribusi.destroy', $retribusi), [
            'password_otorisasi' => self::PASSWORD,
        ]);

        $response->assertSessionHasErrors('foto_bukti_otentikasi');
        $this->assertDatabaseHas('retribusi_tanah', ['id_retribusi' => $retribusi->id_retribusi]);
    }

    // ------------------------------------------------------------------
    // AKSES FILE BUKTI
    // ------------------------------------------------------------------

    public function test_bukti_dapat_diakses_admin_aset(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();
        $history = TanahHistory::create([
            'id_tanah' => $tanah->id_tanah,
            'id_user' => auth()->id(),
            'aksi' => 'CREATE',
            'objek' => 'tanah',
            'foto_bukti' => 'bukti-otorisasi/2026/01/contoh.jpg',
        ]);
        Storage::disk(BuktiOtorisasi::DISK)->put('bukti-otorisasi/2026/01/contoh.jpg', 'isi-foto');

        $response = $this->get(route('bukti-otorisasi.show', $history));

        $response->assertOk();
    }

    public function test_bukti_ditolak_untuk_role_tidak_berwenang(): void
    {
        $role = Role::create(['nama_role' => 'Pegawai']);
        $pegawai = User::create([
            'id_role' => $role->id_role,
            'username' => 'pegawai',
            'password' => self::PASSWORD,
            'status_user' => 'aktif',
        ]);
        $this->actingAs($pegawai);

        $tanah = $this->tanah();
        $history = TanahHistory::create([
            'id_tanah' => $tanah->id_tanah,
            'id_user' => $pegawai->id_user,
            'aksi' => 'CREATE',
            'objek' => 'tanah',
            'foto_bukti' => 'bukti-otorisasi/2026/01/contoh.jpg',
        ]);

        $this->get(route('bukti-otorisasi.show', $history))->assertForbidden();
    }

    public function test_bukti_tanpa_foto_menghasilkan_404(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();
        $history = TanahHistory::create([
            'id_tanah' => $tanah->id_tanah,
            'id_user' => auth()->id(),
            'aksi' => 'CREATE',
            'objek' => 'tanah',
            'foto_bukti' => null,
        ]);

        $this->get(route('bukti-otorisasi.show', $history))->assertNotFound();
    }

    // ------------------------------------------------------------------
    // TAMPILAN RIWAYAT
    // ------------------------------------------------------------------

    public function test_riwayat_tanpa_foto_menampilkan_pesan_kosong(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();
        TanahHistory::create([
            'id_tanah' => $tanah->id_tanah,
            'id_user' => auth()->id(),
            'aksi' => 'CREATE',
            'objek' => 'tanah',
            'foto_bukti' => null,
        ]);

        $this->get(route('tanah.show', $tanah))
            ->assertOk()
            ->assertSee('Tidak ada foto bukti untuk aktivitas ini');
    }

    public function test_riwayat_dengan_foto_menampilkan_tombol_lihat_bukti(): void
    {
        $this->actingAs($this->adminAset());
        $tanah = $this->tanah();
        TanahHistory::create([
            'id_tanah' => $tanah->id_tanah,
            'id_user' => auth()->id(),
            'aksi' => 'CREATE',
            'objek' => 'tanah',
            'foto_bukti' => 'bukti-otorisasi/2026/01/contoh.jpg',
        ]);

        $this->get(route('tanah.show', $tanah))
            ->assertOk()
            ->assertSee('Lihat Bukti');
    }
}
