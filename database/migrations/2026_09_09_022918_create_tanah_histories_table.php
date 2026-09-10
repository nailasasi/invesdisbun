<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('tanah_histories', function (Blueprint $table) {

            $table->id('id_history');

            $table->unsignedInteger('id_tanah');

            $table->unsignedInteger('id_user');


            $table->string('aksi');


            $table->json('data_lama')
                ->nullable();


            $table->json('data_baru')
                ->nullable();


            $table->timestamps();


            $table->foreign('id_tanah')
                ->references('id_tanah')
                ->on('tanah')
                ->cascadeOnDelete();


            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnDelete();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('tanah_histories');
    }

};