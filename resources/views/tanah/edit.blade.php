@extends('layouts.app')

@section('title', 'Edit Tanah - INVENSBUN')

@section('content')

<div class="mb-6 flex items-center justify-between">

    <div>
        <h2 class="text-2xl font-bold text-slate-900">
            Edit Tanah
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Perbarui informasi aset tanah.
        </p>
    </div>


    <a href="{{ route('tanah.index') }}"
       class="rounded-xl border px-4 py-2 text-sm">
        Kembali
    </a>

</div>


<div class="rounded-2xl border bg-white shadow-sm">


<form method="POST"
      action="{{ route('tanah.update',$tanah->id_tanah) }}">

@csrf
@method('PUT')


{{-- =====================
     INFORMASI KIB A
===================== --}}

<div class="border-b px-6 py-5">

<h3 class="font-bold text-slate-800">
Informasi KIB A
</h3>

<p class="text-sm text-slate-500">
Data dasar aset tanah.
</p>

</div>



<div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">



{{-- DESKRIPSI --}}

<div>

<label class="block text-sm font-semibold">
Deskripsi Objek
</label>


<input type="text"
name="deskripsi_objek"
value="{{ old('deskripsi_objek',$tanah->deskripsi_objek) }}"
class="mt-2 w-full rounded-xl border px-4 py-2">


</div>




{{-- KIB --}}

<div>

<label class="block text-sm font-semibold">
KIB
</label>


<input type="text"
name="kib"
value="{{ old('kib',$tanah->kib) }}"
class="mt-2 w-full rounded-xl border px-4 py-2">

</div>

<div>

<label class="block text-sm font-semibold">
Tanggal Buku
</label>

<input type="date"
name="tanggal_buku"
value="{{ old('tanggal_buku',
optional($tanah->tanggal_buku)->format('Y-m-d')) }}"
class="mt-2 w-full rounded-xl border px-4 py-2">

</div>

<div>

<label class="block text-sm font-semibold">
No Excel
</label>

<input type="number"
name="no_excel"
value="{{ old('no_excel',$tanah->no_excel) }}"
class="mt-2 w-full rounded-xl border px-4 py-2">

</div>

{{-- TANGGAL PEROLEHAN --}}

<div>

<label class="block text-sm font-semibold">
Tanggal Perolehan
</label>


<input type="date"
name="tanggal_perolehan"
value="{{ old('tanggal_perolehan',
optional($tanah->tanggal_perolehan)->format('Y-m-d')) }}"
class="mt-2 w-full rounded-xl border px-4 py-2">

</div>




{{-- NILAI PEROLEHAN --}}

<div>

<label class="block text-sm font-semibold">
Nilai Perolehan
</label>


<input type="number"
name="nilai_perolehan"
value="{{ old('nilai_perolehan',$tanah->nilai_perolehan) }}"
class="mt-2 w-full rounded-xl border px-4 py-2">


</div>


</div>



{{-- =====================
     INFORMASI TANAH
===================== --}}


<div class="border-y px-6 py-5">


<h3 class="font-bold text-slate-800">
Informasi Tanah
</h3>


<p class="text-sm text-slate-500">
Legalitas dan lokasi aset.
</p>


</div>



<div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">



{{-- LUAS --}}

<div>

<label class="block text-sm font-semibold">
Luas Tanah
</label>


<input type="number"
name="luas_tanah"
value="{{ old('luas_tanah',$tanah->luas_tanah) }}"
class="mt-2 w-full rounded-xl border px-4 py-2">


</div>



{{-- STATUS HAK --}}

<div>

<label class="block text-sm font-semibold">
Status Hak
</label>


<input type="text"
name="status_hak"
value="{{ old('status_hak',$tanah->status_hak) }}"
class="mt-2 w-full rounded-xl border px-4 py-2">


</div>



{{-- NOMOR SERTIFIKAT --}}

<div>

<label class="block text-sm font-semibold">
Nomor Sertifikat
</label>


<input type="text"
name="nomor_sertifikat"
value="{{ old('nomor_sertifikat',$tanah->nomor_sertifikat) }}"
class="mt-2 w-full rounded-xl border px-4 py-2">


</div>



{{-- TANGGAL SERTIFIKAT --}}

<div>

<label class="block text-sm font-semibold">
Tanggal Sertifikat
</label>


<input type="date"
name="tanggal_sertifikat"
value="{{ old('tanggal_sertifikat',
optional($tanah->tanggal_sertifikat)->format('Y-m-d')) }}"
class="mt-2 w-full rounded-xl border px-4 py-2">


</div>



{{-- PENGGUNAAN --}}

<div>

<label class="block text-sm font-semibold">
Penggunaan
</label>


<input type="text"
name="penggunaan"
value="{{ old('penggunaan',$tanah->penggunaan) }}"
class="mt-2 w-full rounded-xl border px-4 py-2">


</div>

{{-- PENGGUNAAN AIR --}}
<div>

    <label for="penggunaan_air"
           class="block text-sm font-semibold text-slate-700">
        Penggunaan Air
    </label>

    <select id="penggunaan_air"
            name="penggunaan_air"
            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">

        <option value="">
            Pilih penggunaan air
        </option>

        @foreach([
            'Mata Air',
            'Air Tanah',
            'Air Sungai',
            'Air Lainnya'
        ] as $air)

            <option value="{{ $air }}"
                @selected(old('penggunaan_air', $tanah->penggunaan_air) == $air)>
                {{ $air }}
            </option>

        @endforeach

    </select>

    @error('penggunaan_air')
        <p class="mt-1 text-xs text-red-500">
            {{ $message }}
        </p>
    @enderror

</div>



{{-- KETKEL --}}
<div>

    <label for="ketkel"
           class="block text-sm font-semibold text-slate-700">
        Ketkel
    </label>

    <input type="text"
           id="ketkel"
           name="ketkel"
           value="{{ old('ketkel', $tanah->ketkel) }}"
           class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">

    @error('ketkel')
        <p class="mt-1 text-xs text-red-500">
            {{ $message }}
        </p>
    @enderror

</div>



{{-- KONDISI --}}
<div>

    <label for="kondisi"
           class="block text-sm font-semibold text-slate-700">
        Kondisi
    </label>

    <select id="kondisi"
            name="kondisi"
            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">

        <option value="">
            Pilih kondisi
        </option>

        @foreach(['Baik', 'Rusak Ringan', 'Rusak Berat'] as $kondisi)

            <option value="{{ $kondisi }}"
                @selected(old('kondisi', $tanah->kondisi) == $kondisi)>
                {{ $kondisi }}
            </option>

        @endforeach

    </select>

    @error('kondisi')
        <p class="mt-1 text-xs text-red-500">
            {{ $message }}
        </p>
    @enderror

</div>



{{-- NAMA PETUGAS --}}
<div>

    <label for="nama_petugas"
           class="block text-sm font-semibold text-slate-700">
        Nama Petugas
    </label>

    <input type="text"
           id="nama_petugas"
           name="nama_petugas"
           value="{{ old('nama_petugas', $tanah->nama_petugas) }}"
           class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">

    @error('nama_petugas')
        <p class="mt-1 text-xs text-red-500">
            {{ $message }}
        </p>
    @enderror

</div>



{{-- NOMOR HP PETUGAS --}}
<div>

    <label for="nomor_hp_petugas"
           class="block text-sm font-semibold text-slate-700">
        Nomor HP Petugas
    </label>

    <input type="text"
           id="nomor_hp_petugas"
           name="nomor_hp_petugas"
           value="{{ old('nomor_hp_petugas', $tanah->nomor_hp_petugas) }}"
           class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">

    @error('nomor_hp_petugas')
        <p class="mt-1 text-xs text-red-500">
            {{ $message }}
        </p>
    @enderror

</div>



{{-- ALAMAT --}}
<div class="md:col-span-2">

    <label for="alamat"
           class="block text-sm font-semibold text-slate-700">
        Alamat
    </label>

    <textarea id="alamat"
              name="alamat"
              rows="3"
              class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('alamat', $tanah->alamat) }}</textarea>

    @error('alamat')
        <p class="mt-1 text-xs text-red-500">
            {{ $message }}
        </p>
    @enderror

</div>



{{-- GOOGLE MAPS --}}
<div class="md:col-span-2">

    <label for="google_maps"
           class="block text-sm font-semibold text-slate-700">
        Google Maps
    </label>

    <textarea id="google_maps"
              name="google_maps"
              rows="2"
              placeholder="https://maps.google.com/..."
              class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('google_maps', $tanah->google_maps) }}</textarea>

    @error('google_maps')
        <p class="mt-1 text-xs text-red-500">
            {{ $message }}
        </p>
    @enderror

</div>



{{-- KETERANGAN --}}
<div class="md:col-span-2">

    <label for="keterangan"
           class="block text-sm font-semibold text-slate-700">
        Keterangan
    </label>

    <textarea id="keterangan"
              name="keterangan"
              rows="3"
              placeholder="Tambahkan keterangan jika diperlukan..."
              class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('keterangan', $tanah->keterangan) }}</textarea>

    @error('keterangan')
        <p class="mt-1 text-xs text-red-500">
            {{ $message }}
        </p>
    @enderror

</div>

</div>



{{-- =========================
     TOMBOL
========================= --}}

<div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-6 py-4">

    <a href="{{ route('tanah.index') }}"
       class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
        Batal
    </a>

    <button type="submit"
            class="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:from-emerald-600 hover:to-teal-600">
        Simpan Perubahan
    </button>

</div>


</form>

</div>

@endsection