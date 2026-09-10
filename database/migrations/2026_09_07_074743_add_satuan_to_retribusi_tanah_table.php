<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retribusi_tanah', function (Blueprint $table) {
            $table->string('satuan', 100)
                ->nullable()
                ->after('total_tarif_sewa');
        });
    }

    public function down(): void
    {
        Schema::table('retribusi_tanah', function (Blueprint $table) {
            $table->dropColumn('satuan');
        });
    }
};