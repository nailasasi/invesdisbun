<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('aset', function (Blueprint $table) {
            if (Schema::hasColumn('aset', 'tanggal_perolehan')) {
                $table->dropColumn('tanggal_perolehan');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aset', function (Blueprint $table) {
            if (! Schema::hasColumn('aset', 'tanggal_perolehan')) {
                $table->date('tanggal_perolehan')->nullable()->after('tanggal_pengadaan');
            }
        });

        if (Schema::hasColumn('aset', 'tanggal_perolehan') && Schema::hasColumn('aset', 'tanggal_pengadaan')) {
            DB::table('aset')->whereNull('tanggal_perolehan')->update([
                'tanggal_perolehan' => DB::raw('tanggal_pengadaan'),
            ]);
        }
    }
};
