<?php

namespace App\Http\Controllers;

use App\Models\TemplateDokumen;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TemplateDokumenController extends Controller
{
    public function index()
    {
        Carbon::setLocale('id');
        $templates = TemplateDokumen::orderBy('id_template')->get();
        return view('template-dokumen.index', compact('templates'));
    }

    public function update(Request $request, TemplateDokumen $template)
    {
        $request->validate([
            'file_template' => ['required', 'file', 'mimes:docx,xlsx', 'max:10240'],
        ]);

        // Hapus file lama dari storage jika ada
        if ($template->file_path && Storage::disk('public')->exists($template->file_path)) {
            Storage::disk('public')->delete($template->file_path);
        }

        $file = $request->file('file_template');
        $namaAsli = $file->getClientOriginalName();
        $path = $file->storeAs('templates', $template->kode_template . '_' . time() . '.' . $file->getClientOriginalExtension(), 'public');

        $template->update([
            'nama_file_asli' => $namaAsli,
            'file_path' => $path,
            'status' => 'Siap Digunakan',
        ]);

        return back()->with('success', "Berkas {$template->nama_template} berhasil diperbarui.");
    }

    public function download(TemplateDokumen $template)
    {
        if (!$template->file_path || !Storage::disk('public')->exists($template->file_path)) {
            return back()->with('error', 'Berkas fisik template belum diunggah.');
        }

        return Storage::disk('public')->download($template->file_path, $template->nama_file_asli);
    }

    public function destroy(TemplateDokumen $template)
    {
        if ($template->file_path && Storage::disk('public')->exists($template->file_path)) {
            Storage::disk('public')->delete($template->file_path);
        }

        $template->update([
            'nama_file_asli' => null,
            'file_path' => null,
            'status' => 'Belum Diunggah',
        ]);

        return back()->with('success', "Berkas {$template->nama_template} berhasil dihapus.");
    }
}