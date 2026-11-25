<?php

namespace Tests;

use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Catatan fixture sementara yang dibuat selama pengujian.
     *
     * @var array<int, array{user: User, pegawai: Pegawai}>
     */
    protected array $fixtureUsersSementara = [];

    /**
     * Dapatkan nama database pengujian MySQL yang terisolasi.
     * Mengutamakan konfigurasi dari .env.testing atau DB_DATABASE di phpunit.xml.
     */
    protected function namaDatabasePengujian(): string
    {
        // 1. Baca dari .env.testing jika tersedia
        $envTesting = base_path('.env.testing');
        if (file_exists($envTesting)) {
            foreach (file($envTesting) as $line) {
                if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                    $nilai = trim(explode('=', $line, 2)[1]);
                    if ($nilai !== '') {
                        return $nilai;
                    }
                }
            }
        }

        // 2. Baca dari environment testing
        $db = env('DB_DATABASE');
        if (!empty($db) && $db !== ':memory:') {
            return $db;
        }

        // 3. Fallback database pengujian terisolasi
        return 'invesdisbunver3_test';
    }

    /**
     * Mengambil akun user berdasarkan role jika ada, atau membuat user sementara jika belum tersedia.
     */
    protected function ambilAtauBuatUserRole(string $namaRole, ?int $idSkpd = null): User
    {
        $query = User::whereHas('role', fn ($q) => $q->where('nama_role', $namaRole));
        if ($idSkpd !== null) {
            $query->whereHas('pegawai', fn ($q) => $q->where('id_skpd', $idSkpd));
        }

        $user = $query->first();
        if ($user) {
            return $user;
        }

        return $this->buatUserSementara($namaRole, $idSkpd);
    }

    /**
     * Membuat akun user dan pegawai sementara khusus pengujian dengan identitas unik.
     */
    protected function buatUserSementara(string $namaRole, ?int $idSkpd = null): User
    {
        $role = Role::firstOrCreate(['nama_role' => $namaRole]);

        if ($idSkpd === null) {
            $skpd = Skpd::first();
            $idSkpd = $skpd?->id_skpd;
        }

        $uniqueSuffix = uniqid();
        $nip = '9' . substr(str_pad((string) random_int(10000000, 99999999), 8, '0'), 0, 8) . substr($uniqueSuffix, -6);
        $pegawai = Pegawai::create([
            'nip' => $nip,
            'nama_pegawai' => 'Uji ' . $namaRole . ' ' . $uniqueSuffix,
            'jabatan' => $namaRole,
            'id_skpd' => $idSkpd,
        ]);

        $username = 'usr_' . strtolower(str_replace(' ', '_', $namaRole)) . '_' . $uniqueSuffix;
        $user = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $role->id_role,
            'username' => $username,
            'password' => 'password123',
            'status_user' => 'aktif',
        ]);

        $this->fixtureUsersSementara[] = [
            'user' => $user,
            'pegawai' => $pegawai,
        ];

        return $user;
    }

    /**
     * Bersihkan seluruh fixture akun sementara setelah masing-masing pengujian selesai.
     */
    protected function tearDown(): void
    {
        if (!empty($this->fixtureUsersSementara)) {
            foreach ($this->fixtureUsersSementara as $item) {
                try {
                    $item['user']->delete();
                    $item['pegawai']->delete();
                } catch (\Throwable) {
                    // Abaikan jika sudah di-rollback oleh transaksi database pengujian
                }
            }
            $this->fixtureUsersSementara = [];
        }

        parent::tearDown();
    }
}
