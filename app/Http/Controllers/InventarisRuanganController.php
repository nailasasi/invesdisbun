<?php

namespace App\Http\Controllers;

use App\Models\DetailMutasiAset;
use App\Models\InventarisRuangan;
use App\Models\MutasiAset;
use App\Models\Ruangan;
use App\Models\UsulanPenghapusan;
use App\Services\AsetScope;
use App\Services\UsulanPenghapusanService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD inventaris lokal ruangan (barang non-aset resmi).
 *
 * Mengikuti pola otorisasi Aset Ruangan: cakupan penuh hanya Admin Aset,
 * Admin UPT dibatasi lokasi UPT-nya.
 */
class InventarisRuanganController extends Controller
{
    /** Kondisi yang diakui (konsisten dengan nilai `aset.kondisi`). */
    private const KONDISI = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

    public function __construct(
        private readonly AsetScope $scope,
        private readonly ?UsulanPenghapusanService $usulanPenghapusan = null,
    ) {
    }

    private function usulan(): UsulanPenghapusanService
    {
        return $this->usulanPenghapusan ?? app(UsulanPenghapusanService::class);
    }

    private function authorizeRuangan(Ruangan $ruangan, string $aksi = 'mengelola inventaris ruangan'): void
    {
        abort_unless(
            $this->scope->bolehAksesRuangan($ruangan->id_ruangan),
            403,
            $this->scope->pesanAkses($aksi)
        );
    }

    /**
     * Pastikan record inventaris memang milik ruangan pada URL (tolak 404
     * bila id_ruangan tidak cocok) — mencegah manipulasi id lintas ruangan.
     */
    private function pastikanMilik(Ruangan $ruangan, InventarisRuangan $inventaris): void
    {
        abort_unless((int) $inventaris->id_ruangan === (int) $ruangan->id_ruangan, 404);
    }

    public function store(Request $request, Ruangan $ruangan)
    {
        $this->authorizeRuangan($ruangan, 'menambah inventaris ruangan');

        $data = $this->validated($request);

        $this->createInventaris($ruangan, $data);

        return back()->with('success', 'Inventaris lokal berhasil ditambahkan.');
    }

    public function update(Request $request, Ruangan $ruangan, InventarisRuangan $inventaris)
    {
        $this->authorizeRuangan($ruangan, 'mengubah inventaris ruangan');
        $this->pastikanMilik($ruangan, $inventaris);

        if ($inventaris->status !== InventarisRuangan::STATUS_AKTIF) {
            return back()->with('error', 'Inventaris lokal berstatus ' . $inventaris->status . ' tidak dapat diubah.');
        }

        $data = $this->validated($request, $inventaris);

        // Ruangan hanya berubah lewat endpoint `move`, bukan edit.
        unset($data['id_ruangan']);

        // Kode kosong = pertahankan kode lama (tidak diubah lewat edit).
        if (! isset($data['kode_inventaris']) || $data['kode_inventaris'] === null || $data['kode_inventaris'] === '') {
            unset($data['kode_inventaris']);
        }

        try {
            $inventaris->update($data);

            if (($data['kondisi'] ?? null) === UsulanPenghapusanService::KONDISI_RUSAK_BERAT && $inventaris->status === InventarisRuangan::STATUS_AKTIF) {
                $this->usulan()->usulkanInventaris(
                    $inventaris,
                    alasan: UsulanPenghapusanService::KONDISI_RUSAK_BERAT,
                    keterangan: 'Otomatis: kondisi inventaris diubah menjadi Rusak Berat.'
                );
            }
        } catch (QueryException $e) {
            if ($this->isDuplicateEntry($e)) {
                throw ValidationException::withMessages([
                    'kode_inventaris' => 'Kode inventaris sudah digunakan oleh barang lain.',
                ]);
            }

            throw $e;
        }

        return back()->with('success', 'Inventaris lokal berhasil diperbarui.');
    }

    /**
     * Tampilan label cetak untuk satu inventaris lokal.
     *
     * Hanya menampilkan data inventaris ruangan (non-aset) — tidak menyentuh
     * aset resmi. Akses dibatasi role di route + object check ruangan dan
     * kepemilikan record (anti-IDOR).
     */
    public function cetakLabel(Request $request, Ruangan $ruangan, InventarisRuangan $inventaris)
    {
        $this->authorizeRuangan($ruangan, 'mencetak label inventaris ruangan');
        $this->pastikanMilik($ruangan, $inventaris);

        $ruangan->load('lokasi');

        return view('aset-ruangan.inventaris-label', compact('ruangan', 'inventaris'));
    }

    /**
     * Pindahkan inventaris ke ruangan lain. Identitas & kode dipertahankan,
     * hanya `id_ruangan` yang berubah di dalam transaksi. Transaksi dicatat
     * pada modul Mutasi Aset (jenis 'Pindah Ruangan') dan detail mutasi.
     *
     * `keterangan` bersifat opsional: bila dikirim, isian pada modal mutasi
     * disimpan sebagai catatan inventaris dan keterangan mutasi aset.
     */
    public function move(Request $request, Ruangan $ruangan, InventarisRuangan $inventaris)
    {
        $this->authorizeRuangan($ruangan, 'memindahkan inventaris ruangan');
        $this->pastikanMilik($ruangan, $inventaris);

        if ($inventaris->status !== InventarisRuangan::STATUS_AKTIF) {
            return back()->with('error', 'Inventaris lokal berstatus ' . $inventaris->status . ' tidak dapat dipindahkan.');
        }

        $data = $request->validate([
            'id_ruangan_tujuan' => ['required', 'integer', 'exists:ruangan,id_ruangan'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $tujuan = Ruangan::findOrFail($data['id_ruangan_tujuan']);

        if ((int) $ruangan->id_ruangan === (int) $tujuan->id_ruangan) {
            return back()->with('error', 'Ruangan tujuan tidak boleh sama dengan ruangan asal.');
        }

        // Wajib berwenang atas ruangan asal (sudah) DAN tujuan.
        $this->authorizeRuangan($tujuan, 'memindahkan inventaris ke ruangan tujuan');

        abort_if($tujuan->status !== 'Aktif', 422, 'Ruangan tujuan harus berstatus aktif.');

        DB::transaction(function () use ($inventaris, $ruangan, $tujuan, $data) {
            // Ambil ulang data inventaris dengan row lock untuk mencegah race condition
            $lockedInv = InventarisRuangan::whereKey($inventaris->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Validasi ulang setelah data terkunci
            if ((int) $lockedInv->id_ruangan !== (int) $ruangan->id_ruangan) {
                throw ValidationException::withMessages([
                    'id_ruangan_tujuan' => 'Inventaris lokal sudah dipindahkan ke ruangan lain oleh transaksi lain.',
                ]);
            }

            if ($lockedInv->status !== InventarisRuangan::STATUS_AKTIF) {
                throw ValidationException::withMessages([
                    'id_ruangan_tujuan' => 'Inventaris lokal berstatus ' . $lockedInv->status . ' dan tidak dapat dipindahkan.',
                ]);
            }

            $update = ['id_ruangan' => $tujuan->id_ruangan];

            // Catatan inventaris hanya ditulis bila field-nya benar-benar dikirim.
            $catatan = null;
            if (array_key_exists('keterangan', $data)) {
                $rawCatatan = $data['keterangan'];
                $catatan = is_string($rawCatatan) && trim($rawCatatan) !== '' ? trim($rawCatatan) : null;
                $update['keterangan'] = $catatan;
            }

            // Hanya relasi ruangan yang berubah — kode & identitas dipertahankan.
            $lockedInv->update($update);

            // Buat riwayat transaksi mutasi pada tabel mutasi_aset
            $mutasi = MutasiAset::create([
                'tanggal_mutasi' => now()->toDateString(),
                'jenis_mutasi' => 'Pindah Ruangan',
                'keterangan' => array_key_exists('keterangan', $data) && ! empty($data['keterangan'])
                    ? trim($data['keterangan'])
                    : null,
                'id_user_penginput' => auth()->id(),
                'status_mutasi' => 'selesai',
            ]);

            // Buat detail transaksi pada tabel detail_mutasi_aset
            DetailMutasiAset::create([
                'id_mutasi' => $mutasi->id_mutasi,
                'id_aset' => null,
                'id_inventaris' => $lockedInv->id_inventaris,
                'pegawai_lama' => null,
                'pegawai_baru' => null,
                'ruangan_lama' => $ruangan->id_ruangan,
                'ruangan_baru' => $tujuan->id_ruangan,
            ]);
        });

        return back()->with('success', "Inventaris dipindahkan ke {$tujuan->nama_ruangan}.");
    }

    /**
     * Usulkan penghapusan inventaris lokal ruangan (bukan hard delete).
     *
     * Status inventaris menjadi 'diusulkan_hapus', kondisi menjadi 'Rusak Berat',
     * dan satu entri usulan dibuat pada tabel usulan_penghapusan.
     */
    public function usulkanPenghapusan(Ruangan $ruangan, InventarisRuangan $inventaris)
    {
        $this->authorizeRuangan($ruangan, 'mengusulkan penghapusan inventaris ruangan');
        $this->pastikanMilik($ruangan, $inventaris);

        if ($inventaris->status !== InventarisRuangan::STATUS_AKTIF) {
            return back()->with('error', 'Hanya inventaris lokal berstatus aktif yang dapat diusulkan penghapusan.');
        }

        if (UsulanPenghapusan::where('id_inventaris', $inventaris->id_inventaris)->where('status_usulan', 'diajukan')->exists()) {
            return back()->with('error', 'Inventaris lokal sudah memiliki usulan penghapusan yang aktif.');
        }

        $sukses = $this->usulan()->usulkanInventaris(
            $inventaris,
            alasan: UsulanPenghapusanService::KONDISI_RUSAK_BERAT,
            keterangan: 'Diusulkan melalui tombol Usulkan Penghapusan pada inventaris ruangan.'
        );

        if (! $sukses) {
            return back()->with('error', 'Gagal mengajukan usulan penghapusan inventaris lokal.');
        }

        return back()->with('success', 'Inventaris lokal berhasil diusulkan penghapusan (kondisi Rusak Berat) dan dialihkan ke antrean Usulan Penghapusan.');
    }

    /**
     * Endpoint destroy dialihkan ke alur usulan penghapusan (anti hard-delete).
     */
    public function destroy(Ruangan $ruangan, InventarisRuangan $inventaris)
    {
        return $this->usulkanPenghapusan($ruangan, $inventaris);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?InventarisRuangan $existing = null): array
    {
        $this->normalisasiKode($request);

        return $request->validate([
            'kode_inventaris' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('inventaris_ruangan', 'kode_inventaris')
                    ->ignore($existing?->id_inventaris, 'id_inventaris'),
            ],
            'nama_barang' => ['required', 'string', 'max:150'],
            'merk' => ['nullable', 'string', 'max:100'],
            'kondisi' => ['nullable', Rule::in(self::KONDISI)],
            'keterangan' => ['nullable', 'string'],
        ]);
    }

    /**
     * Rapikan kode manual sebelum validasi unik: trim spasi tepi dan ubah kode
     * kosong menjadi null (agar `unique` + auto-generate tidak terganggu).
     * Input yang hanya berisi spasi (bukan kosong) ditolak sebagai tidak valid.
     */
    private function normalisasiKode(Request $request): void
    {
        if (! $request->exists('kode_inventaris')) {
            return;
        }

        $raw = $request->input('kode_inventaris');

        if (! is_string($raw)) {
            return;
        }

        $trimmed = trim($raw);

        if ($raw !== '' && $trimmed === '') {
            throw ValidationException::withMessages([
                'kode_inventaris' => 'Kode inventaris tidak boleh hanya berisi spasi.',
            ]);
        }

        $request->merge(['kode_inventaris' => $trimmed === '' ? null : $trimmed]);
    }

    /**
     * Buat record. Bila kode manual diisi, dipakai apa adanya (sekali coba,
     * bentrok = error validasi). Bila kosong, kode di-generate ulang bila
     * terjadi bentrok (unique index) — mis. saat dua admin menyimpan bersamaan.
     */
    private function createInventaris(Ruangan $ruangan, array $data): InventarisRuangan
    {
        $data['id_ruangan'] = $ruangan->id_ruangan;

        $manualKode = $data['kode_inventaris'] ?? null;
        $manualKode = is_string($manualKode) && trim($manualKode) !== '' ? trim($manualKode) : null;

        if ($manualKode !== null) {
            $data['kode_inventaris'] = $manualKode;

            try {
                return InventarisRuangan::create($data);
            } catch (QueryException $e) {
                if ($this->isDuplicateEntry($e)) {
                    throw ValidationException::withMessages([
                        'kode_inventaris' => 'Kode inventaris sudah digunakan. Gunakan kode lain.',
                    ]);
                }

                throw $e;
            }
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $data['kode_inventaris'] = $this->generateKode($ruangan->id_ruangan);

            try {
                return InventarisRuangan::create($data);
            } catch (QueryException $e) {
                if (! $this->isDuplicateEntry($e) || $attempt === 4) {
                    throw $e;
                }
            }
        }

        // Tidak tercapai (loop selalu throw/return), jaga-jaga.
        throw new \RuntimeException('Gagal membuat kode inventaris unik.');
    }

    /**
     * Prefix stabil berdasarkan identitas ruangan: RU<alpha> untuk id 1 = RUA,
     * 2 = RUB, 27 = RUAA. Tidak bergantung pada nama ruangan.
     */
    private function generateKode(int $idRuangan): string
    {
        $prefix = 'RU' . $this->alphaId($idRuangan) . '-INV-';

        $max = 0;
        foreach (InventarisRuangan::where('kode_inventaris', 'like', $prefix . '%')->pluck('kode_inventaris') as $kode) {
            $seq = (int) substr($kode, strlen($prefix));
            if ($seq > $max) {
                $max = $seq;
            }
        }

        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * 1 => A, 2 => B, ... 26 => Z, 27 => AA (basis-26 alfabet).
     */
    private function alphaId(int $id): string
    {
        if ($id < 1) {
            return 'A';
        }

        $s = '';
        while ($id > 0) {
            $id--;
            $s = chr(65 + ($id % 26)) . $s;
            $id = intdiv($id, 26);
        }

        return $s;
    }

    private function isDuplicateEntry(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
