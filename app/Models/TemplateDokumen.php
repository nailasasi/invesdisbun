<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TemplateDokumen extends Model
{
    use HasFactory;

    public const DISK = 'templates';

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

    /**
     * Periksa apakah berkas fisik template tersedia di penyimpanan server (prioritas disk privat).
     */
    public function fileExists(): bool
    {
        if (empty($this->file_path)) {
            return false;
        }

        if (Storage::disk(self::DISK)->exists($this->file_path)) {
            return true;
        }

        return Storage::disk('public')->exists($this->file_path);
    }

    /**
     * Dapatkan path fisik absolut berkas template.
     */
    public function realPath(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        if (Storage::disk(self::DISK)->exists($this->file_path)) {
            return Storage::disk(self::DISK)->path($this->file_path);
        }

        if (Storage::disk('public')->exists($this->file_path)) {
            return Storage::disk('public')->path($this->file_path);
        }

        return null;
    }
}