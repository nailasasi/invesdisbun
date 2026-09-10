<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {

        Schema::table('dokumen_pbb_histories', function (Blueprint $table) {

            // hapus index lama
            $table->dropIndex('dokumen_pbb_histories_id_pbb_foreign');

        });


        Schema::table('dokumen_pbb_histories', function (Blueprint $table) {

            // ubah menjadi nullable
            $table->unsignedInteger('id_pbb')
                ->nullable()
                ->change();

        });


        Schema::table('dokumen_pbb_histories', function (Blueprint $table) {

            // buat foreign key baru
            $table->foreign('id_pbb')
                ->references('id_pbb')
                ->on('dokumen_pbb_tanah')
                ->nullOnDelete();

        });

    }


    public function down(): void
    {

        Schema::table('dokumen_pbb_histories', function (Blueprint $table) {

            $table->dropForeign([
                'id_pbb'
            ]);

        });

    }

};