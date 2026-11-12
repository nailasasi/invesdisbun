<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Kendaraan extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kendaraan';

    use HasFactory;

    protected $primaryKey = 'id_kendaraan';

    protected $fillable = ['id_aset', 'nama_kendaraan', 'jenis_kendaraan', 'nomor_rangka', 'nomor_mesin', 'merk', 'tipe', 'pemegang', 'foto', 'keterangan', 'status_penggunaan'];

    /**
     * Sumber tunggal nama tampilan kendaraan.
     *
     * Prioritas: nama_kendaraan tersimpan -> gabungan merk/tipe ->
     * jenis_kendaraan -> '-'. Fallback hanya untuk tampilan; tidak pernah
     * menulis/mengubah data nama.
     */
    public function namaTampil(): string
    {
        $nama = trim((string) $this->nama_kendaraan);
        if ($nama !== '') {
            return $nama;
        }

        $merkTipe = trim(trim((string) $this->merk).' '.trim((string) $this->tipe));
        if ($merkTipe !== '') {
            return $merkTipe;
        }

        $jenis = trim((string) $this->jenis_kendaraan);

        return $jenis !== '' ? $jenis : '-';
    }

    public function aset()
    {
        return $this->belongsTo(Aset::class, 'id_aset');
    }

    public function riwayatPlat()
    {
        return $this->hasMany(RiwayatPlat::class, 'id_kendaraan');
    }

    public function pajak()
    {
        return $this->hasMany(PajakKendaraan::class, 'id_kendaraan');
    }

    public function izin()
    {
        return $this->hasMany(IzinKendaraan::class, 'id_kendaraan');
    }

    public function platAktif()
    {
        return $this->hasOne(RiwayatPlat::class, 'id_kendaraan')->where('status', 'Aktif');
    }

    /**
     * Relasi pajak tahunan aktif (PKB 1 Tahunan berstatus Aktif).
     */
    public function pajakTahunanAktif()
    {
        return $this->hasOne(PajakKendaraan::class, 'id_kendaraan')
            ->where('pajak_5_tahunan', false)
            ->where('status', 'Aktif');
    }

    /**
     * Relasi pajak lima tahunan aktif (PKB 5 Tahunan / Ganti Plat berstatus Aktif).
     */
    public function pajakLimaTahunanAktif()
    {
        return $this->hasOne(PajakKendaraan::class, 'id_kendaraan')
            ->where('pajak_5_tahunan', true)
            ->where('status', 'Aktif');
    }

    /**
     * Alias backward-compatibility: merujuk ke pajak tahunan aktif.
     */
    public function pajakAktif()
    {
        return $this->hasOne(PajakKendaraan::class, 'id_kendaraan')
            ->where('pajak_5_tahunan', false)
            ->where('status', 'Aktif');
    }

    /**
     * Memeriksa apakah kendaraan sedang aktif digunakan saat ini (aktual).
     * Yaitu memiliki izin berstatus 'Disetujui' dengan waktu sekarang berada
     * di antara waktu mulai (berangkat) dan waktu selesai (kembali).
     */
    public function sedangDigunakanAktual(?Carbon $now = null): bool
    {
        $now = $now ?? now();
        $nowStr = $now->format('Y-m-d H:i:s');

        $driver = DB::connection()->getDriverName();
        $startExpr = $driver === 'sqlite'
            ? "datetime(tanggal_berangkat || ' ' || coalesce(waktu_berangkat, '00:00:00'))"
            : "TIMESTAMP(tanggal_berangkat, COALESCE(waktu_berangkat, '00:00:00'))";
        $endExpr = $driver === 'sqlite'
            ? "datetime(coalesce(tanggal_kembali, tanggal_berangkat) || ' ' || coalesce(waktu_kembali, '23:59:59'))"
            : "TIMESTAMP(COALESCE(tanggal_kembali, tanggal_berangkat), COALESCE(waktu_kembali, '23:59:59'))";

        return $this->izin()
            ->where('status_approval', 'Disetujui')
            ->whereRaw("{$startExpr} <= ?", [$nowStr])
            ->whereRaw("{$endExpr} >= ?", [$nowStr])
            ->exists();
    }
}
