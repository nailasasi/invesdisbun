{{-- Modal Penempatan Awal Kendaraan --}}
<div id="penempatan-awal-kendaraan-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
    <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Penempatan Awal Kendaraan</h3>
                <p class="mt-0.5 text-xs text-slate-500">Pilih pemegang untuk kendaraan ini.</p>
            </div>
            <button type="button" data-close-pa-kendaraan class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="form-penempatan-awal-kendaraan" method="POST">
            @csrf
            <input type="hidden" name="mode" value="pegawai">
            <div class="px-5 py-4 space-y-3">
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Pemegang</label>
                    <input type="text" id="pa-kendaraan-pegawai-search" placeholder="Cari nama pegawai..." autocomplete="off" class="block h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400">
                    <select id="pa-kendaraan-pegawai" name="id_pegawai" size="6" class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400" data-searchable="true">
                        @foreach ($pegawais as $p)
                            <option value="{{ $p->id_pegawai }}">{{ $p->nama_pegawai }}@if($p->nip) ({{ $p->nip }})@endif</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500">Pilih satu pegawai. Ruangan mengikuti ruang kerja pegawai.</p>
                </div>
                <div class="rounded-lg bg-amber-50 px-2.5 py-2 text-xs text-amber-700">
                    Penempatan awal tidak membuat Mutasi, BAST, atau SPPKD baru.
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                <button type="button" data-close-pa-kendaraan class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                <button type="submit" class="inline-flex items-center rounded-lg bg-disbun-700 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Edit Pemegang Kendaraan (penempatan belum difinalisasi) --}}
<div id="edit-pemegang-kendaraan-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
    <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Edit Pemegang Kendaraan</h3>
                <p class="mt-0.5 text-xs text-slate-500">Perbaiki pemegang penempatan kendaraan ini. Tidak membuat Mutasi, BAST, atau SPPKD.</p>
            </div>
            <button type="button" data-close-epkend class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="form-edit-pemegang-kendaraan" method="POST">
            @csrf
            <div class="px-5 py-4 space-y-3">
                <div id="epk-kendaraan-name" class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm font-medium text-slate-800"></div>
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Pemegang</label>
                    <input type="text" id="epk-pegawai-search" placeholder="Cari nama pegawai..." autocomplete="off" class="block h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400">
                    <select id="epk-pegawai" name="id_pegawai" size="6" class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400" data-searchable="true">
                        @foreach ($pegawais as $p)
                            <option value="{{ $p->id_pegawai }}">{{ $p->nama_pegawai }}@if($p->nip) ({{ $p->nip }})@endif</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500">Pilih satu pegawai. Ruangan mengikuti ruang kerja pegawai.</p>
                </div>
                <div class="rounded-lg bg-amber-50 px-2.5 py-2 text-xs text-amber-700">
                    Edit hanya bisa dilakukan sebelum finalisasi. Setelah finalisasi, gunakan Mutasi Pemegang.
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                <button type="button" data-close-epkend class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                <button type="submit" class="inline-flex items-center rounded-lg bg-disbun-700 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">Simpan</button>
            </div>
        </form>
    </div>
</div>

