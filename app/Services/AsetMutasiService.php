<?php

namespace App\Services;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service terpusat untuk transaksi mutasi aset:
 * - Mutasi Ganti Pemegang (Aset Berpemegang)
 * - Mutasi Pindah Ruangan (Barang Melekat)
 *
 * Menjamin integritas transaksi atomik, penguncian baris (row locking),
 * penegakan aturan finalisasi penempatan, sinkronisasi SKPD & lokasi,
 * serta pencegahan IDOR ruangan asal dan validasi ruangan tujuan aktif.
 */
class AsetMutasiService
{
    protected AsetScope $scope;

    public function __construct(AsetScope $scope)
    {
        $this->scope = $scope;
    }

    /**
     * Mutasi Ganti Pemegang aset:
     * - Menutup pemegang lama
     * - Membuka pemegang baru
     * - Ruangan mengikuti ruang kerja pegawai baru (atau ditutup jika belum punya ruang)
     * - Penempatan baru langsung difinalisasi (is_finalized = 1)
     * - Sinkronisasi SKPD & lokasi
     * - Pencatatan ke mutasi_aset dan detail_mutasi_aset
     *
     * @param Aset        $aset
     * @param int         $pegawaiBaruId
     * @param string|null $keterangan
     * @param int         $userId
     * @return MutasiAset
     * @throws ValidationException
     */
    public function mutasiGantiPemegang(Aset $aset, int $pegawaiBaruId, ?string $keterangan, int $userId): MutasiAset
    {
        // Validasi hak akses awal pengguna terhadap aset
        abort_unless(
            $this->scope->bolehAksesAset($aset),
            403,
            $this->scope->pesanAkses('memutasi aset')
        );

        $now = now()->toDateString();
        $pemegangLamaId = $aset->pemegangSaatIni?->pegawai?->id_pegawai;

        if ($pemegangLamaId && (int) $pemegangLamaId === (int) $pegawaiBaruId) {
            throw ValidationException::withMessages([
                'id_pegawai' => 'Aset sudah dipegang oleh pegawai yang dipilih.',
            ]);
        }

        $pegawaiBaru = Pegawai::findOrFail($pegawaiBaruId);

        // Validasi cakupan spatial Admin UPT terhadap pegawai baru
        if ($this->scope->isAdminUpt()) {
            $idLokasiCakupan = $this->scope->idLokasi() ?: [];
            $pegawaiLokasi = $pegawaiBaru->ruangan?->id_lokasi;
            $pegawaiSkpd = $pegawaiBaru->id_skpd;
            $dalamCakupan = ($pegawaiLokasi && in_array($pegawaiLokasi, $idLokasiCakupan, true))
                || ($pegawaiSkpd && (int) $pegawaiSkpd === (int) $this->scope->skpdId());
            abort_unless($dalamCakupan, 403, 'Pegawai baru berada di luar cakupan UPT Anda.');
        }

        // Ruangan kerja pemegang baru (null bila belum punya ruangan)
        $ruanganBaru = $pegawaiBaru->id_ruangan;

        // Otorisasi ruangan baru dijalankan sebelum penulisan database
        if ($ruanganBaru) {
            abort_unless(
                $this->scope->bolehAksesRuangan((int) $ruanganBaru),
                403,
                $this->scope->pesanAkses('memindahkan aset ke pemegang baru')
            );
        }

        return DB::transaction(function () use ($aset, $pemegangLamaId, $pegawaiBaruId, $keterangan, $userId, $now, $ruanganBaru) {
            $asetLock = Aset::where('id_aset', $aset->id_aset)->lockForUpdate()->first();
            if (! $asetLock || $asetLock->status_aset !== 'aktif') {
                throw ValidationException::withMessages([
                    'tipe' => 'Aset sedang diubah oleh proses lain atau statusnya telah berubah.',
                ]);
            }

            // Kunci baris pemegang aktif & penempatan aktif
            $pemegangAktifLock = PemegangAset::where('id_aset', $asetLock->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            $penempatanAktifLock = PenempatanAset::where('id_aset', $asetLock->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            // Re-validasi prasyarat mutasi setelah row lock
            if (! AsetPenempatan::bolehMutasi($pemegangAktifLock !== null, $penempatanAktifLock !== null)) {
                throw ValidationException::withMessages([
                    'tipe' => 'Tetapkan pemegang atau lokasi terlebih dahulu sebelum melakukan mutasi.',
                ]);
            }

            if (! AsetPenempatan::bolehMutasiFinalisasi($penempatanAktifLock)) {
                throw ValidationException::withMessages([
                    'tipe' => AsetPenempatan::pesanMutasiBelumDifinalisasi(),
                ]);
            }

            // Validasi pemegang lama belum berubah secara konkuren
            $currentPemegangId = $pemegangAktifLock?->id_pegawai;
            if ($pemegangLamaId && (int) $currentPemegangId !== (int) $pemegangLamaId) {
                throw ValidationException::withMessages([
                    'tipe' => 'Pemegang aktif telah berubah oleh proses lain. Silakan muat ulang halaman.',
                ]);
            }

            if ($currentPemegangId && (int) $currentPemegangId === (int) $pegawaiBaruId) {
                throw ValidationException::withMessages([
                    'id_pegawai' => 'Aset sudah dipegang oleh pegawai yang dipilih.',
                ]);
            }

            $ruanganLama = $penempatanAktifLock?->id_ruangan;

            // Tutup pemegang lama
            if ($pemegangAktifLock) {
                $pemegangAktifLock->update(['status' => 'tidak aktif', 'tanggal_selesai' => $now]);
            } else {
                PemegangAset::where('id_aset', $asetLock->id_aset)
                    ->where('status', 'aktif')
                    ->update(['status' => 'tidak aktif', 'tanggal_selesai' => $now]);
            }

            // Buka pemegang baru
            PemegangAset::create([
                'id_aset' => $asetLock->id_aset,
                'id_pegawai' => $pegawaiBaruId,
                'tanggal_mulai' => $now,
                'status' => 'aktif',
            ]);

            // Ruangan aset mengikuti ruang kerja pemegang baru
            if ($ruanganBaru) {
                // Mutasi Ganti Pemegang: penempatan baru langsung difinalisasi (is_finalized = 1)
                $asetLock->placeAtRoom((int) $ruanganBaru, true);
            } else {
                // Pemegang baru belum punya ruang: tutup penempatan aktif lama
                PenempatanAset::where('id_aset', $asetLock->id_aset)
                    ->where('status', 'aktif')
                    ->update(['status' => 'tidak aktif', 'tanggal_selesai' => $now]);
                $asetLock->unsetRelation('penempatanAktif');
            }

            // Sinkronisasi SKPD & Lokasi mengikuti data pegawai dan ruang kerjanya
            AsetPenempatan::sinkronAset($asetLock, $pegawaiBaruId, $ruanganBaru ? (int) $ruanganBaru : null);

            // Catat mutasi sebagai riwayat
            $mutasi = MutasiAset::create([
                'tanggal_mutasi' => $now,
                'jenis_mutasi' => 'Ganti Pemegang',
                'keterangan' => $keterangan,
                'id_user_penginput' => $userId,
                'status_mutasi' => 'selesai',
            ]);

            DetailMutasiAset::create([
                'id_mutasi' => $mutasi->id_mutasi,
                'id_aset' => $asetLock->id_aset,
                'pegawai_lama' => $currentPemegangId,
                'pegawai_baru' => $pegawaiBaruId,
                'ruangan_lama' => $ruanganLama,
                'ruangan_baru' => $ruanganBaru ? (int) $ruanganBaru : null,
            ]);

            return $mutasi;
        });
    }

    /**
     * Mutasi Pindah Ruangan barang melekat:
     * - Memastikan aset aktif dan tidak memiliki pemegang
     * - Memastikan penempatan aktif sudah difinalisasi
     * - Memastikan ruangan tujuan tersedia dan berstatus Aktif
     * - Memvalidasi ruangan asal (bila disediakan dari URL ruangan untuk anti-IDOR)
     * - Menutup penempatan lama
     * - Membuka penempatan baru aktif dan langsung terfinalisasi (is_finalized = 1)
     * - Sinkronisasi SKPD & lokasi
     * - Mencatat ke mutasi_aset dan detail_mutasi_aset
     *
     * @param Aset        $aset
     * @param int         $ruanganTujuanId
     * @param string|null $keterangan
     * @param int         $userId
     * @param int|null    $ruanganAsalId
     * @return array{mutasi: ?MutasiAset, sama: bool, ruangan_tujuan: Ruangan, lokasi_lama: ?int, lokasi_baru: ?int}
     * @throws ValidationException
     */
    public function mutasiPindahRuangan(Aset $aset, int $ruanganTujuanId, ?string $keterangan, int $userId, ?int $ruanganAsalId = null): array
    {
        // Validasi hak akses awal pengguna terhadap aset
        abort_unless(
            $this->scope->bolehAksesAset($aset),
            403,
            $this->scope->pesanAkses('memindahkan aset')
        );

        $target = Ruangan::findOrFail($ruanganTujuanId);

        // Ruangan tujuan wajib berstatus Aktif (berlaku untuk kedua controller)
        abort_if($target->status !== 'Aktif', 422, 'Ruangan tujuan tidak aktif.');

        // Otorisasi cakupan ruangan tujuan
        abort_unless(
            $this->scope->bolehAksesRuangan($target->id_ruangan),
            403,
            $this->scope->pesanAkses('memindahkan aset ke ruangan tujuan')
        );

        // Jika ruangan asal diberikan (dari AsetRuanganController), validasi otorisasi ruangan asal
        if ($ruanganAsalId) {
            abort_unless(
                $this->scope->bolehAksesRuangan($ruanganAsalId),
                403,
                $this->scope->pesanAkses('memindahkan aset dari ruangan')
            );
        }

        // Cek pemegang aktif pada model awal sebelum transaksi
        $pemegangAktif = $aset->pemegangSaatIni?->pegawai?->nama_pegawai;
        if ($pemegangAktif) {
            throw ValidationException::withMessages([
                'id_aset' => 'Aset sedang dipegang pegawai; ruangan mengikuti pemegang. Gunakan Mutasi Ganti Pemegang.',
                'id_ruangan' => 'Aset memiliki pemegang; ruangan mengikuti pemegang. Gunakan Mutasi → Ganti Pemegang.',
            ]);
        }

        return DB::transaction(function () use ($aset, $target, $keterangan, $userId, $ruanganAsalId) {
            $asetLock = Aset::where('id_aset', $aset->id_aset)->lockForUpdate()->first();
            if (! $asetLock || $asetLock->status_aset !== 'aktif') {
                throw ValidationException::withMessages([
                    'tipe' => 'Aset sedang diubah oleh proses lain atau statusnya telah berubah.',
                ]);
            }

            abort_unless(
                $this->scope->bolehAksesAset($asetLock),
                403,
                $this->scope->pesanAkses('memindahkan aset')
            );

            // Pastikan tidak ada pemegang aktif yang dibuat secara konkuren
            $pemegangAktifLock = PemegangAset::where('id_aset', $asetLock->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            if ($pemegangAktifLock) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Aset sedang dipegang pegawai; ruangan mengikuti pemegang. Gunakan Mutasi Ganti Pemegang.',
                    'id_ruangan' => 'Aset memiliki pemegang; ruangan mengikuti pemegang. Gunakan Mutasi → Ganti Pemegang.',
                ]);
            }

            $penempatanLamaLock = PenempatanAset::where('id_aset', $asetLock->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            if (! $penempatanLamaLock) {
                throw ValidationException::withMessages([
                    'tipe' => 'Aset tidak memiliki penempatan aktif.',
                    'id_aset' => 'Aset tidak memiliki penempatan aktif.',
                ]);
            }

            // Mutasi hanya sah bila penempatan aktif SUDAH difinalisasi (is_finalized = 1)
            if (! AsetPenempatan::bolehMutasiFinalisasi($penempatanLamaLock)) {
                throw ValidationException::withMessages([
                    'tipe' => AsetPenempatan::pesanMutasiBelumDifinalisasi(),
                    'id_aset' => AsetPenempatan::pesanMutasiBelumDifinalisasi(),
                ]);
            }

            // Validasi IDOR ruangan asal: bila ruanganAsalId disediakan, pastikan aset memang di ruangan tersebut
            if ($ruanganAsalId !== null) {
                abort_unless(
                    (int) $penempatanLamaLock->id_ruangan === (int) $ruanganAsalId,
                    422,
                    'Aset tidak berada di ruangan asal ini.'
                );
            }

            $ruanganLamaId = $penempatanLamaLock->id_ruangan;
            $ruanganBaruId = $target->id_ruangan;
            $lokasiLama = $asetLock->id_lokasi;

            // Sama ruangan = no-op (tidak ada mutasi baru)
            if ($ruanganLamaId && (int) $ruanganLamaId === (int) $ruanganBaruId) {
                return [
                    'mutasi' => null,
                    'sama' => true,
                    'ruangan_tujuan' => $target,
                    'lokasi_lama' => $lokasiLama,
                    'lokasi_baru' => $lokasiLama,
                ];
            }

            // Tutup penempatan lama
            $penempatanLamaLock->update([
                'status' => 'tidak aktif',
                'tanggal_selesai' => now()->toDateString(),
            ]);

            // Buka penempatan baru yang aktif dan LANGSUNG terfinalisasi (is_finalized = 1)
            $dataPenempatan = [
                'id_aset' => $asetLock->id_aset,
                'id_ruangan' => $ruanganBaruId,
                'tanggal_mulai' => now()->toDateString(),
                'status' => 'aktif',
            ];

            if (AsetPenempatan::kolomFinalisasiTersedia()) {
                $dataPenempatan = array_merge(
                    $dataPenempatan,
                    AsetPenempatan::finalisasiLangsung(AsetPenempatan::kolomJejakFinalisasiTersedia())
                );
            }

            PenempatanAset::create($dataPenempatan);

            // SKPD & Lokasi mengikuti ruangan tujuan
            AsetPenempatan::sinkronAset($asetLock, null, (int) $ruanganBaruId);

            $now = now()->toDateString();
            $mutasi = MutasiAset::create([
                'tanggal_mutasi' => $now,
                'jenis_mutasi' => 'Pindah Ruangan',
                'keterangan' => $keterangan,
                'id_user_penginput' => $userId,
                'status_mutasi' => 'selesai',
            ]);

            DetailMutasiAset::create([
                'id_mutasi' => $mutasi->id_mutasi,
                'id_aset' => $asetLock->id_aset,
                'pegawai_lama' => null,
                'pegawai_baru' => null,
                'ruangan_lama' => $ruanganLamaId,
                'ruangan_baru' => (int) $ruanganBaruId,
            ]);

            return [
                'mutasi' => $mutasi,
                'sama' => false,
                'ruangan_tujuan' => $target,
                'lokasi_lama' => $lokasiLama,
                'lokasi_baru' => $asetLock->fresh()->id_lokasi,
            ];
        });
    }
}
