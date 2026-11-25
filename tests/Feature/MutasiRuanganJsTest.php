<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Reproduksi bug submit Mutasi Ruangan di sisi JavaScript.
 *
 * Bug yang pernah terjadi: handler submit modal Mutasi Ruangan berada di blok
 * <script> KEDUA, tetapi memakai variabel `csrf` yang hanya dideklarasikan
 * (const, di dalam IIFE) pada blok <script> PERTAMA. Akibatnya saat tombol
 * "Pindahkan" ditekan terjadi `ReferenceError: csrf is not defined` saat
 * membuat object `headers`, request TIDAK PERNAH terkirim ke server, dan
 * catch generik menampilkan "Koneksi bermasalah. Coba lagi."
 *
 * Test ini menjalankan blok <script> blade lewat harness Node (DOM tiruan)
 * lalu memastikan:
 *   - tidak ada ReferenceError,
 *   - request benar-benar terkirim dengan header CSRF yang benar,
 *   - tidak ada pesan "Koneksi bermasalah" yang muncul.
 *
 * Ditambah pemeriksaan statis (tanpa Node) sebagai jaring pengaman kelas
 * bug yang sama: setiap identifier yang dipakai sebagai token CSRF harus
 * dideklarasikan di blok <script> yang sama.
 */
class MutasiRuanganJsTest extends TestCase
{
    private const BLADE = 'resources/views/aset-ruangan/show.blade.php';

    private const HARNESS = 'tests/Support/js/mutasi-ruangan-harness.js';

    private function nodeBinary(): ?string
    {
        $version = @shell_exec('node --version 2>&1');

        if (is_string($version) && str_starts_with(trim($version), 'v')) {
            return 'node';
        }

        return null;
    }

    /**
     * Jalankan harness Node terhadap blade dan kembalikan hasil JSON.
     */
    private function jalankanHarness(): array
    {
        $node = $this->nodeBinary();

        if (! $node) {
            $this->markTestSkipped('Node.js tidak tersedia di mesin ini.');
        }

        $perintah = escapeshellarg($node)
            . ' ' . escapeshellarg(base_path(self::HARNESS))
            . ' ' . escapeshellarg(base_path(self::BLADE))
            . ' 2>&1';

        $output = shell_exec($perintah);

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray(
            $decoded,
            'Harness JS gagal dijalankan. Output: ' . substr((string) $output, 0, 2000)
        );

        return $decoded;
    }

    public function test_submit_modal_mutasi_ruangan_mengirim_request_dengan_csrf_yang_benar(): void
    {
        $hasil = $this->jalankanHarness();

        // Tidak boleh ada error JavaScript (mis. ReferenceError variabel tak
        // terdefinisi) — inilah akar bug "Koneksi bermasalah".
        $this->assertSame([], $hasil['errors'], 'Terjadi error JavaScript: ' . implode(' | ', $hasil['errors']));

        // Request mutasi benar-benar terkirim (bug lama: fetch tidak pernah dipanggil).
        $mutasiCalls = array_values(array_filter($hasil['fetchCalls'], fn ($c) => ($c['method'] ?? null) === 'POST'));
        $this->assertCount(1, $mutasiCalls, 'Exactly one request harus terkirim.');
        $this->assertSame('POST', $mutasiCalls[0]['method']);

        // Header CSRF harus terisi dengan token dari meta tag.
        $headers = $mutasiCalls[0]['headers'];
        $this->assertArrayHasKey('X-CSRF-TOKEN', $headers);
        $this->assertNotEmpty($headers['X-CSRF-TOKEN']);
        $this->assertSame('TOKEN-CSRF-ABC123', $headers['X-CSRF-TOKEN']);

        // Halaman dimuat ulang setelah mutasi berhasil.
        $this->assertTrue($hasil['reloaded'], 'Halaman harus reload setelah mutasi berhasil.');

        // Tidak boleh memunculkan pesan koneksi generik.
        $this->assertNotContains('Koneksi bermasalah. Coba lagi.', $hasil['alerts']);
        $this->assertSame([], $hasil['alerts'], 'Tidak seharusnya ada alert pada alur sukses.');
    }

    /**
     * Jaring pengaman statis: identifier token CSRF harus dideklarasikan di
     * blok <script> yang sama (blok script terpisah punya cakupan `const`
     * sendiri, seperti di browser).
     */
    public function test_token_csrf_terdeklarasi_di_blok_script_yang_sama(): void
    {
        $src = file_get_contents(base_path(self::BLADE));

        $this->assertIsString($src);

        preg_match_all('/<script\b[^>]*>([\s\S]*?)<\/script>/i', $src, $m);

        $this->assertNotEmpty($m[1], 'Blade harus punya blok <script>.');

        foreach ($m[1] as $index => $block) {
            preg_match_all("/'X-CSRF-TOKEN'\s*:\s*([A-Za-z_$][A-Za-z0-9_$]*)/", $block, $pakai);

            if (! $pakai[1]) {
                continue;
            }

            foreach ($pakai[1] as $identifier) {
                $adaDeklarasi = preg_match(
                    '/\b(?:const|let|var)\s+' . preg_quote($identifier, '/') . '\b/',
                    $block
                ) === 1;

                $this->assertTrue(
                    $adaDeklarasi,
                    "Blok <script> #" . ($index + 1) . " memakai token CSRF `{$identifier}` "
                    . "tanpa deklarasi di blok yang sama — akan memicu ReferenceError saat submit."
                );
            }
        }
    }
}
