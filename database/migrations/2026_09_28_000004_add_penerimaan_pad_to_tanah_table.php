<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tanah', function (Blueprint $table) {
            $table->decimal('penerimaan_pad', 15, 2)->nullable()->after('nilai_perolehan');
        });

        DB::table('retribusi_tanah')
            ->select('id_tanah', DB::raw('SUM(PAD) as total'))
            ->groupBy('id_tanah')
            ->get()
            ->each(function ($row) {
                DB::table('tanah')
                    ->where('id_tanah', $row->id_tanah)
                    ->update(['penerimaan_pad' => $row->total]);
            });
    }

    public function down(): void
    {
        Schema::table('tanah', function (Blueprint $table) {
            $table->dropColumn('penerimaan_pad');
        });
    }
};