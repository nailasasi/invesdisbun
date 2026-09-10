<?php

namespace App\Http\Controllers;

use App\Models\Tanah;
use App\Models\TanahHistory;
use Illuminate\Http\Request;

class TanahController extends Controller
{
    /**
     * Menampilkan daftar seluruh data tanah.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $tanahList = Tanah::with([
                'dokumenPbb'
            ])
            ->withSum('retribusi', 'PAD')
            ->withSum('retribusi', 'total_tarif_sewa')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('no_excel', 'like', "%{$search}%")
                        ->orWhere('kib', 'like', "%{$search}%")
                        ->orWhere('deskripsi_objek', 'like', "%{$search}%")
                        ->orWhere('alamat', 'like', "%{$search}%")
                        ->orWhere('nomor_sertifikat', 'like', "%{$search}%")
                        ->orWhere('status_hak', 'like', "%{$search}%")
                        ->orWhere('penggunaan', 'like', "%{$search}%")
                        ->orWhere('penggunaan_air', 'like', "%{$search}%")
                        ->orWhere('nama_petugas', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id_tanah')
            ->paginate(10)
            ->withQueryString();

        return view('tanah.index', compact('tanahList'));
    }

    /**
     * Menampilkan detail tanah.
     */
    public function show(Tanah $tanah)
    {
        $tanah->load([
            'retribusi',
            'dokumenPbb.uploader',
            'dokumenPbbHistories.user',
            'histories.user',
        ]);


        $historyPbb = \App\Models\DokumenPbbHistory::with('user')
            ->whereIn(
                'id_pbb',
                $tanah->dokumenPbb->pluck('id_pbb')
            )
            ->latest()
            ->get();


        return view('tanah.show', compact(
            'tanah',
            'historyPbb'
        ));
    }

    /**
     * Menampilkan form tambah tanah.
     */
    public function create()
    {
        return view('tanah.create');
    }

    /**
     * Menyimpan data tanah baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'no_excel' => ['nullable', 'integer', 'min:1'],

            'kib' => ['nullable', 'string', 'max:100'],
            'tanggal_buku' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric', 'min:0'],
            'deskripsi_objek' => ['nullable', 'string'],

            'luas_tanah' => ['nullable', 'numeric', 'min:0'],
            'alamat' => ['nullable', 'string'],
            'ketkel' => ['nullable', 'string', 'max:100'],
            'status_hak' => ['nullable', 'string', 'max:50'],

            'nomor_sertifikat' => ['nullable', 'string', 'max:100'],
            'tanggal_sertifikat' => ['nullable', 'date'],

            'penggunaan' => ['nullable', 'string', 'max:100'],
            'penggunaan_air' => ['nullable', 'string', 'max:100'],
            'kondisi' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],

            'nama_petugas' => ['nullable', 'string', 'max:100'],
            'nomor_hp_petugas' => ['nullable', 'string', 'max:20'],
            'google_maps' => ['nullable', 'string'],

            'foto_tanah' => ['nullable', 'string', 'max:255'],
            'video_tanah' => ['nullable', 'string', 'max:255'],
        ]);

        Tanah::create($validated);

        return redirect()
            ->route('tanah.index')
            ->with('success', 'Data tanah berhasil ditambahkan.');
    }

    /**
     * Menampilkan form edit tanah.
     */
    public function edit(Tanah $tanah)
    {
        return view('tanah.edit', compact('tanah'));
    }

    /**
     * Memperbarui data tanah.
     */
    public function update(Request $request, Tanah $tanah)
    {
        $validated = $request->validate([
            'no_excel' => ['nullable', 'integer', 'min:1'],

            'kib' => ['nullable', 'string', 'max:100'],
            'tanggal_buku' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric', 'min:0'],
            'deskripsi_objek' => ['nullable', 'string'],

            'luas_tanah' => ['nullable', 'numeric', 'min:0'],
            'alamat' => ['nullable', 'string'],
            'ketkel' => ['nullable', 'string', 'max:100'],
            'status_hak' => ['nullable', 'string', 'max:50'],

            'nomor_sertifikat' => ['nullable', 'string', 'max:100'],
            'tanggal_sertifikat' => ['nullable', 'date'],

            'penggunaan' => ['nullable', 'string', 'max:100'],
            'penggunaan_air' => ['nullable', 'string', 'max:100'],
            'kondisi' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],

            'nama_petugas' => ['nullable', 'string', 'max:100'],
            'nomor_hp_petugas' => ['nullable', 'string', 'max:20'],
            'google_maps' => ['nullable', 'string'],

            'foto_tanah' => ['nullable', 'string', 'max:255'],
            'video_tanah' => ['nullable', 'string', 'max:255'],
        ]);

        $dataLama = $tanah->toArray();


        $tanah->update($validated);


        $dataBaru = $tanah->fresh()->toArray();



        TanahHistory::create([
            'id_tanah' => $tanah->id_tanah,
            'id_user' => auth()->id(),
            'aksi' => 'UPDATE',
            'data_lama' => $dataLama,
            'data_baru' => $dataBaru,
        ]);


        return redirect()
            ->route('tanah.index')
            ->with('success', 'Data tanah berhasil diperbarui.');
    }
}