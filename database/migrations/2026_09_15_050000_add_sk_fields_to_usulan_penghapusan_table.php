<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan_penghapusan', function (Blueprint $table) {
            $table->string('no_sk', 100)->nullable()->after('keterangan');
            $table->date('tanggal_sk')->nullable()->after('no_sk');
        });
    }

    public function down(): void
    {
        Schema::table('usulan_penghapusan', function (Blueprint $table) {
            $table->dropColumn(['no_sk', 'tanggal_sk']);
        });
    }
};