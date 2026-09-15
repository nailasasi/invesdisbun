@extends('layouts.app')

@section('title', 'Template Dokumen')

@section('content')
<div class="space-y-6">
    {{-- Alert Notifikasi --}}
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 shadow-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-3.5 text-sm font-medium text-red-700 shadow-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Kontainer Utama --}}
    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h2 class="text-lg font-bold text-slate-900">Pengaturan Template Dokumen (Dinas Perkebunan Pusat)</h2>
            </div>
            <span class="text-xs font-semibold text-emerald-600">Format: Word (.docx) & Excel (.xlsx)</span>
        </div>
        <p class="mt-1 text-sm text-slate-500">Ganti format standar cetak dokumen khusus untuk unit ini dengan mengunggah template Word (.docx) atau Excel (.xlsx).</p>

        {{-- Grid Kartu Template --}}
        <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach($templates as $tpl)
                @php
                    $isReady = ($tpl->status === 'Siap Digunakan') || !empty($tpl->file_path);
                @endphp
                <div class="flex flex-col justify-between rounded-2xl border-2 {{ $isReady ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-200 bg-slate-50/40' }} p-5 transition-all">
                    <div>
                        {{-- Judul Template & Ikon --}}
                        <div class="flex items-center gap-2">
                            @if(str_contains(strtolower($tpl->kode_template), 'bast'))
                                <span class="text-sm">📦</span>
                            @elseif(str_contains(strtolower($tpl->kode_template), 'kendaraan'))
                                <span class="text-sm">🚗</span>
                            @elseif(str_contains(strtolower($tpl->kode_template), 'kir') || str_contains(strtolower($tpl->kode_template), 'label'))
                                <span class="text-sm">🏷️</span>
                            @else
                                <span class="text-sm">📄</span>
                            @endif
                            <h3 class="text-xs font-extrabold uppercase tracking-wide text-slate-900">{{ $tpl->nama_template }}</h3>
                        </div>

                        {{-- Nama File & Tanggal --}}
                        <p class="mt-2 truncate font-mono text-[13px] text-slate-400">
                            {{ $tpl->nama_file_asli ?? 'Belum ada file diunggah' }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-400">Terakhir diubah</p>
                        <p class="text-[11px] text-slate-400">
                            {{ $tpl->updated_at ? $tpl->updated_at->timezone('Asia/Jakarta')->format('d M Y - H:i') . ' WIB' : '-' }}
                        </p>
                    </div>

                    <div class="mt-5 space-y-3">
                        {{-- Baris Kapsul Abu-abu (Pill Shape) --}}
                        <div class="flex items-center rounded-2xl bg-slate-100 p-1">
                            {{-- Tombol Ganti File --}}
                            <form action="{{ route('template-dokumen.update', $tpl->id_template ?? $tpl->id) }}" method="POST" enctype="multipart/form-data" class="flex-1">
                                @csrf
                                <label class="flex w-full cursor-pointer items-center justify-center gap-1.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:text-slate-900">
                                    <svg class="h-3.5 w-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                    </svg>
                                    <span>{{ $isReady ? 'Ganti File' : 'Upload File' }}</span>
                                    <input type="file" name="file_template" accept=".docx,.xlsx" class="hidden" onchange="this.form.submit()">
                                </label>
                            </form>

                            {{-- Tombol Mata & Hapus: Aktif saat status Siap Digunakan --}}
                            @if($isReady)
                                {{-- Tombol Lihat/Preview (Ikon Mata Biru Keabuan) --}}
                                <a href="{{ $tpl->file_path ? asset('storage/' . $tpl->file_path) : route('template-dokumen.download', $tpl->id_template ?? $tpl->id) }}" 
                                   target="_blank" 
                                   class="ml-1 flex h-7 w-8 items-center justify-center rounded-xl text-white transition hover:opacity-90" 
                                   style="background-color: #94a3b8;"
                                   title="Lihat Dokumen">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>

                                {{-- Tombol Hapus (Ikon Tong Sampah Merah Pastel) --}}
                                <form action="{{ route('template-dokumen.destroy', $tpl->id_template ?? $tpl->id) }}" method="POST" onsubmit="return confirm('Hapus file template ini?')" class="ml-1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="flex h-7 w-8 items-center justify-center rounded-xl text-white transition hover:opacity-90" 
                                            style="background-color: #fca5a5;"
                                            title="Hapus Dokumen">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- Status Kesiapan --}}
                        <div class="flex items-center gap-1.5 text-[11px] font-semibold {{ $isReady ? 'text-emerald-500' : 'text-slate-400' }}">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ $isReady ? 'Terpasang' : 'Belum Diunggah' }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection