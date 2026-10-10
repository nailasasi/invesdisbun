<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PajakKendaraan extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pajak_kendaraan';
    use HasFactory;

    protected $primaryKey = 'id_pajak';

    protected $fillable = ['id_kendaraan', 'jenis_pajak', 'tahun', 'tanggal_bayar', 'tanggal_berakhir', 'nominal', 'total_pajak', 'pajak_5_tahunan', 'status', 'bukti_pajak_path', 'bukti_pajak_nama'];

    protected $casts = [
        'tahun' => 'integer',
        'tanggal_bayar' => 'date',
        'tanggal_berakhir' => 'date',
        'nominal' => 'decimal:2',
        'total_pajak' => 'decimal:2',
        'pajak_5_tahunan' => 'boolean',
    ];

    public function kendaraan()
    {
        return $this->belongsTo(Kendaraan::class, 'id_kendaraan');
    }

    /**
     * Ada berkas bukti pembayaran yang tersimpan?
     */
    public function punyaBukti(): bool
    {
        return ! empty($this->bukti_pajak_path);
    }

    /**
     * Hitung tanggal jatuh tempo pajak berikutnya sesuai aturan Samsat.
     *
     * Aturan:
     * - Tanggal dan bulan JATUH TEMPO harus TETAP (diambil dari masa berlaku
     *   pajak sebelumnya). Hanya tahun yang ditambahkan (+1 atau +5).
     * - Tidak menggunakan tanggal bayar atau tanggal input untuk mengubah
     *   tanggal/bulan jatuh tempo. Tanggal bayar dicatat terpisah sebagai
     *   riwayat transaksi.
     * - Bila tidak ada pajak sebelumnya, acuan tanggal/bulan jatuh tempo
     *   dapat menggunakan tanggal bayar (untuk pajak pertama). Namun tetap
     *   hanya tahun yang ditambahkan — dan tanggal/bulan tidak diubah oleh
     *   tanggal hari ini.
     */
    public static function hitungJatuhTempo(?Carbon $sebelumnya, ?Carbon $tanggalBayar, bool $limaTahunan = false): Carbon
    {
        $langkah = $limaTahunan ? 5 : 1;

        if ($sebelumnya) {
            $tahunBaru = $sebelumnya->year + $langkah;
            return Carbon::create($tahunBaru, $sebelumnya->month, $sebelumnya->day);
        }

        $acuan = $tanggalBayar ?: Carbon::today();
        $tahunBaru = $acuan->year + $langkah;
        return Carbon::create($tahunBaru, $acuan->month, $acuan->day);
    }
}
