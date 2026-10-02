<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Pindahkan sisa data tabel legacy `assets` (kategori/pegawai_id/spbi_path)
     * ke tabel standar `aset`, lalu hapus tabel legacy tersebut.
     */
    public function up(): void
    {
        if (! Schema::hasTable('assets')) {
            return;
        }

        foreach (DB::table('assets')->orderBy('id_aset')->get() as $legacy) {
            $kode = trim((string) $legacy->kode_aset);
            $nama = trim((string) $legacy->nama_aset);

            if ($kode !== '' && DB::table('aset')->where('nomor_kartu_barang', $kode)->exists()) {
                continue;
            }

            $idBarang = $this->resolveIdBarang($kode, $nama, $legacy->kategori ?? null);

            DB::table('aset')->insert([
                'id_barang' => $idBarang,
                'nomor_kartu_barang' => $kode !== '' ? $kode : null,
                'merk' => $nama !== '' ? $nama : null,
                'tanggal_pengadaan' => $legacy->tanggal_perolehan ?? null,
                'tanggal_perolehan' => $legacy->tanggal_perolehan ?? null,
                'nilai_perolehan' => null,
                'kondisi' => 'Baik',
                'status_aset' => $this->normalizeStatus($legacy->status ?? null),
                'is_kendaraan' => false,
                'created_at' => $legacy->created_at ?? now(),
                'updated_at' => $legacy->updated_at ?? now(),
            ]);
        }

        Schema::dropIfExists('assets');
    }

    /**
     * Cari master barang yang cocok (kode segmen terakhir / nama), atau buat baru
     * bila master barang-nya belum tersedia.
     */
    private function resolveIdBarang(string $kode, string $nama, ?string $kategori): ?int
    {
        if ($kode !== '') {
            $suffix = Str::afterLast($kode, '-');
            $found = DB::table('master_barang')->where('kode_barang', $suffix)->value('id_barang');
            if ($found) {
                return (int) $found;
            }
        }

        if ($nama !== '') {
            $kata = Str::of($nama)->explode(' ')->first();
            $found = DB::table('master_barang')
                ->whereRaw('LOWER(nama_barang) LIKE ?', ['%'.mb_strtolower($kata).'%'])
                ->value('id_barang');
            if ($found) {
                return (int) $found;
            }
        }

        if ($nama === '' && $kode === '') {
            return null;
        }

        return (int) DB::table('master_barang')->insertGetId([
            'kode_barang' => $kode !== '' ? $kode : null,
            'nama_barang' => $nama !== '' ? $nama : null,
            'satuan' => 'Unit',
            'id_kategori' => $kategori
                ? DB::table('kategori_aset')->where('nama_kategori', $kategori)->value('id_kategori')
                : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeStatus(?string $status): string
    {
        $status = mb_strtolower(trim((string) $status));

        return match ($status) {
            '', null => 'aktif',
            'aktif' => 'aktif',
            'nonaktif', 'tidak aktif', 'tidakaktif' => 'nonaktif',
            default => $status,
        };
    }

    /**
     * Tidak mengembalikan data: tabel legacy dikembalikan agar skema tetap
     * kompatibel bila ada kode lama, data hasil migrasi tetap berada di `aset`.
     */
    public function down(): void
    {
        if (Schema::hasTable('assets')) {
            return;
        }

        Schema::create('assets', function (Blueprint $table) {
            $table->increments('id_aset');
            $table->string('kode_aset', 50);
            $table->string('nama_aset', 150);
            $table->string('kategori', 100);
            $table->unsignedInteger('pegawai_id')->nullable();
            $table->string('status', 50);
            $table->date('tanggal_perolehan')->nullable();
            $table->string('spbi_path', 255)->nullable();
            $table->timestamps();
        });
    }
};
