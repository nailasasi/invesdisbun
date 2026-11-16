@extends('layouts.app')

@section('title', 'Template Dokumen')

@section('page-title', 'Template Dokumen')

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
    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-3.5 text-sm font-medium text-red-700 shadow-sm">
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Kontainer Utama --}}
    <x-card :padding="false">
        <x-action-bar>
            <div class="flex min-w-0 items-center gap-2.5">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <p class="min-w-0 text-sm text-slate-500">Kelola master format cetak dokumen resmi dinas dengan mengunggah template Word (.docx) atau Excel (.xlsx).</p>
            </div>
            <x-slot name="actions">
                <span class="inline-flex items-center gap-1.5 rounded-2xl border border-disbun-100 bg-disbun-50 px-3 py-1.5 text-xs font-bold text-disbun-700">Format: Word (.docx) &amp; Excel (.xlsx)</span>
            </x-slot>
        </x-action-bar>

        {{-- Grid Kartu Template --}}
        <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($templates as $tpl)
                @php
                    $isReady = ($tpl->status === 'Siap Digunakan') && $tpl->fileExists();
                    $deskripsiDefault = match($tpl->kode_template) {
                        'sppbi' => 'Format Word (.docx) Surat Perintah Penyaluran Barang Inventaris ke ASN pemegang.',
                        'label' => 'Format Excel (.xlsx) cetak stiker barcode/label inventaris barang dan ruangan.',
                        'kir' => 'Format Word (.docx) cetak lembar Kartu Inventaris Ruangan (KIR).',
                        'bast_barang' => 'Format Word (.docx) cetak Berita Acara Serah Terima mutasi barang inventaris.',
                        'sppkd' => 'Format Word (.docx) cetak Surat Perjanjian Pemakaian Kendaraan Dinas.',
                        'bast_kendaraan' => 'Format Word (.docx) cetak Berita Acara Serah Terima mutasi kendaraan bermotor.',
                        'surat_izin_kendaraan' => 'Format Word (.docx) untuk cetak surat izin pemakaian kendaraan dinas.',
                        default => $tpl->deskripsi ?? 'Format template dokumen dinas resmi.',
                    };
                @endphp
                <div class="flex flex-col justify-between rounded-2xl border-2 {{ $isReady ? 'border-disbun-200 bg-disbun-50/20' : 'border-slate-200 bg-slate-50/40' }} p-5 transition-all">
                    <div>
                        {{-- Judul Template & Ikon --}}
                        <div class="flex items-center gap-2">
                            @if(str_contains(strtolower($tpl->kode_template), 'bast_kendaraan') || str_contains(strtolower($tpl->kode_template), 'izin_kendaraan') || str_contains(strtolower($tpl->kode_template), 'kendaraan'))
                                <span class="text-sm">🚗</span>
                            @elseif(str_contains(strtolower($tpl->kode_template), 'bast'))
                                <span class="text-sm">📦</span>
                            @elseif(str_contains(strtolower($tpl->kode_template), 'kir') || str_contains(strtolower($tpl->kode_template), 'label'))
                                <span class="text-sm">🏷️</span>
                            @else
                                <span class="text-sm">📄</span>
                            @endif
                            <h3 class="text-xs font-extrabold uppercase tracking-wide text-slate-900">{{ $tpl->nama_template }}</h3>
                        </div>

                        <p class="mt-2 text-[11px] leading-relaxed text-slate-500 min-h-[32px]">{{ $tpl->deskripsi ?: $deskripsiDefault }}</p>

                        {{-- Nama File & Tanggal --}}
                        <p class="mt-2 truncate font-mono text-[13px] text-slate-600">
                            {{ $tpl->nama_file_asli ?? 'Belum ada file diunggah' }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-400">Terakhir diubah</p>
                        <p class="text-[11px] font-mono text-slate-500">
                            {{ $isReady && $tpl->updated_at ? $tpl->updated_at->timezone('Asia/Jakarta')->format('d M Y - H:i') . ' WIB' : '—' }}
                        </p>
                    </div>

                    <div class="mt-5 space-y-3">
                        {{-- Baris Kapsul Abu-abu (Pill Shape) --}}
                        <div class="flex items-center rounded-2xl bg-slate-100 p-1">
                            {{-- Tombol Ganti File --}}
                            <form action="{{ route('template-dokumen.update', $tpl->id_template) }}" method="POST" enctype="multipart/form-data" class="flex-1">
                                @csrf
                                <label class="flex w-full cursor-pointer items-center justify-center gap-1.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:text-slate-900">
                                    <svg class="h-3.5 w-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                    </svg>
                                    <span>{{ $isReady ? 'Ganti File' : 'Upload File' }}</span>
                                    <input type="file" name="file_template" accept="{{ $tpl->tipe_berkas === 'excel' ? '.xlsx' : '.docx' }}" class="hidden" onchange="this.form.submit()">
                                </label>
                            </form>

                            {{-- Tombol Unduh & Hapus: Aktif saat status Siap Digunakan --}}
                            @if($isReady)
                                {{-- Tombol Unduh File Template Resmi --}}
                                <a href="{{ route('template-dokumen.download', $tpl->id_template) }}"
                                   class="ml-1 flex h-7 w-8 items-center justify-center rounded-xl text-white transition hover:opacity-90 bg-disbun-600 hover:bg-disbun-700"
                                   title="Unduh File Template">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </a>

                                {{-- Tombol Hapus (Ikon Tong Sampah Merah Pastel) --}}
                                <form action="{{ route('template-dokumen.destroy', $tpl->id_template) }}" method="POST" onsubmit="return confirm('Hapus berkas fisik template {{ $tpl->nama_template }}?')" class="ml-1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="flex h-7 w-8 items-center justify-center rounded-xl text-white transition hover:opacity-90 bg-rose-500 hover:bg-rose-600"
                                            title="Hapus Berkas Template">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- Status Kesiapan --}}
                        <div class="flex items-center gap-1.5 text-[11px] font-semibold {{ $isReady ? 'text-disbun-600' : 'text-slate-400' }}">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ $isReady ? 'Terpasang' : 'Belum Diunggah' }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>
</div>
@endsection
