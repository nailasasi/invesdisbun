<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membongkar overlay `unit_kerja` sepenuhnya.
 *
 * Setelah 000010 & 000011 berjalan, seluruh data organisasi sudah
 * ter-migrasi ke `skpd`. Tabel `unit_kerja` beserta kolom `id_unit_kerja`
 * di `pegawai`, `ruangan`, dan `aset` tidak lagi dipakai.
 *
 * Peringatan: `down()` hanya merekonstruksi struktur kosong. Data
 * `unit_kerja` lama TIDAK dapat dikembalikan — backup database sebelum
 * menjalankan `up()` pada data produksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pegawai', 'ruangan', 'aset'] as $tabel) {
            if (! Schema::hasColumn($tabel, 'id_unit_kerja')) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                if ($this->hasForeign($tabel, 'id_unit_kerja')) {
                    $table->dropForeign(['id_unit_kerja']);
                }
            });

            Schema::table($tabel, function (Blueprint $table) {
                $table->dropColumn('id_unit_kerja');
            });
        }

        Schema::dropIfExists('unit_kerja');
    }

    public function down(): void
    {
        if (! Schema::hasTable('unit_kerja')) {
            Schema::create('unit_kerja', function (Blueprint $table) {
                $table->increments('id_unit_kerja');
                $table->string('nama_unit_kerja', 100)->notNull();
                $table->enum('jenis_unit_kerja', ['Bidang', 'UPT'])->default('Bidang');
                $table->unsignedInteger('id_skpd')->nullable();
                $table->enum('status', ['Aktif', 'Nonaktif'])->default('Aktif');
                $table->timestamps();

                $table->unique('nama_unit_kerja');
                $table->foreign('id_skpd')->references('id_skpd')->on('skpd')->onDelete('set null');
            });
        }

        // Struktur dikembalikan, namun data lama (nama unit, pemetaan ke
        // skpd) sudah hilang. Aplikasi harus re-migrate bila benar-benar
        // ingin memakai overlay ini lagi.
        foreach (['pegawai', 'ruangan', 'aset'] as $tabel) {
            if (Schema::hasColumn($tabel, 'id_unit_kerja')) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                $table->unsignedInteger('id_unit_kerja')->nullable()->after('id_skpd');

                if ($tabel === 'aset') {
                    $table->foreign('id_unit_kerja')->references('id_unit_kerja')->on('unit_kerja')->nullOnDelete();
                } else {
                    $table->foreign('id_unit_kerja')->references('id_unit_kerja')->on('unit_kerja')->onDelete('set null');
                }
            });
        }
    }

    private function hasForeign(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) !== null;
    }
};
