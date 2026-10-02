<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aset extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'aset';
    use HasFactory;

    protected $primaryKey = 'id_aset';

    public $timestamps = false;

    protected $fillable = [
        'id_barang', 'id_skpd', 'id_lokasi', 'nomor_kartu_barang', 'merk', 'tanggal_pengadaan',
        'tanggal_perolehan', 'tanggal_habis_pakai', 'nilai_perolehan',
        'kondisi', 'status_aset', 'is_kendaraan',
    ];

    protected $casts = [
        'tanggal_pengadaan' => 'date',
        'tanggal_perolehan' => 'date',
        'tanggal_habis_pakai' => 'date',
        'nilai_perolehan' => 'decimal:2',
        'is_kendaraan' => 'boolean',
    ];

    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'id_barang');
    }

    /**
     * Unit organisasi penanggung jawab aset (bukan lokasi fisik).
     */
    public function skpd()
    {
        return $this->belongsTo(Skpd::class, 'id_skpd');
    }

    /**
     * Lokasi fisik aset. Tidak selalu sama dengan SKPD penanggung jawab:
     * aset milik Bidang dapat berada di UPT dan sebaliknya.
     */
    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'id_lokasi');
    }

    public function pemegang()
    {
        return $this->hasMany(PemegangAset::class, 'id_aset');
    }

    public function pemegangSaatIni()
    {
        return $this->hasOne(PemegangAset::class, 'id_aset')->where('status', 'aktif');
    }

    public function penempatan()
    {
        return $this->hasMany(PenempatanAset::class, 'id_aset');
    }

    public function penempatanAktif()
    {
        return $this->hasOne(PenempatanAset::class, 'id_aset')->where('status', 'aktif');
    }

    public function mutasiDetails()
    {
        return $this->hasMany(DetailMutasiAset::class, 'id_aset');
    }

    public function kendaraan()
    {
        return $this->hasOne(Kendaraan::class, 'id_aset');
    }

    public function tanah()
    {
        return $this->hasOne(Tanah::class, 'id_aset');
    }

    public function scopeKendaraan($query)
    {
        return $query->where('is_kendaraan', true);
    }

    public function scopeBarang($query)
    {
        return $query->where('is_kendaraan', false);
    }

    /**
     * Tempatkan aset ke ruangan tertentu (menutup penempatan aktif lama).
     * Mengembalikan id_ruangan yang aktif, atau null bila ruangan kosong/dibatalkan.
     */
    public function placeAtRoom(?int $ruanganId): ?int
    {
        if (!$ruanganId) {
            return null;
        }

        PenempatanAset::where('id_aset', $this->id_aset)
            ->where('status', 'aktif')
            ->update(['status' => 'tidak aktif', 'tanggal_selesai' => now()->toDateString()]);

        PenempatanAset::create([
            'id_aset' => $this->id_aset,
            'id_ruangan' => $ruanganId,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        return $ruanganId;
    }

    /**
     * Pindahkan aset ke ruangan lain sambil mencatat mutasi 'Pindah Ruangan'.
     *
     * @param int|null    $ruanganId  ruangan tujuan (null = tanpa ruangan)
     * @param int|null    $pegawaiId  pemegang saat ini (untuk riwayat; sama untuk ppindah ruangan)
     * @param string|null $keterangan keterangan mutasi
     * @param int|null    $idMutasi   reuse induk MutasiAset untuk perpindahan massal
     */
    public function moveToRoom(?int $ruanganId, ?int $pegawaiId = null, ?string $keterangan = null, ?int $idMutasi = null): void
    {
        $ruanganLama = $this->penempatanAktif?->id_ruangan;

        $this->placeAtRoom($ruanganId);

        // Lokasi fisik aset mengikuti ruang barunya. Unit penanggung jawab
        // TIDAK ikut berubah — pemindahan fisik bukan pemindahan kepemilikan.
        $lokasiBaru = Ruangan::where('id_ruangan', $ruanganId)->value('id_lokasi');

        if ($lokasiBaru) {
            $this->forceFill(['id_lokasi' => $lokasiBaru])->saveQuietly();
        }

        if ($ruanganLama == $ruanganId) {
            return;
        }

        if (!$idMutasi) {
            $mutasi = MutasiAset::create([
                'tanggal_mutasi' => now()->toDateString(),
                'jenis_mutasi' => 'Pindah Ruangan',
                'keterangan' => $keterangan,
                'id_user_penginput' => auth()->id(),
                'status_mutasi' => 'selesai',
            ]);
            $idMutasi = $mutasi->id_mutasi;
        }

        DetailMutasiAset::create([
            'id_mutasi' => $idMutasi,
            'id_aset' => $this->id_aset,
            'pegawai_lama' => $pegawaiId,
            'pegawai_baru' => $pegawaiId,
            'ruangan_lama' => $ruanganLama,
            'ruangan_baru' => $ruanganId,
        ]);
    }
}
