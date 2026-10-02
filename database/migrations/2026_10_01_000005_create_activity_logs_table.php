<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log universal.
     *
     * Struktur polimorfik (subject_type + subject_id) supaya tabel ini bisa
     * dipakai controller modul lain di kemudian hari tanpa perlu migrasi
     * ulang. Kolom ditulis eksplisit (bukan morphs()) karena kunci utama
     * subjek pada tabel yang ada memakai int unsigned, sedangkan morphs()
     * memaksa bigint.
     *
     * id_user memakai onDelete('set null') — kebalikan dari tanah_histories
     * yang cascade — supaya riwayat tetap terbaca meski akun aktor dihapus.
     * Nama aktor disimpan sebagai snapshot string untuk keperluan yang sama.
     */
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id_activity_log');
            $table->string('log_type', 50);
            $table->string('description', 255)->nullable();

            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->unsignedInteger('id_user')->nullable();
            $table->string('nama_aktor', 100)->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'activity_logs_subject_index');
            $table->index('log_type', 'activity_logs_type_index');
            $table->index('id_user', 'activity_logs_user_index');

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};