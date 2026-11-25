@if ($isAdminAset)
{{-- Modal Pasang Aset ke Ruangan.
     PENEMPATAN AWAL: memasang master aset yang sudah ada dan belum
     berpemegang. Tidak membuat record `aset` baru, tidak membuat
     pemegang, dan tidak mencatat mutasi. --}}
<div id="pasang-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-pasang-close></div>
    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Pasang Aset</h3>
                <p class="mt-0.5 text-sm text-slate-500">Pilih master aset tanpa pemegang untuk {{ $ruangan->nama_ruangan }}.</p>
            </div>
            <button type="button" data-pasang-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="pasang-form" method="POST" autocomplete="off">
            @csrf
            <div class="max-h-[70vh] space-y-4 overflow-y-auto px-6 py-6">
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Jenis Barang <span class="text-red-500">*</span></label>
                    <input type="text" id="field-jenis-search" placeholder="Cari nama barang..." autocomplete="off" class="block h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400">
                    <select id="field-jenis-select" name="id_barang" size="6" class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400" data-searchable="true">
                        <option value="" disabled selected class="text-slate-400">Memuat data barang...</option>
                    </select>
                    <p class="text-xs text-slate-500">Pilih jenis barang untuk memasang beberapa aset sekaligus.</p>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_barang"></p>
                </div>

                <div class="space-y-1.5">
                    <label for="field-jumlah" class="block text-sm font-medium text-slate-700">Jumlah <span class="text-red-500">*</span></label>
                    <input type="number" id="field-jumlah" name="jumlah" inputmode="numeric" min="1" max="100" required
                           class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    <p class="text-xs text-slate-500">Masukkan jumlah aset yang ingin dipasang.</p>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="jumlah"></p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                    <p class="font-semibold text-slate-700">Tersedia <span id="pasang-tersedia" class="font-bold text-disbun-800">0</span> aset sejenis.</p>
                    <p class="mt-1">Sistem memilih dari nomor KIB/nomor kartu barang <span class="font-semibold text-slate-800">terendah ke tertinggi</span>.</p>
                    <p class="mt-1">Hanya aset tanpa pemegang aktif dan tanpa penempatan aktif yang dipilih.</p>
                    <p class="mt-1">Penempatan baru akan berstatus <span class="font-semibold text-amber-700">Belum Difinalisasi</span>.</p>
                </div>

                <div id="pasang-ringkasan" class="hidden space-y-1 rounded-xl border border-slate-200 bg-white p-4 text-xs text-slate-600">
                    <p class="font-semibold text-slate-700">Ringkasan</p>
                    <p class="mt-1">Jenis Barang: <span id="ringkasan-jenis" class="font-semibold text-slate-800">-</span></p>
                    <p>Jumlah: <span id="ringkasan-jumlah" class="font-semibold text-slate-800">0</span> aset</p>
                    <p>Ruangan Tujuan: <span class="font-semibold text-slate-800">{{ $ruangan->nama_ruangan }}</span></p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <button type="button" data-pasang-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="submit" id="btn-pasang-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">
                    Pasang
                </button>
            </div>
        </form>
    </div>
</div>
@endif

