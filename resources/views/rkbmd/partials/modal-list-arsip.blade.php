{{-- Partial: Modal Daftar Arsip Dokumen Sah RKBMD (dengan Search & Filter) --}}
@php
    $arsipTahunList = $arsipList->pluck('tahun_anggaran')->filter()->unique()->sortDesc()->values();
    if ($arsipTahunList->isEmpty()) {
        $arsipTahunList = collect([now()->year - 2, now()->year - 1, now()->year, now()->year + 1]);
    }
@endphp

<div id="modalArsipList"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
     data-modal>

    <div class="relative flex flex-col w-full max-w-2xl max-h-[85vh] rounded-3xl bg-white shadow-2xl border border-slate-100 overflow-hidden">

        {{-- Header Modal (sticky) --}}
        <div class="flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Arsip Dokumen Sah RKBMD</h3>
                    <p class="text-[11px] text-slate-400" id="totalArsipLabel">Menampilkan {{ $arsipList->count() }} berkas dokumen pengesahan</p>
                </div>
            </div>
            <button type="button" data-close class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" title="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Toolbar Filter & Search (sticky di atas list) --}}
        <div class="shrink-0 border-b border-slate-100 bg-slate-50/70 px-6 py-3">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <div class="relative">
                    <input type="text" id="searchArsipInput" onkeyup="filterArsipItems()"
                           placeholder="Cari nama SK / dokumen..."
                           class="w-full rounded-xl border border-slate-200 bg-white py-1.5 pr-2.5 pl-8 text-xs text-slate-700 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <svg class="absolute top-2.5 left-2.5 h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>

                <div>
                    <select id="filterTahunArsip" onchange="filterArsipItems()"
                            class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        <option value="">Semua Tahun</option>
                        @foreach ($arsipTahunList as $tahunArsip)
                            <option value="{{ $tahunArsip }}">TA {{ $tahunArsip }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <select id="filterBidangArsip" onchange="filterArsipItems()"
                            class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        <option value="">Semua Bidang / Unit</option>
                        @foreach ($bidangOptions as $opt)
                            <option value="{{ $opt->nama_skpd }}">{{ $opt->nama_skpd }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Daftar Berkas (scrollable) --}}
        <div class="flex-1 space-y-2.5 overflow-y-auto px-6 py-4 text-xs" id="arsipListContainer">
            @forelse ($arsipList as $arsip)
                @php
                    $fileUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($arsip->file_path);
                @endphp
                <div class="arsip-item flex flex-col justify-between gap-3 rounded-2xl border border-slate-100 bg-white p-3.5 shadow-2xs transition hover:border-slate-200 sm:flex-row sm:items-center"
                     data-title="{{ strtolower(($arsip->nama_dokumen ?: 'Dokumen RKBMD')) }}"
                     data-tahun="{{ $arsip->tahun_anggaran }}"
                     data-bidang="{{ strtolower($arsip->skpd?->nama_skpd ?? '-') }}">
                    <div class="min-w-0">
                        <h4 class="truncate font-bold text-slate-800">{{ $arsip->nama_dokumen ?: 'Dokumen RKBMD' }}</h4>
                        <p class="mt-0.5 text-[11px] text-slate-400">
                            {{ $arsip->skpd?->nama_skpd ?? '-' }} • TA {{ $arsip->tahun_anggaran }}
                            @if ($arsip->tanggal_pengesahan)
                                • Sah {{ $arsip->tanggal_pengesahan->format('d M Y') }}
                            @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5">
                        <a href="{{ $fileUrl }}" target="_blank" rel="noopener" title="Pratinjau PDF"
                           class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50">
                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM21 12c0 4.42-4.03 8-9 8s-9-3.58-9-8 4.03-8 9-8 9 3.58 9 8z"/></svg>
                            <span>Pratinjau</span>
                        </a>
                        <a href="{{ $fileUrl }}" download title="Unduh PDF"
                           class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-1.5 text-[11px] font-bold text-white transition hover:bg-slate-800">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Unduh</span>
                        </a>
                        @if ($isAdmin)
                            <form method="POST" action="{{ route('rkbmd.arsip.destroy', $arsip->id_arsip) }}"
                                  onsubmit="return confirm('Hapus arsip dokumen ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Hapus"
                                        class="inline-flex items-center rounded-lg p-1.5 text-slate-300 transition hover:bg-rose-50 hover:text-rose-500">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-slate-400">Belum ada berkas arsip yang tersimpan.</div>
            @endforelse

            <div id="noMatchArsip" class="hidden py-8 text-center text-slate-400">Tidak ada arsip yang sesuai dengan filter.</div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        // Filter & pencarian live arsip dokumen (tanpa reload).
        function filterArsipItems() {
            const searchVal = document.getElementById('searchArsipInput').value.toLowerCase();
            const tahunVal = document.getElementById('filterTahunArsip').value;
            const bidangVal = document.getElementById('filterBidangArsip').value.toLowerCase();

            const items = document.querySelectorAll('.arsip-item');
            let visibleCount = 0;

            items.forEach(function (item) {
                const itemTitle = item.getAttribute('data-title') || '';
                const itemTahun = item.getAttribute('data-tahun') || '';
                const itemBidang = item.getAttribute('data-bidang') || '';

                const matchSearch = !searchVal || itemTitle.includes(searchVal);
                const matchTahun = !tahunVal || itemTahun === tahunVal;
                const matchBidang = !bidangVal || itemBidang.includes(bidangVal);

                if (matchSearch && matchTahun && matchBidang) {
                    item.classList.remove('hidden');
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                }
            });

            const noMatch = document.getElementById('noMatchArsip');
            if (noMatch) {
                noMatch.classList.toggle('hidden', visibleCount > 0 || items.length === 0);
            }

            const label = document.getElementById('totalArsipLabel');
            if (label) {
                label.textContent = items.length === 0
                    ? 'Belum ada berkas dokumen pengesahan'
                    : 'Menampilkan ' + visibleCount + ' dari ' + items.length + ' berkas dokumen pengesahan';
            }
        }
    </script>
@endpush