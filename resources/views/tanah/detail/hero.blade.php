{{-- Kolom Kiri: Hero & Ringkasan Aset Tanah --}}
<div class="space-y-4 lg:col-span-4">
    <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-2xs">
        {{-- Media: foto aset --}}
        <div class="relative h-48 w-full overflow-hidden rounded-2xl border border-slate-100 bg-slate-100 sm:h-52">
            @if ($fotoUrl)
                <img src="{{ $fotoUrl }}" alt="Foto aset tanah" class="h-full w-full object-cover">
            @else
                <div class="flex h-full w-full flex-col items-center justify-center">
                    <svg class="h-14 w-14 shrink-0 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M8 7h.01M12 7h.01M16 7h.01M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01"/>
                    </svg>
                    <p class="mt-2 text-xs font-semibold text-emerald-400">Belum ada foto aset</p>
                </div>
            @endif
        </div>

        {{-- Body hero --}}
        <div class="p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">KIB</p>
                    <p class="mt-0.5 truncate font-mono text-lg font-bold text-slate-900">{{ $tanah->kib ?: '-' }}</p>
                </div>
            </div>

            <p class="mt-2 text-sm text-slate-600">{{ $tanah->deskripsi_objek ?: 'Belum ada deskripsi objek.' }}</p>

            @if ($tanah->penggunaan)
                <span class="mt-3 inline-flex items-center rounded-full bg-disbun-50 px-2.5 py-0.5 text-xs font-medium text-disbun-800">
                    <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-disbun-600"></span>
                    {{ $tanah->penggunaan }}
                </span>
            @endif

            {{-- Ringkasan info --}}
            <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Petugas Pemelihara</p>
                        <p class="truncate text-sm font-medium text-slate-800">{{ $tanah->nama_petugas ?: '-' }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Kontak No HP</p>
                        @if ($tanah->nomor_hp_petugas)
                            <a href="tel:{{ $tanah->nomor_hp_petugas }}" class="truncate text-sm font-medium text-slate-800 transition hover:text-disbun-700">{{ $tanah->nomor_hp_petugas }}</a>
                        @else
                            <p class="truncate text-sm font-medium text-slate-800">-</p>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Kondisi Aset</p>
                        <span class="mt-0.5 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase {{ $kondisiColor }}">{{ $kondisiTeks ?: '-' }}</span>
                    </div>
                </div>
            </div>

            {{-- Aksi lokasi --}}
            @if ($tanah->google_maps)
                <a href="{{ $tanah->google_maps }}" target="_blank" rel="noopener noreferrer"
                   class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2.5 text-xs font-bold text-white shadow-xs transition hover:bg-disbun-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Buka Lokasi di Google Maps</span>
                </a>
            @else
                <div class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-medium text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Lokasi belum dicantumkan</span>
                </div>
            @endif

            {{-- Footer: editor terakhir --}}
            @if ($lastEdit)
                <div class="mt-2 flex items-center gap-1.5 border-t border-slate-100 pt-2 text-[11px] text-slate-400">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Terakhir diedit oleh:</span>
                    <span class="font-semibold text-slate-500">{{ $lastEdit->user?->pegawai?->nama_pegawai ?? $lastEdit->user?->username ?? 'Admin' }}</span>
                    <span>•</span>
                    <span>{{ \Carbon\Carbon::parse($lastEdit->created_at)->diffForHumans() }}</span>
                </div>
            @endif
        </div>
    </div>
</div>

