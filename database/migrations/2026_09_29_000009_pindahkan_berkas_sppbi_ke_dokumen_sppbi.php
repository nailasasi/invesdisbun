<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const LAMA = 'sppbi';

    private const BARU = 'dokumen_sppbi';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $records = DB::table('dokumen_sppbi')->where('file_path', 'like', self::LAMA.'/%')->get();

        foreach ($records as $record) {
            $namaFile = basename($record->file_path);
            $tujuan = self::BARU.'/'.$namaFile;

            if (Storage::disk('public')->exists($record->file_path)) {
                Storage::disk('public')->move($record->file_path, $tujuan);
            }

            DB::table('dokumen_sppbi')->where('id_sppbi', $record->id_sppbi)->update([
                'file_path' => $tujuan,
            ]);
        }

        DB::table('migrations')
            ->where('migration', '2026_09_01_000002_create_assets_table')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $records = DB::table('dokumen_sppbi')->where('file_path', 'like', self::BARU.'/%')->get();

        foreach ($records as $record) {
            $namaFile = basename($record->file_path);
            $tujuan = self::LAMA.'/'.$namaFile;

            if (Storage::disk('public')->exists($record->file_path)) {
                Storage::disk('public')->move($record->file_path, $tujuan);
            }

            DB::table('dokumen_sppbi')->where('id_sppbi', $record->id_sppbi)->update([
                'file_path' => $tujuan,
            ]);
        }
    }
};
