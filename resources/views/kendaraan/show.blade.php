@extends('layouts.app')

@section('title', 'Detail Kendaraan')

@section('page-title', 'Detail Kendaraan')

@section('content')
    {{-- Navigasi Kembali Minimalis di Atas Judul --}}
    <div class="mb-3">
        <a href="{{ route('kendaraan.index') }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-slate-900">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Kendaraan</span>
        </a>
    </div>

    {{-- Header Judul --}}
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Detail Kendaraan</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $kendaraan->namaTampil() ?? 'Kendaraan' }} — {{ $kendaraan->aset?->nomor_kartu_barang ?? '-' }}</p>
        </div>
    </div>

    @php
        $isAdmin = $isAdminAset;
        $kondisiColor = match ($kendaraan->aset?->kondisi) {
            'Baik' => 'bg-disbun-100 text-disbun-800',
            'Rusak Ringan' => 'bg-amber-100 text-amber-700',
            'Rusak Berat' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
        $platAktif = $kendaraan->platAktif;
        $pajakAktif = $kendaraan->pajakAktif;
        $statusBadge = match ($kendaraan->aset?->status_aset) {
            'aktif' => 'bg-disbun-100 text-disbun-800',
            'diusulkan_hapus' => 'bg-amber-100 text-amber-700',
            'dihapuskan' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
    @endphp

    {{-- Informasi Kendaraan --}}
    @include('kendaraan.partials.detail-informasi')

    {{-- Riwayat Mutasi Kendaraan --}}
    @include('kendaraan.partials.detail-riwayat-mutasi')

    {{-- Grid Riwayat Plat & Pajak Kendaraan --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        @include('kendaraan.partials.detail-riwayat-plat')
        @include('kendaraan.partials.detail-pajak')
    </div>

    @if ($isAdmin)
        @push('modals')
            @include('kendaraan.partials.modal-kendaraan')
            @include('kendaraan.partials.modal-pajak')
        @endpush
    @endif

    @include('kendaraan.partials.scripts-show')
@endsection
