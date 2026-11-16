<?php

namespace App\Http\Controllers;

use App\Models\TemplateDokumen;
use App\Services\AsetScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TemplateDokumenController extends Controller
{
    public function __construct(
        private readonly AsetScope $scope
    ) {
    }

    /**
     * Pastikan hanya Admin Aset yang berwenang mengakses Modul Template Dokumen.
     */
    private function authorizeAdminAset(): void
    {
        abort_unless($this->scope->isAdminAset(), 403, 'Hanya Admin Aset yang berwenang mengelola Template Dokumen.');
    }

    /**
     * Tampilkan galeri seluruh template dokumen aktif.
     */
    public function index()
    {
        $this->authorizeAdminAset();

        Carbon::setLocale('id');
        $templates = TemplateDokumen::orderBy('id_template')->get();

        return view('template-dokumen.index', compact('templates'));
    }

    /**
     * Perbarui berkas fisik template dengan validasi ketat sesuai tipe berkas.
     */
    public function update(Request $request, TemplateDokumen $template)
    {
        $this->authorizeAdminAset();

        // 1. Validasi dinamis sesuai tipe_berkas (Word -> docx, Excel -> xlsx)
        $isWord = $template->tipe_berkas === 'word';
        $rules = $isWord
            ? ['required', 'file', 'mimes:docx', 'max:10240']
            : ['required', 'file', 'mimes:xlsx', 'max:10240'];

        $messages = [
            'file_template.required' => 'Berkas template wajib diunggah.',
            'file_template.file' => 'Berkas yang diunggah tidak valid.',
            'file_template.mimes' => $isWord
                ? 'Format berkas harus berupa dokumen Word (.docx).'
                : 'Format berkas harus berupa dokumen Excel (.xlsx).',
            'file_template.max' => 'Ukuran berkas template tidak boleh melebihi 10 MB.',
        ];

        $request->validate([
            'file_template' => $rules,
        ], $messages);

        $file = $request->file('file_template');
        $namaAsli = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());
        $expectedExt = $isWord ? 'docx' : 'xlsx';

        if ($ext !== $expectedExt) {
            return back()->with('error', "Ekstensi berkas tidak sesuai. Template {$template->nama_template} memerlukan berkas .{$expectedExt}.");
        }

        $oldFilePath = $template->file_path;
        $newFilename = $template->kode_template . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        try {
            // 2. Simpan berkas baru terlebih dahulu ke lokasi privat
            $newPath = $file->storeAs('templates', $newFilename, TemplateDokumen::DISK);
            if (! $newPath || ! Storage::disk(TemplateDokumen::DISK)->exists($newPath)) {
                throw new \RuntimeException('Gagal menyimpan berkas baru ke media penyimpanan server.');
            }

            // 3. Pastikan berkas baru benar-benar tersedia dan dapat dibaca
            $realPath = Storage::disk(TemplateDokumen::DISK)->path($newPath);
            if (! file_exists($realPath) || filesize($realPath) === 0) {
                Storage::disk(TemplateDokumen::DISK)->delete($newPath);
                throw new \RuntimeException('Berkas baru yang tersimpan kosong atau tidak terbaca.');
            }

            // 4. Perbarui record database dalam transaksi
            DB::transaction(function () use ($template, $namaAsli, $newPath) {
                $template->update([
                    'nama_file_asli' => $namaAsli,
                    'file_path' => $newPath,
                    'status' => 'Siap Digunakan',
                ]);
            });

            // 5. Setelah database berhasil diperbarui, hapus berkas lama
            if ($oldFilePath && $oldFilePath !== $newPath) {
                if (Storage::disk(TemplateDokumen::DISK)->exists($oldFilePath)) {
                    Storage::disk(TemplateDokumen::DISK)->delete($oldFilePath);
                }
                if (Storage::disk('public')->exists($oldFilePath)) {
                    Storage::disk('public')->delete($oldFilePath);
                }
            }

            return back()->with('success', "Berkas {$template->nama_template} berhasil diperbarui.");
        } catch (\Throwable $e) {
            // 6. Jika proses penyimpanan atau update database gagal, bersihkan berkas baru
            if (isset($newPath) && Storage::disk(TemplateDokumen::DISK)->exists($newPath)) {
                Storage::disk(TemplateDokumen::DISK)->delete($newPath);
            }

            return back()->with('error', 'Terjadi kesalahan saat memperbarui template: ' . $e->getMessage());
        }
    }

    /**
     * Unduh berkas template fisik resmi dari penyimpanan privat.
     */
    public function download(TemplateDokumen $template): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        $this->authorizeAdminAset();

        if (! $template->fileExists()) {
            return back()->with('error', 'Berkas fisik template belum diunggah.');
        }

        $realPath = $template->realPath();
        $mime = $template->tipe_berkas === 'excel'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        return response()->download($realPath, $template->nama_file_asli, [
            'Content-Type' => $mime,
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * Hapus berkas template fisik dari storage privat dan set status menjadi Belum Diunggah.
     */
    public function destroy(TemplateDokumen $template)
    {
        $this->authorizeAdminAset();

        $oldFilePath = $template->file_path;

        if ($oldFilePath) {
            if (Storage::disk(TemplateDokumen::DISK)->exists($oldFilePath)) {
                Storage::disk(TemplateDokumen::DISK)->delete($oldFilePath);
            }
            if (Storage::disk('public')->exists($oldFilePath)) {
                Storage::disk('public')->delete($oldFilePath);
            }
        }

        $template->update([
            'nama_file_asli' => null,
            'file_path' => null,
            'status' => 'Belum Diunggah',
        ]);

        return back()->with('success', "Berkas {$template->nama_template} berhasil dihapus.");
    }
}
