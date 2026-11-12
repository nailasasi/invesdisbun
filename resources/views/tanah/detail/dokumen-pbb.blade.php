{{-- Tab 3: Dokumen PBB --}}
<div data-tab-panel id="tab-pbb" class="mt-4 hidden">
    <x-card :padding="false">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900">Dokumen PBB</h3>
                <p class="text-xs text-slate-400">Arsip file SPPT / PBB yang terunggah untuk aset ini</p>
            </div>
            @if ($isAdmin)
                <button type="button" data-open-pbb-modal
                        class="inline-flex items-center gap-1.5 rounded-xl bg-disbun-700 px-3.5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-disbun-800">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Tambah Dokumen PBB</span>
                </button>
            @endif
        </div>

        @if ($tanah->dokumenPbb->count())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tahun PBB</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal Upload</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Diunggah Oleh</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">File</th>
                            @if ($isAdmin)
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($tanah->dokumenPbb as $pbb)
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $pbb->tahun_pbb ?: '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pbb->tanggal_upload?->format('d M Y H:i') ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $namaUser($pbb->uploader) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    @if ($pbb->file_pbb)
                                        <div class="flex items-center gap-1.5">
                                            <a href="{{ Storage::url($pbb->file_pbb) }}" target="_blank" rel="noopener noreferrer"
                                               class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">
                                                <svg class="mr-1 h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                Lihat
                                            </a>
                                            <a href="{{ Storage::url($pbb->file_pbb) }}" download
                                               class="inline-flex items-center rounded-lg bg-disbun-50 px-2.5 py-1.5 text-xs font-medium text-disbun-800 transition hover:bg-disbun-100">
                                                <svg class="mr-1 h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                Unduh
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                @if ($isAdmin)
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <button type="button"
                                                data-open-hapus-pbb
                                                data-action="{{ route('tanah.dokumen.destroy', $pbb) }}"
                                                data-tahun="{{ $pbb->tahun_pbb ?: '-' }}"
                                                class="inline-flex items-center rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100">
                                            Hapus
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-6">
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-6 text-center">
                    <p class="text-sm font-medium text-slate-700">Belum ada dokumen PBB.</p>
                    <p class="mt-1 text-sm text-slate-500">Unggah file SPPT / PBB untuk melengkapi arsip aset ini.</p>
                </div>
            </div>
        @endif
    </x-card>
</div>

