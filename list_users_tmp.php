<?php

require_once __DIR__ . '/bootstrap/app.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

foreach (User::query()->with('role')->with('pegawai.skpd')->get() as $u) {
    printf(
        "%s | role=%s | skpd=%s(%s) | status=%s | id_pegawai=%s\n",
        $u->username,
        $u->role?->nama_role,
        $u->pegawai?->skpd?->nama_skpd,
        $u->pegawai?->skpd?->jenis_skpd,
        $u->status_user,
        $u->id_pegawai
    );
}
