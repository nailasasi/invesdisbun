<?php

namespace Tests\Unit;

use App\Support\Word\SppbiTemplate;
use App\Support\Word\WordTemplateFiller;
use Tests\TestCase;

/**
 * Integrasi generator SPPBI dengan template SPPBI aktif.
 *
 * Test ini murni lokal: membaca berkas template aktif di storage (tanpa
 * database) lalu merender dokumen contoh. Bila berkas template tidak ada,
 * test dilewati — bukan gagal — agar suite tetap jalan dilingkungan tanpa
 * storage terisi.
 */
class SppbiTemplateTest extends TestCase
{
    private function templateAktif(): ?string
    {
        $pattern = function_exists('storage_path') && app() instanceof \Illuminate\Contracts\Foundation\Application
            ? storage_path('app/public/templates/sppbi_*.docx')
            : dirname(__DIR__, 2).'/storage/app/public/templates/sppbi_*.docx';

        $files = glob($pattern) ?: [];
        $files = array_values(array_filter($files, 'is_file'));

        if ($files === []) {
            return null;
        }

        // Template terbaru = upload terakhir.
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    private function teksDokumen(string $path): string
    {
        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        preg_match_all('~<w:t\b[^>]*>(.*?)</w:t>~s', $xml, $m);

        return implode('', $m[1]);
    }

    private function barisContoh(): array
    {
        return [
            [
                'jenis' => 'Laptop Asus', 'merk' => 'ASUS', 'type' => '-',
                'tanggal' => '13', 'bulan' => 'Maret', 'tahun' => '2025',
                'kode' => 'KIB-001', 'tahun_pengadaan' => '2025', 'jumlah' => 1,
                'register' => 'KIB-001', 'harga' => '17300993700', 'nominal' => 17300993700,
            ],
            [
                'jenis' => 'Kursi Guru', 'merk' => 'HARMONI', 'type' => '-',
                'tanggal' => '14', 'bulan' => 'Maret', 'tahun' => '2025',
                'kode' => 'KIB-002', 'tahun_pengadaan' => '2025', 'jumlah' => 1,
                'register' => 'KIB-002', 'harga' => '39460000', 'nominal' => 39460000,
            ],
            [
                'jenis' => 'Meja Rapat', 'merk' => 'LONGWOOD', 'type' => '-',
                'tanggal' => '15', 'bulan' => 'April', 'tahun' => '2026',
                'kode' => 'KIB-003', 'tahun_pengadaan' => '2026', 'jumlah' => 1,
                'register' => 'KIB-003', 'harga' => '-', 'nominal' => 0,
            ],
        ];
    }

    private function render(array $rows): string
    {
        $template = $this->templateAktif();
        $this->assertNotNull($template, 'Template SPPBI aktif tidak ditemukan di storage.');

        return WordTemplateFiller::make($template)->render(
            values: [
                '{Nama ASN}' => 'BUDI SANTOSO',
                '{NAMA ASN}' => 'BUDI SANTOSO',
                '{NIP ASN}' => '19700101 199001 1 001',
                '{Jabatan ASN}' => 'Penata Laksana Barang',
                '{Tanggal, Bulan Tahun}' => '22 Oktober 2026',
            ],
            options: [
                'blocks' => [[
                    'start_marker' => SppbiTemplate::BLOCK_START,
                    'end_marker' => SppbiTemplate::BLOCK_END,
                    'currency_token' => SppbiTemplate::CURRENCY_TOKEN,
                    'tokens' => SppbiTemplate::BLOCK_TOKENS,
                    'rows' => $rows,
                ]],
            ],
        );
    }

    public function test_terbilang_rupiah(): void
    {
        $this->assertSame('', WordTemplateFiller::terbilangRupiah(0));
        $this->assertSame('', WordTemplateFiller::terbilangRupiah(null));
        $this->assertSame('satu', WordTemplateFiller::terbilangRupiah(1));
        $this->assertSame('dua belas', WordTemplateFiller::terbilangRupiah(12));
        $this->assertSame('seratus satu ribu', WordTemplateFiller::terbilangRupiah(101000));
        $this->assertSame(
            'tiga puluh sembilan juta empat ratus enam puluh ribu',
            WordTemplateFiller::terbilangRupiah(39460000)
        );
        $this->assertSame(
            'tujuh belas miliar tiga ratus juta sembilan ratus sembilan puluh tiga ribu tujuh ratus',
            WordTemplateFiller::terbilangRupiah(17300993700)
        );
    }

    public function test_semua_placeholder_template_aktif_dikenali_generator(): void
    {
        $template = $this->templateAktif();
        if (! $template) {
            $this->markTestSkipped('Template SPPBI aktif tidak ada di storage.');
        }

        $text = $this->teksDokumen($template);
        preg_match_all('~\{[^{}]*\}~', $text, $m);

        $placeholderTemplate = array_values(array_unique($m[0]));
        $dikenal = SppbiTemplate::semuaToken();

        foreach ($placeholderTemplate as $placeholder) {
            $this->assertContains(
                $placeholder,
                $dikenal,
                "Placeholder '{$placeholder}' pada template SPPBI aktif belum dikenali generator."
            );
        }
    }

    public function test_render_mengisi_semua_placeholder_tanpa_sisa(): void
    {
        if (! $this->templateAktif()) {
            $this->markTestSkipped('Template SPPBI aktif tidak ada di storage.');
        }

        $out = $this->render($this->barisContoh());
        $text = $this->teksDokumen($out);
        @unlink($out);

        preg_match_all('~\{[^{}]*\}~', $text, $m);
        $this->assertSame([], $m[0], 'Masih ada placeholder yang tidak terisi di dokumen SPPBI.');
    }

    public function test_render_mengulang_blok_spesifikasi_per_barang(): void
    {
        if (! $this->templateAktif()) {
            $this->markTestSkipped('Template SPPBI aktif tidak ada di storage.');
        }

        $rows = $this->barisContoh();
        $out = $this->render($rows);
        $text = $this->teksDokumen($out);
        @unlink($out);

        $this->assertSame(
            count($rows),
            substr_count($text, 'Spesfikasi Teknis'),
            'Jumlah blok spesifikasi harus sama dengan jumlah aset yang memenuhi syarat.'
        );

        foreach ($rows as $row) {
            $this->assertStringContainsString($row['jenis'], $text);
            $this->assertStringContainsString($row['merk'], $text);
            $this->assertStringContainsString($row['kode'], $text);
        }
    }

    public function test_render_mengisi_identitas_asn_dan_tanggal(): void
    {
        if (! $this->templateAktif()) {
            $this->markTestSkipped('Template SPPBI aktif tidak ada di storage.');
        }

        $out = $this->render($this->barisContoh());
        $text = $this->teksDokumen($out);
        @unlink($out);

        // Halaman 1 + Pakta Integritas (3 kemunculan nama & NIP).
        $this->assertSame(3, substr_count($text, 'BUDI SANTOSO'));
        $this->assertSame(3, substr_count($text, '19700101 199001 1 001'));
        $this->assertStringContainsString('Penata Laksana Barang', $text);
        $this->assertSame(2, substr_count($text, '22 Oktober 2026'));
    }

    public function test_render_mengisi_terbilang_harga_satuan(): void
    {
        if (! $this->templateAktif()) {
            $this->markTestSkipped('Template SPPBI aktif tidak ada di storage.');
        }

        $out = $this->render($this->barisContoh());
        $text = $this->teksDokumen($out);
        @unlink($out);

        $this->assertStringContainsString('Rp. 17300993700', $text);
        $this->assertStringContainsString(
            'tujuh belas miliar tiga ratus juta sembilan ratus sembilan puluh tiga ribu tujuh ratus',
            $text
        );
        $this->assertStringContainsString('Rp. 39460000', $text);
        $this->assertStringContainsString(
            'tiga puluh sembilan juta empat ratus enam puluh ribu',
            $text
        );
    }
}
