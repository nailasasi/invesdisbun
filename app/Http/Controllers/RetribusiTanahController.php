<?php

namespace App\Http\Controllers;

use App\Models\Tanah;
use App\Models\RetribusiTanah;
use Illuminate\Http\Request;

class RetribusiTanahController extends Controller
{
    /**
     * Menampilkan form tambah retribusi.
     */
    public function create(Tanah $tanah)
    {
        return view('retribusi_tanah.create', compact('tanah'));
    }

    /**
     * Menyimpan data retribusi.
     */
    public function store(Request $request, Tanah $tanah)
    {
        $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],

            'status_pemanfaatan' => [
                'nullable',
                'string',
                'max:50'
            ],

            'biaya_pengurusan' => ['nullable', 'numeric', 'min:0'],
            'PAD' => ['nullable', 'numeric', 'min:0'],
            'tarif_retribusi' => ['nullable', 'numeric', 'min:0'],
            'total_tarif_sewa' => ['nullable', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $validated['id_tanah'] = $tanah->id_tanah;

        RetribusiTanah::create($validated);

        return redirect()
            ->route('tanah.show', $tanah)
            ->with('success', 'Data retribusi berhasil ditambahkan.');
    }

    /**
     * Menampilkan form edit retribusi.
     */
    public function edit(RetribusiTanah $retribusi)
    {
        $tanah = $retribusi->tanah;

        return view('retribusi_tanah.edit', compact(
            'retribusi',
            'tanah'
        ));
    }

    /**
     * Memperbarui data retribusi.
     */
    public function update(
        Request $request,
        RetribusiTanah $retribusi
    ) {
       $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],

            'status_pemanfaatan' => [
                'nullable',
                'string',
                'max:50'
            ],

            'biaya_pengurusan' => ['nullable', 'numeric', 'min:0'],
            'PAD' => ['nullable', 'numeric', 'min:0'],
            'tarif_retribusi' => ['nullable', 'numeric', 'min:0'],
            'total_tarif_sewa' => ['nullable', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $retribusi->update($validated);

        return redirect()
            ->route('tanah.show', $retribusi->tanah)
            ->with('success', 'Data retribusi berhasil diperbarui.');
    }

    /**
     * Menghapus data retribusi.
     */
    public function destroy(RetribusiTanah $retribusi)
    {
        $tanah = $retribusi->tanah;

        $retribusi->delete();

        return redirect()
            ->route('tanah.show', $tanah)
            ->with('success', 'Data retribusi berhasil dihapus.');
    }
}