@extends('layouts.app')

@section('title', 'Dashboard')

@section('page-title', 'Dashboard')

@section('content')
    {{-- Konten khusus role (hero & grid ditentukan di tiap partial) --}}
    @if ($role === 'Admin Aset')
        @include('dashboard.partials.admin-aset')
    @elseif ($role === 'Admin Bidang')
        @include('dashboard.partials.admin-bidang')
    @elseif ($role === 'Pegawai')
        @include('dashboard.partials.pegawai')
    @else
        @include('dashboard.partials.unit')
    @endif
@endsection