<?php

namespace Database\Seeders;

use App\Models\MasterKodeBarangRkbmd;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterKodeBarangRkbmdSeeder extends Seeder
{
    /**
     * Jalur relatif default berkas data PHP di dalam lingkungan proyek.
     */
    protected string $defaultRelativePath = 'seeders/data/master_kode_barang.php';

    /**
     * Jalankan seeder master kode barang RKBMD.
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

        $this->command?->info("Membaca data PHP Master Kode Barang: {$path}");

        $items = require $path;

        if (! is_array($items)) {
            throw new \UnexpectedValueException("Berkas data Master Kode Barang harus mengembalikan array PHP: {$path}");
        }

        $terbaca = 0;
        $kosong = 0;
        $duplikat = 0;
        $daftarKode = [];
        $records = [];
        $now = now()->toDateTimeString();

        foreach ($items as $key => $item) {
            $terbaca++;

            if (is_array($item)) {
                $rawKode = $item['kode_barang'] ?? $item['kode'] ?? null;
                $rawNama = $item['nama_barang'] ?? $item['uraian'] ?? $item['nama'] ?? '-';
            } else {
                // Mendukung format key-value ['kode' => 'nama']
                $rawKode = $key;
                $rawNama = $item;
            }

            $kode = trim((string) $rawKode);
            $nama = trim((string) $rawNama);

            if ($kode === '') {
                $kosong++;
                continue;
            }

            // Mencegah duplikasi kode barang
            if (isset($daftarKode[$kode])) {
                $duplikat++;
                continue;
            }

            $daftarKode[$kode] = true;
            $records[] = [
                'kode_barang' => $kode,
                'nama_barang' => $nama !== '' ? $nama : '-',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $totalValid = count($records);
        $this->command?->info("Total data mentah terbaca: {$terbaca}");
        $this->command?->info("Data kosong diabaikan: {$kosong}");
        $this->command?->info("Data duplikat diabaikan: {$duplikat}");
        $this->command?->info("Data kode barang valid & unik siap disimpan: {$totalValid}");

        $berhasil = 0;

        if (! empty($records)) {
            $chunkSize = 500;
            DB::transaction(function () use ($records, $chunkSize, &$berhasil) {
                foreach (array_chunk($records, $chunkSize) as $chunk) {
                    MasterKodeBarangRkbmd::upsert(
                        $chunk,
                        ['kode_barang'],
                        ['nama_barang', 'updated_at']
                    );
                    $berhasil += count($chunk);
                }
            });
        }

        $this->command?->info("Penyimpanan berhasil: {$berhasil} record.");

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
                throw new \InvalidArgumentException("Berkas alternatif Master Kode Barang tidak ditemukan di: {$filePath}");
            }

            return $filePath;
        }

        $defaultPath = database_path($this->defaultRelativePath);
        if (! file_exists($defaultPath)) {
            throw new \RuntimeException("Berkas data default Master Kode Barang tidak ditemukan di: {$defaultPath}");
        }

        return $defaultPath;
    }
}
