<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create
                            {--username= : Username untuk login}
                            {--password= : Password (jika kosong, otomatis dibuat)}
                            {--role=Pegawai : Role pengguna (Admin Aset|Bidang Perlindungan Perkebunan|Bidang Pengelolahan dan Pemasaran Hasil|Bidang Produksi Tanaman Semusim|Bidang Produksi Tanaman Tahunan|UPT P2BTP|UPT PSBP|Pegawai)}
                            {--status=aktif : Status (aktif|nonaktif)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membuat akun pengguna baru (username & password) yang diberikan oleh Admin Aset.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = $this->option('username') ?: $this->ask('Username (untuk login)');
        $roleName = $this->option('role');
        $status = $this->option('status');
        $password = $this->option('password') ?: $this->secret('Password (biarkan kosong untuk acak)');

        if (empty($password)) {
            $password = str()->random(10);
            $this->warn("Password otomatis dibuat: {$password}");
        }

        $role = Role::where('nama_role', $roleName)->first();

        $validator = Validator::make([
            'username' => $username,
        ], [
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
        ]);

        if ($validator->fails() || ! $role) {
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }
            }
            if (! $role) {
                $this->error("Role '{$roleName}' tidak ditemukan. Pilihan: Admin Aset | Bidang Perlindungan Perkebunan | Bidang Pengelolahan dan Pemasaran Hasil | Bidang Produksi Tanaman Semusim | Bidang Produksi Tanaman Tahunan | UPT P2BTP | UPT PSBP | Pegawai");
            }

            return self::FAILURE;
        }

        User::create([
            'username' => $username,
            'password' => $password,
            'id_role' => $role->id_role,
            'id_pegawai' => null,
            'status_user' => in_array($status, ['aktif', 'nonaktif']) ? $status : 'aktif',
        ]);

        $this->info("Pengguna '{$username}' berhasil dibuat dengan role '{$roleName}'.");

        return self::SUCCESS;
    }
}
