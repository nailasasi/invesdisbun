<?php

namespace Database\Seeders;

use App\Models\MasterKegiatanRkbmd;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterKegiatanRkbmdSeeder extends Seeder
{
    /**
     * Jalur relatif default berkas data PHP di dalam lingkungan proyek.
     */
    protected string $defaultRelativePath = 'seeders/data/master_kegiatan.php';

    /**
     * Jalankan seeder master kegiatan RKBMD.
     *
     * @param string|null $filePath Jalur berkas alternatif (opsional). Jika tidak valid, proses seketika berhenti.
     * @return array Ringkasan hasil pembacaan & penyimpanan.
     *
     * @throws \InvalidArgumentException Jika jalur alternatif yang diberikan tidak ditemukan.
     * @throws \RuntimeException Jika berkas default proyek tidak ditemukan.
     * @throws \UnexpectedValueException Jika berkas tidak mengembalikan array PHP.
     */
    public function run(?string $filePath = null): array
    {
        $path = $this->resolvePath($filePath);

        $this->command?->info("Membaca data PHP Master Kegiatan: {$path}");

        $items = require $path;

        if (! is_array($items)) {
            throw new \UnexpectedValueException("Berkas data Master Kegiatan harus mengembalikan array PHP: {$path}");
        }

        $terbaca = 0;
        $kosong = 0;
        $duplikat = 0;
        $daftarKombinasi = [];
        $records = [];
        $now = now()->toDateTimeString();

        foreach ($items as $item) {
            $terbaca++;

            if (! is_array($item)) {
                $kosong++;
                continue;
            }

            $bidang = trim((string) ($item['bidang_urusan'] ?? $item[0] ?? ''));
            $program = trim((string) ($item['nama_program'] ?? $item[1] ?? ''));
            $subKegiatan = trim((string) ($item['nama_sub_kegiatan'] ?? $item[2] ?? ''));

            if ($bidang === '' || $program === '' || $subKegiatan === '') {
                $kosong++;
                continue;
            }

            // Kunci unik kombinasi Bidang Urusan + Nama Program + Sub Kegiatan
            $comboKey = $bidang . '||' . $program . '||' . $subKegiatan;

            if (isset($daftarKombinasi[$comboKey])) {
                $duplikat++;
                continue;
            }

            $daftarKombinasi[$comboKey] = true;
            $records[] = [
                'bidang_urusan' => $bidang,
                'nama_program' => $program,
                'nama_sub_kegiatan' => $subKegiatan,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $totalValid = count($records);
        $this->command?->info("Total data mentah terbaca: {$terbaca}");
        $this->command?->info("Data tidak lengkap/kosong diabaikan: {$kosong}");
        $this->command?->info("Data kombinasi duplikat diabaikan: {$duplikat}");
        $this->command?->info("Kombinasi unik (Bidang + Program + Sub Kegiatan) siap disimpan: {$totalValid}");

        $berhasil = 0;

        if (! empty($records)) {
            DB::transaction(function () use ($records, &$berhasil) {
                MasterKegiatanRkbmd::upsert(
                    $records,
                    ['bidang_urusan', 'nama_program', 'nama_sub_kegiatan'],
                    ['updated_at']
                );
                $berhasil = count($records);
            });
        }

        $this->command?->info("Penyimpanan berhasil: {$berhasil} record unik.");

        return [
            'terbaca' => $terbaca,
            'valid' => $totalValid,
            'kosong' => $kosong,
            'duplikat' => $duplikat,
            'berhasil' => $berhasil,
            'gagal' => 0,
        ];
    }

    /**
     * Resolusi jalur berkas sumber data PHP.
     * Jika path alternatif diberikan tetapi tidak valid, hentikan seketika (tanpa fallback diam-diam).
     *
     * @param string|null $filePath
     * @return string
     */
    protected function resolvePath(?string $filePath): string
    {
        if ($filePath !== null) {
            if (! file_exists($filePath)) {
                throw new \InvalidArgumentException("Berkas alternatif Master Kegiatan tidak ditemukan di: {$filePath}");
            }

            return $filePath;
        }

        $defaultPath = database_path($this->defaultRelativePath);
        if (! file_exists($defaultPath)) {
            throw new \RuntimeException("Berkas data default Master Kegiatan tidak ditemukan di: {$defaultPath}");
        }

        return $defaultPath;
    }
}
