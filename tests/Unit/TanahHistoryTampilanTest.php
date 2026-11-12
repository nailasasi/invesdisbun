<?php

namespace Tests\Unit;

use App\Models\Pegawai;
use App\Models\TanahHistory;
use App\Models\User;
use Tests\TestCase;

/**
 * Tahap 3 — tampilan riwayat: judul aktivitas, identitas aktor (snapshot),
 * dan daftar field yang berubah. Tidak menyentuh database.
 */
class TanahHistoryTampilanTest extends TestCase
{
    public function test_judul_aktivitas_sesuai_objek_dan_aksi(): void
    {
        $kasus = [
            ['tanah', 'CREATE', 'Tambah Tanah'],
            ['tanah', 'UPDATE', 'Edit Tanah'],
            ['retribusi', 'CREATE', 'Tambah Retribusi'],
            ['retribusi', 'UPDATE', 'Edit Retribusi'],
            ['retribusi', 'DELETE', 'Hapus Retribusi'],
        ];

        foreach ($kasus as [$objek, $aksi, $harapan]) {
            $history = new TanahHistory(['objek' => $objek, 'aksi' => $aksi]);
            $this->assertSame($harapan, $history->judul_aktivitas);
        }
    }

    public function test_judul_aktivitas_fallback_untuk_kombinasi_tak_dikenal(): void
    {
        $this->assertSame('Perubahan Retribusi', (new TanahHistory([
            'objek' => 'retribusi',
            'aksi' => 'ANEH',
        ]))->judul_aktivitas);

        $this->assertSame('Perubahan Tanah', (new TanahHistory([
            'objek' => 'tanah',
            'aksi' => 'ANEH',
        ]))->judul_aktivitas);
    }

    public function test_nama_aktor_utama_dari_snapshot(): void
    {
        $history = new TanahHistory(['nama_aktor' => 'Budi Santoso']);

        $this->assertSame('Budi Santoso', $history->nama_aktor_tampil);
    }

    public function test_nama_aktor_fallback_ke_pegawai_relasi(): void
    {
        $user = new User(['username' => 'budi']);
        $user->setRelation('pegawai', new Pegawai(['nama_pegawai' => 'Budi Santoso']));

        $history = new TanahHistory(['nama_aktor' => null]);
        $history->setRelation('user', $user);

        $this->assertSame('Budi Santoso', $history->nama_aktor_tampil);
    }

    public function test_nama_aktor_fallback_ke_username(): void
    {
        $user = new User(['username' => 'budi']);
        $user->setRelation('pegawai', null);

        $history = new TanahHistory(['nama_aktor' => null]);
        $history->setRelation('user', $user);

        $this->assertSame('budi', $history->nama_aktor_tampil);
    }

    public function test_nama_aktor_fallback_terakhir_pengguna_sistem(): void
    {
        $history = new TanahHistory(['nama_aktor' => null]);
        $history->setRelation('user', null);

        $this->assertSame('Pengguna Sistem', $history->nama_aktor_tampil);
    }

    public function test_perubahan_tampil_hanya_field_yang_berbeda(): void
    {
        $history = new TanahHistory([
            'data_lama' => ['kib' => 'A', 'kondisi' => 'Baik', 'keterangan' => null],
            'data_baru' => ['kib' => 'B', 'kondisi' => 'Baik', 'keterangan' => null],
        ]);

        $perubahan = collect($history->perubahan_tampil)->keyBy('field');

        $this->assertTrue($perubahan->has('kib'));
        $this->assertSame('A', $perubahan['kib']['lama']);
        $this->assertSame('B', $perubahan['kib']['baru']);
        $this->assertFalse($perubahan->has('kondisi'));
        $this->assertFalse($perubahan->has('keterangan'));
    }

    public function test_perubahan_tampil_kosong_untuk_create_tanpa_data_lama(): void
    {
        $history = new TanahHistory([
            'data_lama' => null,
            'data_baru' => ['kib' => 'A'],
        ]);

        $fields = collect($history->perubahan_tampil)->pluck('field')->all();

        $this->assertContains('kib', $fields);
    }
}
