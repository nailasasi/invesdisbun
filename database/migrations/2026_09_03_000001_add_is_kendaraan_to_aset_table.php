<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsKendaraanToAsetTable extends Migration
{
    public function up()
    {
        Schema::table('aset', function (Blueprint $table) {
            $table->boolean('is_kendaraan')->default(false)->after('status_aset');
        });
    }

    public function down()
    {
        Schema::table('aset', function (Blueprint $table) {
            $table->dropColumn('is_kendaraan');
        });
    }
}
