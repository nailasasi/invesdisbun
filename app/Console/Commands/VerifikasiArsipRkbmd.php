<?php

namespace App\Console\Commands;

use App\Http\Controllers\RkbmdController;
use App\Models\ArsipRkbmd;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Verifikasi pasca-migrasi arsip RKBMD.
 *
 * Untuk setiap record: memastikan file tersedia di disk PRIVAT dan (bila file
 * sumber lama masih ada) SHA-256 sumber == SHA-256 tujuan. Tidak mengubah
 * apa pun dan tidak menampilkan isi dokumen.
 *
 * Perintah ini HANYA memeriksa; tidak menyalin, memindah, atau menghapus.
 */
class VerifikasiArsipRkbmd extends Command
{
    protected $signature = 'arsip:verifikasi-privat
                            {--id= : Batasi ke satu id_arsip tertentu (opsional)}';

    protected $description = 'Memverifikasi arsip RKBMD tersedia di disk privat dan identik (SHA-256) dengan sumber lama.';

    public function handle(): int
    {
        $privat = Storage::disk(RkbmdController::DISK_ARSIP);
        $publik = Storage::disk(RkbmdController::DISK_ARSIP_LAMA);

        $query = ArsipRkbmd::query()->orderBy('id_arsip');
        if ($this->option('id') !== null) {
            $query->where('id_arsip', (int) $this->option('id'));
        }

        $ok = 0;
        $belum = 0;
        $tidakCocok = 0;

        foreach ($query->cursor() as $arsip) {
            $nama = basename((string) $arsip->file_path);
            $path = 'arsip-rkbmd/'.$nama;

            $adaPrivat = $nama !== '' && $nama !== '.' && $privat->exists($path);

            if (! $adaPrivat) {
                $belum++;
                $this->line(sprintf('id=%d | BELUM | %s | tidak ada di disk privat', $arsip->id_arsip, $nama ?: '-'));

                continue;
            }

            // Bila sumber lama masih ada, bandingkan hash.
            if ($publik->exists($path)) {
                $hashPrivat = hash('sha256', (string) $privat->get($path));
                $hashPublik = hash('sha256', (string) $publik->get($path));

                if (! hash_equals($hashPrivat, $hashPublik)) {
                    $tidakCocok++;
                    $this->line(sprintf('id=%d | BEDA | %s | SHA-256 tidak identik dengan sumber', $arsip->id_arsip, $nama));

                    continue;
                }
            }

            $ok++;
            $this->line(sprintf('id=%d | OK | %s | tersedia di disk privat', $arsip->id_arsip, $nama));
        }

        $this->newLine();
        $this->table(
            ['Hasil', 'Jumlah'],
            [
                ['OK (ada di privat, hash cocok)', $ok],
                ['Belum ada di privat', $belum],
                ['Isi berbeda dengan sumber', $tidakCocok],
            ]
        );

        return ($belum + $tidakCocok) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
