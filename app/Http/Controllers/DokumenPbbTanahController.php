<?php

namespace App\Http\Controllers;

use App\Models\Tanah;
use App\Models\DokumenPbbTanah;
use App\Models\DokumenPbbHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DokumenPbbTanahController extends Controller
{

    public function create(Tanah $tanah)
    {
        return view(
            'dokumen_pbb.create',
            compact('tanah')
        );
    }


    public function store(Request $request, Tanah $tanah)
    {

        $validated = $request->validate([
            'tahun_pbb' => [
                'nullable',
                'integer'
            ],

            'file_pbb' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120'
            ],
        ]);


        $file = $request->file('file_pbb');

        $path = $file->store(
            'dokumen-pbb',
            'public'
        );


        $pbb = DokumenPbbTanah::create([
            'id_tanah' => $tanah->id_tanah,
            'tahun_pbb' => $request->tahun_pbb,
            'file_pbb' => $path,
            'tanggal_upload' => now(),
            'uploaded_by' => auth()->id(),
        ]);


        DokumenPbbHistory::create([
            'id_tanah' => $tanah->id_tanah,
            'id_pbb' => $pbb->id_pbb,
            'id_user' => auth()->id(),
            'aksi' => 'UPLOAD',
            'nama_file' => basename($path),
        ]);


        return redirect()
            ->route('tanah.show',$tanah)
            ->with(
                'success',
                'Dokumen PBB berhasil ditambahkan'
            );
    }



    public function destroy(DokumenPbbTanah $dokumen)
    {

        $tanah = $dokumen->tanah;


        DokumenPbbHistory::create([
            'id_tanah' => $tanah->id_tanah,
            'id_pbb' => $dokumen->id_pbb,
            'id_user' => auth()->id(),
            'aksi' => 'DELETE',
            'nama_file' => basename($dokumen->file_pbb),
        ]);


        if($dokumen->file_pbb){

            Storage::disk('public')
                ->delete($dokumen->file_pbb);

        }


        $dokumen->delete();


        return redirect()
            ->route('tanah.show',$tanah)
            ->with(
                'success',
                'Dokumen PBB berhasil dihapus'
            );
    }

}