<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dokumen_pbb_tanah', function (Blueprint $table) {
            $table->increments('id_pbb');
            $table->unsignedInteger('id_tanah')->nullable();
            $table->year('tahun_pbb')->nullable();
            $table->string('file_pbb', 255)->nullable();
            $table->date('tanggal_upload')->nullable();
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('id_tanah')->references('id_tanah')->on('tanah')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id_user')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen_pbb_tanah');
    }
};
