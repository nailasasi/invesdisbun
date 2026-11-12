<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKodeBarangRkbmd extends Model
{
    use HasFactory;

    /**
     * Nama tabel terkait.
     *
     * @var string
     */
    protected $table = 'master_kode_barang_rkbmd';

    /**
     * Primary key tabel.
     *
     * @var string
     */
    protected $primaryKey = 'id_master_barang';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'kode_barang',
        'nama_barang',
    ];

    /**
     * Tipe data casting atribut.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'kode_barang' => 'string',
        'nama_barang' => 'string',
    ];

    /**
     * Scope untuk pencarian AJAX autocomplete (berdasarkan kode atau nama barang).
     */
    public function scopeCari($query, ?string $keyword)
    {
        if (! $keyword) {
            return $query;
        }

        return $query->where(function ($q) use ($keyword) {
            $q->where('kode_barang', 'like', "%{$keyword}%")
              ->orWhere('nama_barang', 'like', "%{$keyword}%");
        });
    }
}

