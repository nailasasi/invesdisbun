<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\Lokasi;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\PenempatanAset;
use App\Models\InventarisRuangan;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\TemplateDokumen;
use App\Services\AsetPenempatan;
use App\Services\AsetScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpWord\TemplateProcessor;

class AsetRuanganController extends Controller
{
    /**
     * Status aset yang boleh tampil di daftar & dipilih untuk penempatan.
     * Status sistem penghapusan tidak ikut (Aturan 15).
     */
    const STATUS_USER = ['aktif', 'nonaktif'];

    public function __construct(private readonly AsetScope $scope)
    {
    }

    /**
     * Role Admin Aset (case/format tolerant: Admin Aset, admin_aset, admin).
     */
    private function isAdminAset(): bool
    {
        return $this->scope->isAdminAset();
    }

    private function authorizeAdminAset(string $pesan = 'Hanya Admin Aset yang diizinkan.'): void
    {
        abort_unless($this->isAdminAset(), 403, $pesan);
    }

    /**
     * Pastikan user boleh menyentuh ruangan ini (object-level check).
     */
    private function authorizeRuangan(Ruangan $ruangan, string $aksi = 'mengelola ruangan'): void
    {
        abort_unless(
            $this->scope->bolehAksesRuangan($ruangan->id_ruangan),
            403,
            $this->scope->pesanAkses($aksi)
        );
    }

    /**
     * Validasi input `id_lokasi` / `id_skpd` pada form ruangan agar user
     * non-Admin-Aset tidak dapat menulis ruangan ke lokasi/SKPD luar scope
     * (termasuk mengosongkan lokasi agar ruangan jadi tak terscope).
     */
    private function authorizeInputRuangan(array $data): void
    {
        if ($this->scope->cakupanPenuh()) {
            return;
        }

        $idLokasi = $data['id_lokasi'] ?? null;

        abort_unless(
            $idLokasi !== null && $this->scope->bolehAksesLokasi((int) $idLokasi),
            403,
            $this->scope->pesanAkses('memilih lokasi ruangan')
        );

        if (($data['id_skpd'] ?? null) !== null) {
            abort_unless(
                (int) $data['id_skpd'] === (int) $this->scope->skpdId(),
                403,
                'Anda tidak dapat menetapkan SKPD di luar unit Anda.'
            );
        }
    }

    /**
     * Validasi khusus skenario Kantor Dinas:
     * Bila lokasi adalah Kantor Dinas (id_lokasi = 1), id_skpd boleh NULL (ruang bersama).
     * Bila diisi, hanya ID 1-5 (Sekretariat & Bidang) yang diperbolehkan. ID 6 dan 7 (UPT) ditolak.
     */
    private function validasiLokasiDinas(array $data): void
    {
        $idLokasi = isset($data['id_lokasi']) ? (int) $data['id_lokasi'] : null;
        $idLokasiDinas = Lokasi::dinas()?->id_lokasi ?? 1;

        if ($idLokasi === (int) $idLokasiDinas) {
            $idSkpd = $data['id_skpd'] ?? null;
            if ($idSkpd !== null && $idSkpd !== '') {
                $allowedDinasSkpd = [1, 2, 3, 4, 5];
                if (! in_array((int) $idSkpd, $allowedDinasSkpd, true)) {
                    throw ValidationException::withMessages([
                        'id_skpd' => 'Untuk lokasi Kantor Dinas, SKPD hanya boleh Sekretariat atau Bidang (ID 1-5), atau Tanpa SKPD.',
                    ]);
                }
            }
        }
    }

    /**
     * Batasi query ruangan pada lokasi fisik dalam cakupan user.
     *
     * Cakupan berdasarkan LOKASI FISIK sesuai role + penempatan skpd
     * (lihat AsetScope::idLokasi()): Admin Aset tanpa filter, Admin UPT
     * hanya lokasi UPT-nya, Admin Bidang hanya lokasi Dinas, identitas
     * invalid -> nol baris.
     */
    private function scopeLokasi(Builder $query): Builder
    {
        if ($this->scope->cakupanPenuh()) {
            return $query;
        }

        return $query->whereIn('id_lokasi', $this->scope->idLokasi() ?: [0]);
    }

    /**
     * Daftar ruangan + total aset di dalamnya.
     *
     * Total Aset = aset resmi yang menempati ruangan TANPA pemegang saat ini
     * (non-kendaraan) + jumlah baris inventaris lokal ruangan. Aset resmi yang
     * sedang dipegang pegawai tidak dihitung (ruangannya mengikuti pemegang),
     * dan inventaris lokal adalah entitas terpisah sehingga tidak ada
     * penghitungan ganda.
     */
    public function index(Request $request)
    {
        $ruanganList = $this->scopeLokasi(
            Ruangan::with(['skpd', 'lokasi'])
                ->withCount([
                    'penempatanAset as jumlah_aset' => fn ($q) => $q->where('status', 'aktif')
                        ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false)->whereDoesntHave('pemegangSaatIni')),
                    'inventaris as jumlah_inventaris' => fn ($q) => $q->where('status', InventarisRuangan::STATUS_AKTIF),
                ])
        )
            ->when($request->filled('search'), fn ($q) => $q->where('nama_ruangan', 'like', '%' . trim($request->search) . '%'))
            ->when($request->filled('lokasi'), fn ($q) => $q->where('id_lokasi', $request->integer('lokasi')))
            ->orderBy('id_ruangan')
            ->paginate(10)
            ->withQueryString();

        // Dropdown lokasi fisik untuk filter & modal tambah/edit ruangan —
        // disesuaikan cakupan user (non-admin hanya lokasi dalam scope-nya).
        $idLokasiScope = $this->scope->cakupanPenuh() ? null : ($this->scope->idLokasi() ?: [0]);

        $lokasiOptions = Lokasi::where('status', 'Aktif')->orderBy('jenis_lokasi')->orderBy('nama_lokasi')
            ->when($idLokasiScope !== null, fn ($q) => $q->whereIn('id_lokasi', $idLokasiScope))
            ->get(['id_lokasi', 'nama_lokasi', 'jenis_lokasi']);

        // SKPD pemilik ruangan = unit organisasi nyata (Sekretariat/Bidang/UPT).
        // Ruang bersama tidak punya SKPD (opsional di form). Non-admin hanya
        // melihat SKPD-nya sendiri.
        $skpdOptions = Skpd::whereNotNull('jenis_skpd')
            ->when(! $this->scope->cakupanPenuh(), fn ($q) => $q->where('id_skpd', $this->scope->skpdId()))
            ->orderBy('jenis_skpd')->orderBy('nama_skpd')
            ->get(['id_skpd', 'nama_skpd', 'jenis_skpd']);

        $isAdminAset = $this->isAdminAset();

        // Role yang boleh membuka form Tambah/Edit Ruangan = sesuai middleware
        // route (`Admin Aset,Admin UPT`). Ini hanya penanda tampilan: daftar
        // opsi lokasi tetap dibatasi cakupan user lewat `$lokasiOptions`, dan
        // `authorizeInputRuangan()` tetap menjadi pengaman utama di server.
        $bolehKelolaRuangan = $this->scope->cakupanPenuh() || $this->scope->isAdminUpt();

        return view('aset-ruangan.index', compact(
            'ruanganList',
            'lokasiOptions',
            'skpdOptions',
            'isAdminAset',
            'bolehKelolaRuangan',
        ));
    }

    /**
     * Detail aset yang berada di dalam satu ruangan.
     */
    public function show(Ruangan $ruangan)
    {
        // Guard di backend: user UPT / non-admin tidak boleh membuka ruang di luar lokasi cakupannya.
        $this->authorizeRuangan($ruangan, 'melihat isi ruangan');

        $ruangan->load(['skpd', 'lokasi']);

        // Basis query yang sama untuk kedua tabel: penempatan aktif, non-kendaraan,
        // status aset yang boleh tampil (aktif/nonaktif), tidak dalam kondisi Rusak Berat
        // (usulan penghapusan), dan ruangan yang sedang dibuka.
        $basisPenempatan = fn () => PenempatanAset::with(['aset.barang.kategori', 'aset.pemegangSaatIni.pegawai', 'aset.skpd', 'aset.lokasi'])
            ->where('id_ruangan', $ruangan->id_ruangan)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false)
                ->whereIn('status_aset', self::STATUS_USER)
                ->where('kondisi', '!=', 'Rusak Berat')
            );

        // Barang melekat: aset nonkendaraan tanpa pemegang aktif.
        //
        // Status finalisasi penempatan ikut dibawa ke view: ikon Mutasi
        // Ruangan hanya boleh muncul bila penempatan aktif sudah
        // difinalisasi (Aturan Finalisasi). Penyeragaman ini memakai
        // helper yang sama dengan backend, jadi tombol di tabel tidak
        // pernah berbeda dari penolakan di server.
        $kolomFinalisasi = AsetPenempatan::kolomFinalisasiTersedia();

        $asetMelekat = $basisPenempatan()
            ->whereDoesntHave('aset.pemegangSaatIni')
            ->get()
            ->map(function (PenempatanAset $p) use ($kolomFinalisasi) {
                $aset = $p->aset;

                if ($aset) {
                    $aset->setAttribute('is_finalized', $p->is_finalized);
                    $aset->setAttribute(
                        'boleh_mutasi_ruangan',
                        AsetPenempatan::bolehMutasiFinalisasi($p, $kolomFinalisasi)
                    );
                }

                return $aset;
            })
            ->filter()
            ->values();

        // Barang dipegang: aset nonkendaraan dengan pemegang aktif. Ruangannya
        // mengikuti ruangan kerja pemegang, jadi ditampilkan baca-saja.
        $asetDipegang = $basisPenempatan()
            ->whereHas('aset.pemegangSaatIni')
            ->get()
            ->map(fn ($p) => $p->aset)
            ->filter()
            ->values();

        $isAdminAset = $this->isAdminAset();

        // Inventaris lokal ruangan (non-aset). Tampil baca-saja untuk semua
        // yang boleh melihat ruangan; CRUD digating oleh $bolehKelolaRuangan.
        $inventarisLokal = $ruangan->inventaris()
            ->where('status', InventarisRuangan::STATUS_AKTIF)
            ->orderBy('kode_inventaris')
            ->get();

        // Admin Aset penuh; Admin UPT boleh mengelola dalam cakupan lokasinya.
        $bolehKelolaRuangan = $this->scope->cakupanPenuh() || $this->scope->isAdminUpt();

        // Hak unduh label ruangan & KIR: Admin Aset (global) dan Admin UPT (ruangan dalam UPT sendiri).
        $bolehCetakDokumen = $isAdminAset || $this->scope->isAdminUpt();

        // Kandidat ruangan tujuan pindah: dalam cakupan user, berstatus aktif,
        // selain ruangan yang sedang dibuka.
        $ruanganTujuan = Ruangan::where('status', 'Aktif')
            ->where('id_ruangan', '!=', $ruangan->id_ruangan)
            ->when(! $this->scope->cakupanPenuh(), function ($q) {
                $idLokasi = $this->scope->idLokasi() ?: [];
                $q->whereIn('id_lokasi', $idLokasi ?: [-1]);
            })
            ->orderBy('nama_ruangan')
            ->get(['id_ruangan', 'nama_ruangan']);

        // Optimasi: Hanya Admin Aset yang boleh memasang aset ke ruangan.
        // Cukup cek boolean exists() tanpa memuat seluruh koleksi aset ke memori PHP.
        $adaKandidatPasang = $isAdminAset && Aset::where('is_kendaraan', false)
            ->where('status_aset', 'aktif')
            ->where('kondisi', '!=', 'Rusak Berat')
            ->whereDoesntHave('pemegangSaatIni')
            ->whereDoesntHave('penempatanAktif')
            ->tap(fn ($q) => $this->scope->terapkan($q))
            ->exists();

        return view('aset-ruangan.show', compact(
            'ruangan',
            'asetMelekat',
            'asetDipegang',
            'isAdminAset',
            'adaKandidatPasang',
            'inventarisLokal',
            'bolehKelolaRuangan',
            'bolehCetakDokumen',
            'ruanganTujuan',
        ));
    }

    /**
     * Daftar kandidat aset yang dapat dipasang ke ruangan (untuk dropdown
     * "jenis barang" modal Pasang Aset).
     *
     * Sumber = master aset yang LAYAK dipasang: berstatus aktif, bukan
     * kendaraan, tanpa pemegang aktif, tanpa penempatan aktif, dan berada
     * dalam AsetScope. Hasil dikelompokkan per jenis barang (`id_barang`)
     * dengan info jumlah yang tersedia.
     */
    public function kandidatPasang(Ruangan $ruangan)
    {
        $this->authorizeAdminAset('Akses ditolak: hanya Admin Aset yang dapat memasang aset ke ruangan.');

        $daftar = $this->asetPasangKandidat();
        $kelompok = $daftar->groupBy('id_barang')->map(function ($items) {
            $first = $items->first();

            return [
                'id_barang' => (int) $first->id_barang,
                'nama_barang' => $first->barang?->nama_barang ?? '-',
                'tersedia' => $items->count(),
            ];
        })->sortBy('nama_barang')->values();

        return response()->json([
            'success' => true,
            'kandidat' => $kelompok,
        ]);
    }

    /**
     * Pasang BEBERAPA aset sejenis sekaligus ke ruangan ini.
     *
     * Backend MEMILIH aset nyata (tidak membuat record baru) yang sesuai jenis
     * barang, diurutkan KIB terendah → tertinggi, sebanyak `jumlah` yang
     * diminta. Bila jumlah melebihi yang tersedia, permintaan DITOLAK utuh
     * (tidak menyimpan sebagian). Seluruh pemasangan terjadi dalam satu
     * transaksi + `lockForUpdate`.
     *
     * Tidak membuat MutasiAset / DetailMutasiAset / BAST / SPPBI.
     */
    public function pasangAsetMassal(Request $request, Ruangan $ruangan)
    {
        $this->authorizeAdminAset('Akses ditolak: hanya Admin Aset yang dapat memasang aset ke ruangan.');

        $data = $request->validate([
            'id_barang' => ['required', 'integer', 'exists:master_barang,id_barang'],
            'jumlah' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'id_barang.required' => 'Pilih jenis barang terlebih dahulu.',
            'id_barang.exists' => 'Jenis barang yang dipilih tidak ditemukan.',
            'jumlah.required' => 'Jumlah aset wajib diisi.',
            'jumlah.min' => 'Jumlah minimal 1.',
            'jumlah.max' => 'Maksimal 100 aset per pemasangan.',
        ]);

        $sqlBaru = DB::transaction(function () use ($ruangan, $data) {
            // Kunci baris master jenis barang (seri) supaya pemilihan tetap stabil.
            MasterBarang::where('id_barang', $data['id_barang'])->lockForUpdate()->first();

            // Kandidat = aset nyata yang layak; diurutkan KIB terendah dulu.
            $kandidat = $this->asetPasangKandidat()->filter(fn ($a) => (int) $a->id_barang === (int) $data['id_barang']);

            if ($kandidat->isEmpty()) {
                throw ValidationException::withMessages([
                    'id_barang' => 'Tidak ada aset sejenis yang tersedia untuk dipasang.',
                ]);
            }

            $barangDb = MasterBarang::find($data['id_barang']);
            $diambil = $this->urutkanDanAmbil($kandidat, $data['jumlah']);

            // Jumlah < permintaan = tolak utuh, jangan menyimpan sebagian.
            if ($diambil->count() < (int) $data['jumlah']) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Aset sejenis yang tersedia hanya '.$diambil->count().' dari '.$data['jumlah'].' yang diminta. Aksi tidak menyimpan sebagian aset.',
                ]);
            }

            $dipasang = 0;
            foreach ($diambil as $kandidatAset) {
                $asetLock = Aset::where('id_aset', $kandidatAset->id_aset)->lockForUpdate()->first();

                if (! $asetLock || ! $this->scope->bolehAksesAset($asetLock)) {
                    $this->abortAsetGagalDipasang($barangDb?->nama_barang, 'di luar cakupan Anda');
                }

                if ($asetLock->pemegangSaatIni()->exists()) {
                    $this->abortAsetGagalDipasang($barangDb?->nama_barang, 'sudah memiliki pemegang aktif');
                }

                if ($asetLock->penempatanAktif()->exists()) {
                    $this->abortAsetGagalDipasang($barangDb?->nama_barang, 'sudah memiliki penempatan aktif');
                }

                $this->buatPenempatanAwal($asetLock, $ruangan);
                AsetPenempatan::sinkronAset($asetLock, null, $ruangan->id_ruangan);
                $dipasang++;
            }

            return ['dipasang' => $dipasang];
        });

        return response()->json([
            'success' => true,
            'message' => $sqlBaru['dipasang'].' aset berhasil dipasang di '.$ruangan->nama_ruangan.'.',
        ]);
    }

    /**
     * Pasang aset tunggal ke ruangan (penempatan awal).
     *
     * Hanya memasang master aset yang sudah ada dan belum punya penempatan/pemegang.
     * Penempatan baru berstatus Belum Difinalisasi (is_finalized = 0).
     */
    public function pasangAset(Ruangan $ruangan, Aset $aset)
    {
        $this->authorizeAdminAset('Akses ditolak: hanya Admin Aset yang dapat memasang aset ke ruangan.');

        abort_if((bool) $aset->is_kendaraan, 422, 'Aset kendaraan tidak dapat dipasang sebagai aset barang.');
        abort_if($aset->status_aset !== 'aktif', 422, 'Hanya aset berstatus aktif yang dapat dipasang.');
        abort_if($aset->kondisi === 'Rusak Berat', 422, 'Aset dengan kondisi Rusak Berat tidak dapat dipasang ke ruangan.');

        $aset = DB::transaction(function () use ($aset, $ruangan) {
            $asetLock = Aset::where('id_aset', $aset->id_aset)->lockForUpdate()->first();

            abort_unless(
                $asetLock && $this->scope->bolehAksesAset($asetLock),
                403,
                $this->scope->pesanAkses('memasang aset ke ruangan')
            );

            $pemegang = $asetLock->pemegangSaatIni?->pegawai?->nama_pegawai;

            if ($pemegang) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Aset sudah dipegang '.$pemegang.'. Ruangan mengikuti pemegang — gunakan Mutasi.',
                ]);
            }

            if ($asetLock->penempatanAktif()->exists()) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Aset sudah berada di ruangan '.$asetLock->penempatanAktif->ruangan?->nama_ruangan
                        .'. Gunakan Mutasi → Pindah Ruangan.',
                ]);
            }

            $this->buatPenempatanAwal($asetLock, $ruangan, false);

            AsetPenempatan::sinkronAset($asetLock, null, $ruangan->id_ruangan);

            return $asetLock->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil dipasang di '.$ruangan->nama_ruangan.'.',
            'id_lokasi' => $aset->id_lokasi,
        ]);
    }

    /**
     * Kandidat aset yang LAYAK dipasang: aktif, bukan kendaraan, tidak rusak berat
     * (bukan usulan penghapusan), tanpa pemegang aktif, tanpa penempatan aktif, dalam AsetScope.
     */
    private function asetPasangKandidat()
    {
        return Aset::with('barang')
            ->barang()
            ->where('status_aset', 'aktif')
            ->where('kondisi', '!=', 'Rusak Berat')
            ->whereDoesntHave('pemegangSaatIni')
            ->whereDoesntHave('penempatanAktif')
            ->tap(fn ($q) => $this->scope->terapkan($q))
            ->orderBy('nomor_kartu_barang')
            ->get(['id_aset', 'nomor_kartu_barang', 'id_barang']);
    }

    /**
     * Urutkan kandidat berdasarkan KIB (nomor kartu barang) terendah dulu dan
     * ambil $jumlah teratas.
     */
    private function urutkanDanAmbil($kandidat, int $jumlah)
    {
        return $kandidat
            ->sortBy(fn (Aset $a) => AsetPenempatan::kunciKib($a->nomor_kartu_barang))
            ->values()
            ->take($jumlah);
    }

    /**
     * Buat penempatan aktif baru tanpa pemegang.
     *
     * Default (`$langsungDifinalisasi = false`): `is_finalized = 0` (Belum
     * Difinalisasi) bila kolom tersedia — dipakai alur Pasang Aset dan Pasang
     * Aset Massal, yang penempatannya tetap wajib difinalisasi Admin Aset
     * secara terpisah.
     *
     * `$langsungDifinalisasi = true` dipakai HANYA alur mutasi (Mutasi
     * Ruangan / Pindah Ruangan): penempatan baru langsung berstatus 1 (Sudah
     * Difinalisasi) beserta `finalized_at`/`finalized_by` — mutasi tidak
     * memerlukan finalisasi ulang.
     */
    private function buatPenempatanAwal(Aset $aset, Ruangan $ruangan, bool $langsungDifinalisasi = false): PenempatanAset
    {
        $data = [
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangan->id_ruangan,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ];

        if (Schema::hasColumn('penempatan_aset', 'is_finalized')) {
            if ($langsungDifinalisasi) {
                // Sumber tunggal jejak finalisasi untuk mutasi.
                $data = array_merge(
                    $data,
                    AsetPenempatan::finalisasiLangsung(AsetPenempatan::kolomJejakFinalisasiTersedia())
                );
            } else {
                $data['is_finalized'] = 0;
            }
        }

        return PenempatanAset::create($data);
    }

    /**
     * Gagalkan pemasangan aset tertentu tanpa meringkus transaksi lain.
     */
    private function abortAsetGagalDipasang(?string $namaBarang, string $alasan): void
    {
        throw ValidationException::withMessages([
            'id_barang' => 'Sebagian aset jenis "'.($namaBarang ?? '-').'" tidak dapat dipasang karena '.$alasan.'. Aksi dibatalkan tanpa menyimpan sebagian.',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_ruangan' => ['required', 'string', 'max:100'],
            'lantai' => ['nullable', 'string', 'max:20'],
            'id_lokasi' => ['nullable', 'exists:lokasi,id_lokasi'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
        ]);

        $this->validasiLokasiDinas($data);
        $this->authorizeInputRuangan($data);

        Ruangan::create($data);

        return redirect()->route('aset-ruangan.index')
            ->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function update(Request $request, Ruangan $ruangan)
    {
        $this->authorizeRuangan($ruangan, 'mengubah ruangan');

        $data = $request->validate([
            'nama_ruangan' => ['required', 'string', 'max:100'],
            'lantai' => ['nullable', 'string', 'max:20'],
            'id_lokasi' => ['nullable', 'exists:lokasi,id_lokasi'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
        ]);

        $this->validasiLokasiDinas($data);
        $this->authorizeInputRuangan($data);

        DB::transaction(function () use ($ruangan, $data) {
            $lokasiLama = $ruangan->id_lokasi;

            $ruangan->update($data);

            // Tujuan A: bila lokasi fisik ruangan berubah, aset resmi yang
            // masih punya penempatan AKTIF di ruangan ini ikut menyesuaikan
            // `aset.id_lokasi` lewat mekanisme AsetPenempatan. Pemegang,
            // id_skpd, status, dan histori mutasi TIDAK diubah — tidak ada
            // mutasi baru yang dibuat.
            if ((int) $lokasiLama !== (int) $ruangan->id_lokasi) {
                $this->sinkronkanLokasiAsetRuangan($ruangan);
            }
        });

        return redirect()->route('aset-ruangan.index')
            ->with('success', 'Ruangan berhasil diperbarui.');
    }

    /**
     * Sinkronkan `aset.id_lokasi` untuk semua aset resmi yang punya penempatan
     * AKTIF di ruangan ini. Dipakai saat `ruangan.id_lokasi` berubah agar lokasi
     * fisik aset tidak tertinggal. Hanya kolom `id_lokasi` yang disentuh —
     * pemegang, `id_skpd`, status, dan histori tidak diubah.
     */
    private function sinkronkanLokasiAsetRuangan(Ruangan $ruangan): void
    {
        PenempatanAset::query()
            ->where('id_ruangan', $ruangan->id_ruangan)
            ->where('status', 'aktif')
            ->with('aset')
            ->lazyById(200, 'id_penempatan')
            ->each(function (PenempatanAset $penempatan) use ($ruangan) {
                if ($penempatan->aset) {
                    AsetPenempatan::sinkronLokasiAset($penempatan->aset, $ruangan->id_ruangan);
                }
            });
    }

    /**
     * Ubah status ruangan (Aktif <-> Nonaktif / Soft State Management).
     * Ruangan tidak boleh di-nonaktifkan jika masih ada aset aktif, inventaris lokal,
     * atau pegawai yang berkantor di dalamnya.
     */
    public function toggleStatus(Ruangan $ruangan)
    {
        $this->authorizeRuangan($ruangan, 'mengubah status ruangan');

        if ($ruangan->status === 'Aktif') {
            // Selaraskan dengan hitungan daftar ruangan: kendaraan tidak
            // dihitung sebagai aset barang yang menempati ruangan.
            $jumlahAsetAktif = $ruangan->penempatanAset()
                ->where('status', 'aktif')
                ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
                ->count();

            if ($jumlahAsetAktif > 0) {
                return back()->with(
                    'error',
                    'Ruangan tidak dapat dinonaktifkan karena masih terdapat aset di dalamnya. Silakan mutasikan aset terlebih dahulu.'
                );
            }

            // Inventaris lokal juga menahan penonaktifan. Berbeda dari aset
            // resmi, inventaris lokal dipindah/dihapus langsung (bukan mutasi).
            if ($ruangan->inventaris()->exists()) {
                return back()->with(
                    'error',
                    'Ruangan tidak dapat dinonaktifkan karena masih memiliki inventaris lokal. Pindahkan atau hapus inventaris tersebut terlebih dahulu.'
                );
            }

            // Pegawai aktif yang berkantor di ruangan ini juga menahan penonaktifan.
            $jumlahPegawai = \App\Models\Pegawai::where('id_ruangan', $ruangan->id_ruangan)->count();
            if ($jumlahPegawai > 0) {
                return back()->with(
                    'error',
                    "Ruangan tidak dapat dinonaktifkan karena masih tercatat sebagai ruangan kerja {$jumlahPegawai} pegawai. Pindahkan ruangan kerja pegawai terlebih dahulu."
                );
            }

            $ruangan->update(['status' => 'Nonaktif']);

            return back()->with('success', "Ruangan '{$ruangan->nama_ruangan}' berhasil dinonaktifkan.");
        }

        $ruangan->update(['status' => 'Aktif']);

        return back()->with('success', "Ruangan '{$ruangan->nama_ruangan}' berhasil diaktifkan kembali.");
    }

    /**
     * Unduh semua label aset aktif di satu ruangan (.xlsx).
     * Menggunakan master template label, menduplikasi blok B2:F7 ke bawah.
     */
    public function downloadLabelRuangan($id_ruangan)
    {
        $ruangan = Ruangan::findOrFail($id_ruangan);
        $this->authorizeRuangan($ruangan, 'mengunduh label aset ruangan');

        // Cakupan dari modal. Bila tidak ada parameter sama sekali (mis. tautan
        // lama/bookmark), pertahankan perilaku lama: hanya aset resmi.
        $flags = request();
        $adaScope = $flags->has('aset') || $flags->has('inventaris');
        $cetakAset = $adaScope ? $flags->boolean('aset') : true;
        $cetakInventaris = $adaScope ? $flags->boolean('inventaris') : false;

        $ruangan->load([
            'penempatanAset' => function ($q) {
                $q->where('status', 'aktif')
                    ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false)
                        ->whereDoesntHave('pemegangSaatIni')
                        ->whereIn('status_aset', self::STATUS_USER)
                        ->where('kondisi', '!=', 'Rusak Berat')
                    )
                    ->with(['aset.barang', 'aset.pemegangSaatIni.pegawai']);
            },
        ]);

        $asetList = $cetakAset
            ? $ruangan->penempatanAset->map(fn ($p) => $p->aset)->filter()->values()
            : collect();

        // Inventaris lokal hanya diikutkan bila dipilih; selaras dengan kartu
        // "Inventaris Lokal Ruangan" (barang non-aset, terpisah dari aset resmi).
        $inventarisList = $cetakInventaris
            ? InventarisRuangan::where('id_ruangan', $id_ruangan)->orderBy('kode_inventaris')->get()
            : collect();

        // Normalkan kedua jenis menjadi satu daftar baris label. Jenis dibedakan
        // lewat `{lokasi_user}`: aset resmi = nama ruangan, inventaris lokal =
        // "Inventaris Lokal" — sehingga tetap jelas bila dicetak dalam satu proses.
        $labelRows = collect();

        foreach ($asetList as $aset) {
            $labelRows->push([
                'tipe' => 'aset',
                'no_kartu' => $aset->nomor_kartu_barang ?? '-',
                'nama_barang' => $aset->barang->nama_barang ?? '-',
                'merk_tipe' => $aset->merk ?? '-',
                'tahun' => $aset->tanggal_pengadaan ? \Carbon\Carbon::parse($aset->tanggal_pengadaan)->format('Y') : date('Y'),
                'lokasi_user' => $ruangan->nama_ruangan,
                'ruangan' => $ruangan->nama_ruangan,
            ]);
        }

        foreach ($inventarisList as $inventaris) {
            $labelRows->push([
                'tipe' => 'inventaris',
                'no_kartu' => $inventaris->kode_inventaris,
                'nama_barang' => $inventaris->nama_barang,
                'merk_tipe' => $inventaris->merk ?: '-',
                'tahun' => '-',
                'lokasi_user' => 'Inventaris Lokal',
                'ruangan' => $ruangan->nama_ruangan,
            ]);
        }

        if ($labelRows->isEmpty()) {
            return back()->with('error', 'Belum ada barang melekat di ruangan ini yang dapat dicetak labelnya.');
        }

        $template = TemplateDokumen::where('kode_template', 'label')->first();
        if (!$template || !$template->file_path || !Storage::disk('public')->exists($template->file_path)) {
            return back()->with('error', 'File template label belum diunggah.');
        }

        try {
            $spreadsheet = IOFactory::load(Storage::disk('public')->path($template->file_path));

            // Hapus sheet tambahan jika template punya lebih dari 1 sheet bawaan.
            while ($spreadsheet->getSheetCount() > 1) {
                $spreadsheet->removeSheetByIndex(1);
            }

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Label Ruangan');

            // Dimensi 1 blok label: Baris 2 sampai 7, Kolom B sampai F
            $srcStartRow = 2;
            $srcEndRow   = 7;
            $labelHeight = 6;
            $gap         = 1; // 1 baris kosong sebagai pembatas potong gunting
            $step        = $labelHeight + $gap; // Tiap label baru berjarak 7 baris ke bawah

            $colStart = Coordinate::columnIndexFromString('B');
            $colEnd   = Coordinate::columnIndexFromString('F');

            // Catat seluruh posisi sel gabungan (merged cells) di blok B2:F7
            $baseMerges = [];
            foreach ($sheet->getMergeCells() as $mergeRange) {
                if (preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $mergeRange, $matches)) {
                    $r1 = (int) $matches[2];
                    $r2 = (int) $matches[4];
                    if ($r1 >= $srcStartRow && $r2 <= $srcEndRow) {
                        $baseMerges[] = [
                            'col1' => $matches[1],
                            'row1_offset' => $r1 - $srcStartRow,
                            'col2' => $matches[3],
                            'row2_offset' => $r2 - $srcStartRow,
                        ];
                    }
                }
            }

            // Ambil logo master yang ada di sheet (jika ada)
            $baseDrawing = null;
            $logoSourcePath = null;
            $logoTempPath = null;

            foreach ($sheet->getDrawingCollection() as $drawing) {
                if ($drawing instanceof Drawing && str_starts_with($drawing->getCoordinates(), 'B')) {
                    $baseDrawing = $drawing;
                    break;
                }
            }

            if ($baseDrawing) {
                $logoPath = $baseDrawing->getPath();

                // Path gambar dari worksheet hasil load berbentuk zip://...#xl/media/xxx
                // yang tidak bisa dipakai langsung, jadi ekstrak biner media ke file sementara.
                if (str_starts_with($logoPath, 'zip://') && preg_match('/#(xl\/media\/.+)$/', $logoPath, $mediaMatch)) {
                    $xlsxPath = Storage::disk('public')->path($template->file_path);
                    $zip = new \ZipArchive();

                    if ($zip->open($xlsxPath) === true) {
                        $binary = $zip->getFromName($mediaMatch[1]);
                        $zip->close();

                        if ($binary !== false) {
                            $ext = strtolower(pathinfo($mediaMatch[1], PATHINFO_EXTENSION)) ?: 'png';
                            $logoTempPath = storage_path('app/private/temp/label_' . uniqid() . '.' . $ext);
                            if (!is_dir(dirname($logoTempPath))) {
                                mkdir(dirname($logoTempPath), 0755, true);
                            }
                            file_put_contents($logoTempPath, $binary);
                            $logoSourcePath = $logoTempPath;
                        }
                    }
                } else {
                    $logoSourcePath = $logoPath;
                }
            }

            // 1. Gandakan layout blok kotak ke bawah di sheet yang sama
            for ($i = 1; $i < $labelRows->count(); $i++) {
                $destStartRow = $srcStartRow + ($i * $step);

                for ($r = 0; $r < $labelHeight; $r++) {
                    $fromRow = $srcStartRow + $r;
                    $toRow   = $destStartRow + $r;

                    // Samakan tinggi baris
                    $rowHeight = $sheet->getRowDimension($fromRow)->getRowHeight();
                    if ($rowHeight > 0) {
                        $sheet->getRowDimension($toRow)->setRowHeight($rowHeight);
                    }

                    for ($c = $colStart; $c <= $colEnd; $c++) {
                        $colStr = Coordinate::stringFromColumnIndex($c);

                        // Copy nilai & formula (setCellValue: sel tujuan masih baru)
                        $sheet->setCellValue($colStr . $toRow, $sheet->getCell($colStr . $fromRow)->getValue());

                        // Copy format background, border, font
                        $sheet->duplicateStyle($sheet->getStyle($colStr . $fromRow), $colStr . $toRow);
                    }
                }

                // Terapkan merge cells pada blok baru
                foreach ($baseMerges as $m) {
                    $sheet->mergeCells(
                        $m['col1'] . ($destStartRow + $m['row1_offset']) . ':'
                            . $m['col2'] . ($destStartRow + $m['row2_offset'])
                    );
                }

                // Duplikasi logo DISBUN ke kotak baru
                if ($baseDrawing && $logoSourcePath && file_exists($logoSourcePath)) {
                    $newDrawing = new Drawing();
                    $newDrawing->setName($baseDrawing->getName());
                    $newDrawing->setDescription($baseDrawing->getDescription() ?? '');
                    $newDrawing->setPath($logoSourcePath);
                    $newDrawing->setHeight($baseDrawing->getHeight());
                    $newDrawing->setWidth($baseDrawing->getWidth());
                    $newDrawing->setOffsetX($baseDrawing->getOffsetX());
                    $newDrawing->setOffsetY($baseDrawing->getOffsetY());
                    $newDrawing->setCoordinates('B' . $destStartRow);
                    $newDrawing->setWorksheet($sheet);
                }
            }

            // 2. Isi nilai & replace placeholder untuk setiap barang
            foreach ($labelRows as $index => $row) {
                $currentRow = $srcStartRow + ($index * $step);

                $values = [
                    '{no_kartu}' => $row['no_kartu'],
                    '{nama_barang}' => $row['nama_barang'],
                    '{merk_tipe}' => $row['merk_tipe'],
                    '{tahun}' => $row['tahun'],
                    '{lokasi_user}' => $row['lokasi_user'],
                    '{ruangan}' => $row['ruangan'],
                ];

                // Loop sel dalam batas kotak barang ini
                for ($r = $currentRow; $r < ($currentRow + $labelHeight); $r++) {
                    for ($c = $colStart; $c <= $colEnd; $c++) {
                        $colStr = Coordinate::stringFromColumnIndex($c);
                        $cell = $sheet->getCell($colStr.$r);
                        $val = (string) $cell->getValue();

                        if ($val !== '' && str_contains($val, '{')) {
                            $cell->setValue(strtr($val, $values));
                        }
                    }
                }
            }

            // 3. Ekspor 1 sheet tunggal
            $namaFile = 'Label_Ruangan_' . Str::slug($ruangan->nama_ruangan, '_') . '.xlsx';
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

            return response()->streamDownload(function () use ($writer, $logoTempPath) {
                $writer->save('php://output');
                if ($logoTempPath && is_file($logoTempPath)) {
                    @unlink($logoTempPath);
                }
            }, $namaFile, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $namaFile . '"',
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses template label. ' . $e->getMessage());
        }
    }

    /**
     * Unduh Kartu Inventaris Ruangan (KIR) (.docx).
     * Berbasis template Word 'kir': placeholder nama ruangan bisa berupa
     * ${NAMA_RUANGAN}/${nama_ruangan} maupun {NAMA_RUANGAN}/{nama_ruangan} tanpa
     * tanda dollar (ditangani via patch XML sebelum saveAs). Placeholder lain:
     * ${tanggal_cetak}, ${nama_pejabat_1}/${nip_pejabat_1},
     * ${nama_pejabat_2}/${nip_pejabat_2}, dan baris tabel ${no}, ${nama_barang},
     * ${merk}, ${volume}, ${kondisi_layak}, ${kondisi_tidak} yang di-clone per
     * kelompok (nama_barang + merk) aset aktif.
     */
    public function downloadKIR(Request $request, $id_ruangan)
    {
        $ruangan = Ruangan::findOrFail($id_ruangan);
        $this->authorizeRuangan($ruangan, 'mengunduh Kartu Inventaris Ruangan (KIR)');

        // 1. Ambil aset resmi yang melekat pada ruangan dan SUDAH DIFINALISASI (is_finalized = 1).
        // Aset belum difinalisasi (is_finalized = 0 atau NULL) tidak boleh dimasukkan ke dalam KIR.
        // Tidak termasuk kendaraan, yang sedang dipegang pegawai, status non-user, atau kondisi Rusak Berat.
        $penempatanQuery = PenempatanAset::with(['aset.barang'])
            ->where('id_ruangan', $id_ruangan)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false)
                ->whereDoesntHave('pemegangSaatIni')
                ->whereIn('status_aset', self::STATUS_USER)
                ->where('kondisi', '!=', 'Rusak Berat')
            );

        if (AsetPenempatan::kolomFinalisasiTersedia()) {
            $penempatanQuery->where('is_finalized', 1);
        }

        $penempatanList = $penempatanQuery->get();

        // 2. Ambil seluruh inventaris lokal ruangan berstatus aktif dan BUKAN Rusak Berat (inventaris lokal tidak memerlukan proses finalisasi)
        $inventarisList = InventarisRuangan::where('id_ruangan', $id_ruangan)
            ->where('status', InventarisRuangan::STATUS_AKTIF)
            ->where(function ($q) {
                $q->where('kondisi', '!=', 'Rusak Berat')
                  ->orWhereNull('kondisi');
            })
            ->orderBy('kode_inventaris')
            ->get();

        // 3. Normalisasi item dari kedua sumber data
        $itemsToPrint = collect();

        foreach ($penempatanList as $p) {
            $aset = $p->aset;
            if (! $aset) {
                continue;
            }

            $nama = $aset->barang?->nama_barang ?: 'Lainnya';
            $merk = $aset->merk ?: '-';
            $kondisi = $aset->kondisi ?: 'Baik';

            $itemsToPrint->push([
                'nama_barang' => $nama,
                'merk' => $merk,
                'kondisi' => $kondisi,
                'sumber' => 'aset',
                'id' => $aset->id_aset,
            ]);
        }

        foreach ($inventarisList as $inv) {
            $nama = $inv->nama_barang ?: 'Lainnya';
            $merk = $inv->merk ?: '-';
            $kondisi = $inv->kondisi ?: 'Baik';

            $itemsToPrint->push([
                'nama_barang' => $nama,
                'merk' => $merk,
                'kondisi' => $kondisi,
                'sumber' => 'inventaris',
                'id' => $inv->id_inventaris,
            ]);
        }

        if ($itemsToPrint->isEmpty()) {
            return back()->with('error', 'Belum ada barang melekat atau inventaris di ruangan ini yang memenuhi syarat untuk dicetak pada KIR.');
        }

        // 4. Grouping barang berdasarkan (nama_barang + merk + status kelayakan)
        // Mapping kondisi: Baik -> Layak, Rusak Ringan -> Tidak Layak
        $groupedItems = $itemsToPrint->groupBy(function ($item) {
            $nama = Str::upper(trim($item['nama_barang']));
            $merk = Str::upper(trim($item['merk']));
            $kondisiRaw = strtolower(trim($item['kondisi']));
            $kelayakan = ($kondisiRaw === 'baik') ? 'LAYAK' : 'TIDAK_LAYAK';

            return $nama . '|' . $merk . '|' . $kelayakan;
        })->map(function ($items) {
            $first = $items->first();
            $volume = $items->count();
            $kondisiRaw = strtolower(trim($first['kondisi']));
            $isLayak = ($kondisiRaw === 'baik');

            return [
                'nama_barang'   => $first['nama_barang'],
                'merk'          => $first['merk'],
                'volume'        => $volume,
                'kondisi_layak' => $isLayak ? 'v' : '',
                'kondisi_tidak' => ! $isLayak ? 'v' : '',
            ];
        })->values();

        // 5. Load Template Word KIR
        $templateDoc = TemplateDokumen::where('kode_template', 'kir')->first();
        if (!$templateDoc || !$templateDoc->file_path || !Storage::disk('public')->exists($templateDoc->file_path)) {
            return back()->with('error', 'File template KIR belum diunggah. Silakan unggah di menu Pengaturan Template Dokumen.');
        }

        $templatePath = storage_path('app/public/' . $templateDoc->file_path);
        $phpWord = new TemplateProcessor($templatePath);

        // 6. Set Header Ruangan, Lokasi, SKPD & Tanggal
        $namaRuangUpper = Str::upper($ruangan->nama_ruangan);
        $namaLokasi = $ruangan->lokasi?->nama_lokasi ?? 'Kantor Dinas Perkebunan Provinsi Jawa Timur';
        $namaSkpd = $ruangan->skpd?->nama_skpd ?? '-';

        $phpWord->setValue('nama_ruangan', $namaRuangUpper);
        $phpWord->setValue('NAMA_RUANGAN', $namaRuangUpper);
        $phpWord->setValue('ruangan', $ruangan->nama_ruangan);
        $phpWord->setValue('nama_lokasi', $namaLokasi);
        $phpWord->setValue('lokasi', $namaLokasi);
        $phpWord->setValue('nama_skpd', $namaSkpd);
        $phpWord->setValue('skpd', $namaSkpd);
        // 7. Data Pejabat Penandatangan & Tanggal Cetak (Diisi Manual oleh Pengguna)
        $namaPejabat1 = is_string($request->query('nama_pejabat_1')) ? trim($request->query('nama_pejabat_1')) : '';
        $nipPejabat1  = is_string($request->query('nip_pejabat_1')) ? trim($request->query('nip_pejabat_1')) : '';
        $namaPejabat2 = is_string($request->query('nama_pejabat_2')) ? trim($request->query('nama_pejabat_2')) : '';
        $nipPejabat2  = is_string($request->query('nip_pejabat_2')) ? trim($request->query('nip_pejabat_2')) : '';
        $rawTanggal   = is_string($request->query('tanggal_cetak')) ? trim($request->query('tanggal_cetak')) : '';

        // Batasi panjang string ke batas wajar
        if (mb_strlen($namaPejabat1) > 150) $namaPejabat1 = mb_substr($namaPejabat1, 0, 150);
        if (mb_strlen($nipPejabat1) > 50) $nipPejabat1 = mb_substr($nipPejabat1, 0, 50);
        if (mb_strlen($namaPejabat2) > 150) $namaPejabat2 = mb_substr($namaPejabat2, 0, 150);
        if (mb_strlen($nipPejabat2) > 50) $nipPejabat2 = mb_substr($nipPejabat2, 0, 50);

        // Format tanggal cetak: gunakan tanggal manual yang valid (Y-m-d), fallback ke hari ini
        $tanggalCetakTampil = now()->translatedFormat('d F Y');
        if ($rawTanggal !== '') {
            try {
                $parsedDate = \Carbon\Carbon::createFromFormat('Y-m-d', $rawTanggal);
                if ($parsedDate && $parsedDate->format('Y-m-d') === $rawTanggal) {
                    $tanggalCetakTampil = $parsedDate->translatedFormat('d F Y');
                }
            } catch (\Throwable) {
                // Abaikan kesalahan format, fallback ke tanggal hari ini
            }
        }

        $phpWord->setValue('tanggal_cetak', $tanggalCetakTampil);
        $phpWord->setValue('nama_pejabat_1', $namaPejabat1 !== '' ? $namaPejabat1 : '( .................................................... )');
        $phpWord->setValue('nip_pejabat_1', $nipPejabat1 !== '' ? $nipPejabat1 : '....................................................');
        $phpWord->setValue('nama_pejabat_2', $namaPejabat2 !== '' ? $namaPejabat2 : '( .................................................... )');
        $phpWord->setValue('nip_pejabat_2', $nipPejabat2 !== '' ? $nipPejabat2 : '....................................................');

        // 8. Gandakan Baris Tabel (cloneRow)
        $phpWord->cloneRow('no', $groupedItems->count());

        foreach ($groupedItems as $index => $row) {
            $no = $index + 1;
            $phpWord->setValue("no#{$no}", $no);
            $phpWord->setValue("nama_barang#{$no}", $row['nama_barang']);
            $phpWord->setValue("merk#{$no}", $row['merk']);
            $phpWord->setValue("volume#{$no}", $row['volume']);
            $phpWord->setValue("kondisi_layak#{$no}", $row['kondisi_layak']);
            $phpWord->setValue("kondisi_tidak#{$no}", $row['kondisi_tidak']);
        }

        // 9. Simpan & Unduh
        // Patch langsung ke XML untuk placeholder tanpa tanda dollar ($),
        // misal {NAMA_RUANGAN} / {nama_ruangan} yang tidak diproses setValue().
        try {
            $ref = new \ReflectionClass($phpWord);
            foreach (['tempDocumentMainPart', 'tempDocumentHeaders', 'tempDocumentFooters'] as $propName) {
                if (!$ref->hasProperty($propName)) {
                    continue;
                }
                $prop = $ref->getProperty($propName);
                $prop->setAccessible(true);
                $partXML = $prop->getValue($phpWord);
                if (!is_string($partXML) || $partXML === '') {
                    continue;
                }
                $partXML = str_replace(
                    ['{NAMA_RUANGAN}', '{nama_ruangan}', '${NAMA_RUANGAN}', '${nama_ruangan}'],
                    htmlspecialchars($namaRuangUpper),
                    $partXML
                );
                $prop->setValue($phpWord, $partXML);
            }
        } catch (\Throwable $th) {
            // Abaikan; fallback ke nilai placeholder yang masih tersisa di template.
        }

        $namaFile = 'KIR_' . Str::slug($ruangan->nama_ruangan, '_') . '.docx';
        $tempPath = tempnam(sys_get_temp_dir(), 'KIR_');
        $phpWord->saveAs($tempPath);

        return response()->download($tempPath, $namaFile)->deleteFileAfterSend(true);
    }

    /**
     * Keluarkan aset dari ruangan (nonaktifkan penempatan aktifnya).
     * Terlindungi dari kerentanan manipulasi ID (IDOR) dengan memastikan aset
     * benar-benar ditempatkan di ruangan yang ada pada URL.
     */
    public function detachAset(Ruangan $ruangan, Aset $aset)
    {
        $this->authorizeRuangan($ruangan, 'mengeluarkan aset dari ruangan');

        abort_unless(
            $this->scope->bolehAksesAset($aset),
            403,
            $this->scope->pesanAkses('mengeluarkan aset dari ruangan')
        );

        // Aset yang sedang dipegang pegawai tidak boleh dikeluarkan manual,
        // karena ruangannya selalu mengikuti ruangan pegawai.
        if ($aset->pemegangSaatIni()->exists()) {
            return redirect()->route('aset-ruangan.show', $ruangan->id_ruangan)
                ->with('error', 'Aset sedang dipegang pegawai, ruangan mengikuti pegawai dan tidak dapat dikeluarkan secara manual.');
        }

        DB::transaction(function () use ($ruangan, $aset) {
            // Pengamanan IDOR & Integritas: Cari penempatan aktif dan pastikan
            // aset memang benar-benar ditempatkan di ruangan yang ada pada URL.
            $penempatan = PenempatanAset::where('id_aset', $aset->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            abort_unless(
                $penempatan && (int) $penempatan->id_ruangan === (int) $ruangan->id_ruangan,
                422,
                'Aset tidak berada di ruangan ini.'
            );

            // Tutup penempatan aktif sambil mencatat tanggal selesai — histori dipertahankan.
            $penempatan->update(['status' => 'tidak aktif', 'tanggal_selesai' => now()->toDateString()]);

            // Kosongkan lokasi fisik bila aset tidak lagi punya penempatan aktif lain.
            if (! $aset->penempatanAktif()->exists()) {
                AsetPenempatan::sinkronLokasiAset($aset, null);
            }
        });

        return redirect()->route('aset-ruangan.show', $ruangan->id_ruangan)
            ->with('success', 'Aset dikeluarkan dari ruangan.');
    }

    /**
     * Mutasi pindah ruangan untuk aset melekat (tanpa pemegang aktif).
     *
     * Backend SAMA dengan alur mutasi tipe ruangan di AsetBarangController,
     * tetapi dipicu dari halaman Detail Ruangan dan secara ketat memvalidasi
     * ulang kelayakan di dalam transaksi: aset harus tanpa pemegang aktif,
     * punya penempatan aktif di ruangan asal, ruangan tujuan Aktif & dalam
     * AsetScope.
     *
     * Menutup penempatan lama, membuat penempatan baru LANGSUNG
     * `is_finalized = 1` (Sudah Difinalisasi, sesuai aturan mutasi ruangan —
     * tidak perlu finalisasi ulang), catat MutasiAset + DetailMutasiAset tepat
     * satu kali, dan sinkronkan SKPD/lokasi.
     */
    public function mutasiRuangan(Request $request, Ruangan $ruangan, Aset $aset)
    {
        abort_unless(
            $this->isAdminAset() || $this->scope->isAdminUpt(),
            403,
            'Hanya Admin Aset dan Admin UPT yang berwenang memindahkan aset ruangan.'
        );
        $this->authorizeRuangan($ruangan, 'memindahkan aset dari ruangan');

        $data = $request->validate([
            'id_ruangan_tujuan' => ['required', 'integer', 'exists:ruangan,id_ruangan'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ], [
            'id_ruangan_tujuan.required' => 'Pilih ruangan tujuan.',
            'id_ruangan_tujuan.exists' => 'Ruangan tujuan tidak ditemukan.',
        ]);

        $target = Ruangan::findOrFail($data['id_ruangan_tujuan']);

        $this->authorizeRuangan($target, 'memindahkan aset ke ruangan tujuan');

        /* Ruangan tujuan wajib Aktif. */
        abort_if($target->status !== 'Aktif', 422, 'Ruangan tujuan tidak aktif.');

        $result = DB::transaction(function () use ($aset, $ruangan, $target) {
            // Kunci baris aset + penempatan aktif supaya pemindahan bersamaan
            // tidak menghasilkan penempatan aktif ganda.
            $asetLock = Aset::where('id_aset', $aset->id_aset)->lockForUpdate()->first();

            abort_unless(
                $asetLock && $this->scope->bolehAksesAset($asetLock),
                403,
                $this->scope->pesanAkses('memindahkan aset')
            );

            if ($asetLock->pemegangSaatIni()->exists()) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Aset sedang dipegang pegawai; ruangan mengikuti pemegang. Gunakan Mutasi Ganti Pemegang.',
                ]);
            }

            $penempatanLama = PenempatanAset::where('id_aset', $asetLock->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            if (! $penempatanLama) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Aset tidak memiliki penempatan aktif.'
                ]);
            }

            // Mutasi hanya sah bila penempatan aktif SUDAH difinalisasi
            // (is_finalized = 1). Status 0 (Belum Difinalisasi) dan NULL
            // (Perlu Verifikasi) ditolak — aturan yang sama dengan ikon
            // Mutasi di tabel, jadi tombol dan server tidak berbeda.
            if (! AsetPenempatan::bolehMutasiFinalisasi($penempatanLama)) {
                throw ValidationException::withMessages([
                    'id_aset' => AsetPenempatan::pesanMutasiBelumDifinalisasi(),
                ]);
            }

            // Pastikan aset memang berada di ruangan asal yang sedang dibuka.
            abort_unless(
                (int) $penempatanLama->id_ruangan === (int) $ruangan->id_ruangan,
                422,
                'Aset tidak berada di ruangan asal ini.'
            );

            $ruanganLamaId = $penempatanLama->id_ruangan;
            $ruanganBaruId = $target->id_ruangan;

            // Sama ruangan = no-op (tidak ada mutasi baru).
            if ((int) $ruanganLamaId === (int) $ruanganBaruId) {
                return ['id_aset' => $asetLock->id_aset, 'sama' => true];
            }

            // Tutup penempatan lama (histori dipertahankan).
            $penempatanLama->update(['status' => 'tidak aktif', 'tanggal_selesai' => now()->toDateString()]);

            // Buka penempatan baru. Mutasi Ruangan adalah perpindahan ruang
            // yang sudah diperiksa Admin Aset, jadi penempatan baru LANGSUNG
            // berstatus "Sudah Difinalisasi" (is_finalized = 1) beserta jejak
            // finalisasinya — tidak perlu finalisasi ulang.
            $this->buatPenempatanAwal($asetLock, $target, true);

            // SKPD + lokasi mengikuti ruangan tujuan.
            AsetPenempatan::sinkronAset($asetLock, null, $ruanganBaruId);

            // Riwayat mutasi tepat satu kali.
            $mutasi = MutasiAset::create([
                'tanggal_mutasi' => now()->toDateString(),
                'jenis_mutasi' => 'Pindah Ruangan',
                'keterangan' => $data['keterangan'] ?? null,
                'id_user_penginput' => auth()->id(),
                'status_mutasi' => 'selesai',
            ]);

            DetailMutasiAset::create([
                'id_mutasi' => $mutasi->id_mutasi,
                'id_aset' => $asetLock->id_aset,
                'pegawai_lama' => null,
                'pegawai_baru' => null,
                'ruangan_lama' => $ruanganLamaId,
                'ruangan_baru' => $ruanganBaruId,
            ]);

            return ['id_aset' => $asetLock->id_aset, 'sama' => false];
        });

        return response()->json([
            'success' => true,
            'message' => $result['sama']
                ? 'Aset sudah berada di ruangan tersebut.'
                : 'Aset berhasil dipindah ke '.$target->nama_ruangan.'.',
        ]);
    }
}
