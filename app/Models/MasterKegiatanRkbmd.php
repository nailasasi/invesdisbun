<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKegiatanRkbmd extends Model
{
    use HasFactory;

    /**
     * Nama tabel terkait.
     *
     * @var string
     */
    protected $table = 'master_kegiatan_rkbmd';

    /**
     * Primary key tabel.
     *
     * @var string
     */
    protected $primaryKey = 'id_kegiatan';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bidang_urusan',
        'nama_program',
        'nama_sub_kegiatan',
    ];

    /**
     * Tipe data casting atribut.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'bidang_urusan' => 'string',
        'nama_program' => 'string',
        'nama_sub_kegiatan' => 'string',
    ];

    /**
     * Ambil daftar Program unik untuk dropdown pertama.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function daftarProgram()
    {
        return static::query()
            ->select('nama_program')
            ->distinct()
            ->orderBy('nama_program')
            ->pluck('nama_program');
    }

    /**
     * Ambil daftar Sub Kegiatan berdasarkan Program terpilih untuk dropdown kedua.
     *
     * @param string $program
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function subKegiatanPerProgram(string $program)
    {
        return static::query()
            ->where('nama_program', $program)
            ->orderBy('nama_sub_kegiatan')
            ->get(['id_kegiatan', 'bidang_urusan', 'nama_program', 'nama_sub_kegiatan']);
    }
}

