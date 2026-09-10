<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tanah', function (Blueprint $table) {
            $table->string('penggunaan_air', 100)
                ->nullable()
                ->after('penggunaan');
        });
    }

    public function down(): void
    {
        Schema::table('tanah', function (Blueprint $table) {
            $table->dropColumn('penggunaan_air');
        });
    }
};