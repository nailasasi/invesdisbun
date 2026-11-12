{{-- Modal Tambah & Hapus Dokumen PBB --}}
@if ($isAdmin)
    {{-- MODAL TAMBAH DOKUMEN PBB --}}
    <div id="dokumen-pbb-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-modal-close></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Tambah Dokumen PBB</h3>
                    <p class="text-xs text-slate-400">KIB {{ $tanah->kib ?: '-' }}</p>
                </div>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form action="{{ route('tanah.dokumen.store', $tanah) }}" method="POST" enctype="multipart/form-data" class="space-y-4 px-6 py-6">
                @csrf
                <div class="space-y-1.5">
                    <label for="pbb-tahun" class="block text-sm font-medium text-slate-700">Tahun PBB</label>
                    <input type="number" id="pbb-tahun" name="tahun_pbb" min="1900" max="2100" placeholder="contoh: 2025"
                           class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                </div>
                <div class="space-y-1.5">
                    <label for="pbb-file" class="block text-sm font-medium text-slate-700">File PBB <span class="text-red-500">*</span></label>
                    <input type="file" id="pbb-file" name="file_pbb" accept=".pdf,image/jpeg,image/png" required
                           class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400 file:mr-3 file:rounded-lg file:border-0 file:bg-disbun-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-disbun-800 hover:file:bg-disbun-100">
                    <p class="text-xs text-slate-400">Format: PDF/JPG/PNG, maksimal 5MB.</p>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                    <button type="submit" class="rounded-xl bg-disbun-700 px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-disbun-800">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL KONFIRMASI HAPUS DOKUMEN PBB (verifikasi password server-side + webcam) --}}
    @php $gagalHapusPbbId = session('hapus_pbb_gagal_id'); @endphp
    <div id="hapus-pbb-modal"
         data-auto-open="{{ ($errors->has('password_otorisasi') || $errors->has('foto_bukti_otentikasi')) && $gagalHapusPbbId ? '1' : '0' }}"
         class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-modal-close></div>
        <div class="relative my-auto flex w-full max-w-md sm:max-w-lg max-h-[90vh] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            {{-- Header (Fixed) --}}
            <div class="flex shrink-0 items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Konfirmasi Hapus Dokumen PBB</h3>
                    <p class="text-xs text-slate-400">Tahun <span data-hapus-pbb-tahun>—</span></p>
                </div>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Form dengan Area Konten yang Dapat Di-scroll --}}
            <form id="hapus-pbb-form" method="POST"
                  action="{{ $gagalHapusPbbId ? route('tanah.dokumen.destroy', $gagalHapusPbbId) : '' }}"
                  class="flex flex-1 flex-col overflow-hidden">
                @csrf
                @method('DELETE')

                {{-- Scrollable Content Body --}}
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                    <p class="text-sm text-slate-600">
                        File dokumen PBB akan dihapus dari server secara permanen. Tindakan ini memerlukan otorisasi password dan foto konfirmasi webcam.
                    </p>
                    <div class="space-y-1.5">
                        <label for="hapus-pbb-password-otorisasi" class="block text-sm font-medium text-slate-700">
                            Password Otorisasi (Wajib) <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="hapus-pbb-password-otorisasi" name="password_otorisasi" required
                               autocomplete="current-password" placeholder="Masukkan ulang password akun Anda"
                               class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                        @if ($gagalHapusPbbId && $errors->has('password_otorisasi'))
                            <p class="text-xs font-semibold text-red-600">{{ $errors->first('password_otorisasi') }}</p>
                        @endif

                        {{-- Jam real-time + kamera bukti otorisasi (wajib sebelum hapus) --}}
                        <x-otorisasi-bukti />
                        @if ($gagalHapusPbbId && $errors->has('foto_bukti_otentikasi'))
                            <p class="text-xs font-semibold text-red-600">{{ $errors->first('foto_bukti_otentikasi') }}</p>
                        @endif
                    </div>
                </div>

                {{-- Footer (Fixed & Aksesibel) --}}
                <div class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-3">
                    <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                    <button type="submit" class="rounded-xl bg-red-600 px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-red-700">Hapus</button>
                </div>
            </form>
        </div>
    </div>
@endif

