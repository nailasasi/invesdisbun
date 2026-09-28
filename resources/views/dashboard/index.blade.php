@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    {{-- Hero --}}
    <div class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 p-6 text-white shadow-soft sm:p-8">
        <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-20 right-24 h-56 w-56 rounded-full bg-teal-300/20 blur-3xl"></div>
        <div class="relative flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                    Halo, {{ auth()->user()->pegawai?->nama_pegawai ?? auth()->user()->username }} 👋
                </h1>
                <p class="mt-2 max-w-xl text-sm text-emerald-100/90 sm:text-base">
                    Selamat datang di Sistem Informasi Inventaris Aset Dinas Perkebunan.
                </p>
            </div>
            <div class="flex items-center gap-2 self-start rounded-full bg-white/10 px-4 py-2 text-sm font-medium backdrop-blur sm:self-auto">
                <svg class="h-4 w-4 text-emerald-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ now()->translatedFormat('l, d F Y') }}
            </div>
        </div>
    </div>

    @if (! $isAdminAset && $izinTracking->isNotEmpty())
        <div class="mb-6 w-full space-y-3">
            <div class="flex items-center gap-2 px-0.5">
                <h3 class="text-sm font-bold text-slate-800">Status Pengajuan Izin Kendaraan Anda</h3>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                    {{ $izinTracking->count() }}
                </span>
            </div>

            @foreach ($izinTracking as $izin)
                @php
                    $statusAktual = $izin->status_approval ?? 'Menunggu';
                    $statusInfo = match ($statusAktual) {
                        'Disetujui' => ['label' => 'Disetujui', 'badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'icon' => 'bg-emerald-100 text-emerald-600'],
                        'Ditolak' => ['label' => 'Ditolak', 'badge' => 'bg-red-50 text-red-700 ring-red-200', 'icon' => 'bg-red-100 text-red-600'],
                        default => ['label' => 'Menunggu Verifikasi Admin', 'badge' => 'bg-amber-50 text-amber-700 ring-amber-200', 'icon' => 'bg-amber-100 text-amber-600'],
                    };
                    $namaKendaraan = $izin->kendaraan?->aset?->barang?->nama_barang ?: 'Kendaraan';
                    $plat = $izin->kendaraan?->platAktif?->nomor_plat;
                @endphp
                <div class="flex items-start gap-4 rounded-2xl bg-white p-5 shadow-sm">
                    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $statusInfo['icon'] }}">
                        @if ($statusAktual === 'Disetujui')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @elseif ($statusAktual === 'Ditolak')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-slate-800">Pengajuan izin kendaraan</p>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $statusInfo['badge'] }}">
                                {{ $statusInfo['label'] }}
                            </span>
                        </div>
                        <p class="mt-1 truncate text-xs text-slate-500">
                            {{ $namaKendaraan }}
                            @if ($plat) · {{ $plat }} @endif
                            · {{ $izin->tanggal_berangkat?->format('d M Y') ?? '-' }}
                            @if ($izin->durasi) · {{ $izin->durasi }} @endif
                        </p>
                        @if ($statusAktual === 'Ditolak' && $izin->alasan_penolakan)
                            <p class="mt-1.5 rounded-lg bg-red-50/70 px-2 py-1 text-[11px] leading-snug text-red-600">
                                <span class="font-semibold">Alasan:</span> {{ $izin->alasan_penolakan }}
                            </p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <a href="{{ route('layanan.izin-kendaraan.index') }}"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Lihat Detail
                        </a>
                        <form method="POST" action="{{ route('dashboard.dismiss-notification') }}">
                            @csrf
                            <input type="hidden" name="id_izin" value="{{ $izin->id_izin }}">
                            <button type="submit"
                                    class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                    title="Tutup notifikasi">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="animate-fade-in-up rounded-2xl border border-slate-200/80 bg-white p-5 shadow-soft" style="animation-delay: 40ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Total Aset</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-glow">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($totalAset, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">tercatat di sistem</p>
        </div>

        <div class="animate-fade-in-up rounded-2xl border border-slate-200/80 bg-white p-5 shadow-soft" style="animation-delay: 80ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Kategori</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 text-white shadow-soft shadow-sky-600/30">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($totalKategori, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">kategori aset</p>
        </div>

        <div class="animate-fade-in-up rounded-2xl border border-slate-200/80 bg-white p-5 shadow-soft" style="animation-delay: 120ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Total Pengguna</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-soft shadow-violet-600/30">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($totalPengguna, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">akun terdaftar</p>
        </div>

        <div class="animate-fade-in-up rounded-2xl border border-slate-200/80 bg-white p-5 shadow-soft" style="animation-delay: 160ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Nilai Aset</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-soft shadow-amber-600/30">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">Rp {{ number_format($totalNilaiAset, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">total nilai perolehan</p>
        </div>
    </div>

    {{-- Pengajuan izin kendaraan pending (khusus Admin Aset) --}}
    @if ($isAdminAset)
        <div class="animate-fade-in-up mt-6 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-soft">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM7 9h8m3 5h-3m-8 0h8M4 9v2m13-4V5m-4-1H7a2 2 0 00-2 2v4m13 2v4a1 1 0 01-1 1h-1m-9 0H6a1 1 0 01-1-1v-4"/></svg>
                </span>
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Pengajuan Izin Kendaraan Pending</h3>
                    <p class="text-xs text-slate-400">Menunggu persetujuan Admin Aset</p>
                </div>
            </div>

            @if ($izinMenungguCount > 0)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700 ring-1 ring-inset ring-amber-200">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $izinMenungguCount }} pengajuan belum diproses
                </span>
            @endif
        </div>

        <div class="mt-4 space-y-3">
            @forelse ($izinMenungguTerbaru as $izin)
                @php
                    $namaKendaraan = $izin->kendaraan?->aset?->barang?->nama_barang ?: 'Kendaraan';
                    $plat = $izin->kendaraan?->platAktif?->nomor_plat;
                @endphp
                <div class="grid grid-cols-1 items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/50 px-4 py-3 md:grid-cols-[1fr_auto_auto]">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-800">{{ $izin->pengaju?->nama_pegawai ?? '-' }}</p>
                        <p class="text-xs text-slate-400">{{ $namaKendaraan }}@if ($plat) · {{ $plat }}@endif</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Tanggal Pakai</p>
                        <p class="text-sm font-medium text-slate-700">
                            {{ $izin->tanggal_berangkat?->format('d M Y') ?? '-' }}
                            @if ($izin->durasi)
                                <span class="text-xs text-slate-400">· {{ $izin->durasi }}</span>
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('layanan.izin-kendaraan.index') }}"
                       class="inline-flex w-fit items-center gap-1.5 rounded-xl bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200 transition hover:bg-amber-100">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Periksa
                    </a>
                </div>
            @empty
                <div class="flex h-36 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 text-center">
                    <svg class="h-10 w-10 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="mt-3 text-sm font-medium text-slate-400">Semua pengajuan telah diproses</p>
                </div>
            @endforelse
        </div>
        </div>
    @endif

    {{-- Ringkasan kondisi & aktivitas --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-soft lg:col-span-2">
            <h3 class="mb-4 text-base font-semibold text-slate-800">Gambaran Umum Aset</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-emerald-100 bg-gradient-to-b from-emerald-50 to-white p-4">
                    <p class="text-xs font-medium text-emerald-700">Kondisi Baik</p>
                    <p class="mt-2 text-2xl font-extrabold text-emerald-600">{{ number_format($asetBaik, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-gradient-to-b from-amber-50 to-white p-4">
                    <p class="text-xs font-medium text-amber-700">Rusak Ringan</p>
                    <p class="mt-2 text-2xl font-extrabold text-amber-600">{{ number_format($asetRusakRingan, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-red-100 bg-gradient-to-b from-red-50 to-white p-4">
                    <p class="text-xs font-medium text-red-700">Rusak Berat</p>
                    <p class="mt-2 text-2xl font-extrabold text-red-600">{{ number_format($asetRusakBerat, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-soft">
            <h3 class="mb-4 text-base font-semibold text-slate-800">Aktivitas Terbaru</h3>
            <div class="flex h-40 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 text-center">
                <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="mt-3 text-sm font-medium text-slate-400">Belum ada aktivitas</p>
            </div>
        </div>
    </div>
@endsection