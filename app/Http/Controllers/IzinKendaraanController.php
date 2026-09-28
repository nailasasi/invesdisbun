<?php

namespace App\Http\Controllers;

use App\Models\IzinKendaraan;
use App\Models\Kendaraan;
use App\Models\Pegawai;
use App\Models\TemplateDokumen;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\TemplateProcessor;

class IzinKendaraanController extends Controller
{
    /**
     * Daftar pengajuan izin kendaraan (halaman layanan).
     *
     * - Admin Aset : melihat seluruh pengajuan.
     * - Role lain   : hanya melihat pengajuan miliknya.
     */
    public function index()
    {
        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';

        $izinList = IzinKendaraan::with([
            'kendaraan.aset.barang',
            'kendaraan.platAktif',
            'pengaju',
            'penyerah',
        ])
            ->when(! $isAdminAset, fn ($q) => $q->where('id_pegawai_pengaju', auth()->user()->pegawai?->id_pegawai))
            ->orderByDesc('id_izin')
            ->get();

        // Kendaraan dinas yang tersedia untuk diajukan (belum dihapus & tidak
        // sedang dipakai oleh pengajuan lain yang masih berjalan).
        $kendaraanList = Kendaraan::with(['aset.barang', 'platAktif'])
            ->whereHas('aset', fn ($a) => $a->where('status_aset', 'aktif'))
            ->where('status_penggunaan', 'Tersedia')
            ->orderBy('jenis_kendaraan')
            ->get();

        // Calon Pengurus Barang (Penyerah): hanya pegawai yang memiliki
        // akun/role "Admin Aset" yang ditampilkan di dropdown.
        $pengurusBarangList = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Aset'))
            ->with('pegawai')
            ->get()
            ->pluck('pegawai')
            ->filter()
            ->unique('id_pegawai')
            ->sortBy('nama_pegawai')
            ->values();

        $kendaraanOptions = $kendaraanList->mapWithKeys(function (Kendaraan $k) {
            $nama = $k->aset?->barang?->nama_barang ?: ($k->jenis_kendaraan ?? 'Kendaraan');
            $plat = $k->platAktif?->nomor_plat;

            return [$k->id_kendaraan => trim($nama.($plat ? " ({$plat})" : ''))];
        })->all();

        $pengurusOptions = $pengurusBarangList->mapWithKeys(function (Pegawai $p) {
            return [$p->id_pegawai => $p->nama_pegawai];
        })->all();

        return view('layanan.izin-kendaraan.index', compact(
            'izinList', 'kendaraanList', 'pengurusBarangList', 'kendaraanOptions',
            'pengurusOptions', 'isAdminAset'
        ));
    }

    /**
     * Simpan pengajuan izin kendaraan dari halaman layanan.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'id_kendaraan' => ['required', 'exists:kendaraan,id_kendaraan'],
            'nama_pengemudi' => ['required', 'string', 'max:100'],
            'id_pengurus_barang' => ['required', 'exists:pegawai,id_pegawai'],
            'tujuan' => ['required', 'string', 'max:500'],
            'durasi' => ['required', 'string', 'max:50'],
            'tanggal_berangkat' => ['required', 'date'],
        ]);

        // Pastikan kendaraan tidak sudah disetujui dipakai pada tanggal yang sama.
        $bentrok = IzinKendaraan::where('id_kendaraan', $data['id_kendaraan'])
            ->where('tanggal_berangkat', $data['tanggal_berangkat'])
            ->where('status_approval', 'Disetujui')
            ->exists();

        if ($bentrok) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['id_kendaraan' => 'Kendaraan tersebut sudah dipakai (disetujui) pada tanggal berangkat yang dipilih.']);
        }

        IzinKendaraan::create([
            'id_kendaraan' => $data['id_kendaraan'],
            'id_pegawai_pengaju' => auth()->user()->pegawai?->id_pegawai,
            'id_pengurus_barang' => $data['id_pengurus_barang'],
            'nama_pengemudi' => $data['nama_pengemudi'],
            'tujuan' => $data['tujuan'],
            'durasi' => $data['durasi'],
            'tanggal_berangkat' => $data['tanggal_berangkat'],
            'status_approval' => 'Menunggu',
        ]);

        return redirect()->route('layanan.izin-kendaraan.index')
            ->with('success', 'Pengajuan izin berhasil dikirim dan menunggu verifikasi pengurus barang.');
    }

    /**
     * Verifikasi cepat pengajuan (khusus Admin Aset) dari halaman layanan.
     *
     * Penolakan wajib disertai alasan yang disimpan ke kolom
     * `alasan_penolakan` agar bisa dibaca kembali oleh pemohon & admin.
     */
    public function updateStatus(Request $request, IzinKendaraan $izin)
    {
        abort_unless(auth()->user()?->role?->nama_role === 'Admin Aset', 403);

        $rules = [
            'status_approval' => ['required', Rule::in(['Disetujui', 'Ditolak'])],
            'alasan_penolakan' => ['nullable', 'string', 'max:1000'],
        ];

        if (($request->input('status_approval') ?? '') === 'Ditolak') {
            $rules['alasan_penolakan'][] = 'required';
        }

        $data = $request->validate($rules);

        $izin->update([
            'status_approval' => $data['status_approval'],
            'alasan_penolakan' => $data['status_approval'] === 'Ditolak' ? $data['alasan_penolakan'] : null,
        ]);

        // Saat disetujui, tandai kendaraan sedang dipakai sehingga tidak bisa
        // dipesan ulang oleh pegawai lain sampai dikembalikan.
        if ($data['status_approval'] === 'Disetujui' && $izin->kendaraan) {
            $izin->kendaraan->update(['status_penggunaan' => 'Digunakan']);
        }

        // Saat ditolak (mis. dari status yang sempat disetujui), bebaskan
        // kendaraan selama tidak ada pengajuan lain yang masih disetujui.
        if ($data['status_approval'] === 'Ditolak' && $izin->kendaraan) {
            $masihDisetujui = IzinKendaraan::where('id_kendaraan', $izin->id_kendaraan)
                ->where('id_izin', '!=', $izin->id_izin)
                ->where('status_approval', 'Disetujui')
                ->exists();

            if (! $masihDisetujui) {
                $izin->kendaraan->update(['status_penggunaan' => 'Tersedia']);
            }
        }

        return redirect()->route('layanan.izin-kendaraan.index')
            ->with('success', $data['status_approval'] === 'Disetujui'
                ? 'Pengajuan izin kendaraan disetujui.'
                : 'Pengajuan izin kendaraan ditolak.');
    }

    /**
     * Konfirmasi pengembalian kendaraan oleh pengaju.
     *
     * Berisi catatan kondisi kendaraan, foto bukti (opsional), dan waktu
     * pengembalian riil. Status pengajuan menjadi "Selesai" dan kendaraan
     * otomatis kembali tersedia untuk dipesan pegawai lain.
     */
    public function returnKendaraan(Request $request, IzinKendaraan $izin)
    {
        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';
        $isPengaju = $izin->id_pegawai_pengaju === auth()->user()?->pegawai?->id_pegawai;

        abort_unless($isAdminAset || $isPengaju, 403);
        abort_unless($izin->status_approval === 'Disetujui', 422);

        $data = $request->validate([
            'catatan_kondisi' => ['nullable', 'string', 'max:1000'],
            'foto_pengembalian' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'waktu_pengembalian' => ['nullable', 'date'],
        ]);

        $fotoPath = null;
        if ($request->hasFile('foto_pengembalian')) {
            $fotoPath = $request->file('foto_pengembalian')->store('pengembalian', 'public');
        }

        $izin->update([
            'status_approval' => 'Selesai',
            'catatan_pengembalian' => $data['catatan_kondisi'] ?? null,
            'foto_pengembalian' => $fotoPath,
            'waktu_pengembalian' => $data['waktu_pengembalian'] ?? now(),
        ]);

        // Kendaraan hanya dikembalikan ke Tersedia bila tidak ada lagi
        // pengajuan lain atas kendaraan tersebut yang masih disetujui.
        $masihDipakai = IzinKendaraan::where('id_kendaraan', $izin->id_kendaraan)
            ->where('status_approval', 'Disetujui')
            ->exists();

        if (! $masihDipakai && $izin->kendaraan) {
            $izin->kendaraan->update(['status_penggunaan' => 'Tersedia']);
        }

        return redirect()->route('layanan.izin-kendaraan.index')
            ->with('success', 'Pengembalian kendaraan dikonfirmasi. Status pengajuan menjadi "Selesai".');
    }

    /**
     * Unduh surat izin (.docx) untuk pengajuan yang telah disetujui.
     *
     * Menggunakan file template "Surat Izin / Tugas Kendaraan Dinas" yang
     * diunggah Admin Aset lewat menu Pengaturan Template Dokumen
     * (kode_template: surat_izin_kendaraan). Placeholder yang didukung:
     *   {{ nomor_surat }}, {{ nama_pemohon }}, {{ nip_pemohon }},
     *   {{ jabatan_pemohon }}, {{ nama_pengemudi }}, {{ nama_kendaraan }},
     *   {{ jenis_kendaraan }}, {{ merk_kendaraan }}, {{ tipe_kendaraan }},
     *   {{ plat_nomor }}, {{ tujuan }}, {{ tanggal_berangkat }}, {{ durasi }},
     *   {{ nama_pengurus_barang }}, {{ nip_pengurus_barang }},
     *   {{ nama_pejabat }}, {{ nip_pejabat }}, {{ kota_surat }}, {{ tanggal_surat }}.
     *
     * Jika template belum diunggah, digunakan mekanisme generate otomatis
     * agar fitur unduh tetap berfungsi.
     */
    public function downloadSurat(IzinKendaraan $izin)
    {
        abort_unless($izin->status_approval === 'Disetujui', 403);

        $izin->load(['kendaraan.aset.barang', 'kendaraan.platAktif', 'pengaju', 'penyerah']);

        $template = TemplateDokumen::where('kode_template', 'surat_izin_kendaraan')
            ->whereNotNull('file_path')
            ->first();

        if ($template && Storage::disk('public')->exists($template->file_path)) {
            return $this->generateFromTemplate($izin, $template);
        }

        return $this->generateManual($izin);
    }

    /**
     * Isi placeholder pada file template .docx yang diunggah Admin Aset,
     * lalu unduh hasilnya sebagai surat izin siap cetak.
     */
    protected function generateFromTemplate(IzinKendaraan $izin, TemplateDokumen $template)
    {
        $kendaraan = $izin->kendaraan;
        $plat = $kendaraan?->platAktif?->nomor_plat ?? '-';
        $tanggal = Carbon::parse($izin->tanggal_berangkat)->locale('id');
        $hariIni = Carbon::now()->locale('id');

        $namaKendaraan = $kendaraan?->aset?->barang?->nama_barang ?: ($kendaraan?->jenis_kendaraan ?? '-');

        $values = [
            'nomor_surat' => sprintf('%03d', $izin->id_izin).'/IZINK-DISBUN/'.$tanggal->format('Y'),
            'nama_pemohon' => $izin->pengaju?->nama_pegawai ?? '-',
            'nip_pemohon' => $izin->pengaju?->nip ?? '-',
            'jabatan_pemohon' => $izin->pengaju?->jabatan ?? '-',
            'nama_pengemudi' => $izin->nama_pengemudi ?? '-',
            'nama_kendaraan' => $namaKendaraan,
            'jenis_kendaraan' => $kendaraan?->jenis_kendaraan ?? '-',
            'merk_kendaraan' => $kendaraan?->merk ?? '-',
            'tipe_kendaraan' => $kendaraan?->tipe ?? '-',
            'plat_nomor' => $plat,
            'tujuan' => $izin->tujuan ?? '-',
            'tanggal_berangkat' => $tanggal->translatedFormat('d F Y'),
            'durasi' => $izin->durasi ?? '-',
            'nama_pengurus_barang' => $izin->penyerah?->nama_pegawai ?? '-',
            'nip_pengurus_barang' => $izin->penyerah?->nip ?? '-',
            'nama_pejabat' => $izin->penyerah?->nama_pegawai ?? '-',
            'nip_pejabat' => $izin->penyerah?->nip ?? '-',
            'jabatan_pejabat' => $izin->penyerah?->jabatan ?? '-',
            'nama_pengurus' => $izin->penyerah?->nama_pegawai ?? '-',
            'nip_pengurus' => $izin->penyerah?->nip ?? '-',
            'waktu' => $izin->durasi ?? '-',
            'kota_surat' => 'Surabaya',
            'tanggal_surat' => $hariIni->translatedFormat('d F Y'),
        ];

        $tmpZip = sys_get_temp_dir().'/Surat_Izin_TPL_'.uniqid().'.docx';
        copy(Storage::disk('public')->path($template->file_path), $tmpZip);

        $zip = new \ZipArchive;

        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);

            return back()->with('error', 'Berkas template surat izin tidak dapat dibaca.');
        }

        $xml = $zip->getFromName('word/document.xml');

        if ($xml !== false) {
            // 1) Ubah notasi {{ key }} menjadi ${key} agar dikenali PHPWord
            //    TemplateProcessor (setValue/ensureMacroCompleted).
            $xml = preg_replace('/\{\{\s*/', '\\${', $xml);
            $xml = preg_replace('/\s*\}\}/', '}', $xml);

            // 2) Rapikan token ${...} yang terpecah antar run menjadi satu run
            //    utuh (pertahankan format run pertama). Cakupan dibatasi per
            //    paragraf -(?!</w:p>).- agar teks antar paragraf TIDAK pernah
            //    disambung/ditumpuk.
            $xml = preg_replace_callback('/(<w:p\b[^>]*>(?:(?!<\/w:p>).)*?<\/w:p>)/s', function ($pm) {
                return preg_replace_callback(
                    '/(<w:r\b[^>]*>(?:<w:rPr>.*?<\/w:rPr>)?<w:t(?: [^>]*)?>)\$\{(?:(?!<\/w:p>).)*?\}<\/w:t><\/w:r>/s',
                    function ($m) {
                        [$full, $openTag] = $m;
                        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $full, $tt);
                        $text = html_entity_decode(implode('', $tt[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                        $text = preg_replace('/\s+/u', ' ', trim($text));

                        return $openTag.$text.'</w:t></w:r>';
                    },
                    $pm[1]
                );
            }, $xml);

            // 3) Rapatkan spasi sebelum '}' pada token yang penutupnya berada
            //    di run/rata yang sama (mis. "${nama_pengemudi }" -> "${nama_pengemudi}").
            $xml = preg_replace('/(\$\{[^}]*?)\s+\}/', '$1}', $xml);

            // 4) Hapus run yang isinya HANYA '}' (artefak pemisahan penutup token
            //    oleh grammar-check Word). Pola dibatasi tag-per-tag agar tidak
            //    pernah menyambung/menghapus teks lain.
            $xml = preg_replace('/<w:r\b[^>]*>(?:<w:rPr>(?:<[^>]*>)*<\/w:rPr>)?<w:t(?: [^>]*)?>\s*\}\s*<\/w:t><\/w:r>/', '', $xml);

            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $xml);
        }

        $zip->close();

        // 5) Isi placeholder satu per satu dengan setValue (per variabel tunggal)
        //    agar hanya token yang diganti, teks label di sekitarnya tetap utuh.
        $templateProcessor = new TemplateProcessor($tmpZip);

        foreach ($values as $key => $value) {
            $templateProcessor->setValue($key, $value);
        }

        $tmpOutput = sys_get_temp_dir().'/Surat_Izin_Kendaraan_'.uniqid().'.docx';
        $templateProcessor->saveAs($tmpOutput);
        @unlink($tmpZip);

        $fileName = 'Surat_Izin_Kendaraan_'.str_replace(' ', '_', $plat).'_'.$tanggal->format('Ymd').'.docx';

        return response()->download($tmpOutput, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Fallback: generate surat izin langsung dari data (tanpa template upload).
     */
    protected function generateManual(IzinKendaraan $izin)
    {
        $kendaraan = $izin->kendaraan;
        $plat = $kendaraan?->platAktif?->nomor_plat ?? '-';
        $tanggal = Carbon::parse($izin->tanggal_berangkat)->locale('id');

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginTop' => 720,
            'marginBottom' => 720,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ]);

        $center = ['alignment' => Jc::CENTER];
        $bold = ['bold' => true];

        // Kop surat
        $section->addText('PEMERINTAH PROVINSI JAWA TIMUR', $bold, $center);
        $section->addText('DINAS PERKEBUNAN', $bold, $center);
        $section->addText('SURAT IZIN PEMAKAIAN KENDARAAN DINAS', ['bold' => true, 'size' => 11], $center);
        $section->addText('Nomor: ......../IZINK/....', ['size' => 11], $center);

        $section->addTextBreak(1);
        $section->addText('Yang bertanda tangan di bawah ini memberikan izin pemakaian kendaraan dinas kepada:');

        // Detail tabel
        $table = $section->addTable([
            'borderSize' => 4,
            'borderColor' => '000000',
            'cellMargin' => 60,
        ]);
        $detail = [
            'Nama Pengemudi' => $izin->nama_pengemudi ?? '-',
            'Unit / Pegawai Pemohon' => $izin->pengaju?->nama_pegawai ?? '-',
            'Kendaraan Dinas' => ($kendaraan?->jenis_kendaraan ?? '-').' ('.$plat.')',
            'Merk / Tipe' => $kendaraan?->merk && $kendaraan?->tipe
                ? $kendaraan->merk.' / '.$kendaraan->tipe
                : ($kendaraan?->merk ?: ($kendaraan?->aset?->barang?->nama_barang ?: '-')),
            'Tanggal Berangkat' => $tanggal->translatedFormat('d F Y'),
            'Waktu / Durasi' => $izin->durasi ?? '-',
            'Tujuan' => $izin->tujuan ?? '-',
            'Pengurus Barang (Penyerah)' => $izin->penyerah?->nama_pegawai ?? '-',
        ];

        foreach ($detail as $label => $value) {
            $table->addRow();
            $table->addCell(4200, ['gridSpan' => 1])->addText($label, ['bold' => true, 'size' => 11]);
            $table->addCell(7200)->addText($value, ['size' => 11]);
        }

        $section->addTextBreak(1);
        $section->addText('Demikian surat izin ini dibuat agar digunakan sebagaimana mestinya, dan kendaraan dijaga serta dirawat dengan baik selama dipergunakan.');

        $section->addTextBreak(2);

        // Blok tanda tangan
        $sigTable = $section->addTable(['cellMargin' => 60]);
        $sigTable->addRow();
        $col1 = $sigTable->addCell(5400);
        $col2 = $sigTable->addCell(5400);

        $left = [
            'Pemohon,',
            '',
            '',
            $izin->pengaju?->nama_pegawai ?? '........................',
            'NIP. '.($izin->pengaju?->nip ?? '........................'),
        ];
        $right = [
            'Surabaya, '.$tanggal->translatedFormat('d F Y'),
            'Pengurus Barang,',
            '',
            '',
            $izin->penyerah?->nama_pegawai ?? 'Achmar Adrian Ramadhan, A.Md.',
            'NIP. '.($izin->penyerah?->nip ?? '19991223 202504 1 006'),
        ];

        foreach ($left as $line) {
            $col1->addText($line, ['size' => 11], ['alignment' => Jc::CENTER]);
        }
        foreach ($right as $line) {
            $col2->addText($line, ['size' => 11], ['alignment' => Jc::CENTER]);
        }

        $filePath = sys_get_temp_dir().'/Surat_Izin_Kendaraan_'.uniqid().'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($filePath);

        $fileName = 'Surat_Izin_Kendaraan_'.str_replace(' ', '_', $plat).'_'.$tanggal->format('Ymd').'.docx';

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Simpan pengajuan izin dari halaman detail kendaraan (alur lama).
     */
    public function kendaraanStore(Request $request, Kendaraan $kendaraan)
    {
        $data = $request->validate([
            'id_pegawai_pengaju' => ['nullable', 'exists:pegawai,id_pegawai'],
            'tanggal_berangkat' => ['nullable', 'date'],
            'waktu_berangkat' => ['nullable', 'string', 'max:5'],
            'tanggal_kembali' => ['nullable', 'date'],
            'waktu_kembali' => ['nullable', 'string', 'max:5'],
            'tujuan' => ['nullable', 'string', 'max:500'],
            'jenis_pengemudi' => ['nullable', 'string', 'max:50'],
            'id_pegawai_pengemudi' => ['nullable', 'exists:pegawai,id_pegawai'],
            'nama_pengemudi' => ['nullable', 'string', 'max:100'],
            'file_surat' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $filePath = null;
        if ($request->hasFile('file_surat')) {
            $filePath = $request->file('file_surat')->store('izin-kendaraan', 'public');
        }

        IzinKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'id_pegawai_pengaju' => $data['id_pegawai_pengaju'] ?? auth()->user()->pegawai?->id_pegawai,
            'tanggal_berangkat' => $data['tanggal_berangkat'] ?? null,
            'waktu_berangkat' => $data['waktu_berangkat'] ?? null,
            'tanggal_kembali' => $data['tanggal_kembali'] ?? null,
            'waktu_kembali' => $data['waktu_kembali'] ?? null,
            'tujuan' => $data['tujuan'] ?? null,
            'jenis_pengemudi' => $data['jenis_pengemudi'] ?? null,
            'id_pegawai_pengemudi' => $data['id_pegawai_pengemudi'] ?? null,
            'nama_pengemudi' => $data['nama_pengemudi'] ?? null,
            'status_approval' => 'Menunggu',
            'file_surat' => $filePath,
        ]);

        return response()->json(['success' => true, 'message' => 'Permohonan izin berhasil diajukan.']);
    }

    public function approve(Request $request, Kendaraan $kendaraan, IzinKendaraan $izin)
    {
        if ($izin->id_kendaraan !== $kendaraan->id_kendaraan) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid.'], 422);
        }

        $data = $request->validate([
            'status_approval' => ['required', Rule::in(['Disetujui', 'Ditolak'])],
        ]);

        $izin->update(['status_approval' => $data['status_approval']]);

        return response()->json([
            'success' => true,
            'message' => $data['status_approval'] === 'Disetujui'
                ? 'Permohonan izin disetujui.'
                : 'Permohonan izin ditolak.',
        ]);
    }

    public function destroy(Kendaraan $kendaraan, IzinKendaraan $izin)
    {
        if ($izin->id_kendaraan !== $kendaraan->id_kendaraan) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid.'], 422);
        }
        $izin->delete();

        return response()->json(['success' => true, 'message' => 'Permohonan izin dihapus.']);
    }
}
