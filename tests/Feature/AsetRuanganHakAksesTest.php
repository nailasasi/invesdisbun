<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\InventarisRuangan;
use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AsetRuanganHakAksesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        foreach (file(base_path('.env')) as $line) {
            if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                $nilai = trim(explode('=', $line, 2)[1]);

                return $nilai === '' ? 'invesdisbun' : $nilai;
            }
        }

        return 'invesdisbun';
    }

    private function userByRole(string $roleName): ?User
    {
        return User::whereHas('role', fn ($r) => $r->where('nama_role', $roleName))->first();
    }

    // =========================================================================
    // 1. ADMIN ASET
    // =========================================================================

    public function test_admin_aset_bisa_mengakses_index_dengan_pencarian_dan_filter(): void
    {
        $user = $this->userByRole('Admin Aset');
        if (! $user) {
            $this->markTestSkipped('User Admin Aset tidak ditemukan.');
        }

        $res = $this->actingAs($user)->get(route('aset-ruangan.index', ['search' => 'Ruang']));
        $res->assertOk();
        $res->assertSee('Tambah Ruangan');
        $res->assertSee('Cari nama ruangan...');
    }

    public function test_admin_aset_bisa_tambah_dan_edit_ruangan(): void
    {
        $user = $this->userByRole('Admin Aset');
        if (! $user) {
            $this->markTestSkipped('User Admin Aset tidak ditemukan.');
        }

        $lokasi = Lokasi::where('status', 'Aktif')->first();

        // Tambah ruangan
        $resStore = $this->actingAs($user)->post(route('aset-ruangan.store'), [
            'nama_ruangan' => 'Ruang Test Admin Aset Otomatis',
            'lantai' => 'Lantai 1',
            'id_lokasi' => $lokasi?->id_lokasi,
        ]);
        $resStore->assertRedirect(route('aset-ruangan.index'));

        $ruangan = Ruangan::where('nama_ruangan', 'Ruang Test Admin Aset Otomatis')->first();
        $this->assertNotNull($ruangan);

        // Edit ruangan
        $resUpdate = $this->actingAs($user)->post(route('aset-ruangan.update', $ruangan->id_ruangan), [
            'nama_ruangan' => 'Ruang Test Admin Aset Diubah',
            'lantai' => 'Lantai 2',
            'id_lokasi' => $lokasi?->id_lokasi,
        ]);
        $resUpdate->assertRedirect(route('aset-ruangan.index'));
        $this->assertSame('Ruang Test Admin Aset Diubah', $ruangan->fresh()->nama_ruangan);
    }

    // =========================================================================
    // 2. ADMIN BIDANG
    // =========================================================================

    public function test_admin_bidang_bisa_melihat_ruangan_dinas_lintas_bidang_dan_melakukan_search(): void
    {
        $user = $this->userByRole('Admin Bidang');
        if (! $user) {
            $this->markTestSkipped('User Admin Bidang tidak ditemukan.');
        }

        $res = $this->actingAs($user)->get(route('aset-ruangan.index', ['search' => 'Rapat']));
        $res->assertOk();
        // Tombol "Tambah Ruangan" dilarang untuk Admin Bidang
        $res->assertDontSee('Tambah Ruangan');
        // Fitur search & filter form tetap ada dan bisa digunakan
        $res->assertSee('Cari nama ruangan...');
    }

    public function test_admin_bidang_ditolak_saat_tambah_edit_toggle_detach_cetak_dan_pasang(): void
    {
        $user = $this->userByRole('Admin Bidang');
        if (! $user) {
            $this->markTestSkipped('User Admin Bidang tidak ditemukan.');
        }

        $ruangan = Ruangan::where('status', 'Aktif')->first();
        $aset = Aset::where('is_kendaraan', false)->first();

        // 1. Ditolak Store (403 via middleware role:Admin Aset,Admin UPT)
        $this->actingAs($user)->post(route('aset-ruangan.store'), [
            'nama_ruangan' => 'Ruang Liar Bidang',
        ])->assertForbidden();

        // 2. Ditolak Update (403)
        $this->actingAs($user)->post(route('aset-ruangan.update', $ruangan->id_ruangan), [
            'nama_ruangan' => 'Ruang Diubah Bidang',
        ])->assertForbidden();

        // 3. Ditolak Toggle Status (403)
        $this->actingAs($user)->post(route('aset-ruangan.toggle-status', $ruangan->id_ruangan))
            ->assertForbidden();

        // 4. Ditolak Detach Aset (403)
        if ($aset) {
            $this->actingAs($user)->delete(route('aset-ruangan.aset.detach', [
                'ruangan' => $ruangan->id_ruangan,
                'aset' => $aset->id_aset,
            ]))->assertForbidden();
        }

        // 5. Ditolak Cetak Label (403)
        $this->actingAs($user)->get(route('aset-ruangan.label.download', $ruangan->id_ruangan))
            ->assertForbidden();

        // 6. Ditolak Cetak KIR (403)
        $this->actingAs($user)->get(route('aset-ruangan.kir.download', $ruangan->id_ruangan))
            ->assertForbidden();

        // 7. Ditolak Pasang Aset Massal (403)
        $this->actingAs($user)->post(route('aset-ruangan.aset.pasang-massal', $ruangan->id_ruangan), [
            'id_barang' => 1,
            'jumlah' => 1,
        ])->assertForbidden();
    }

    // =========================================================================
    // 3. ADMIN UPT (ISOLASI & HAK AKSES UPT)
    // =========================================================================

    public function test_admin_upt_hanya_melihat_ruangan_di_lokasi_upt_sendiri(): void
    {
        $userUpt = $this->userByRole('Admin UPT');
        $lokasiUpt = Lokasi::untukPegawai($userUpt?->pegawai);

        if (! $userUpt || ! $lokasiUpt) {
            $this->markTestSkipped('User Admin UPT dengan lokasi valid tidak ditemukan.');
        }

        $idLokasiUpt = $lokasiUpt->id_lokasi;

        // Ambil satu ruangan di lokasi UPT sendiri dan satu di luar UPT (misal Dinas / UPT lain)
        $ruanganOwnUpt = Ruangan::where('id_lokasi', $idLokasiUpt)->first();
        $ruanganOther = Ruangan::where('id_lokasi', '!=', $idLokasiUpt)->first();

        // Index page
        $resIndex = $this->actingAs($userUpt)->get(route('aset-ruangan.index'));
        $resIndex->assertOk();
        $resIndex->assertSee('Tambah Ruangan');
        $resIndex->assertSee('Cari nama ruangan...');

        if ($ruanganOwnUpt) {
            $resIndex->assertSee($ruanganOwnUpt->nama_ruangan);
            // Show page ruangan sendiri diizinkan
            $this->actingAs($userUpt)->get(route('aset-ruangan.show', $ruanganOwnUpt->id_ruangan))->assertOk();
        }

        if ($ruanganOther) {
            $resIndex->assertDontSee($ruanganOther->nama_ruangan);
            // Show page ruangan lain ditolak (403)
            $this->actingAs($userUpt)->get(route('aset-ruangan.show', $ruanganOther->id_ruangan))->assertForbidden();
        }
    }

    public function test_admin_upt_bisa_kelola_ruangan_di_upt_sendiri_tetapi_ditolak_di_luar(): void
    {
        $userUpt = $this->userByRole('Admin UPT');
        $lokasiUpt = Lokasi::untukPegawai($userUpt?->pegawai);

        if (! $userUpt || ! $lokasiUpt) {
            $this->markTestSkipped('User Admin UPT dengan lokasi valid tidak ditemukan.');
        }

        $idLokasiUpt = $lokasiUpt->id_lokasi;
        $lokasiLain = Lokasi::where('id_lokasi', '!=', $idLokasiUpt)->first();

        // 1. Tambah ruangan di UPT sendiri: BERHASIL
        $resStoreOwn = $this->actingAs($userUpt)->post(route('aset-ruangan.store'), [
            'nama_ruangan' => 'Ruang UPT Sendiri Baru',
            'id_lokasi' => $idLokasiUpt,
        ]);
        $resStoreOwn->assertRedirect(route('aset-ruangan.index'));

        // 2. Tambah ruangan di lokasi luar: DITOLAK (403)
        if ($lokasiLain) {
            $this->actingAs($userUpt)->post(route('aset-ruangan.store'), [
                'nama_ruangan' => 'Ruang UPT di Lokasi Lain',
                'id_lokasi' => $lokasiLain->id_lokasi,
            ])->assertForbidden();
        }

        // 3. Edit ruangan di UPT sendiri: BERHASIL
        $ruanganOwn = Ruangan::where('id_lokasi', $idLokasiUpt)->first();
        if ($ruanganOwn) {
            $resUpdateOwn = $this->actingAs($userUpt)->post(route('aset-ruangan.update', $ruanganOwn->id_ruangan), [
                'nama_ruangan' => $ruanganOwn->nama_ruangan . ' Diperbarui',
                'id_lokasi' => $idLokasiUpt,
            ]);
            $resUpdateOwn->assertRedirect(route('aset-ruangan.index'));
        }

        // 4. Edit ruangan di luar UPT: DITOLAK (403)
        $ruanganOther = Ruangan::where('id_lokasi', '!=', $idLokasiUpt)->first();
        if ($ruanganOther) {
            $this->actingAs($userUpt)->post(route('aset-ruangan.update', $ruanganOther->id_ruangan), [
                'nama_ruangan' => 'Nama Bajakan',
                'id_lokasi' => $ruanganOther->id_lokasi,
            ])->assertForbidden();
        }
    }

    public function test_admin_upt_bisa_cetak_label_dan_kir_di_upt_sendiri_tetapi_ditolak_di_ruangan_lain(): void
    {
        $userUpt = $this->userByRole('Admin UPT');
        $lokasiUpt = Lokasi::untukPegawai($userUpt?->pegawai);

        if (! $userUpt || ! $lokasiUpt) {
            $this->markTestSkipped('User Admin UPT dengan lokasi valid tidak ditemukan.');
        }

        $idLokasiUpt = $lokasiUpt->id_lokasi;
        $ruanganOwn = Ruangan::where('id_lokasi', $idLokasiUpt)->first();
        $ruanganOther = Ruangan::where('id_lokasi', '!=', $idLokasiUpt)->first();

        if ($ruanganOwn) {
            // Label download untuk ruangan sendiri -> diizinkan (200 / streamed download)
            $resLabelOwn = $this->actingAs($userUpt)->get(route('aset-ruangan.label.download', [
                'ruangan' => $ruanganOwn->id_ruangan,
                'aset' => 1,
            ]));
            $this->assertSame(200, $resLabelOwn->getStatusCode());

            // KIR download untuk ruangan sendiri -> diizinkan
            $resKirOwn = $this->actingAs($userUpt)->get(route('aset-ruangan.kir.download', $ruanganOwn->id_ruangan));
            $this->assertSame(200, $resKirOwn->getStatusCode());
        }

        if ($ruanganOther) {
            // Label download untuk ruangan di luar UPT -> 403 Forbidden
            $this->actingAs($userUpt)->get(route('aset-ruangan.label.download', [
                'ruangan' => $ruanganOther->id_ruangan,
                'aset' => 1,
            ]))->assertForbidden();

            // KIR download untuk ruangan di luar UPT -> 403 Forbidden
            $this->actingAs($userUpt)->get(route('aset-ruangan.kir.download', $ruanganOther->id_ruangan))
                ->assertForbidden();
        }
    }

    public function test_admin_upt_bisa_keluarkan_aset_di_upt_sendiri_tetapi_ditolak_di_ruangan_lain(): void
    {
        $userUpt = $this->userByRole('Admin UPT');
        $lokasiUpt = Lokasi::untukPegawai($userUpt?->pegawai);

        if (! $userUpt || ! $lokasiUpt) {
            $this->markTestSkipped('User Admin UPT dengan lokasi valid tidak ditemukan.');
        }

        $idLokasiUpt = $lokasiUpt->id_lokasi;
        $ruanganOwn = Ruangan::where('id_lokasi', $idLokasiUpt)->first();
        $ruanganOther = Ruangan::where('id_lokasi', '!=', $idLokasiUpt)->first();

        // Siapkan aset melekat di ruangan UPT
        if ($ruanganOwn) {
            $aset = Aset::where('is_kendaraan', false)
                ->where('status_aset', 'aktif')
                ->whereDoesntHave('pemegangSaatIni')
                ->first();

            if ($aset) {
                // Sinkronkan aset ke ruangan UPT
                $aset->update([
                    'id_lokasi' => $ruanganOwn->id_lokasi,
                    'id_skpd' => $ruanganOwn->id_skpd,
                ]);

                // Buat penempatan aktif di ruangan UPT
                PenempatanAset::where('id_aset', $aset->id_aset)->update(['status' => 'tidak aktif']);
                PenempatanAset::create([
                    'id_aset' => $aset->id_aset,
                    'id_ruangan' => $ruanganOwn->id_ruangan,
                    'tanggal_mulai' => now()->toDateString(),
                    'status' => 'aktif',
                ]);

                // Admin UPT mengeluarkan aset dari ruangannya sendiri: BERHASIL
                $resDetach = $this->actingAs($userUpt)->delete(route('aset-ruangan.aset.detach', [
                    'ruangan' => $ruanganOwn->id_ruangan,
                    'aset' => $aset->id_aset,
                ]));
                $resDetach->assertRedirect(route('aset-ruangan.show', $ruanganOwn->id_ruangan));

                // Pastikan status penempatan sudah 'tidak aktif'
                $penempatan = PenempatanAset::where('id_aset', $aset->id_aset)
                    ->where('id_ruangan', $ruanganOwn->id_ruangan)
                    ->latest('id_penempatan')
                    ->first();
                $this->assertSame('tidak aktif', $penempatan?->status);
            }
        }

        // Admin UPT mencoba keluarkan aset dari ruangan di luar UPT: DITOLAK (403)
        if ($ruanganOther) {
            $asetOther = Aset::where('is_kendaraan', false)->first();
            if ($asetOther) {
                $this->actingAs($userUpt)->delete(route('aset-ruangan.aset.detach', [
                    'ruangan' => $ruanganOther->id_ruangan,
                    'aset' => $asetOther->id_aset,
                ]))->assertForbidden();
            }
        }
    }

    public function test_admin_upt_ditolak_memasang_aset_massal_dan_mutasi_ruangan(): void
    {
        $userUpt = $this->userByRole('Admin UPT');
        $lokasiUpt = Lokasi::untukPegawai($userUpt?->pegawai);

        if (! $userUpt || ! $lokasiUpt) {
            $this->markTestSkipped('User Admin UPT tidak ditemukan.');
        }

        $ruangan = Ruangan::where('id_lokasi', $lokasiUpt->id_lokasi)->first();
        if (! $ruangan) {
            $this->markTestSkipped('Ruangan UPT tidak ditemukan.');
        }

        // Pasang massal -> 403 Forbidden
        $this->actingAs($userUpt)->post(route('aset-ruangan.aset.pasang-massal', $ruangan->id_ruangan), [
            'id_barang' => 1,
            'jumlah' => 1,
        ])->assertForbidden();

        // Mutasi ruangan -> 403 Forbidden
        $aset = Aset::where('is_kendaraan', false)->first();
        if ($aset) {
            $this->actingAs($userUpt)->post(route('aset-ruangan.aset.mutasi-ruangan', [
                'ruangan' => $ruangan->id_ruangan,
                'aset' => $aset->id_aset,
            ]), [
                'id_ruangan_tujuan' => $ruangan->id_ruangan,
            ])->assertForbidden();
        }
    }

    // =========================================================================
    // 4. PEGAWAI (READ-ONLY & SEARCH/FILTER)
    // =========================================================================

    public function test_pegawai_bisa_search_dan_filter_tetapi_read_only(): void
    {
        $userPegawai = $this->userByRole('Pegawai');
        if (! $userPegawai) {
            $this->markTestSkipped('User Pegawai tidak ditemukan.');
        }

        $resIndex = $this->actingAs($userPegawai)->get(route('aset-ruangan.index', ['search' => 'Rapat']));
        $resIndex->assertOk();
        $resIndex->assertDontSee('Tambah Ruangan');
        $resIndex->assertSee('Cari nama ruangan...');

        $ruangan = Ruangan::where('status', 'Aktif')->first();

        // Seluruh endpoint modifikasi ditolak (403)
        $this->actingAs($userPegawai)->post(route('aset-ruangan.store'), ['nama_ruangan' => 'Ruang Baru'])->assertForbidden();
        $this->actingAs($userPegawai)->post(route('aset-ruangan.update', $ruangan->id_ruangan), ['nama_ruangan' => 'Ruang Ubah'])->assertForbidden();
        $this->actingAs($userPegawai)->post(route('aset-ruangan.toggle-status', $ruangan->id_ruangan))->assertForbidden();
        $this->actingAs($userPegawai)->get(route('aset-ruangan.label.download', $ruangan->id_ruangan))->assertForbidden();
        $this->actingAs($userPegawai)->get(route('aset-ruangan.kir.download', $ruangan->id_ruangan))->assertForbidden();
    }

    // =========================================================================
    // 5. KEAMANAN DETACH ASET (IDOR & OBJECT MISMATCH PREVENTION)
    // =========================================================================

    public function test_detach_aset_mencegah_idor_bila_aset_tidak_berada_di_ruangan_tersebut(): void
    {
        $adminAset = $this->userByRole('Admin Aset');
        if (! $adminAset) {
            $this->markTestSkipped('User Admin Aset tidak ditemukan.');
        }

        // Ambil dua ruangan berbeda
        $ruanganA = Ruangan::where('status', 'Aktif')->orderBy('id_ruangan')->first();
        $ruanganB = Ruangan::where('status', 'Aktif')->where('id_ruangan', '!=', $ruanganA->id_ruangan)->first();

        $aset = Aset::where('is_kendaraan', false)
            ->where('status_aset', 'aktif')
            ->whereDoesntHave('pemegangSaatIni')
            ->first();

        if (! $aset || ! $ruanganA || ! $ruanganB) {
            $this->markTestSkipped('Data aset atau ruangan tidak mencukupi.');
        }

        // Tempatkan aset di Ruangan B
        PenempatanAset::where('id_aset', $aset->id_aset)->update(['status' => 'tidak aktif']);
        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruanganB->id_ruangan,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        // Coba kirim request detach dari Ruangan A untuk aset yang ada di Ruangan B
        $res = $this->actingAs($adminAset)->delete(route('aset-ruangan.aset.detach', [
            'ruangan' => $ruanganA->id_ruangan,
            'aset' => $aset->id_aset,
        ]));

        // Harus ditolak dengan status 422 (Unprocessable Entity) karena IDOR / salah ruangan
        $res->assertStatus(422);

        // Pastikan penempatan aset di Ruangan B tetap aktif dan tidak tertutup
        $penempatan = PenempatanAset::where('id_aset', $aset->id_aset)
            ->where('status', 'aktif')
            ->first();
        $this->assertNotNull($penempatan);
        $this->assertSame((int) $ruanganB->id_ruangan, (int) $penempatan->id_ruangan);
    }

    // =========================================================================
    // 6. VALIDASI PENONAKTIFAN RUANGAN (TOGGLE STATUS)
    // =========================================================================

    public function test_penonaktifan_ruangan_ditolak_jika_memiliki_aset_aktif(): void
    {
        $adminAset = $this->userByRole('Admin Aset');
        if (! $adminAset) {
            $this->markTestSkipped('User Admin Aset tidak ditemukan.');
        }

        // Buat ruangan aktif baru
        $ruangan = Ruangan::create([
            'nama_ruangan' => 'Ruang Uji Nonaktif Ada Aset',
            'status' => 'Aktif',
        ]);

        $aset = Aset::where('is_kendaraan', false)->where('status_aset', 'aktif')->first();
        if (! $aset) {
            $this->markTestSkipped('Aset tidak ditemukan.');
        }

        // Pasang aset aktif ke ruangan
        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangan->id_ruangan,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        // Request toggle status dengan referer index
        $res = $this->actingAs($adminAset)
            ->from(route('aset-ruangan.index'))
            ->post(route('aset-ruangan.toggle-status', $ruangan->id_ruangan));

        $res->assertRedirect(route('aset-ruangan.index'));
        $res->assertSessionHas('error');
        $this->assertSame('Aktif', $ruangan->fresh()->status);
    }

    public function test_penonaktifan_ruangan_ditolak_jika_memiliki_inventaris_lokal_aktif(): void
    {
        $adminAset = $this->userByRole('Admin Aset');
        if (! $adminAset) {
            $this->markTestSkipped('User Admin Aset tidak ditemukan.');
        }

        $ruangan = Ruangan::create([
            'nama_ruangan' => 'Ruang Uji Nonaktif Ada Inventaris',
            'status' => 'Aktif',
        ]);

        // Tambah inventaris lokal
        InventarisRuangan::create([
            'id_ruangan' => $ruangan->id_ruangan,
            'kode_inventaris' => 'TEST-INV-001',
            'nama_barang' => 'Kipas Angin Dinding',
            'kondisi' => 'Baik',
        ]);

        $res = $this->actingAs($adminAset)
            ->from(route('aset-ruangan.index'))
            ->post(route('aset-ruangan.toggle-status', $ruangan->id_ruangan));

        $res->assertRedirect(route('aset-ruangan.index'));
        $res->assertSessionHas('error');
        $this->assertSame('Aktif', $ruangan->fresh()->status);
    }

    public function test_penonaktifan_ruangan_ditolak_jika_memiliki_pegawai_yang_ditempatkan(): void
    {
        $adminAset = $this->userByRole('Admin Aset');
        if (! $adminAset) {
            $this->markTestSkipped('User Admin Aset tidak ditemukan.');
        }

        $ruangan = Ruangan::create([
            'nama_ruangan' => 'Ruang Uji Nonaktif Ada Pegawai',
            'status' => 'Aktif',
        ]);

        $pegawai = Pegawai::first();
        if (! $pegawai) {
            $this->markTestSkipped('Pegawai tidak ditemukan.');
        }

        // Tautkan ruangan ke pegawai
        $pegawai->update(['id_ruangan' => $ruangan->id_ruangan]);

        $res = $this->actingAs($adminAset)
            ->from(route('aset-ruangan.index'))
            ->post(route('aset-ruangan.toggle-status', $ruangan->id_ruangan));

        $res->assertRedirect(route('aset-ruangan.index'));
        $res->assertSessionHas('error');
        $this->assertSame('Aktif', $ruangan->fresh()->status);
    }

    public function test_penonaktifan_ruangan_berhasil_jika_ruangan_benar_benar_kosong(): void
    {
        $adminAset = $this->userByRole('Admin Aset');
        if (! $adminAset) {
            $this->markTestSkipped('User Admin Aset tidak ditemukan.');
        }

        // Ruangan kosong tanpa aset, tanpa inventaris, tanpa pegawai
        $ruangan = Ruangan::create([
            'nama_ruangan' => 'Ruang Kosong Benar',
            'status' => 'Aktif',
        ]);

        $res = $this->actingAs($adminAset)
            ->from(route('aset-ruangan.index'))
            ->post(route('aset-ruangan.toggle-status', $ruangan->id_ruangan));

        $res->assertRedirect(route('aset-ruangan.index'));
        $res->assertSessionHas('success');
        $this->assertSame('Nonaktif', $ruangan->fresh()->status);
    }

    // =========================================================================
    // 7. FILTER STATUS USULAN PENGHAPUSAN (KONDISI RUSAK BERAT / DIUSULKAN HAPUS)
    // =========================================================================

    public function test_aset_rusak_berat_dan_diusulkan_hapus_dikecualikan_dari_tabel_dan_cetak(): void
    {
        $adminAset = $this->userByRole('Admin Aset');
        if (! $adminAset) {
            $this->markTestSkipped('User Admin Aset tidak ditemukan.');
        }

        $ruangan = Ruangan::where('status', 'Aktif')->first();
        if (! $ruangan) {
            $this->markTestSkipped('Ruangan tidak ditemukan.');
        }

        // Buat aset tiruan dengan kondisi 'Rusak Berat' dan status 'diusulkan_hapus'
        $asetRusak = Aset::where('is_kendaraan', false)->first();
        if (! $asetRusak) {
            $this->markTestSkipped('Aset tidak ditemukan.');
        }

        $asetRusak->update([
            'kondisi' => 'Rusak Berat',
            'status_aset' => 'diusulkan_hapus',
            'nomor_kartu_barang' => 'TEST-KIB-RUSAK-BERAT-999',
        ]);

        // Tempatkan di ruangan
        PenempatanAset::create([
            'id_aset' => $asetRusak->id_aset,
            'id_ruangan' => $ruangan->id_ruangan,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        // Buka halaman show
        $resShow = $this->actingAs($adminAset)->get(route('aset-ruangan.show', $ruangan->id_ruangan));
        $resShow->assertOk();
        // Nomor KIB aset rusak berat tidak boleh muncul di tabel
        $resShow->assertDontSee('TEST-KIB-RUSAK-BERAT-999');
    }
}
