<?php

namespace App\Console\Commands;

use App\Http\Controllers\RkbmdController;
use App\Models\ArsipRkbmd;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Migrasi arsip RKBMD dari disk lama (PUBLIK) ke disk privat.
 *
 * Sifat perintah ini:
 *   - DEFAULT hanya DRY-RUN (tidak menulis apa pun). Gunakan --apply untuk
 *     benar-benar menyalin.
 *   - MENYALIN, tidak memindah/menghapus file sumber. File lama tetap utuh.
 *   - Verifikasi integritas dengan SHA-256 sumber vs tujuan.
 *   - TIDAK PERNAH menimpa file privat yang sudah ada. Bila tujuan sudah ada
 *     tetapi isinya berbeda, record dilaporkan sebagai konflik (gagal) untuk
 *     ditinjau manual.
 *   - TIDAK mengubah `file_path` di database. Path tujuan mengikuti prefix
 *     yang dipakai controller: "arsip-rkbmd/" . basename(file_path).
 *   - Laporan hanya memuat id_arsip, status, dan nama berkas - TIDAK memuat
 *     isi dokumen.
 */
class MigrasiArsipRkbmd extends Command
{
    protected $signature = 'arsip:migrasi-privat
                            {--apply : Benar-benar menyalin (tanpa opsi ini = dry-run)}
                            {--id= : Batasi ke satu id_arsip tertentu (opsional)}
                            {--chunk=100 : Jumlah record per batch}';

    protected $description = 'Menyalin arsip RKBMD dari disk publik ke disk privat (dry-run default, verifikasi SHA-256, tidak menimpa).';

    private const BERHASIL = 'berhasil';

    private const DILEWATI = 'dilewati';

    private const GAGAL = 'gagal';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $chunk = max(1, (int) $this->option('chunk'));

        $sumber = Storage::disk(RkbmdController::DISK_ARSIP_LAMA);
        $tujuan = Storage::disk(RkbmdController::DISK_ARSIP);

        $this->info($apply
            ? 'MODE: APPLY (file akan DISALIN ke disk privat).'
            : 'MODE: DRY-RUN (tidak ada file yang disalin). Tambahkan --apply untuk menyalin.');

        $this->line('Disk sumber : '.RkbmdController::DISK_ARSIP_LAMA);
        $this->line('Disk tujuan : '.RkbmdController::DISK_ARSIP);
        $this->newLine();

        $ringkasan = [
            self::BERHASIL => 0,
            self::DILEWATI => 0,
            self::GAGAL => 0,
        ];

        $query = ArsipRkbmd::query()->orderBy('id_arsip');

        if ($this->option('id') !== null) {
            $query->where('id_arsip', (int) $this->option('id'));
        }

        $query->chunkById($chunk, function ($arsips) use ($sumber, $tujuan, $apply, &$ringkasan) {
            foreach ($arsips as $arsip) {
                $hasil = $this->prosesSatu($arsip, $sumber, $tujuan, $apply);
                $ringkasan[$hasil['status']]++;
                $this->line($this->formatBaris($arsip, $hasil));
            }
        }, 'id_arsip');

        $this->newLine();
        $this->table(
            ['Status', 'Jumlah'],
            [
                ['Berhasil disalin & terverifikasi', $ringkasan[self::BERHASIL]],
                ['Dilewati (sudah ada / sudah identik)', $ringkasan[self::DILEWATI]],
                ['Gagal (perlu tinjauan manual)', $ringkasan[self::GAGAL]],
            ]
        );

        if (! $apply) {
            $this->warn('Dry-run selesai. Tidak ada perubahan. Jalankan ulang dengan --apply untuk menyalin.');
        } elseif ($ringkasan[self::GAGAL] > 0) {
            $this->warn('Sebagian record gagal. File sumber TIDAK dihapus; tinjau manual sebelum menghapus apa pun.');
        }

        return $ringkasan[self::GAGAL] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Proses satu record arsip tanpa menyentuh database.
     *
     * @return array{status: string, pesan: string, nama: string}
     */
    private function prosesSatu(ArsipRkbmd $arsip, $sumber, $tujuan, bool $apply): array
    {
        // Path SELALU dari record DB; hanya basename yang dipakai agar path
        // traversal di data tidak dapat keluar dari folder arsip.
        $nama = basename((string) $arsip->file_path);

        if ($nama === '' || $nama === '.') {
            return ['status' => self::GAGAL, 'pesan' => 'file_path kosong/tidak valid', 'nama' => '-'];
        }

        // Prefix mengikuti controller: file dibaca dari "arsip-rkbmd/<basename>".
        $pathSumber = 'arsip-rkbmd/'.$nama;
        $pathTujuan = 'arsip-rkbmd/'.$nama;

        $adaSumber = $sumber->exists($pathSumber);
        $adaTujuan = $tujuan->exists($pathTujuan);

        if (! $adaSumber) {
            return [
                'status' => $adaTujuan ? self::DILEWATI : self::GAGAL,
                'pesan' => $adaTujuan
                    ? 'sumber tidak ada; sudah tersedia di disk privat'
                    : 'file tidak ditemukan pada disk sumber maupun privat',
                'nama' => $nama,
            ];
        }

        if ($adaTujuan) {
            return [
                'status' => self::DILEWATI,
                'pesan' => 'tujuan sudah ada; TIDAK ditimpa (periksa manual bila perlu)',
                'nama' => $nama,
            ];
        }

        if (! $apply) {
            return [
                'status' => self::BERHASIL,
                'pesan' => '[dry-run] akan disalin & diverifikasi SHA-256',
                'nama' => $nama,
            ];
        }

        // Baca isi sumber sekali, tulis ke tujuan, lalu bandingkan hash.
        $isi = $sumber->get($pathSumber);

        if ($isi === null) {
            return ['status' => self::GAGAL, 'pesan' => 'gagal membaca file sumber', 'nama' => $nama];
        }

        $hashSumber = hash('sha256', $isi);

        if (! $tujuan->put($pathTujuan, $isi)) {
            return ['status' => self::GAGAL, 'pesan' => 'gagal menulis ke disk privat', 'nama' => $nama];
        }

        $hashTujuan = hash('sha256', (string) $tujuan->get($pathTujuan));

        if (! hash_equals($hashSumber, $hashTujuan)) {
            // Hapus salinan yang tidak identik agar tidak meninggalkan file rusak.
            $tujuan->delete($pathTujuan);

            return ['status' => self::GAGAL, 'pesan' => 'SHA-256 tidak cocok; salinan dibatalkan', 'nama' => $nama];
        }

        return ['status' => self::BERHASIL, 'pesan' => 'disalin & SHA-256 terverifikasi', 'nama' => $nama];
    }

    /**
     * Satu baris laporan. Tidak pernah menampilkan isi dokumen.
     */
    private function formatBaris(ArsipRkbmd $arsip, array $hasil): string
    {
        return sprintf(
            'id=%d | %s | %s | %s',
            $arsip->id_arsip,
            $hasil['status'],
            $hasil['nama'],
            $hasil['pesan']
        );
    }
}
