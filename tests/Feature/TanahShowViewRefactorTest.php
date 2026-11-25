<?php

namespace Tests\Feature;

use App\Models\Tanah;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Pengujian rendering Blade untuk halaman Detail Tanah pasca refactor Tahap 3B.
 *
 * Memastikan pemecahan ke folder resources/views/tanah/detail/ berjalan utuh:
 * - hero.blade.php
 * - informasi.blade.php
 * - retribusi.blade.php
 * - dokumen-pbb.blade.php
 * - riwayat.blade.php
 * - modal-retribusi.blade.php
 * - modal-dokumen-pbb.blade.php
 * - modal-bukti.blade.php
 * - scripts.blade.php
 */
class TanahShowViewRefactorTest extends TestCase
{
    private function dummyTanah(): Tanah
    {
        $tanah = new Tanah([
            'kib' => '01.01.01.02.999',
            'luas_tanah' => 1250.50,
            'nilai_perolehan' => 750000000,
            'status_hak' => 'Hak Pakai',
            'nomor_sertifikat' => 'HP-123456',
            'deskripsi_objek' => 'Lahan Perkebunan Percobaan',
            'penggunaan' => 'Kebun Percobaan',
            'nama_petugas' => 'Budi Santoso',
            'nomor_hp_petugas' => '08123456789',
            'kondisi' => 'Baik',
            'ketkel' => 'Kec. Sukajadi',
            'alamat' => 'Jl. Kebun Percobaan No. 10',
            'keterangan' => 'Aset produktif',
        ]);
        $tanah->id_tanah = 999;
        $tanah->exists = true;

        $tanah->setRelation('retribusi', new Collection);
        $tanah->setRelation('dokumenPbb', new Collection);
        $tanah->setRelation('histories', new Collection);

        return $tanah;
    }

    private function renderShow(Tanah $tanah, ?User $user = null): string
    {
        if ($user) {
            $this->actingAs($user);
        }

        return view('tanah.show', [
            'tanah' => $tanah,
            'lastEdit' => null,
            'errors' => new ViewErrorBag,
        ])->render();
    }

    public function test_show_blade_merender_seluruh_tab_dan_hero_dengan_benar(): void
    {
        $admin = new User(['username' => 'admin_aset']);
        $admin->setRelation('role', new Role(['nama_role' => 'Admin Aset']));

        $tanah = $this->dummyTanah();
        $html = $this->renderShow($tanah, $admin);

        // Header & Breadcrumb
        $this->assertStringContainsString('Detail Tanah', $html);
        $this->assertStringContainsString('Inventaris Tanah • Detail Aset', $html);

        // Hero (detail/hero.blade.php)
        $this->assertStringContainsString('01.01.01.02.999', $html);
        $this->assertStringContainsString('Lahan Perkebunan Percobaan', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('08123456789', $html);

        // Tab Navigation Pills
        $this->assertStringContainsString('data-tab-target="tab-kib"', $html);
        $this->assertStringContainsString('data-tab-target="tab-retribusi"', $html);
        $this->assertStringContainsString('data-tab-target="tab-pbb"', $html);
        $this->assertStringContainsString('data-tab-target="tab-history"', $html);

        // Tab 1: KIB & Legalitas (detail/informasi.blade.php)
        $this->assertStringContainsString('id="tab-kib"', $html);
        $this->assertStringContainsString('Data KIB A &amp; Legalitas', $html);
        $this->assertStringContainsString('1.250,50 m²', $html);
        $this->assertStringContainsString('Hak Pakai', $html);
        $this->assertStringContainsString('HP-123456', $html);

        // Tab 2: Retribusi (detail/retribusi.blade.php)
        $this->assertStringContainsString('id="tab-retribusi"', $html);
        $this->assertStringContainsString('Pemanfaatan &amp; Retribusi', $html);
        $this->assertStringContainsString('Realisasi PAD', $html);

        // Tab 3: Dokumen PBB (detail/dokumen-pbb.blade.php)
        $this->assertStringContainsString('id="tab-pbb"', $html);
        $this->assertStringContainsString('Dokumen PBB', $html);

        // Tab 4: Riwayat Perubahan (detail/riwayat.blade.php)
        $this->assertStringContainsString('id="tab-history"', $html);
        $this->assertStringContainsString('Riwayat Perubahan', $html);

        // Modals Admin (detail/modal-dokumen-pbb, detail/modal-bukti)
        $this->assertStringContainsString('id="dokumen-pbb-modal"', $html);
        $this->assertStringContainsString('id="hapus-pbb-modal"', $html);
        $this->assertStringContainsString('id="bukti-modal"', $html);

        // Scripts (detail/scripts.blade.php)
        $this->assertStringContainsString('activateTab', $html);
        $this->assertStringContainsString('bukaHapusPbb', $html);
        $this->assertStringContainsString('bukaBukti', $html);
    }

    public function test_modal_admin_tidak_dirender_untuk_pengguna_bukan_admin(): void
    {
        $pegawai = new User(['username' => 'pegawai_biasa']);
        $pegawai->setRelation('role', new Role(['nama_role' => 'Pegawai']));

        $tanah = $this->dummyTanah();
        $html = $this->renderShow($tanah, $pegawai);

        // Modals khusus admin tidak boleh muncul
        $this->assertStringNotContainsString('id="dokumen-pbb-modal"', $html);
        $this->assertStringNotContainsString('id="hapus-pbb-modal"', $html);

        // Modal bukti otorisasi tetap tersedia untuk melihat transparansi riwayat
        $this->assertStringContainsString('id="bukti-modal"', $html);
    }

    public function test_show_blade_merender_baris_retribusi_dokumen_pbb_dan_riwayat(): void
    {
        $admin = new User(['username' => 'admin_aset']);
        $admin->setRelation('role', new Role(['nama_role' => 'Admin Aset']));

        $tanah = $this->dummyTanah();

        $retribusi = new \App\Models\RetribusiTanah([
            'id_retribusi' => 50,
            'id_tanah' => 999,
            'tahun' => 2025,
            'status_pemanfaatan' => 'Sewa Kebun',
            'biaya_pengurusan' => 1500000,
            'PAD' => 25000000,
            'tarif_retribusi' => 100000,
            'total_tarif_sewa' => 25000000,
            'satuan' => 'Pohon/Tahun',
        ]);
        $retribusi->id_retribusi = 50;
        $retribusi->exists = true;

        $dokumen = new \App\Models\DokumenPbbTanah([
            'id_tanah' => 999,
            'tahun_pbb' => 2024,
            'file_pbb' => 'dokumen_pbb/sppt_2024.pdf',
            'uploaded_by' => 1,
        ]);
        $dokumen->id_pbb = 77;
        $dokumen->exists = true;
        $dokumen->setRelation('uploader', $admin);

        $history = new \App\Models\TanahHistory([
            'id_history' => 88,
            'id_tanah' => 999,
            'aksi' => 'UPDATE',
            'objek' => 'retribusi',
            'id_objek' => 50,
            'data_lama' => ['PAD' => 20000000],
            'data_baru' => ['PAD' => 25000000],
            'foto_bukti' => 'bukti_otorisasi/bukti_88.jpg',
            'created_at' => now(),
        ]);
        $history->id_history = 88;
        $history->exists = true;
        $history->setRelation('user', $admin);

        $tanah->setRelation('retribusi', new Collection([$retribusi]));
        $tanah->setRelation('dokumenPbb', new Collection([$dokumen]));
        $tanah->setRelation('histories', new Collection([$history]));

        $html = $this->renderShow($tanah, $admin);

        // Baris retribusi (tombol Edit menuju form edit retribusi, hapus retribusi ditiadakan)
        $this->assertStringContainsString('Sewa Kebun', $html);
        $this->assertStringContainsString('Rp 25.000.000', $html);
        $this->assertStringContainsString(route('tanah.retribusi.edit', $retribusi), $html);
        $this->assertStringContainsString('Edit', $html);

        // Baris dokumen PBB
        $this->assertStringContainsString('2024', $html);
        $this->assertStringContainsString('data-open-hapus-pbb', $html);

        // Baris riwayat & tombol bukti
        $this->assertStringContainsString('data-buka-bukti', $html);
        $this->assertStringContainsString('data-bukti=', $html);
    }
}
