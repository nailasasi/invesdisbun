<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom no_excel sudah tersedia di tabel tanah.
        // Tidak ada perubahan struktur yang diperlukan.
    }

    public function down(): void
    {
        // Tidak ada perubahan yang perlu dibatalkan.
    }
};