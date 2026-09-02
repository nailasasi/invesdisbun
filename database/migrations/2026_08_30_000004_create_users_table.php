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
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id_user');
            $table->unsignedInteger('id_pegawai')->nullable();
            $table->unsignedBigInteger('id_role')->nullable();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('status_user')->default('aktif');
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('id_pegawai')->references('id_pegawai')->on('pegawai')->onDelete('set null');
            $table->foreign('id_role')->references('id_role')->on('role')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
