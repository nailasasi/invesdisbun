{{-- MODAL PAJAK & PRATINJAU BUKTI PAJAK --}}
{{-- Modal Kelola / Tambah / Edit Pajak Kendaraan --}}
<div id="pajak-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-modal-close></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
            <h3 id="pajak-modal-title" class="text-lg font-semibold text-slate-900">Kelola Pajak Kendaraan</h3>
            <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form id="pajak-form" method="POST" class="space-y-4 px-6 py-6">
            @csrf
            <input type="hidden" name="_method" id="pajak-method" value="">
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="pajak-jenis" class="block text-sm font-medium text-slate-700">Jenis <span class="text-red-500">*</span></label>
                    <input type="text" id="pajak-jenis" name="jenis_pajak" required value="PKB" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    <p class="text-xs text-slate-500">Contoh: PKB, SWDKLLJ, Pajak Bumi.</p>
                </div>
                <div class="space-y-1.5">
                    <label for="pajak-tahun" class="block text-sm font-medium text-slate-700">Tahun</label>
                    <input type="number" id="pajak-tahun" name="tahun" min="2000" max="2100" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" value="{{ date('Y') }}">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="pajak-bayar" class="block text-sm font-medium text-slate-700">Tanggal Bayar</label>
                    <input type="date" id="pajak-bayar" name="tanggal_bayar" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                </div>
                <div class="space-y-1.5">
                    <label for="pajak-berakhir" class="block text-sm font-medium text-slate-700">Tanggal Jatuh Tempo <span class="text-red-500">*</span></label>
                    <input type="date" id="pajak-berakhir" name="tanggal_berakhir" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    <p class="text-xs text-slate-500">Masukkan tanggal jatuh tempo sesuai bukti pajak.</p>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_berakhir"></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="pajak-nominal" class="block text-sm font-medium text-slate-700">Nominal Pajak (Rp)</label>
                    <input type="number" id="pajak-nominal" name="nominal" min="0" step="0.01" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 1500000">
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nominal"></p>
                </div>
                <div class="space-y-1.5">
                    <label for="pajak-total" class="block text-sm font-medium text-slate-700">Total Pajak (Rp)</label>
                    <input type="number" id="pajak-total" name="total_pajak" min="0" step="0.01" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 2100000">
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="total_pajak"></p>
                </div>
            </div>
            <div id="pajak-5thn-block" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" id="pajak-5tahunan-ganti" name="pajak_5_tahunan" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        Pajak 5 Tahunan / Ganti Plat
                    </label>
                </div>
                <div id="plat-baru-wrap" class="space-y-1.5 hidden">
                    <label for="plat-baru" class="block text-sm font-medium text-slate-700">Nomor Plat Baru <span class="text-red-500">*</span></label>
                    <input type="text" id="plat-baru" name="nomor_plat_baru" maxlength="20" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: B 1234 ABC">
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_plat_baru"></p>
                </div>
            </div>
            <div class="space-y-1.5">
                <label for="pajak-bukti" class="block text-sm font-medium text-slate-700">Bukti Pembayaran Pajak <span id="pajak-bukti-wajib" class="text-red-500">*</span></label>
                <input type="file" id="pajak-bukti" name="bukti_pajak" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm file:mr-3 file:rounded-lg file:border-0 file:bg-disbun-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-disbun-700 hover:file:bg-disbun-100 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                <p id="pajak-bukti-hint" class="text-xs text-slate-500">Wajib diunggah. Format JPG, PNG, PDF, DOC/DOCX, atau XLS/XLSX. Maksimal 5 MB.</p>
                <p id="pajak-bukti-existing" class="hidden text-xs font-medium text-slate-600"></p>
                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="bukti_pajak"></p>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="submit" class="rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-disbun-800">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL PRATINJAU BUKTI PAJAK --}}
<div id="bukti-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-modal-close></div>
    <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-slate-900">Pratinjau Bukti Pajak</h3>
                <p id="bukti-nama" class="mt-0.5 truncate text-xs text-slate-500"></p>
            </div>
            <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50 px-5 py-4">
            <div id="bukti-image-wrap" class="hidden">
                <img id="bukti-image" src="" alt="Pratinjau bukti pajak" class="mx-auto max-h-[70vh] w-auto max-w-full rounded-xl border border-slate-200 bg-white object-contain">
            </div>
            <div id="bukti-pdf-wrap" class="hidden">
                <iframe id="bukti-pdf" src="" title="Pratinjau PDF bukti pajak" class="h-[70vh] w-full rounded-xl border border-slate-200 bg-white"></iframe>
                <p class="mt-2 text-center text-xs text-slate-500">Jika pratinjau tidak tampil, gunakan tombol Download di bawah.</p>
            </div>
            <div id="bukti-fallback-wrap" class="hidden py-10 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                <p id="bukti-fallback-nama" class="mt-3 text-sm font-medium text-slate-700"></p>
                <p class="mt-1 text-xs text-slate-500">Pratinjau tidak didukung untuk jenis berkas ini. Silakan unduh untuk membuka.</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Tutup</button>
            <a id="bukti-download" href="#" class="inline-flex items-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-disbun-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                Download
            </a>
        </div>
    </div>
</div>

