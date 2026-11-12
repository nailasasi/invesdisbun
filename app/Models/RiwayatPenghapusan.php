<?php

namespace App\Models;

use App\Services\BuktiOtorisasi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RiwayatPenghapusan extends Model
{
    use HasFactory;

    protected $table = 'riwayat_penghapusan';

    protected $primaryKey = 'id_riwayat';

    protected $fillable = [
        'id_usulan_hapus',
        'id_aset',
        'id_inventaris',
        'id_user',
        'no_sk',
        'tanggal_sk',
        'nama_pelaksana',
        'waktu_penghapusan',
        'alasan_penghapusan',
        'keterangan',
        'foto_bukti',
        'snapshot_aset',
    ];

    protected $casts = [
        'tanggal_sk' => 'date',
        'waktu_penghapusan' => 'datetime',
        'snapshot_aset' => 'array',
    ];

    /**
     * Saat baris riwayat dihapus, ikut hapus file bukti fisik pada disk privat.
     */
    protected static function booted(): void
    {
        static::deleting(function (RiwayatPenghapusan $riwayat) {
            if ($riwayat->foto_bukti) {
                Storage::disk(BuktiOtorisasi::DISK)->delete($riwayat->foto_bukti);
            }
        });
    }

    public function aset()
    {
        return $this->belongsTo(Aset::class, 'id_aset');
    }

    public function inventaris()
    {
        return $this->belongsTo(InventarisRuangan::class, 'id_inventaris');
    }

    public function usulan()
    {
        return $this->belongsTo(UsulanPenghapusan::class, 'id_usulan_hapus');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * Nama aset dari snapshot atau relasi aset / inventaris.
     */
    public function getNamaAsetTampilAttribute(): string
    {
        return $this->snapshot_aset['nama_aset']
            ?? $this->aset?->kendaraan?->namaTampil()
            ?? $this->aset?->barang?->nama_barang
            ?? $this->inventaris?->nama_barang
            ?? 'Aset #'.($this->id_aset ?? $this->id_inventaris ?? '-');
    }

    /**
     * Nomor kartu barang dari snapshot atau relasi aset / kode inventaris.
     */
    public function getNomorKartuBarangTampilAttribute(): string
    {
        return $this->snapshot_aset['nomor_kartu_barang']
            ?? $this->aset?->nomor_kartu_barang
            ?? $this->inventaris?->kode_inventaris
            ?? '-';
    }

    /**
     * Jenis aset (Barang, Kendaraan, atau Inventaris Lokal).
     */
    public function getJenisAsetTampilAttribute(): string
    {
        $isKendaraan = $this->snapshot_aset['is_kendaraan'] ?? $this->aset?->is_kendaraan ?? false;
        if ($isKendaraan) {
            return 'Kendaraan';
        }

        if ($this->id_inventaris !== null || ($this->snapshot_aset['is_inventaris'] ?? false)) {
            return 'Inventaris Lokal';
        }

        return 'Barang';
    }

    /**
     * Nilai perolehan terformat rupiah.
     */
    public function getNilaiPerolehanTampilAttribute(): string
    {
        $nilai = (float) ($this->snapshot_aset['nilai_perolehan'] ?? $this->aset?->nilai_perolehan ?? 0);

        return 'Rp '.number_format($nilai, 0, ',', '.');
    }

    /**
     * Nomor SK penghapusan resmi.
     */
    public function getNoSkTampilAttribute(): string
    {
        return $this->no_sk
            ?? $this->snapshot_aset['no_sk']
            ?? $this->usulan?->no_sk
            ?? '-';
    }

    /**
     * Tanggal SK penghapusan resmi terformat.
     */
    public function getTanggalSkTampilAttribute(): string
    {
        $tgl = $this->tanggal_sk ?? $this->usulan?->tanggal_sk;
        if ($tgl instanceof \DateTimeInterface) {
            return $tgl->format('d M Y');
        }

        return $tgl ? date('d M Y', strtotime($tgl)) : '-';
    }
}
