@if ($bolehCetakDokumen)
{{-- Modal Cakupan Cetak Label Ruangan (aset resmi dan/atau inventaris lokal).
     Dapat diakses oleh Admin Aset dan Admin UPT. --}}
<div id="label-scope-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-label-scope-close></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Cetak Label Ruangan</h3>
                <p class="mt-0.5 text-sm text-slate-500">Pilih cakupan label yang akan dicetak.</p>
            </div>
            <button type="button" data-label-scope-close class="flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="label-scope-form" method="GET" action="{{ route('aset-ruangan.label.download', $ruangan->id_ruangan) }}" target="_blank">
            <div class="space-y-3 px-6 py-6">
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                    <input type="checkbox" id="label-cetak-aset" name="aset" value="1" checked
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-disbun-700 focus:ring-disbun-400">
                    <span>
                        <span class="block text-sm font-medium text-slate-800">Aset resmi (tanpa pemegang)</span>
                        <span class="block text-xs text-slate-400">Barang melekat milik ruangan. Kendaraan & aset yang sedang dipegang pegawai tidak termasuk.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                    <input type="checkbox" id="label-cetak-inventaris" name="inventaris" value="1"
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-disbun-700 focus:ring-disbun-400">
                    <span>
                        <span class="block text-sm font-medium text-slate-800">Inventaris lokal</span>
                        <span class="block text-xs text-slate-400">Barang non-aset yang terdaftar di ruangan ini.</span>
                    </span>
                </label>
                <p id="label-scope-error" class="hidden text-xs font-medium text-red-600">Pilih minimal satu jenis label.</p>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <button type="button" data-label-scope-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">Cetak</button>
            </div>
        </form>
    </div>
</div>
@endif

