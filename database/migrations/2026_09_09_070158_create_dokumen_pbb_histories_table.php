<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_pbb_histories', function (Blueprint $table) {

            $table->id('id_history');

            $table->unsignedInteger('id_pbb');

            $table->unsignedInteger('id_user')
                ->nullable();

            $table->string('aksi');

            $table->string('file_lama')
                ->nullable();

            $table->string('file_baru')
                ->nullable();

            $table->timestamps();


            $table->foreign('id_pbb')
                ->references('id_pbb')
                ->on('dokumen_pbb_tanah')
                ->cascadeOnDelete();


            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('dokumen_pbb_histories');
    }
};