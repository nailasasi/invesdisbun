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
        Schema::table('aset', function (Blueprint $table) {
            $table->string('nomor_kartu_barang', 255)->change();
        });
    }

    /**
     * Reverse the changes.
     */
    public function down(): void
    {
        Schema::table('aset', function (Blueprint $table) {
            $table->string('nomor_kartu_barang', 50)->change();
        });
    }
};
