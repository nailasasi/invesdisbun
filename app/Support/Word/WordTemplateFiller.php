<?php

namespace App\Support\Word;

use PhpOffice\PhpWord\TemplateProcessor;
use ReflectionClass;

/**
 * Pengisi template .docx (PhpWord) yang tahan terhadap placeholder yang
 * terpecah lintas <w:r>/<w:t> oleh Word, plus kloning baris tabel berulang.
 *
 * Cara pakai:
 *   WordTemplateFiller::make($pathTemplate)->render(
 *       values: ['nama' => 'Budi'],
 *       rows: [['nama' => 'Kursi']],
 *       options: [
 *           'row_macro' => 'barang',
 *           'row_tokens' => ['nama_barang' => 'nama'],
 *           'patterns'   => ['~Nomor:\s*[\d\/.]+~' => 'Nomor: 123'],
 *       ],
 *   );
 */
class WordTemplateFiller
{
    private const PARTS = [
        'tempDocumentMainPart',
        'tempDocumentHeaders',
        'tempDocumentFooters',
    ];

    public function __construct(private readonly string $templatePath) {}

    public static function make(string $templatePath): self
    {
        return new self($templatePath);
    }

    /**
     * Hasilkan berkas .docx sementara yang sudah terisi.
     */
    public function render(array $values, array $rows = [], array $options = []): string
    {
        $rowMacro = $options['row_macro'] ?? 'barang';
        $rowTokens = $options['row_tokens'] ?? [];
        $patterns = $options['patterns'] ?? [];

        $workingCopy = $this->workingCopy();
        $processor = new TemplateProcessor($workingCopy);

        if ($rows !== [] && $rowTokens !== []) {
            $this->expandRows($processor, $rowMacro, $rowTokens, $rows);
        }

        $this->applyReplacements($processor, $values, $patterns);

        $output = tempnam(sys_get_temp_dir(), 'word_');
        $processor->saveAs($output);
        @unlink($workingCopy);

        return $output;
    }

    /**
     * Salin template ke file sementara agar berkas master tidak pernah berubah.
     */
    private function workingCopy(): string
    {
        $target = tempnam(sys_get_temp_dir(), 'tpl_');

        if (! copy($this->templatePath, $target)) {
            throw new \RuntimeException('Gagal menyalin template dokumen: '.$this->templatePath);
        }

        return $target;
    }

    /**
     * Kloning blok baris tabel yang ditandai {#macro}...{/macro} menjadi satu
     * blok per data. Penanda boleh berada di baris yang sama dengan data
     * (gaya cloneRow) maupun di baris pembatas tersendiri (gaya block).
     */
    private function expandRows(TemplateProcessor $processor, string $macro, array $rowTokens, array $rows): void
    {
        $open = '{#'.$macro.'}';
        $close = '{/'.$macro.'}';

        $xml = $this->readPart($processor, 'tempDocumentMainPart');
        if (! is_string($xml) || ! str_contains($this->runTextOf($xml), $open)) {
            return;
        }

        $blocks = 0;
        while ($blocks < 50) {
            $expanded = $this->expandBlock($xml, $open, $close, $rowTokens, $rows);
            if ($expanded === null) {
                break;
            }

            $xml = $expanded;
            $blocks++;
        }

        if ($blocks > 0) {
            $this->writePart($processor, 'tempDocumentMainPart', $xml);
        }
    }

    /**
     * Perluas satu blok pertama yang ditandai penanda open/close.
     */
    private function expandBlock(string $xml, string $open, string $close, array $rowTokens, array $rows): ?string
    {
        if (! preg_match_all('~<w:tr\b[^>]*>.*?</w:tr>~s', $xml, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $count = count($matches[0]);
        $rows_ = $matches[0];

        $isPlainRow = function (string $rowXml): bool {
            return preg_match_all('~<w:tr\b~', $rowXml) === 1;
        };

        $openIndex = null;
        for ($i = 0; $i < $count; $i++) {
            if ($isPlainRow($rows_[$i][0]) && str_contains($this->runTextOf($rows_[$i][0]), $open)) {
                $openIndex = $i;
                break;
            }
        }

        if ($openIndex === null) {
            return null;
        }

        $closeIndex = null;
        for ($i = $openIndex; $i < $count; $i++) {
            if ($isPlainRow($rows_[$i][0]) && str_contains($this->runTextOf($rows_[$i][0]), $close)) {
                $closeIndex = $i;
                break;
            }
        }

        if ($closeIndex === null) {
            return null;
        }

        $inner = $openIndex === $closeIndex
            ? [$rows_[$openIndex][0]]
            : ($closeIndex - $openIndex < 2 ? [] : array_map(fn ($i) => $rows_[$i][0], range($openIndex + 1, $closeIndex - 1)));

        if ($inner === []) {
            return null;
        }

        $block = $this->replaceFragmentedPlaceholders(implode('', $inner), [$open => '', $close => '']);

        $built = '';
        foreach (array_values($rows) as $index => $row) {
            $built .= $this->replaceFragmentedPlaceholders($block, $this->rowSwap($rowTokens, $row, $index + 1));
        }

        $spanStart = $rows_[$openIndex][1];
        $spanEnd = $rows_[$closeIndex][1] + strlen($rows_[$closeIndex][0]);

        return substr($xml, 0, $spanStart).$built.substr($xml, $spanEnd);
    }

    /**
     * Peta placeholder satu baris tabel + nomor urut otomatis.
     */
    private function rowSwap(array $rowTokens, array $row, int $number): array
    {
        $swap = ['{nomor}' => (string) $number];

        foreach ($rowTokens as $token => $key) {
            $swap['{'.$token.'}'] = (string) ($row[$key] ?? '-');
        }

        return $swap;
    }

    /**
     * Terapkan penggantian pada seluruh bagian dokumen (main part, header, footer).
     */
    private function applyReplacements(TemplateProcessor $processor, array $values, array $patterns): void
    {
        $swap = $this->tokenSwap($values);

        foreach (self::PARTS as $part) {
            $xml = $this->readPart($processor, $part);
            if (! is_string($xml) || $xml === '') {
                continue;
            }

            if ($swap !== []) {
                $xml = $this->replaceFragmentedPlaceholders($xml, $swap);
            }

            if ($patterns !== []) {
                $xml = $this->replacePatterns($xml, $patterns);
            }

            $this->writePart($processor, $part, $xml);
        }
    }

    /**
     * Terima nama placeholder polos (nama) maupun token literal ({nama}).
     * Kedua gaya penanda {@nama|} dan {nama} didukung.
     */
    private function tokenSwap(array $values): array
    {
        $swap = [];

        foreach ($values as $token => $value) {
            $token = (string) $token;
            $value = (string) $value;

            if (str_contains($token, '{')) {
                $swap[$token] = $value;

                continue;
            }

            $swap['{'.$token.'}'] = $value;
            $swap['{|'.$token.'|}'] = $value;
        }

        return $swap;
    }

    /**
     * Ganti teks yang tidak memiliki placeholder, mis. nomor surat yang masih
     * tertanam langsung di paragraf template.
     */
    private function replacePatterns(string $xml, array $patterns): string
    {
        return preg_replace_callback(
            '~<w:p\b[^>]*>.*?</w:p>~s',
            function (array $match) use ($patterns) {
                $para = $match[0];
                $text = $this->runTextOf($para);

                foreach ($patterns as $pattern => $replacement) {
                    if (preg_match($pattern, $text, $found)) {
                        $para = $this->replaceInParagraph($para, [$found[0] => (string) $replacement]);
                    }
                }

                return $para;
            },
            $xml
        ) ?? $xml;
    }

    /**
     * Ganti placeholder lintas-run di seluruh dokumen Word.
     */
    private function replaceFragmentedPlaceholders(string $xml, array $swap): string
    {
        return preg_replace_callback(
            '~<w:p\b[^>]*>.*?</w:p>~s',
            fn (array $match) => $this->replaceInParagraph($match[0], $swap),
            $xml
        ) ?? $xml;
    }

    /**
     * Proses satu paragraf <w:p>: run + raw, ganti token tanpa merusak format.
     */
    private function replaceInParagraph(string $para, array $swap): string
    {
        $segments = preg_split(
            '~(<w:r\b[^>]*>.*?</w:r>|<w:r\b[^>]*\/>)~s',
            $para,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if (! $segments) {
            return $para;
        }

        $full = $this->segmentText($segments);

        foreach ($swap as $token => $value) {
            $pos = 0;
            while (($idx = mb_strpos($full, $token, $pos)) !== false) {
                $end = $idx + mb_strlen($token);

                $covered = [];
                $cursor = 0;
                foreach ($segments as $i => $run) {
                    if (! str_starts_with($run, '<w:r')) {
                        continue;
                    }
                    $len = mb_strlen($this->runText($run));
                    $rs = $cursor;
                    $re = $rs + $len;
                    if ($rs < $end && $re > $idx) {
                        $covered[] = ['i' => $i, 'a' => max($rs, $idx) - $rs, 'b' => min($re, $end) - $rs];
                    }
                    $cursor = $re;
                }

                if ($covered) {
                    $total = 0;
                    foreach ($covered as $c) {
                        $total += $c['b'] - $c['a'];
                    }

                    $slices = [];
                    $alloc = 0;
                    $vLen = mb_strlen($value);
                    foreach ($covered as $c) {
                        $take = (int) floor($vLen * ($c['b'] - $c['a']) / $total);
                        $take = min($take, $vLen - $alloc);
                        $slices[] = mb_substr($value, $alloc, $take);
                        $alloc += $take;
                    }
                    if ($alloc < $vLen && $covered) {
                        $slices[count($slices) - 1] .= mb_substr($value, $alloc);
                    }

                    foreach ($covered as $ci => $c) {
                        $i = $c['i'];
                        $text = $this->runText($segments[$i]);
                        $segments[$i] = $this->setRunText(
                            $segments[$i],
                            mb_substr($text, 0, $c['a']).($slices[$ci] ?? '').mb_substr($text, $c['b'])
                        );
                    }
                }

                $full = $this->segmentText($segments);
                $pos = min($end, mb_strlen($full));
            }
        }

        return implode('', $segments);
    }

    /**
     * Teks gabungan seluruh <w:t> dalam satu <w:r>.
     */
    private function runText(string $runXml): string
    {
        if (! str_starts_with($runXml, '<w:r')) {
            return '';
        }

        if (preg_match_all('~<w:t\b[^>]*>(.*?)</w:t>~s', $runXml, $m)) {
            return implode('', $m[1]);
        }

        return '';
    }

    /**
     * Tulis ulang teks <w:t> (mempertahankan atribut & rPr run).
     */
    private function setRunText(string $runXml, string $newText): string
    {
        if (! preg_match('~^(<w:r\b[^>]*>)(.*)</w:r>$~s', $runXml, $m)) {
            return $runXml;
        }

        $inner = preg_replace_callback(
            '~<w:t\b[^>]*>.*?</w:t>|<w:t\b[^>]*\/>~s',
            fn () => '<w:t xml:space="preserve">'.htmlspecialchars($newText, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t>',
            $m[2],
            1
        );

        return $m[1].$inner.'</w:r>';
    }

    /**
     * Gabungkan teks seluruh segmen run (raw diabaikan).
     */
    private function segmentText(array $segments): string
    {
        $out = '';
        foreach ($segments as $segment) {
            if (str_starts_with($segment, '<w:r')) {
                $out .= $this->runText($segment);
            }
        }

        return $out;
    }

    /**
     * Teks gabungan satu fragmen XML (paragraf/baris) pada basis koordinat
     * yang sama dengan replaceInParagraph(), sehingga token yang terpecah
     * antar-run tetap terdeteksi.
     */
    private function runTextOf(string $xml): string
    {
        return $this->segmentText(preg_split(
            '~(<w:r\b[^>]*>.*?</w:r>|<w:r\b[^>]*\/>)~s',
            $xml,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        ) ?: []);
    }

    private function readPart(TemplateProcessor $processor, string $property): ?string
    {
        try {
            $reflection = new ReflectionClass($processor);
            if (! $reflection->hasProperty($property)) {
                return null;
            }

            $part = $reflection->getProperty($property);
            $part->setAccessible(true);
            $xml = $part->getValue($processor);

            return is_string($xml) ? $xml : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function writePart(TemplateProcessor $processor, string $property, string $xml): void
    {
        try {
            $reflection = new ReflectionClass($processor);
            if (! $reflection->hasProperty($property)) {
                return;
            }

            $part = $reflection->getProperty($property);
            $part->setAccessible(true);
            $part->setValue($processor, $xml);
        } catch (\Throwable) {
            // Abaikan; placeholder yang tersisa tetap terlihat oleh pembuat template.
        }
    }

    /**
     * Konversi bilangan 0-999999 menjadi terbilang Bahasa Indonesia.
     */
    public static function terbilang(int $angka): string
    {
        $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if ($angka < 12) {
            return $satuan[$angka];
        }

        if ($angka < 20) {
            return self::terbilang($angka - 10).' belas';
        }

        if ($angka < 100) {
            return self::terbilang(intdiv($angka, 10)).' puluh'.($angka % 10 ? ' '.self::terbilang($angka % 10) : '');
        }

        if ($angka < 200) {
            return 'seratus'.($angka > 100 ? ' '.self::terbilang($angka - 100) : '');
        }

        if ($angka < 1000) {
            return self::terbilang(intdiv($angka, 100)).' ratus'.($angka % 100 ? ' '.self::terbilang($angka % 100) : '');
        }

        if ($angka < 2000) {
            return 'seribu'.($angka > 1000 ? ' '.self::terbilang($angka - 1000) : '');
        }

        return self::terbilang(intdiv($angka, 1000)).' ribu'.($angka % 1000 ? ' '.self::terbilang($angka % 1000) : '');
    }
}
