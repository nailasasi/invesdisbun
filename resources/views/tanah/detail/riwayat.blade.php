{{-- Tab 4: Riwayat Perubahan & Aktivitas --}}
<div data-tab-panel id="tab-history" class="mt-4 hidden">
    <div class="space-y-4 rounded-3xl border border-slate-100 bg-white p-6 shadow-2xs">
        <div class="border-b border-slate-100 pb-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                {{ $tanah->histories->count() ? 'Riwayat Aktivitas & Perubahan' : 'Riwayat Perubahan' }}
            </h3>
            <p class="text-[11px] text-slate-500">Catatan pengguna yang menambah atau memperbarui data tanah</p>
        </div>

        <div class="divide-y divide-slate-100 text-xs">
            @forelse ($tanah->histories->sortByDesc('created_at') as $history)
                @php
                    $isCreate = strtoupper((string) $history->aksi) === 'CREATE';
                    $user = $history->user;
                    $userName = $history->nama_aktor_tampil;
                    $userRole = $user?->role?->nama_role ?? $user?->pegawai?->jabatan ?? 'Sistem';

                    // Payload untuk modal "Lihat Bukti". Nilai dibangun
                    // di server; foto diakses lewat route terautentikasi.
                    $payloadBukti = [
                        'foto' => $history->foto_bukti
                            ? route('bukti-otorisasi.show', $history->id_history)
                            : null,
                        'aktor' => $userName,
                        'peran' => $userRole,
                        'aktivitas' => $history->judul_aktivitas,
                        'objek' => strtolower((string) $history->objek) === 'retribusi' ? 'Retribusi Tanah' : (strtolower((string) $history->objek) === 'dokumen_pbb' ? 'Dokumen PBB' : 'Tanah'),
                        'waktu' => \Carbon\Carbon::parse($history->created_at)->format('d M Y, H:i:s').' WIB',
                        'perubahan' => array_values($history->perubahan_tampil),
                    ];
                @endphp
                <div class="flex items-start justify-between gap-4 py-3.5">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-xl {{ $isCreate ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600' }}">
                            @if ($isCreate)
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            @else
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            @endif
                        </div>

                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-block rounded-md px-2 py-0.5 text-[10px] font-bold uppercase {{ $isCreate ? 'bg-disbun-100 text-disbun-800' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $history->judul_aktivitas }}
                                </span>
                                <span class="font-bold text-slate-800">{{ $userName }}</span>
                                <span class="text-[11px] text-slate-400">({{ $userRole }})</span>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500">
                                {{ strtolower((string) $history->objek) === 'retribusi' ? 'Perubahan data retribusi tanah' : 'Perubahan informasi data tanah' }}
                            </p>

                            @if ($history->foto_bukti)
                                <button type="button"
                                        class="mt-1.5 inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50"
                                        data-buka-bukti
                                        data-bukti="{{ json_encode($payloadBukti, JSON_HEX_TAG | JSON_HEX_APOS) }}">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Lihat Bukti
                                </button>
                            @else
                                <p class="mt-1.5 text-[11px] italic text-slate-400">Tidak ada foto bukti untuk aktivitas ini</p>
                            @endif
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <span class="block font-mono text-[11px] text-slate-600">
                            {{ \Carbon\Carbon::parse($history->created_at)->format('d M Y, H:i') }} WIB
                        </span>
                        <span class="text-[10px] text-slate-400">
                            {{ \Carbon\Carbon::parse($history->created_at)->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="py-6 text-center text-xs text-slate-400">
                    Belum ada riwayat aktivitas.
                </div>
            @endforelse
        </div>
    </div>
</div>

