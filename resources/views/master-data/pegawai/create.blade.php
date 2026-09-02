@extends('layouts.app')

@section('title', 'Tambah Pegawai')

@section('content')
    <x-page-header title="Tambah Pegawai" subtitle="Tambahkan data pegawai baru">
        <x-slot:actions>
            <x-button href="{{ route('master-data.pegawai.index') }}" variant="secondary">Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    <form action="{{ route('master-data.pegawai.store') }}" method="POST">
        @csrf
        @include('master-data.pegawai._form', ['skpdList' => $skpdList])

        <div class="mt-6 flex items-center justify-end gap-2">
            <x-button href="{{ route('master-data.pegawai.index') }}" variant="secondary">Batal</x-button>
            <x-button type="submit">Simpan</x-button>
        </div>
    </form>
@endsection
