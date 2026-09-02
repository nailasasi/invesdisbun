@extends('layouts.app')

@section('title', 'Edit Pegawai')

@section('content')
    <x-page-header title="Edit Pegawai" subtitle="Perbarui data pegawai">
        <x-slot:actions>
            <x-button href="{{ route('master-data.pegawai.index') }}" variant="secondary">Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    <form action="{{ route('master-data.pegawai.update', $pegawai) }}" method="POST">
        @csrf
        @method('PUT')
        @include('master-data.pegawai._form', ['pegawai' => $pegawai, 'skpdList' => $skpdList])

        <div class="mt-6 flex items-center justify-end gap-2">
            <x-button href="{{ route('master-data.pegawai.index') }}" variant="secondary">Batal</x-button>
            <x-button type="submit">Perbarui</x-button>
        </div>
    </form>
@endsection
