{{-- MODAL MUTASI PEMEGANG (body level, luar kontainer overflow main) --}}
<div id="mutasi-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-mutasi-close></div>
    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Mutasi Pemegang Kendaraan</h3>
                <p class="text-sm text-slate-500">Serah terima kendaraan ke pegawai lain</p>
            </div>
            <button type="button" data-mutasi-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="mutasi-form" method="POST" class="space-y-4 px-6 py-6">
            @csrf
            <input type="hidden" id="mutasi-kendaraan-id" name="id_kendaraan" value="">
            <input type="hidden" id="mutasi-pegawai-lama-id" name="id_pegawai_lama" value="">
            <div class="space-y-1.5">
                <label class="block text-sm font-medium text-slate-700">Kendaraan</label>
                <div id="mutasi-kendaraan-name" class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm font-medium text-slate-800"></div>
            </div>
            <div class="space-y-1.5">
                <label class="block text-sm font-medium text-slate-700">Pemegang Lama</label>
                <div class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-600">
                    <span id="mutasi-pemegang-lama" class="font-medium text-slate-800"></span>
                    <span class="text-[11px] italic text-slate-400">Diubah melalui formulir ini</span>
                </div>
            </div>
            <div class="space-y-1.5">
                <label for="mutasi-pegawai-baru" class="block text-sm font-medium text-slate-700">Pemegang Baru <span class="text-red-500">*</span></label>
                <select id="mutasi-pegawai-baru" name="id_pegawai_baru" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 transition">
                    <option value="">-- Pilih Pegawai --</option>
                    @foreach ($pegawais as $p)
                        <option value="{{ $p->id_pegawai }}" @selected(old('id_pegawai_baru') == $p->id_pegawai)>{{ $p->nama_pegawai }}@if($p->nip) ({{ $p->nip }})@endif</option>
                    @endforeach
                </select>
                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_pegawai_baru"></p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <label for="mutasi-tanggal" class="block text-sm font-medium text-slate-700">Tanggal Mutasi</label>
                    <input type="date" id="mutasi-tanggal" name="tanggal_mutasi" value="{{ old('tanggal_mutasi', now()->format('Y-m-d')) }}" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 transition">
                </div>
                <div class="space-y-1.5">
                    <label for="mutasi-nomor-surat" class="block text-sm font-medium text-slate-700">Nomor Surat <span class="text-[11px] font-normal text-slate-400">(opsional)</span></label>
                    <input type="text" id="mutasi-nomor-surat" name="nomor_surat" maxlength="150" value="{{ old('nomor_surat') }}" placeholder="cth. /442/BKD/2026" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 transition">
                </div>
            </div>
            <div class="space-y-1.5">
                <label for="mutasi-keterangan" class="block text-sm font-medium text-slate-700">Alasan / Catatan</label>
                <textarea id="mutasi-keterangan" name="keterangan" rows="3" maxlength="1000" placeholder="cth. Serah terima karena rotasi jabatan" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 transition">{{ old('keterangan') }}</textarea>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" data-mutasi-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4"/></svg>
                    Simpan Mutasi
                </button>
            </div>
        </form>
    </div>
</div>

