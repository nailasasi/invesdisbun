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
        if (Schema::hasTable('inventaris_ruangan') && ! Schema::hasColumn('inventaris_ruangan', 'status')) {
            Schema::table('inventaris_ruangan', function (Blueprint $table) {
                $table->string('status', 50)->default('aktif')->after('kondisi');
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('inventaris_ruangan') && Schema::hasColumn('inventaris_ruangan', 'status')) {
            Schema::table('inventaris_ruangan', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            });
        }
    }
};

