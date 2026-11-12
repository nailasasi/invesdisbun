@if (! $diri)
    <div class="flex min-h-[280px] flex-col items-center justify-center rounded-3xl border border-dashed border-disbun-card-border bg-white p-8 text-center shadow-bento">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-disbun-amber">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </span>
        <h3 class="mt-4 text-base font-semibold text-slate-800">Profil pegawai belum terhubung</h3>
        <p class="mt-1 max-w-sm text-sm text-slate-500">
            Akun Anda belum terhubung ke data pegawai. Hubungi Admin Aset untuk melengkapi profil Anda.
        </p>
    </div>
@else
    @php
        $inisial = collect(preg_split('/\s+/', trim($diri->nama_pegawai ?? auth()->user()->username ?? '-')))->slice(0,2)->map(fn($w)=>mb_strtoupper(mb_substr($w,0,1)))->implode('');
    @endphp
    {{-- HERO PEGAWAI --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0c2e1b] via-[#133e25] to-[#1c5531] p-6 text-white shadow-bento sm:p-8">
        <div class="absolute -right-14 -top-14 h-56 w-56 rounded-full bg-disbun-amber-glow/20 blur-3xl"></div>
        <div class="absolute -bottom-16 left-20 h-48 w-48 rounded-full bg-emerald-400/15 blur-3xl"></div>
        <div class="relative flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-lg font-extrabold backdrop-blur">
                    {{ $inisial }}
                </span>
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                        Halo, {{ $diri->nama_pegawai ?? auth()->user()->username }} 👋
                    </h1>
                    <p class="mt-2 max-w-lg text-sm text-white/70">
                        Selamat datang di dashboard aset pribadi Anda.
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center">
                <a href="{{ $diri ? route('aset-barang.show', ['pegawai' => $diri->id_pegawai, 'from' => 'dashboard']) : '#' }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-disbun-amber to-disbun-amber-glow px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-black/10 transition hover:brightness-105">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Lihat Aset Saya
                </a>
            </div>
        </div>
    </div>

    {{-- TILE BENTO --}}
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 40ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Aset Saya</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-disbun-600 to-disbun-800 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($asetSaya->count(), 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">aset yang sedang Anda pegang</p>
        </div>
    </div>

    {{-- TRACKING IZIN --}}
    @if ($izinTracking->isNotEmpty())
        <div class="mt-6 w-full space-y-3">
            <div class="flex items-center gap-2 px-0.5">
                <h3 class="text-sm font-bold text-slate-800">Status Pengajuan Izin Kendaraan Anda</h3>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">{{ $izinTracking->count() }}</span>
            </div>
            @foreach ($izinTracking as $izin)
                @php
                    $statusAktual = $izin->status_approval ?? 'Menunggu';
                    $statusInfo = match ($statusAktual) {
                        'Disetujui' => ['label' => 'Disetujui', 'badge' => 'bg-disbun-50 text-disbun-700', 'icon' => 'bg-disbun-100 text-disbun-600'],
                        'Ditolak' => ['label' => 'Ditolak', 'badge' => 'bg-rose-50 text-rose-700', 'icon' => 'bg-rose-100 text-rose-600'],
                        default => ['label' => 'Menunggu Verifikasi Admin', 'badge' => 'bg-amber-50 text-amber-700', 'icon' => 'bg-amber-100 text-amber-600'],
                    };
                    $namaKendaraan = $izin->kendaraan?->namaTampil() ?: 'Kendaraan';
                    $plat = $izin->kendaraan?->platAktif?->nomor_plat;
                @endphp
                <div class="flex items-start gap-4 rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento">
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
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $statusInfo['badge'] }}">{{ $statusInfo['label'] }}</span>
                        </div>
                        <p class="mt-1 truncate text-xs text-slate-500">
                            {{ $namaKendaraan }}{{ $plat ? ' · ' . $plat : '' }} · {{ $izin->tanggal_berangkat?->format('d M Y') ?? '-' }}{{ $izin->durasi ? ' · ' . $izin->durasi : '' }}
                        </p>
                        @if ($statusAktual === 'Ditolak' && $izin->alasan_penolakan)
                            <p class="mt-1.5 rounded-xl bg-rose-50/70 px-2 py-1 text-[11px] leading-snug text-rose-600">
                                <span class="font-semibold">Alasan:</span> {{ $izin->alasan_penolakan }}
                            </p>
                        @endif
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a href="{{ route('layanan.izin-kendaraan.index') }}"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-disbun-600 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-700">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Lihat Detail
                        </a>
                        <form method="POST" action="{{ route('dashboard.dismiss-notification') }}">
                            @csrf
                            <input type="hidden" name="id_izin" value="{{ $izin->id_izin }}">
                            <button type="submit" class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" title="Tutup notifikasi">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif


@endif