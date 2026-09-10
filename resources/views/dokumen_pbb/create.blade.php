@extends('layouts.app')

@section('title', 'Tambah Dokumen PBB')

@section('content')

<div class="min-h-screen bg-slate-50 py-8">

<div class="mx-auto max-w-3xl px-6">

<h1 class="text-2xl font-bold text-slate-900">
    Tambah Dokumen PBB
</h1>

<p class="mt-1 text-sm text-slate-500">
    {{ $tanah->kib }} -
    {{ $tanah->deskripsi_objek }}
</p>


<form action="{{ route('tanah.dokumen.store',$tanah) }}"
      method="POST"
      enctype="multipart/form-data"
      class="mt-6 rounded-xl bg-white p-6 shadow">

@csrf


<div class="mb-5">

<label class="block text-sm font-medium">
    Tahun PBB
</label>

<input type="number"
       name="tahun_pbb"
       value="{{ old('tahun_pbb') }}"
       class="mt-2 w-full rounded-lg border px-3 py-2">

</div>



<div class="mb-5">

<label class="block text-sm font-medium">
    File PBB
</label>

<input type="file"
       name="file_pbb"
       class="mt-2 w-full rounded-lg border px-3 py-2">

<p class="mt-1 text-xs text-slate-400">
    Format: PDF/JPG/PNG maksimal 5MB
</p>

</div>



<div class="flex justify-end gap-3">

<a href="{{ route('tanah.show',$tanah) }}"
   class="rounded-lg border px-4 py-2">
    Batal
</a>


<button type="submit"
        class="rounded-lg bg-emerald-600 px-4 py-2 text-white">
    Simpan
</button>


</div>


</form>

</div>

</div>

@endsection