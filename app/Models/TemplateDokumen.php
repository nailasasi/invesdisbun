<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateDokumen extends Model
{
    use HasFactory;

    protected $table = 'template_dokumen';
    protected $primaryKey = 'id_template';

    protected $fillable = [
        'kode_template',
        'nama_template',
        'nama_file_asli',
        'file_path',
        'tipe_berkas',
        'status',
        'deskripsi',
    ];
}