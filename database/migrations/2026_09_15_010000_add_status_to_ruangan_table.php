<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ruangan', 'status')) {
            Schema::table('ruangan', function (Blueprint $table) {
                $table->enum('status', ['Aktif', 'Nonaktif'])->default('Aktif')->after('lantai');
            });

            DB::table('ruangan')->whereNull('status')->orWhere('status', '')->update(['status' => 'Aktif']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ruangan', 'status')) {
            Schema::table('ruangan', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};