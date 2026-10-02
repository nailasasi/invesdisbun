<?php
require 'C:/laragon/www/invesdisbun_laravel/vendor/autoload.php';
$app = require_once 'C:/laragon/www/invesdisbun_laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'invesdisbun']);
Illuminate\Support\Facades\DB::purge('mysql');
$app->make('view')->share('errors', new \Illuminate\Support\ViewErrorBag);

use App\Models\User;
use App\Http\Controllers\AsetBarangController;
use Illuminate\Http\Request;

foreach (User::where('status_user', 'aktif')->get() as $user) {
    Illuminate\Support\Facades\Auth::login($user);
    try {
        $ctrl = new AsetBarangController(new App\Services\AsetScope($user));
        $view = $ctrl->index(new Request());
        $body = is_string($view) ? $view : $view->render();
        $which = str_contains($body, 'id="aset-form"') ? 'admin-index' : (str_contains($body, 'id="search-pegawai"') ? 'pegawai-list' : 'other');
        $emptyP = str_contains($body, 'Belum ada data pegawai');
        $emptyA = str_contains($body, 'Belum ada data aset');
        $rows = substr_count($body, 'data-mutasi-modal');
        printf("%-18s | view=%-12s | emptyPegawai=%d emptyAset=%d | mutasiBtns=%d | tambah=%d\n",
            $user->username, $which, $emptyP, $emptyA, $rows, str_contains($body,'id="btn-tambah"'));
    } catch (\Throwable $e) {
        printf("%-18s | ERROR %s\n", $user->username, $e->getMessage());
    }
}
