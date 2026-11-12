@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    // Filter tahun aktif dari halaman daftar. HANYA dipakai untuk navigasi
    // (kembali ke daftar); tidak membatasi data yang ditampilkan di detail.
    $filterParams = array_filter([
        'tahun' => request('tahun'),
    ], fn ($v) => $v !== null && $v !== '');

    // Kembali ke daftar mempertahankan filter tahun bila datang dari daftar terfilter.
    $backUrl = route('tanah.index', $filterParams);

    $isAdmin = in_array(
        auth()->user()?->role?->nama_role ?? '',
        ['Admin Aset', 'Admin UPT']
    );

    // Tombol edit mengikuti izin server (TanahScope: Admin Aset + Admin UPT P2BTP).
    $canEdit = $isAdmin;

    $kondisiTeks = trim((string) ($tanah->kondisi ?? ''));
    $kondisiColor = match ($kondisiTeks) {
        'Baik' => 'bg-disbun-100 text-disbun-800',
        'Rusak Ringan' => 'bg-amber-100 text-amber-700',
        'Rusak Berat' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };

    // Detail menampilkan SELURUH baris (semua tahun). Metrik ringkasan pun
    // mengakumulasi semua tahun; filter daftar tidak memengaruhi halaman ini.
    $retribusiRows = $tanah->retribusi->sortByDesc('tahun')->values();

    $sumCol = fn ($col) => (float) $retribusiRows->sum(fn ($r) => (float) ($r->$col ?? 0));
    $totalBiayaKebun = $sumCol('biaya_pengurusan');
    $totalPad = $sumCol('PAD');
    $totalTarif = $sumCol('tarif_retribusi');
    $totalSewa = $sumCol('total_tarif_sewa');
    $satuanTarif = $retribusiRows->first(fn ($r) => !empty($r->satuan))?->satuan ?? '-';
    $latestRetribusi = $retribusiRows->first();

    $rupiah = fn ($n) => $n > 0
        ? 'Rp ' . number_format((float) $n, 0, ',', '.')
        : '-';
    $rupiah0 = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');

    $fotoUrl = null;
    if (!empty($tanah->foto_tanah)) {
        $f = $tanah->foto_tanah;
        if (Str::startsWith(Str::lower($f), ['http://', 'https://'])) {
            $fotoUrl = $f;
        } elseif (Storage::disk('public')->exists($f)) {
            $fotoUrl = Storage::disk('public')->url($f);
        } elseif (Storage::disk('local')->exists($f)) {
            $fotoUrl = Storage::url($f);
        }
    }

    $formatTanggal = fn ($t) => $t?->format('d M Y') ?? '-';
    $namaUser = fn ($u) => $u?->pegawai?->nama_pegawai ?? $u?->username ?? 'Sistem';
@endphp

@extends('layouts.app')

@section('title', 'Detail Tanah')
@section('page-title', 'Detail Tanah')
@section('breadcrumb', 'Inventaris Tanah • Detail Aset')

@section('content')
    {{-- Kembali minimalis --}}
    <div class="mb-3">
        <a href="{{ $backUrl }}"
           class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Kembali ke Inventaris Tanah</span>
        </a>
    </div>

    {{-- Error dari modal (upload PBB) --}}
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.82 15A2 2 0 003.2 22h17.6a2 2 0 001.73-3.14l-8.82-15a2 2 0 00-3.42 0Z"/></svg>
                <div>
                    <p class="text-sm font-semibold text-red-700">Data belum dapat disimpan</p>
                    <ul class="mt-1 list-inside list-disc text-sm text-red-600">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Layout 2 Kolom: 4 kiri / 8 kanan --}}
    <div class="mt-4 grid grid-cols-1 items-start gap-5 lg:grid-cols-12">
        {{-- Kolom Kiri: Hero & Ringkasan --}}
        @include('tanah.detail.hero')

        {{-- Kolom Kanan: Konten Tab --}}
        <div class="space-y-4 lg:col-span-8">
            {{-- Tab Navigation Pills --}}
            <div class="overflow-x-auto">
                <div class="flex w-max min-w-full items-center gap-1 rounded-2xl border border-slate-200/80 bg-white p-1.5 shadow-2xs">
                    <button type="button" data-tab-target="tab-kib"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl bg-disbun-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition" role="tab">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Data KIB A &amp; Legalitas
                    </button>
                    <button type="button" data-tab-target="tab-retribusi"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-xs font-bold text-slate-600 transition" role="tab">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Pemanfaatan &amp; Retribusi
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $retribusiRows->count() }}</span>
                    </button>
                    <button type="button" data-tab-target="tab-pbb"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-xs font-bold text-slate-600 transition" role="tab">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Dokumen PBB
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $tanah->dokumenPbb->count() }}</span>
                    </button>
                    <button type="button" data-tab-target="tab-history"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-xs font-bold text-slate-600 transition" role="tab">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Riwayat Perubahan
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $tanah->histories->count() }}</span>
                    </button>
                </div>
            </div>

            {{-- Tab 1: KIB & Legalitas --}}
            @include('tanah.detail.informasi')

            {{-- Tab 2: Pemanfaatan & Retribusi --}}
            @include('tanah.detail.retribusi')

            {{-- Tab 3: Dokumen PBB --}}
            @include('tanah.detail.dokumen-pbb')

            {{-- Tab 4: Riwayat Perubahan --}}
            @include('tanah.detail.riwayat')
        </div>
    </div>

    {{-- Modal Dialog --}}
    @include('tanah.detail.modal-dokumen-pbb')
    @include('tanah.detail.modal-bukti')

    {{-- Script Interaksi Detail Tanah --}}
    @include('tanah.detail.scripts')
@endsection
