<?php

namespace App\Http\Controllers;

use App\Models\MutasiAset;
use App\Models\TemplateDokumen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

class MutasiAsetController extends Controller
{
    /**
     * Riwayat mutasi aset (read-only) untuk Admin Aset.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $mutasiList = MutasiAset::with(['details.aset.barang', 'details.pegawaiLama', 'details.pegawaiBaru', 'details.ruanganLama', 'details.ruanganBaru', 'userPenginput.pegawai'])
            ->whereHas('details.aset', fn ($a) => $a->where('is_kendaraan', false))
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('jenis_mutasi', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%")
                        ->orWhereHas('details.aset.barang', fn ($b) => $b->where('nama_barang', 'like', "%{$search}%"))
                        ->orWhereHas('details.pegawaiBaru', fn ($p) => $p->where('nama_pegawai', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id_mutasi')
            ->paginate(10)
            ->withQueryString();

        return view('mutasi-aset.index', compact('mutasiList'));
    }

    /**
     * Unduh Berita Acara Serah Terima (BAST) Barang untuk mutasi Ganti Pemegang (.docx).
     * Dokumen digenerate on-demand dari template 'bast_barang' — tidak disimpan
     * ke storage agar tidak membebani hosting dan selalu sinkron dengan data terbaru.
     */
    public function downloadBAST($id_mutasi)
    {
        $mutasi = MutasiAset::with(['details.aset.barang', 'details.pegawaiLama', 'details.pegawaiBaru'])
            ->findOrFail($id_mutasi);

        // BAST hanya untuk serah terima tanggung jawab perorangan.
        // Pindah ruangan merupakan penataan lokasi internal yang tercatat di KIR.
        if ($mutasi->jenis_mutasi !== 'Ganti Pemegang') {
            return back()->with('error', 'Dokumen BAST hanya tersedia untuk mutasi Ganti Pemegang.');
        }

        $detail = $mutasi->details->first();
        if (!$detail || !$detail->aset) {
            return back()->with('error', 'Detail mutasi tidak ditemukan.');
        }

        $template = TemplateDokumen::where('kode_template', 'bast_barang')->first();
        if (!$template || !$template->file_path || !Storage::disk('public')->exists($template->file_path)) {
            return back()->with('error', 'Template BAST Barang belum diunggah di Pengaturan Dokumen.');
        }

        $phpWord = new TemplateProcessor(storage_path('app/public/' . $template->file_path));

        // Waktu dalam Bahasa Indonesia
        $tgl = \Carbon\Carbon::parse($mutasi->tanggal_mutasi ?? now())->locale('id');

        $p1 = $detail->pegawaiLama;
        $p2 = $detail->pegawaiBaru;
        $aset = $detail->aset;

        // 1. Cara standar PhpWord: placeholder ${...}
        $phpWord->setValue('hari', $tgl->translatedFormat('l'));
        $phpWord->setValue('tanggal_terbilang', $this->terbilang((int) $tgl->format('d')));
        $phpWord->setValue('bulan', $tgl->translatedFormat('F'));
        $phpWord->setValue('tahun_terbilang', $this->terbilang((int) $tgl->format('Y')));

        $phpWord->setValue('nama_pihak_pertama', $p1->nama_pegawai ?? '-');
        $phpWord->setValue('nip_pihak_pertama', $p1->nip ?? '-');
        $phpWord->setValue('jabatan_pihak_pertama', $p1->jabatan ?? '-');

        $phpWord->setValue('nama_pihak_kedua', $p2->nama_pegawai ?? '-');
        $phpWord->setValue('nip_pihak_kedua', $p2->nip ?? '-');
        $phpWord->setValue('jabatan_pihak_kedua', $p2->jabatan ?? '-');

        $phpWord->setValue('nomor', '1');
        $phpWord->setValue('nama_barang', $aset->barang->nama_barang ?? '-');
        $phpWord->setValue('merk_barang', $aset->merk ?? '-');
        $phpWord->setValue('tahun_pengadaan', $aset->tanggal_perolehan ? \Carbon\Carbon::parse($aset->tanggal_perolehan)->format('Y') : '-');
        $phpWord->setValue('kode_barang', $aset->nomor_kartu_barang ?? '-');

        // 2. Patch kurung kurawal tunggal {placeholder} langsung di XML.
        // Word sering memecah placeholder lintas beberapa <w:r>/<w:t> (mis. karena
        // proofErr), sehingga setValue + str_replace polos tidak cukup. Gunakan
        // penggantian lintas-run agar tak ada placeholder yang terlewat.
        $swap = [
            '{hari}'              => $tgl->translatedFormat('l'),
            '{tanggal_terbilang}' => $this->terbilang((int) $tgl->format('d')),
            '{bulan}'             => $tgl->translatedFormat('F'),
            '{tahun_terbilang}'   => $this->terbilang((int) $tgl->format('Y')),
            '{nama_pihak_pertama}'  => $p1->nama_pegawai ?? '-',
            '{nip_pihak_pertama}'   => $p1->nip ?? '-',
            '{jabatan_pihak_pertama}' => $p1->jabatan ?? '-',
            '{nama_pihak_kedua}'    => $p2->nama_pegawai ?? '-',
            '{nip_pihak_kedua}'     => $p2->nip ?? '-',
            '{jabatan_pihak_kedua}' => $p2->jabatan ?? '-',
            '{nomor}'           => '1',
            '{nama_barang}'     => $aset->barang->nama_barang ?? '-',
            '{merk_barang}'     => $aset->merk ?? '-',
            '{tahun_pengadaan}' => $aset->tanggal_perolehan ? \Carbon\Carbon::parse($aset->tanggal_perolehan)->format('Y') : '-',
            '{kode_barang}'     => $aset->nomor_kartu_barang ?? '-',
            '{#barang}'         => '',
            '{/barang}'         => '',
        ];

        try {
            $reflection = new \ReflectionClass($phpWord);
            foreach (['tempDocumentMainPart', 'tempDocumentHeaders', 'tempDocumentFooters'] as $propName) {
                if (!$reflection->hasProperty($propName)) {
                    continue;
                }
                $property = $reflection->getProperty($propName);
                $property->setAccessible(true);
                $xml = $property->getValue($phpWord);

                if (!is_string($xml) || $xml === '') {
                    continue;
                }

                $property->setValue($phpWord, $this->replaceFragmentedPlaceholders($xml, $swap));
            }
        } catch (\Throwable $th) {
            // Abaikan; placeholder yang tersisa tetap tampil agar terlihat oleh pembuat template.
        }

        $namaFile = 'BAST_' . Str::slug($aset->barang->nama_barang ?? 'Aset', '_') . '_' . $tgl->format('Ymd') . '.docx';
        $tempPath = tempnam(sys_get_temp_dir(), 'BAST_');
        $phpWord->saveAs($tempPath);

        return response()->download($tempPath, $namaFile)->deleteFileAfterSend(true);
    }

    /**
     * Ganti placeholder lintas-run di XML dokumen Word.
     * Word memecah token placeholder ({hari}, {nama_barang}, dst.) ke beberapa
     * <w:r>/<w:t> (sering diselingi w:proofErr saat spell-check). str_replace
     * polos gagal karena teks tidak kontigu. Metode ini menyambung teks seluruh
     * run per paragraf, menemukan token, lalu mendistribusikan nilai pengganti
     * ke run-run yang menaungi token tanpa merusak format run asli.
     *
     * @return string XML hasil penggantian
     */
    private function replaceFragmentedPlaceholders(string $xml, array $swap): string
    {
        return preg_replace_callback(
            '/<w:p\b[^>]*>.*?<\/w:p>/s',
            function (array $m) use ($swap) {
                return $this->replaceInParagraph($m[0], $swap);
            },
            $xml
        ) ?? $xml;
    }

    /**
     * Proses satu paragraf /w:p: pecah menjadi segmen run + raw lalu replace token.
     */
    private function replaceInParagraph(string $para, array $swap): string
    {
        $segments = preg_split(
            '/(<w:r\b[^>]*>.*?<\/w:r>|<w:r\b[^>]*\/>)/s',
            $para,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if (!$segments || !Str::contains($para, '{')) {
            return $para;
        }

        $full = $this->segmentText($segments);

        foreach ($swap as $token => $value) {
            $pos = 0;
            while (($idx = mb_strpos($full, $token, $pos)) !== false) {
                $end = $idx + mb_strlen($token);

                $covered = [];
                $cursor = 0;
                foreach ($segments as $i => $xml2) {
                    if (!str_starts_with($xml2, '<w:r')) {
                        continue;
                    }
                    $len = mb_strlen($this->runText($xml2));
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
                        $t = $this->runText($segments[$i]);
                        $segments[$i] = $this->setRunText(
                            $segments[$i],
                            mb_substr($t, 0, $c['a']) . ($slices[$ci] ?? '') . mb_substr($t, $c['b'])
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
     * Ambil teks gabungan dari seluruh <w:t> sebuah <w:r>.
     */
    private function runText(string $runXml): string
    {
        if (!str_starts_with($runXml, '<w:r')) {
            return '';
        }
        if (preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/s', $runXml, $m)) {
            return implode('', $m[1]);
        }
        return '';
    }

    /**
     * Tulis ulang teks <w:t> (mempertahankan atribut & rPr run).
     */
    private function setRunText(string $runXml, string $newText): string
    {
        if (!preg_match('/^(<w:r\b[^>]*>)(.*)<\/w:r>$/s', $runXml, $m)) {
            return $runXml;
        }

        $inner = preg_replace_callback(
            '/<w:t\b[^>]*>.*?<\/w:t>|<w:t\b[^>]*\/>/s',
            function ($t) use ($newText) {
                return '<w:t xml:space="preserve">' . htmlspecialchars($newText, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t>';
            },
            $m[2],
            1
        );

        return $m[1] . $inner . '</w:r>';
    }

    /**
     * Gabungkan teks seluruh segmen (run memberikan teksnya, raw diabaikan).
     */
    private function segmentText(array $segments): string
    {
        $out = '';
        foreach ($segments as $xml2) {
            if (str_starts_with($xml2, '<w:r')) {
                $out .= $this->runText($xml2);
            }
        }
        return $out;
    }

    /**
     * Konversi bilangan 0-999999 menjadi terbilang Bahasa Indonesia.
     */
    private function terbilang(int $angka): string
    {
        $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if ($angka < 12) {
            return $satuan[$angka];
        }

        if ($angka < 20) {
            return $this->terbilang($angka - 10) . ' belas';
        }

        if ($angka < 100) {
            return $this->terbilang(intdiv($angka, 10)) . ' puluh' . ($angka % 10 ? ' ' . $this->terbilang($angka % 10) : '');
        }

        if ($angka < 200) {
            return 'seratus' . ($angka > 100 ? ' ' . $this->terbilang($angka - 100) : '');
        }

        if ($angka < 1000) {
            return $this->terbilang(intdiv($angka, 100)) . ' ratus' . ($angka % 100 ? ' ' . $this->terbilang($angka % 100) : '');
        }

        if ($angka < 2000) {
            return 'seribu' . ($angka > 1000 ? ' ' . $this->terbilang($angka - 1000) : '');
        }

        return $this->terbilang(intdiv($angka, 1000)) . ' ribu' . ($angka % 1000 ? ' ' . $this->terbilang($angka % 1000) : '');
    }
}
