<?php

namespace App\Support\Word;

use PhpOffice\PhpWord\TemplateProcessor;
use ReflectionClass;

/**
 * Pengisi template .docx (PhpWord) yang tahan terhadap placeholder yang
 * terpecah lintas <w:r>/<w:t> oleh Word, plus kloning baris tabel berulang
 * dan kloning blok paragraf berulang.
 *
 * Cara pakai:
 *   WordTemplateFiller::make($pathTemplate)->render(
 *       values: ['nama' => 'Budi'],
 *       rows: [['nama' => 'Kursi']],
 *       options: [
 *           'row_macro' => 'barang',
 *           'row_tokens' => ['nama_barang' => 'nama'],
 *           'patterns'   => ['~Nomor:\s*[\d\/.]+~' => 'Nomor: 123'],
 *           'blocks'     => [[
 *               'start_marker'   => 'Spesfikasi Teknis',
 *               'end_marker'     => 'Harga Satuan',
 *               'tokens'         => ['{Jenis Barang}' => 'jenis'],
 *               'rows'           => $items,
 *               'currency_token' => 'Harga Satuan',
 *           ]],
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
        $blocks = $options['blocks'] ?? [];

        $workingCopy = $this->workingCopy();
        $processor = new TemplateProcessor($workingCopy);

        if ($rows !== [] && $rowTokens !== []) {
            $this->expandRows($processor, $rowMacro, $rowTokens, $rows);
        }

        foreach ($blocks as $block) {
            $this->expandParagraphBlocks($processor, $block);
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
     * Kloning BLOK PARAGRAF berulang berdasarkan penanda teks (bukan tabel).
     *
     * Dipakai template yang mengulang satu blok spesifikasi per barang dalam
     * paragraf biasa — mis. SPPBI, yang memuat blok "Spesfikasi Teknis" ...
     * "Harga Satuan: ... (terbilang)" untuk setiap barang.
     *
     * Blok dikenali dari:
     *   - `start_marker`   : teks penanda awal blok (paragraf pertama blok),
     *   - `end_marker`     : teks penanda akhir blok (paragraf terakhir blok),
     *   - `tokens`         : peta token => key data baris,
     *   - `currency_token` (opsional): token nominal yang juga diisi
     *     terbilangnya (mis. `Harga Satuan`).
     *
     * Template umumnya sudah memuat 1-2 blok contoh. Seluruh blok contoh
     * digantikan oleh kloning sebanyak data; blok pertama dipakai sebagai
     * pola (termasuk format paragrafnya).
     *
     * @param  array{start_marker:string, end_marker:string, rows:array, tokens:array<string,string>, currency_token?:string}  $block
     */
    private function expandParagraphBlocks(TemplateProcessor $processor, array $block): void
    {
        $startMarker = $block['start_marker'] ?? null;
        $endMarker = $block['end_marker'] ?? null;
        $rows = $block['rows'] ?? [];
        $tokens = $block['tokens'] ?? [];
        $currencyToken = $block['currency_token'] ?? null;

        if (! $startMarker || ! $endMarker || $rows === [] || $tokens === []) {
            return;
        }

        foreach (self::PARTS as $part) {
            $xml = $this->readPart($processor, $part);
            if (! is_string($xml)) {
                continue;
            }

            $expanded = $this->repeatParagraphBlock($xml, $startMarker, $endMarker, $tokens, array_values($rows), $currencyToken);

            if ($expanded !== null) {
                $this->writePart($processor, $part, $expanded);
            }
        }
    }

    /**
     * Ganti SELURUH kemunculan blok (start..end) dengan kloning per baris.
     */
    private function repeatParagraphBlock(
        string $xml,
        string $startMarker,
        string|array $endMarker,
        array $tokens,
        array $rows,
        ?string $currencyToken
    ): ?string {
        $paragraphs = $this->paragraphs($xml);

        if ($paragraphs === []) {
            return null;
        }

        $endMarkers = is_array($endMarker) ? $endMarker : [$endMarker];

        // Kelompokkan indeks paragraf menjadi blok: start..end berurutan.
        $ranges = [];
        $i = 0;
        $total = count($paragraphs);

        while ($i < $total) {
            if (! str_contains($this->runTextOf($paragraphs[$i][0]), $startMarker)) {
                $i++;
                continue;
            }

            $end = null;
            for ($j = $i; $j < $total; $j++) {
                $text = $this->runTextOf($paragraphs[$j][0]);
                foreach ($endMarkers as $marker) {
                    if (str_contains($text, $marker)) {
                        $end = $j;
                        break 2;
                    }
                }
            }

            if ($end === null) {
                break;
            }

            $ranges[] = [$i, $end];
            $i = $end + 1;
        }

        if ($ranges === []) {
            return null;
        }

        // Pola blok = blok pertama (disimpan per paragraf agar operasi lintas
        // paragraf — seperti PTA parenthetical yang terbelah — tetap aman).
        [$firstStart, $firstEnd] = $ranges[0];
        $patternParas = array_map(
            fn ($k) => $paragraphs[$k][0],
            range($firstStart, $firstEnd)
        );

        $built = '';
        foreach ($rows as $index => $row) {
            $swap = $this->rowSwap($tokens, $row, $index + 1);

            $blockParas = $patternParas;

            if ($currencyToken !== null) {
                $blockParas = $this->isiTerbilang($blockParas, $currencyToken, $row['nominal'] ?? null);
            }

            $blockXml = implode('', $blockParas);
            $built .= $this->replaceFragmentedPlaceholders($blockXml, $swap);
        }

        // Ganti dari awal blok pertama sampai akhir blok terakhir.
        $spanStart = $paragraphs[$ranges[0][0]][1];
        $lastEnd = $ranges[count($ranges) - 1][1];
        $spanEnd = $paragraphs[$lastEnd][1] + strlen($paragraphs[$lastEnd][0]);

        return substr($xml, 0, $spanStart).$built.substr($xml, $spanEnd);
    }

    /**
     * Isi terbilang pada blok harga satuan.
     *
     * Token nominal (mis. `Harga Satuan`) diikuti kurung berisi terbilang
     * contoh dari template — mis. `Rp. {Harga Satuan} (tujuh belas juta
     * ...)`. Kurung itu diganti dengan terbilang aktual dari `$nominal`;
     * bila nominal kosong/0, kurung + isinya dihapus.
     *
     * @param  string[]  $paras  XML paragraf blok
     */
    private function isiTerbilang(array $paras, string $currencyToken, mixed $nominal): array
    {
        $token = '{'.$currencyToken.'}';

        // Bangun teks gabungan + offset tiap paragraf.
        $texts = array_map(fn ($p) => $this->runTextOf($p), $paras);

        $tokenBelok = null;
        $offset = 0;
        foreach ($texts as $t) {
            $pos = mb_strpos($t, $token);
            if ($pos !== false) {
                $tokenBelok = $offset + $pos + mb_strlen($token);
                break;
            }
            $offset += mb_strlen($t);
        }

        if ($tokenBelok === null) {
            return $paras;
        }

        $full = implode('', $texts);
        $open = mb_strpos($full, '(', $tokenBelok);
        $close = $open === false ? false : mb_strpos($full, ')', $open + 1);

        if ($open === false || $close === false || $close <= $open) {
            return $paras;
        }

        $terbilang = self::terbilangRupiah($nominal);

        // Hapus kurung + isinya ketika nominal kosong; sebaliknya hanya isi
        // di dalam kurung yang diganti dengan terbilang baru.
        if ($terbilang === '') {
            $start = $open;
            $end = $close + 1;
            $nilai = '';
        } else {
            $start = $open + 1;
            $end = $close;
            $nilai = $terbilang;
        }

        return $this->replaceRangeInParagraphs($paras, $start, $end, $nilai);
    }

    /**
     * Ganti rentang karakter `[start, end)` di dalam teks gabungan beberapa
     * paragraf Word, dengan nilai yang disebar ke run yang tercakup (run
     * pertama menerima nilai, sisanya dikosongkan) — tahan terhadap teks
     * yang terpecah lintas paragraf.
     *
     * @param  string[]  $paras  XML paragraf
     */
    private function replaceRangeInParagraphs(array $paras, int $start, int $end, string $value): array
    {
        $offset = 0;
        foreach ($paras as $i => $para) {
            $len = mb_strlen($this->runTextOf($para));
            $pStart = $offset;
            $pEnd = $offset + $len;
            $offset = $pEnd;

            if ($pEnd <= $start || $pStart >= $end) {
                continue;
            }

            $a = max($pStart, $start) - $pStart;
            $b = min($pEnd, $end) - $pStart;

            $paras[$i] = $this->replaceRangeInParagraph($para, $a, $b, $value);
            $value = '';
        }

        return $paras;
    }

    /**
     * Ganti rentang karakter `[a, b)` di dalam satu paragraf Word.
     */
    private function replaceRangeInParagraph(string $para, int $a, int $b, string $value): string
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

        $covered = [];
        $cursor = 0;
        foreach ($segments as $i => $segment) {
            if (! str_starts_with($segment, '<w:r')) {
                continue;
            }
            $len = mb_strlen($this->runText($segment));
            $rs = $cursor;
            $re = $rs + $len;
            $cursor = $re;

            if ($rs < $b && $re > $a) {
                $covered[] = ['i' => $i, 'a' => max($rs, $a) - $rs, 'b' => min($re, $b) - $rs];
            }
        }

        if ($covered === []) {
            return $para;
        }

        $vLen = mb_strlen($value);
        $total = 0;
        foreach ($covered as $c) {
            $total += $c['b'] - $c['a'];
        }

        $alloc = 0;
        foreach ($covered as $ci => $c) {
            $take = $total > 0 ? (int) floor($vLen * ($c['b'] - $c['a']) / $total) : 0;
            $take = min($take, $vLen - $alloc);
            $slice = mb_substr($value, $alloc, $take);
            $alloc += $take;

            $text = $this->runText($segments[$c['i']]);
            $segments[$c['i']] = $this->setRunText(
                $segments[$c['i']],
                mb_substr($text, 0, $c['a']).$slice.mb_substr($text, $c['b'])
            );
        }

        if ($alloc < $vLen) {
            $last = $covered[count($covered) - 1];
            $text = $this->runText($segments[$last['i']]);
            $segments[$last['i']] = $this->setRunText(
                $segments[$last['i']],
                mb_substr($text, 0, $last['b']).mb_substr($value, $alloc)
            );
        }

        return implode('', $segments);
    }

    /**
     * Konversi nominal rupiah menjadi terbilang (mis. "tujuh belas juta ...").
     *
     * Hanya mendukung satuan rupiah utuh (tanpa sen), cukup untuk harga
     * satuan aset. Nilai kosong/0 menghasilkan string kosong.
     */
    public static function terbilangRupiah(float|int|string|null $nominal): string
    {
        $angka = (int) round((float) $nominal);

        if ($angka <= 0) {
            return '';
        }

        $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        $ucap = function (int $n) use (&$ucap, $satuan): string {
            if ($n < 12) {
                return $satuan[$n];
            }
            if ($n < 20) {
                return $ucap($n - 10).' belas';
            }
            if ($n < 100) {
                return $ucap(intdiv($n, 10)).' puluh'.($n % 10 ? ' '.$ucap($n % 10) : '');
            }
            if ($n < 200) {
                return 'seratus'.($n > 100 ? ' '.$ucap($n - 100) : '');
            }
            if ($n < 1000) {
                return $ucap(intdiv($n, 100)).' ratus'.($n % 100 ? ' '.$ucap($n % 100) : '');
            }
            if ($n < 2000) {
                return 'seribu'.($n > 1000 ? ' '.$ucap($n - 1000) : '');
            }
            if ($n < 1000000) {
                return $ucap(intdiv($n, 1000)).' ribu'.($n % 1000 ? ' '.$ucap($n % 1000) : '');
            }
            if ($n < 1000000000) {
                return $ucap(intdiv($n, 1000000)).' juta'.($n % 1000000 ? ' '.$ucap($n % 1000000) : '');
            }
            if ($n < 1000000000000) {
                return $ucap(intdiv($n, 1000000000)).' miliar'.($n % 1000000000 ? ' '.$ucap($n % 1000000000) : '');
            }

            return $ucap(intdiv($n, 1000000000000)).' triliun'.($n % 1000000000000 ? ' '.$ucap($n % 1000000000000) : '');
        };

        return trim($ucap($angka));
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

    /**
     * Daftar paragraf Word beserta posisi offset-nya di dalam XML.
     *
     * @return array<int, array{0:string, 1:int}>
     */
    private function paragraphs(string $xml): array
    {
        if (! preg_match_all('~<w:p\b[^>]*>.*?</w:p>~s', $xml, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        return $matches[0];
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
