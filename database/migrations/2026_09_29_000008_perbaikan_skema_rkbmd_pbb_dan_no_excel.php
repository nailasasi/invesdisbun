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
        Schema::dropIfExists('detail_usulan_rkbmd');

        if (! $this->foreignKeyTerkunci()) {
            Schema::table('dokumen_pbb_histories', function (Blueprint $table) {
                $table->foreign('id_tanah', 'dokumen_pbb_histories_id_tanah_foreign')
                    ->references('id_tanah')
                    ->on('tanah')
                    ->onDelete('cascade');
            });
        }

        Schema::table('tanah', function (Blueprint $table) {
            $table->string('no_excel', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->foreignKeyTerkunci()) {
            Schema::table('dokumen_pbb_histories', function (Blueprint $table) {
                $table->dropForeign('dokumen_pbb_histories_id_tanah_foreign');
            });
        }

        Schema::table('tanah', function (Blueprint $table) {
            $table->unsignedInteger('no_excel')->nullable()->change();
        });

        Schema::create('detail_usulan_rkbmd', function (Blueprint $table) {
            $table->increments('id_detail');
            $table->unsignedInteger('id_usulan')->nullable();
            $table->unsignedInteger('id_barang')->nullable();
            $table->integer('jumlah_usulan')->nullable();
            $table->integer('kebutuhan_riil')->nullable();
            $table->integer('kebutuhan_maksimum')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('id_usulan')->references('id_usulan')->on('usulan_rkbmd')->onDelete('cascade');
            $table->foreign('id_barang')->references('id_barang')->on('master_barang')->onDelete('set null');
        });
    }

    private function foreignKeyTerkunci(): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'dokumen_pbb_histories'
               AND COLUMN_NAME  = 'id_tanah'
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        );
    }
};
