{{-- Modal Penempatan Awal --}}
<div id="penempatan-awal-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
    <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Penempatan Awal</h3>
                <p class="mt-0.5 text-xs text-slate-500">Pilih pemegang untuk aset ini.</p>
            </div>
            <button type="button" data-close-pa class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="form-penempatan-awal" method="POST">
            @csrf
            <input type="hidden" name="mode" value="pegawai">
            <div class="px-5 py-4 space-y-3">
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Pemegang</label>
                    <input type="text" id="pa-pegawai-search" placeholder="Cari nama pegawai..." autocomplete="off" class="block h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400">
                    <select id="pa-pegawai" name="id_pegawai" size="6" class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400" data-searchable="true">
                        @foreach ($pemegangOptions as $p)
                            <option value="{{ $p->id_pegawai }}" data-ruangan="{{ $p->id_ruangan ?? '' }}" data-ruangan-nama="{{ $p->ruangan?->nama_ruangan ?? '' }}" data-lokasi-nama="{{ $p->ruangan?->lokasi?->nama_lokasi ?? '' }}" data-skpd-nama="{{ $p->skpd?->nama_skpd ?? ($p->ruangan?->skpd?->nama_skpd ?? '') }}">{{ $p->nama_pegawai }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500">Pilih satu pegawai. Ruangan mengikuti ruang kerja pegawai.</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">Ruangan (otomatis)</label>
                        <input id="pa-ruangan-info" type="text" readonly class="block h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-sm text-slate-600" value="">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">Lokasi Fisik (otomatis)</label>
                        <input id="pa-lokasi-info" type="text" readonly class="block h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-sm text-slate-600" value="">
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">SKPD (informasi)</label>
                    <input id="pa-skpd-info" type="text" readonly class="block h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-sm text-slate-600" value="">
                </div>
                <input type="hidden" id="pa-ruangan" name="id_ruangan" value="">
                <div class="rounded-lg bg-amber-50 px-2.5 py-2 text-xs text-amber-700">
                    Penempatan awal tidak membuat Mutasi, BAST, atau SPPBI.
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                <button type="button" data-close-pa class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                <button type="submit" class="inline-flex items-center rounded-lg bg-disbun-700 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Penempatan Awal Ruangan (tombol + pada kolom Lokasi) --}}
<div id="penempatan-ruangan-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
    <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Penempatan Awal Ruangan</h3>
                <p class="mt-0.5 text-xs text-slate-500">Pilih ruangan untuk aset ini.</p>
            </div>
            <button type="button" data-close-pr class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="form-penempatan-ruangan" method="POST">
            @csrf
            <input type="hidden" name="mode" value="ruangan">
            <div class="px-5 py-4 space-y-3">
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Ruangan</label>
                    <input type="text" id="pr-ruangan-search" placeholder="Cari nama ruangan..." autocomplete="off" class="block h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400">
                    <select id="pr-ruangan" name="id_ruangan" size="6" class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400">
                        <option value="" disabled selected>-- Pilih Ruangan --</option>
                        @foreach ($ruanganOptions ?? [] as $r)
                            <option value="{{ $r->id_ruangan }}" data-ruangan-nama="{{ $r->nama_ruangan }}" data-lokasi-nama="{{ $r->lokasi?->nama_lokasi ?? '' }}" data-skpd-nama="{{ $r->skpd?->nama_skpd ?? '' }}">{{ $r->nama_ruangan }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500">Pilih satu ruangan. Lokasi fisik mengikuti data ruangan.</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">Ruangan Terpilih</label>
                        <input id="pr-ruangan-info" type="text" readonly class="block h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-sm text-slate-600" value="">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">Lokasi Fisik (otomatis)</label>
                        <input id="pr-lokasi-info" type="text" readonly class="block h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-sm text-slate-600" value="">
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">SKPD (informasi)</label>
                    <input id="pr-skpd-info" type="text" readonly class="block h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-sm text-slate-600" value="">
                </div>
                <div class="rounded-lg bg-amber-50 px-2.5 py-2 text-xs text-amber-700">
                    Penempatan awal tidak membuat Mutasi, BAST, atau SPPBI.
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                <button type="button" data-close-pr class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                <button type="submit" class="inline-flex items-center rounded-lg bg-disbun-700 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Edit Penempatan (Edit Pemegang / Edit Ruangan).
     Memperbaiki data penempatan: TIDAK membuat Mutasi, BAST, atau SPPBI. --}}
<div id="edit-penempatan-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
    <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-3">
            <div>
                <h3 id="ep-title" class="text-base font-semibold text-slate-900">Edit Penempatan</h3>
                <p id="ep-subtitle" class="mt-0.5 text-xs text-slate-500">Perbaiki data penempatan aset ini.</p>
            </div>
            <button type="button" data-close-ep class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="form-edit-penempatan" method="POST">
            @csrf
            <input type="hidden" id="ep-mode" name="mode" value="pegawai">
            <div class="px-5 py-4 space-y-3">
                <div id="ep-field-pegawai" class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Pemegang</label>
                    <input type="text" id="ep-pegawai-search" placeholder="Cari nama pegawai..." autocomplete="off" class="block h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400">
                    <select id="ep-pegawai" name="id_pegawai" size="6" class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400">
                        @foreach ($pemegangOptions as $p)
                            <option value="{{ $p->id_pegawai }}" data-ruangan-nama="{{ $p->ruangan?->nama_ruangan ?? '' }}" data-lokasi-nama="{{ $p->ruangan?->lokasi?->nama_lokasi ?? '' }}" data-skpd-nama="{{ $p->skpd?->nama_skpd ?? ($p->ruangan?->skpd?->nama_skpd ?? '') }}">{{ $p->nama_pegawai }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500">Pilih satu pegawai. Ruangan mengikuti ruang kerja pegawai.</p>
                </div>
                <div id="ep-field-ruangan" class="space-y-1.5 hidden">
                    <label class="block text-sm font-medium text-slate-700">Ruangan</label>
                    <input type="text" id="ep-ruangan-search" placeholder="Cari nama ruangan..." autocomplete="off" class="block h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400">
                    <select id="ep-ruangan" name="id_ruangan" size="6" disabled class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400">
                        <option value="" disabled selected>-- Pilih Ruangan --</option>
                        @foreach ($ruanganOptions ?? [] as $r)
                            <option value="{{ $r->id_ruangan }}" data-ruangan-nama="{{ $r->nama_ruangan }}" data-lokasi-nama="{{ $r->lokasi?->nama_lokasi ?? '' }}" data-skpd-nama="{{ $r->skpd?->nama_skpd ?? '' }}">{{ $r->nama_ruangan }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500">Pilih satu ruangan. Lokasi fisik mengikuti data ruangan.</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">Ruangan (otomatis)</label>
                        <input id="ep-ruangan-info" type="text" readonly class="block h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-sm text-slate-600" value="">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">Lokasi Fisik (otomatis)</label>
                        <input id="ep-lokasi-info" type="text" readonly class="block h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-sm text-slate-600" value="">
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">SKPD (informasi)</label>
                    <input id="ep-skpd-info" type="text" readonly class="block h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-sm text-slate-600" value="">
                </div>
                <div class="rounded-lg bg-amber-50 px-2.5 py-2 text-xs text-amber-700">
                    Edit penempatan hanya memperbaiki data penempatan. Tidak membuat Mutasi, BAST, atau SPPBI.
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                <button type="button" data-close-ep class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                <button type="submit" class="inline-flex items-center rounded-lg bg-disbun-700 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">Simpan</button>
            </div>
        </form>
    </div>
</div>

