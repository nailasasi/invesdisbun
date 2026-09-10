@extends('layouts.app')

@section('title','Edit Retribusi Tanah')

@section('content')

<div class="mb-6">

    <h2 class="text-2xl font-bold text-slate-800">
        Edit Retribusi Tanah
    </h2>

    <p class="text-sm text-slate-500 mt-1">
        Perbarui data retribusi tanah.
    </p>

</div>


<div class="bg-white border rounded-2xl shadow-sm p-6">


<form method="POST"
      action="{{ route('tanah.retribusi.update',$retribusi->id_retribusi) }}">

@csrf
@method('PUT')


<div class="grid grid-cols-1 md:grid-cols-2 gap-5">


<div>
<label class="text-sm font-semibold">
Tahun
</label>

<input type="number"
name="tahun"
value="{{ old('tahun',$retribusi->tahun) }}"
class="mt-2 w-full border rounded-xl px-4 py-2">

</div>



<div>
<label class="text-sm font-semibold">
Status Pemanfaatan
</label>

<input type="text"
name="status_pemanfaatan"
value="{{ old('status_pemanfaatan',$retribusi->status_pemanfaatan) }}"
class="mt-2 w-full border rounded-xl px-4 py-2">

</div>



<div>
<label class="text-sm font-semibold">
Biaya Pengurusan
</label>

<input type="number"
name="biaya_pengurusan"
value="{{ old('biaya_pengurusan',$retribusi->biaya_pengurusan) }}"
class="mt-2 w-full border rounded-xl px-4 py-2">

</div>



<div>
<label class="text-sm font-semibold">
PAD
</label>

<input type="number"
name="PAD"
value="{{ old('PAD',$retribusi->PAD) }}"
class="mt-2 w-full border rounded-xl px-4 py-2">

</div>



<div>
<label class="text-sm font-semibold">
Tarif Retribusi
</label>

<input type="number"
name="tarif_retribusi"
value="{{ old('tarif_retribusi',$retribusi->tarif_retribusi) }}"
class="mt-2 w-full border rounded-xl px-4 py-2">

</div>



<div>
<label class="text-sm font-semibold">
Total Tarif
</label>

<input type="number"
name="total_tarif_sewa"
value="{{ old('total_tarif_sewa',$retribusi->total_tarif_sewa) }}"
class="mt-2 w-full border rounded-xl px-4 py-2">

</div>



<div>
<label class="text-sm font-semibold">
Satuan
</label>

<input type="text"
name="satuan"
value="{{ old('satuan',$retribusi->satuan) }}"
class="mt-2 w-full border rounded-xl px-4 py-2">

</div>



<div class="md:col-span-2">

<label class="text-sm font-semibold">
Keterangan
</label>

<textarea name="keterangan"
rows="3"
class="mt-2 w-full border rounded-xl px-4 py-2">{{ old('keterangan',$retribusi->keterangan) }}</textarea>

</div>


</div>


<div class="mt-6 flex justify-end gap-3">


<a href="{{ route('tanah.show',$tanah->id_tanah) }}"
class="border rounded-xl px-5 py-2">
Batal
</a>


<button type="submit"
class="bg-emerald-500 text-white rounded-xl px-5 py-2">
Simpan
</button>


</div>


</form>


</div>


@endsection