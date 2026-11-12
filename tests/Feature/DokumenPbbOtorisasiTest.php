<?php

namespace Tests\Feature;

use App\Models\DokumenPbbHistory;
use App\Models\DokumenPbbTanah;
use App\Models\Role;
use App\Models\Tanah;
use App\Models\TanahHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\Support\MembuatFotoBukti;
use Tests\TestCase;

/**
 * Pengujian Step-Up Authentication pada penghapusan Dokumen PBB Modul Tanah.
 *
 * Menguji verifikasi password otorisasi, foto kamera live, otorisasi role,
 * pencatatan audit log di DokumenPbbHistory & TanahHistory, serta penghapusan berkas fisik.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class DokumenPbbOtorisasiTest extends TestCase
{
    use MembuatFotoBukti;
    use RefreshDatabase;

    private const PASSWORD = 'password123';

    private function buatUser(string $namaRole, ?int $idSkpd = null): User
    {
        $role = Role::firstOrCreate(['nama_role' => $namaRole]);

        return User::create([
            'id_role' => $role->id_role,
            'id_skpd' => $idSkpd,
            'username' => strtolower(str_replace(' ', '_', $namaRole)).'_'.($idSkpd ?? '0'),
            'password' => self::PASSWORD,
            'status_user' => 'aktif',
        ]);
    }

    private function buatTanah(): Tanah
    {
        return Tanah::create([
            'kib' => '01.01.01.02.099',
            'deskripsi_objek' => 'Tanah Kebun Percobaan',
            'luas_tanah' => 1000,
        ]);
    }

    private function buatDokumen(Tanah $tanah): DokumenPbbTanah
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('sppt_pbb_2025.pdf', 100, 'application/pdf');
        $path = $file->store('dokumen-pbb', 'public');

        return DokumenPbbTanah::create([
            'id_tanah' => $tanah->id_tanah,
            'tahun_pbb' => 2025,
            'file_pbb' => $path,
            'tanggal_upload' => now(),
            'uploaded_by' => 1,
        ]);
    }

    public function test_destroy_dokumen_pbb_dengan_password_dan_foto_valid_oleh_admin_aset_berhasil(): void
    {
        $admin = $this->buatUser('Admin Aset');
        $this->actingAs($admin);

        $tanah = $this->buatTanah();
        $dokumen = $this->buatDokumen($tanah);
        $filePath = $dokumen->file_pbb;

        Storage::disk('public')->assertExists($filePath);

        $response = $this->delete(route('tanah.dokumen.destroy', $dokumen), [
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertRedirect(route('tanah.show', $tanah));
        $response->assertSessionHas('success');

        // Pastikan dokumen terhapus dari database
        $this->assertDatabaseMissing('dokumen_pbb_tanah', [
            'id_pbb' => $dokumen->id_pbb,
        ]);

        // Pastikan riwayat tercatat di DokumenPbbHistory
        $this->assertDatabaseHas('dokumen_pbb_histories', [
            'id_tanah' => $tanah->id_tanah,
            'id_pbb' => $dokumen->id_pbb,
            'id_user' => $admin->id_user,
            'aksi' => 'DELETE',
        ]);

        // Pastikan riwayat tercatat di TanahHistory dengan bukti otorisasi
        $this->assertDatabaseHas('tanah_histories', [
            'id_tanah' => $tanah->id_tanah,
            'id_user' => $admin->id_user,
            'aksi' => 'DELETE',
            'objek' => 'dokumen_pbb',
        ]);

        // Pastikan file fisik terhapus dari storage
        Storage::disk('public')->assertMissing($filePath);
    }

    public function test_destroy_dokumen_pbb_oleh_admin_upt_p2btp_berhasil(): void
    {
        // Admin UPT P2BTP memiliki id_skpd = 6
        $adminUpt = $this->buatUser('Admin UPT', 6);
        $this->actingAs($adminUpt);

        $tanah = $this->buatTanah();
        $dokumen = $this->buatDokumen($tanah);

        $response = $this->delete(route('tanah.dokumen.destroy', $dokumen), [
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertRedirect(route('tanah.show', $tanah));
        $this->assertDatabaseMissing('dokumen_pbb_tanah', [
            'id_pbb' => $dokumen->id_pbb,
        ]);
    }

    public function test_destroy_dokumen_pbb_dengan_password_salah_ditolak(): void
    {
        $admin = $this->buatUser('Admin Aset');
        $this->actingAs($admin);

        $tanah = $this->buatTanah();
        $dokumen = $this->buatDokumen($tanah);

        $response = $this->delete(route('tanah.dokumen.destroy', $dokumen), [
            'password_otorisasi' => 'password-yang-salah',
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ]);

        $response->assertRedirect(route('tanah.show', $tanah->id_tanah));
        $response->assertSessionHasErrors('password_otorisasi');
        $response->assertSessionHas('hapus_pbb_gagal_id', $dokumen->id_pbb);

        // Dokumen tidak terhapus
        $this->assertDatabaseHas('dokumen_pbb_tanah', [
            'id_pbb' => $dokumen->id_pbb,
        ]);
    }

    public function test_destroy_dokumen_pbb_dengan_foto_tidak_valid_ditolak(): void
    {
        $admin = $this->buatUser('Admin Aset');
        $this->actingAs($admin);

        $tanah = $this->buatTanah();
        $dokumen = $this->buatDokumen($tanah);

        $response = $this->delete(route('tanah.dokumen.destroy', $dokumen), [
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => 'bukan-data-url-gambar',
        ]);

        $response->assertRedirect(route('tanah.show', $tanah->id_tanah));
        $response->assertSessionHasErrors('foto_bukti_otentikasi');

        // Dokumen tidak terhapus
        $this->assertDatabaseHas('dokumen_pbb_tanah', [
            'id_pbb' => $dokumen->id_pbb,
        ]);
    }

    public function test_destroy_dokumen_pbb_tanpa_bukti_otorisasi_ditolak(): void
    {
        $admin = $this->buatUser('Admin Aset');
        $this->actingAs($admin);

        $tanah = $this->buatTanah();
        $dokumen = $this->buatDokumen($tanah);

        $response = $this->delete(route('tanah.dokumen.destroy', $dokumen), []);

        $response->assertRedirect(route('tanah.show', $tanah->id_tanah));
        $response->assertSessionHasErrors(['password_otorisasi', 'foto_bukti_otentikasi']);

        // Dokumen tidak terhapus
        $this->assertDatabaseHas('dokumen_pbb_tanah', [
            'id_pbb' => $dokumen->id_pbb,
        ]);
    }

    public function test_destroy_dokumen_pbb_oleh_role_tanpa_akses_ditolak_403(): void
    {
        $tanah = $this->buatTanah();
        $dokumen = $this->buatDokumen($tanah);

        // 1. Admin UPT selain P2BTP (misal PSBP id_skpd = 7)
        $adminUptLain = $this->buatUser('Admin UPT', 7);
        $this->actingAs($adminUptLain);
        $this->delete(route('tanah.dokumen.destroy', $dokumen), [
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ])->assertForbidden();

        // 2. Admin Bidang
        $adminBidang = $this->buatUser('Admin Bidang');
        $this->actingAs($adminBidang);
        $this->delete(route('tanah.dokumen.destroy', $dokumen), [
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ])->assertForbidden();

        // 3. Pegawai
        $pegawai = $this->buatUser('Pegawai');
        $this->actingAs($pegawai);
        $this->delete(route('tanah.dokumen.destroy', $dokumen), [
            'password_otorisasi' => self::PASSWORD,
            'foto_bukti_otentikasi' => $this->dataUrlJpegValid(),
        ])->assertForbidden();
    }
}

