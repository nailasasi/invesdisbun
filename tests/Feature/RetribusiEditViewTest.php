<?php

namespace Tests\Feature;

use App\Models\RetribusiTanah;
use App\Models\Role;
use App\Models\Tanah;
use App\Models\User;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Tampilan halaman Edit Retribusi Tanah disamakan dengan halaman Tambah:
 * gaya input/kartu/tombol identik, data lama tetap terisi, dan password
 * otorisasi selalu kosong serta tidak pernah diisi ulang.
 *
 * Pengujian hanya merender Blade (tanpa database).
 */
class RetribusiEditViewTest extends TestCase
{
    private function adminAset(): User
    {
        $user = new User(['username' => 'admin', 'status_user' => 'aktif']);
        $user->setRelation('role', new Role(['nama_role' => 'Admin Aset']));
        $user->setRelation('pegawai', null);

        return $user;
    }

    private function retribusi(array $atribut = []): RetribusiTanah
    {
        $retribusi = new RetribusiTanah;
        $retribusi->forceFill(array_merge([
            'tahun' => 2024,
            'status_pemanfaatan' => 'Disewakan',
            'biaya_pengurusan' => 500000,
            'PAD' => 1000000,
            'tarif_retribusi' => 250000,
            'total_tarif_sewa' => 5000000,
            'satuan' => 'per meter/tahun',
            'keterangan' => 'Catatan lama retribusi',
        ], $atribut));
        $retribusi->id_retribusi = 7;

        return $retribusi;
    }

    private function tanah(): Tanah
    {
        $tanah = new Tanah;
        $tanah->forceFill(['kib' => '01.01.01.01.001', 'deskripsi_objek' => 'Tanah Uji']);
        $tanah->id_tanah = 3;

        return $tanah;
    }

    private function renderEdit(RetribusiTanah $retribusi, ?Tanah $tanah = null): string
    {
        return view('tanah.retribusi.edit', [
            'retribusi' => $retribusi,
            'tanah' => $tanah ?? $this->tanah(),
            'errors' => new ViewErrorBag,
        ])->render();
    }

    private function renderCreate(Tanah $tanah): string
    {
        return view('tanah.retribusi.create', [
            'tanah' => $tanah,
            'errors' => new ViewErrorBag,
        ])->render();
    }

    public function test_edit_menampilkan_data_lama(): void
    {
        $this->actingAs($this->adminAset());
        $this->withoutVite();

        $html = $this->renderEdit($this->retribusi());

        // Nilai lama tetap terisi (kolom decimal di-cast decimal:2).
        $this->assertStringContainsString('value="2024"', $html);
        $this->assertStringContainsString('value="500000.00"', $html);
        $this->assertStringContainsString('value="1000000.00"', $html);
        $this->assertStringContainsString('value="250000.00"', $html);
        $this->assertStringContainsString('value="5000000.00"', $html);
        $this->assertStringContainsString('Catatan lama retribusi', $html);

        // Opsi terpilih mengikuti data lama.
        $this->assertMatchesRegularExpression('/value="Disewakan"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/value="per meter\/tahun"\s+selected/', $html);
    }

    public function test_password_otorisasi_kosong_dan_tidak_terisi_ulang(): void
    {
        $this->actingAs($this->adminAset());
        $this->withoutVite();

        $html = $this->renderEdit($this->retribusi());

        preg_match('/<input[^>]*name="password_otorisasi"[^>]*>/', $html, $cocok);

        $this->assertNotEmpty($cocok, 'Input password otorisasi tidak ditemukan pada halaman Edit.');
        $this->assertStringNotContainsString('value=', $cocok[0], 'Password otorisasi tidak boleh terisi otomatis.');
        $this->assertStringContainsString('required', $cocok[0]);
    }

    public function test_field_password_tidak_pernah_memakai_old_input(): void
    {
        $sumber = file_get_contents(resource_path('views/tanah/retribusi/partials/form.blade.php'));

        $this->assertStringNotContainsString("old('password_otorisasi'", $sumber);
        $this->assertStringNotContainsString('old("password_otorisasi"', $sumber);
    }

    public function test_edit_mempertahankan_status_dan_satuan_lama_yang_belum_ada_di_opsi(): void
    {
        $this->actingAs($this->adminAset());
        $this->withoutVite();

        $html = $this->renderEdit($this->retribusi([
            'status_pemanfaatan' => 'Khusus',
            'satuan' => 'per tahun',
        ]));

        $this->assertMatchesRegularExpression('/value="Khusus"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/value="per tahun"\s+selected/', $html);
    }

    public function test_edit_mempertahankan_action_method_csrf_dan_route_update(): void
    {
        $this->actingAs($this->adminAset());
        $this->withoutVite();

        $html = $this->renderEdit($this->retribusi());

        $this->assertStringContainsString(
            'action="'.route('tanah.retribusi.update', 7).'"',
            $html
        );
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
    }

    public function test_edit_memakai_gaya_tampilan_yang_sama_dengan_tambah(): void
    {
        $this->actingAs($this->adminAset());
        $this->withoutVite();

        $tanah = $this->tanah();
        $create = $this->renderCreate($tanah);
        $edit = $this->renderEdit($this->retribusi(), $tanah);

        $penandaDesainBersama = [
            // Kartu "Data Retribusi".
            'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm',
            // Grid dua kolom.
            'grid grid-cols-1 gap-5 p-6 md:grid-cols-2',
            // Gaya input/select/textarea.
            'rounded-lg border border-slate-300 px-3 py-2.5',
            // Label.
            'mb-2 block text-sm font-medium text-slate-700',
            // Tombol simpan.
            'rounded-lg bg-slate-900 px-5 py-2.5',
            // Tombol batal.
            'rounded-lg border border-slate-300 bg-white px-5 py-2.5',
            // Pembungkus responsif.
            'mx-auto max-w-4xl px-4 sm:px-6 lg:px-8',
        ];

        foreach ($penandaDesainBersama as $penanda) {
            $this->assertStringContainsString($penanda, $create, "Halaman Tambah tidak memakai: {$penanda}");
            $this->assertStringContainsString($penanda, $edit, "Halaman Edit tidak memakai: {$penanda}");
        }

        // Kartu otorisasi hanya muncul di halaman Edit, tidak pada halaman Tambah.
        $kartuOtorisasi = 'overflow-hidden rounded-xl border border-amber-200 bg-amber-50/40 shadow-sm';
        $this->assertStringContainsString($kartuOtorisasi, $edit, "Halaman Edit wajib memiliki kartu otorisasi.");
        $this->assertStringNotContainsString($kartuOtorisasi, $create, "Halaman Tambah tidak boleh memiliki kartu otorisasi.");
        $this->assertStringNotContainsString('name="password_otorisasi"', $create, "Halaman Tambah tidak boleh meminta password otorisasi.");
        $this->assertStringNotContainsString('name="foto_bukti_otentikasi"', $create, "Halaman Tambah tidak boleh meminta foto bukti otentikasi.");
    }

    public function test_edit_tidak_menghilangkan_field_apa_pun(): void
    {
        $this->actingAs($this->adminAset());
        $this->withoutVite();

        $html = $this->renderEdit($this->retribusi([
            'status_pemanfaatan' => 'Pakai',
            'satuan' => 'lainnya',
        ]));

        foreach (['tahun', 'status_pemanfaatan', 'biaya_pengurusan', 'PAD', 'tarif_retribusi', 'total_tarif_sewa', 'satuan', 'keterangan', 'password_otorisasi'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "Field {$field} hilang pada halaman Edit.");
        }
    }
}
