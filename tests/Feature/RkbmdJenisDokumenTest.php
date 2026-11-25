<?php

namespace Tests\Feature;

use App\Http\Controllers\RkbmdController;
use App\Models\ArsipRkbmd;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Revisi fitur RKBMD: Jenis Dokumen (Inspektorat / BPKAD) pada unggah arsip
 * dan filter pada modal daftar arsip.
 *
 * Catatan lingkungan:
 *   - Migration `add_jenis_dokumen_to_arsip_rkbmd` BELUM dijalankan. Tes yang
 *     membutuhkan kolom tersebut otomatis di-SKIP (bukan gagal) sampai Anda
 *     menjalankan `php artisan migrate`. Tes validasi & view tetap berjalan.
 *   - Memakai koneksi MySQL nyata dalam transaksi rollback (pola project).
 *   - File fixture dibuat di disk fake (file arsip nyata tidak disentuh).
 */
class RkbmdJenisDokumenTest extends TestCase
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

        Storage::fake(RkbmdController::DISK_ARSIP);
        Storage::fake(RkbmdController::DISK_ARSIP_LAMA);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        return $this->namaDatabasePengujian();
    }

    /**
     * Tes yang menulis `jenis_dokumen` perlu kolom fisik; lewati bila belum
     * dimigrasi. Ini mencegah tes gagal hanya karena migration belum jalan.
     */
    private function wajibKolomJenisDokumen(): void
    {
        if (! Schema::hasColumn('arsip_rkbmd', 'jenis_dokumen')) {
            $this->markTestSkipped('Kolom arsip_rkbmd.jenis_dokumen belum ada (migration belum dijalankan).');
        }
    }

    private function adminAset(): User
    {
        return $this->ambilAtauBuatUserRole('Admin Aset');
    }

    private function skpd(): Skpd
    {
        return Skpd::firstOrCreate(['nama_skpd' => 'UPT P2BTP'], ['jenis_skpd' => 'UPT']);
    }

    private function unggah(array $override = [])
    {
        return $this->post(route('rkbmd.arsip.store'), array_merge([
            'id_skpd' => $this->skpd()->id_skpd,
            'tahun_anggaran' => 2027,
            'tanggal_pengesahan' => '2026-10-01',
            'nama_dokumen' => 'SK Pengesahan RKBMD 2027',
            'file_dokumen' => UploadedFile::fake()->create('sk.pdf', 100, 'application/pdf'),
        ], $override));
    }

    // ------------------------------------------------------------------
    // A. VALIDASI UNGGAH
    // ------------------------------------------------------------------

    public function test_jenis_dokumen_wajib_diisi(): void
    {
        $this->actingAs($this->adminAset());

        $this->unggah(['jenis_dokumen' => ''])
            ->assertSessionHasErrors('jenis_dokumen');
    }

    public function test_jenis_dokumen_di_luar_pilihan_ditolak(): void
    {
        $this->actingAs($this->adminAset());

        $this->unggah(['jenis_dokumen' => 'BPKP'])
            ->assertSessionHasErrors('jenis_dokumen');
    }

    public function test_unggah_inspektorat_tersimpan(): void
    {
        $this->wajibKolomJenisDokumen();
        $this->actingAs($this->adminAset());

        $this->unggah(['jenis_dokumen' => 'Inspektorat'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('arsip_rkbmd', [
            'nama_dokumen' => 'SK Pengesahan RKBMD 2027',
            'jenis_dokumen' => 'Inspektorat',
        ]);
    }

    public function test_unggah_bpkad_tersimpan(): void
    {
        $this->wajibKolomJenisDokumen();
        $this->actingAs($this->adminAset());

        $this->unggah(['jenis_dokumen' => 'BPKAD'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('arsip_rkbmd', [
            'nama_dokumen' => 'SK Pengesahan RKBMD 2027',
            'jenis_dokumen' => 'BPKAD',
        ]);
    }

    // ------------------------------------------------------------------
    // B. TAMPILAN FILTER & BADGE JENIS PADA MODAL
    // ------------------------------------------------------------------

    public function test_modal_unggah_memiliki_dropdown_jenis_wajib(): void
    {
        $this->actingAs($this->adminAset());

        $html = $this->get(route('rkbmd.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="jenis_dokumen"', $html);
        $this->assertStringContainsString('id="arsip-jenis"', $html);
        $this->assertStringContainsString('Inspektorat', $html);
        $this->assertStringContainsString('BPKAD', $html);
        // Wajib diisi (atribut required pada select).
        $this->assertMatchesRegularExpression('/<select[^>]*id="arsip-jenis"[^>]*required/s', $html);
    }

    public function test_modal_daftar_memiliki_filter_jenis(): void
    {
        $this->actingAs($this->adminAset());

        $html = $this->get(route('rkbmd.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="filterJenisArsip"', $html);
        $this->assertStringContainsString('Semua Jenis Dokumen', $html);
    }

    public function test_setiap_item_arsip_memiliki_atribut_jenis_untuk_filter(): void
    {
        $this->wajibKolomJenisDokumen();

        $arsip = ArsipRkbmd::create([
            'id_skpd' => $this->skpd()->id_skpd,
            'tahun_anggaran' => 2026,
            'nama_dokumen' => 'SK Inspektorat',
            'jenis_dokumen' => 'Inspektorat',
            'file_path' => 'arsip-rkbmd/tampil.pdf',
        ]);

        $this->actingAs($this->adminAset());

        $html = $this->get(route('rkbmd.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-jenis="inspektorat"', $html);
        $this->assertStringContainsString('data-tahun="'.$arsip->tahun_anggaran.'"', $html);
        // Badge jenis tampil di item.
        $this->assertStringContainsString('Inspektorat', $html);
    }

    public function test_arsip_lama_tanpa_jenis_tetap_tampil(): void
    {
        $arsip = ArsipRkbmd::create([
            'id_skpd' => $this->skpd()->id_skpd,
            'tahun_anggaran' => 2025,
            'nama_dokumen' => 'Arsip Lama',
            'file_path' => 'arsip-rkbmd/lama-tampil.pdf',
        ]);

        $this->actingAs($this->adminAset());

        $html = $this->get(route('rkbmd.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-jenis=""', $html);
        $this->assertStringContainsString('Arsip Lama', $html);
    }

    // ------------------------------------------------------------------
    // C. FILTER JS BENAR-BENAR MENYARING (dijalankan lewat Node)
    // ------------------------------------------------------------------

    public function test_logika_filter_js_menyaring_jenis_tahun_dan_bidang_bersamaan(): void
    {
        $node = @shell_exec('node --version 2>&1');
        if (! is_string($node) || ! str_starts_with(trim($node), 'v')) {
            $this->markTestSkipped('Node.js tidak tersedia di mesin ini.');
        }

        $blade = base_path('resources/views/rkbmd/partials/modal-list-arsip.blade.php');
        $js = $this->ekstrakFungsiFilterJs($blade);

        $harness = <<<'JS'
const fs = require('fs');
const src = fs.readFileSync(process.argv[2], 'utf8');

const items = [
  { title: 'sk inspektorat 2026', tahun: '2026', jenis: 'inspektorat', bidang: 'upt p2btp' },
  { title: 'sk bpkad 2026',        tahun: '2026', jenis: 'bpkad',       bidang: 'upt p2btp' },
  { title: 'sk bpkad 2025',        tahun: '2025', jenis: 'bpkad',       bidang: 'bidang a' },
  { title: 'sk inspektorat 2025',  tahun: '2025', jenis: 'inspektorat', bidang: 'bidang a' },
];

let created = [];
function makeItem(d) {
  const attrs = { 'data-title': d.title, 'data-tahun': d.tahun, 'data-jenis': d.jenis, 'data-bidang': d.bidang };
  return {
    _hidden: false,
    classList: { add(){ this._h=true; }, remove(){ this._h=false; } },
    _attrs: attrs,
    getAttribute(k){ return attrs[k] || ''; },
    set hidden(v){ this._hidden = v; },
  };
}
created = items.map(makeItem);

const els = {
  searchArsipInput: { value: '' },
  filterTahunArsip: { value: '' },
  filterJenisArsip: { value: '' },
  filterBidangArsip: { value: '' },
  noMatchArsip: { classList: { toggle(){} } },
  totalArsipLabel: { textContent: '' },
};

global.document = {
  getElementById: (id) => els[id] || null,
  querySelectorAll: (sel) => (sel === '.arsip-item' ? created : []),
};

const fn = new Function(src + '; return filterArsipItems;')();
function jalankan(q) {
  els.searchArsipInput.value = q.search || '';
  els.filterTahunArsip.value = q.tahun || '';
  els.filterJenisArsip.value = q.jenis || '';
  els.filterBidangArsip.value = q.bidang || '';
  fn();
  return created.filter(it => !it.classList._h).map(it => it.getAttribute('data-title'));
}

const hasil = {
  semua: jalankan({}),
  inspektorat: jalankan({ jenis: 'Inspektorat' }),
  bpkad: jalankan({ jenis: 'BPKAD' }),
  bpkad2025: jalankan({ jenis: 'BPKAD', tahun: '2025' }),
  bpkadUpt: jalankan({ jenis: 'BPKAD', bidang: 'UPT P2BTP' }),
  inspektoratCari: jalankan({ jenis: 'Inspektorat', search: 'inspektorat 2025' }),
};
process.stdout.write(JSON.stringify(hasil));
JS;

        $tmp = tempnam(sys_get_temp_dir(), 'rkbmdjs');
        file_put_contents($tmp, $js);

        try {
            $harnessFile = tempnam(sys_get_temp_dir(), 'harness');
            file_put_contents($harnessFile, $harness);

            $cmd = 'node '.escapeshellarg($harnessFile).' '.escapeshellarg($tmp).' 2>&1';
            $out = trim((string) shell_exec($cmd));
            $hasil = json_decode($out, true);

            $this->assertIsArray($hasil, 'Harness JS gagal dijalankan: '.$out);

            $this->assertCount(4, $hasil['semua']);
            $this->assertSame(['sk inspektorat 2026', 'sk inspektorat 2025'], $hasil['inspektorat']);
            $this->assertSame(['sk bpkad 2026', 'sk bpkad 2025'], $hasil['bpkad']);
            $this->assertSame(['sk bpkad 2025'], $hasil['bpkad2025']);
            $this->assertSame(['sk bpkad 2026'], $hasil['bpkadUpt']);
            $this->assertSame(['sk inspektorat 2025'], $hasil['inspektoratCari']);
        } finally {
            @unlink($tmp);
            @unlink($harnessFile);
        }
    }

    /**
     * Ambil isi fungsi `filterArsipItems()` dari blade agar diuji nyata.
     */
    private function ekstrakFungsiFilterJs(string $bladePath): string
    {
        $isi = file_get_contents($bladePath);

        $this->assertSame(1, preg_match('/function\s+filterArsipItems\(\)\s*\{.*?\n        \}/s', $isi, $m),
            'Fungsi filterArsipItems() tidak ditemukan pada blade.');

        return $m[0];
    }

    // ------------------------------------------------------------------
    // D. OTORISASI & ENDPOINT TERLINDUNGI TETAP BERJALAN
    // ------------------------------------------------------------------

    public function test_arsip_dengan_jenis_tetap_melalui_endpoint_terlindungi(): void
    {
        $this->wajibKolomJenisDokumen();

        $path = 'arsip-rkbmd/terlindungi.pdf';
        Storage::disk(RkbmdController::DISK_ARSIP)->put($path, '%PDF-1.4 isi');

        $arsip = ArsipRkbmd::create([
            'id_skpd' => $this->skpd()->id_skpd,
            'tahun_anggaran' => 2026,
            'nama_dokumen' => 'SK BPKAD',
            'jenis_dokumen' => 'BPKAD',
            'file_path' => $path,
        ]);

        $this->actingAs($this->adminAset());

        $this->get(route('rkbmd.arsip.preview', $arsip))->assertOk();
        $this->get(route('rkbmd.arsip.download', $arsip))->assertOk();

        // Tidak ada URL publik langsung pada halaman.
        $html = $this->get(route('rkbmd.index'))->getContent();
        $this->assertStringNotContainsString('/storage/arsip-rkbmd', $html);
    }
}
