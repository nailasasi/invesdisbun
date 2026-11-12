@if ($isAdminAset || $bolehKelolaRuangan)
{{-- Modal Mutasi Ruangan (aset melekat) --}}
<div id="mutasi-ruangan-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-mutasi-ruangan-close></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-start gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-100">
                <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="text-lg font-semibold text-slate-900">Mutasi Ruangan</h3>
                <p class="mt-1 text-sm text-slate-500">Pindahkan aset ini ke ruangan lain. Penempatan baru langsung berstatus <span class="font-medium text-emerald-700">Sudah Difinalisasi</span>.</p>
            </div>
            <button type="button" data-mutasi-ruangan-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="mutasi-ruangan-form" method="POST" autocomplete="off" class="mt-5 space-y-4">
            @csrf
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                <p><span class="font-semibold text-slate-700">Aset:</span> <span id="mutasi-ruangan-aset"></span></p>
                <p class="mt-1"><span class="font-semibold text-slate-700">KIB:</span> <span id="mutasi-ruangan-kib" class="font-mono"></span></p>
                <p class="mt-1"><span class="font-semibold text-slate-700">Ruangan Asal:</span> {{ $ruangan->nama_ruangan }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="mutasi-ruangan-tujuan" class="block text-sm font-medium text-slate-700">Ruangan Tujuan <span class="text-red-500">*</span></label>
                <select id="mutasi-ruangan-tujuan" name="id_ruangan_tujuan" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-400 transition">
                    <option value="" selected disabled>-- Pilih Ruangan Tujuan --</option>
                    @foreach ($ruanganTujuan as $r)
                        <option value="{{ $r->id_ruangan }}">{{ $r->nama_ruangan }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500">Hanya ruangan aktif yang tercantum.</p>
                <p class="text-xs text-emerald-700">Penempatan baru langsung berstatus Sudah Difinalisasi — tidak perlu difinalisasi ulang.</p>
            </div>

            <div class="space-y-1.5">
                <label for="mutasi-ruangan-keterangan" class="block text-sm font-medium text-slate-700">Keterangan</label>
                <textarea id="mutasi-ruangan-keterangan" name="keterangan" rows="2" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-400 transition" placeholder="contoh: penataan ulang"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <button type="button" data-mutasi-ruangan-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                <button type="submit" id="btn-mutasi-ruangan-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">Pindahkan</button>
            </div>
        </form>
    </div>
</div>
@endif

