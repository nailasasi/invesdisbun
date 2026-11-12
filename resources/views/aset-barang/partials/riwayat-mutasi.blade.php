{{--
    Partial: Riwayat Mutasi pada Halaman Detail Aset Barang
    Section ini menyatu secara natural dengan halaman (tanpa pembungkus card bento terpisah).
    Variabel yang digunakan:
      - $riwayatMutasi : Koleksi DetailMutasiAset beserta relasi mutasi, pegawai, dan ruangan
      - $isAdminAset   : Boolean penentu apakah kolom Aksi Dokumen (BAST) ditampilkan
--}}

{{-- Pemisah Visual Rapi --}}
<div class="my-8 border-t border-slate-200/80"></div>

{{-- Section Riwayat Mutasi --}}
<section class="space-y-4">
    {{-- Header Section --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Riwayat Mutasi</h3>
            <p class="mt-0.5 text-xs text-slate-400">Pergantian pemegang &amp; perpindahan ruangan beserta dokumen BAST.</p>
        </div>
        @if ($riwayatMutasi->isNotEmpty())
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 shadow-xs">
                    <span class="h-1.5 w-1.5 rounded-full bg-disbun-600"></span>
                    {{ $riwayatMutasi->count() }} Catatan Mutasi
                </span>
            </div>
        @endif
    </div>

    {{-- Tabel Riwayat Mutasi (Tabel Bersih Responsif) --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang Lama</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang Baru</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nomor Surat</th>
                        @if ($isAdminAset)
                            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi Dokumen</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($riwayatMutasi as $rd)
                        @php
                            $mutasi = $rd->mutasi;
                            $jenisMutasi = $mutasi?->jenis_mutasi ?? '-';
                            $gantiPemegang = $jenisMutasi === 'Ganti Pemegang';
                            $nomorSurat = '';
                            if ($mutasi?->keterangan && preg_match('/Nomor Surat:\s*([^\.]+)/', $mutasi->keterangan, $m)) {
                                $nomorSurat = trim($m[1]);
                            }
                        @endphp
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-6 py-4 align-top text-sm text-slate-700">
                                <span class="whitespace-nowrap font-medium text-slate-900">{{ $mutasi?->tanggal_mutasi?->format('d M Y') ?? '-' }}</span>
                                <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex items-center rounded-full border border-disbun-100 bg-disbun-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-disbun-700">{{ $jenisMutasi }}</span>
                                    @if (($mutasi?->status_mutasi ?? '') !== '')
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $mutasi->status_mutasi }}</span>
                                    @endif
                                </span>
                                <span class="mt-1 block text-[11px] text-slate-400">Diinput oleh {{ $mutasi?->userPenginput?->pegawai?->nama_pegawai ?? $mutasi?->userPenginput?->username ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-slate-700">{{ $rd->pegawaiLama?->nama_pegawai ?? '-' }}</td>
                            <td class="px-6 py-4 align-top text-sm font-medium text-slate-900">
                                {{ $rd->pegawaiBaru?->nama_pegawai ?? '-' }}
                                @if (! $gantiPemegang)
                                    <span class="mt-1 block text-[11px] font-normal text-slate-500">Ruangan: {{ $rd->ruanganLama?->nama_ruangan ?? '-' }} &rarr; {{ $rd->ruanganBaru?->nama_ruangan ?? '-' }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-slate-600">
                                <span class="whitespace-nowrap font-mono text-xs">{{ $nomorSurat ?: '-' }}</span>
                                @if (($mutasi?->keterangan ?? '') !== '')
                                    <span class="mt-1 block max-w-xs whitespace-normal text-[11px] text-slate-400">{{ $mutasi->keterangan }}</span>
                                @endif
                            </td>
                            @if ($isAdminAset)
                                <td class="whitespace-nowrap px-6 py-4 text-right align-top text-sm">
                                    @if ($gantiPemegang)
                                        <a href="{{ route('mutasi-aset.bast.download', $mutasi?->id_mutasi) }}"
                                           class="inline-flex items-center rounded-lg bg-disbun-50 px-2.5 py-1.5 text-xs font-semibold text-disbun-800 transition hover:bg-disbun-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600">
                                            BAST
                                        </a>
                                    @else
                                        <span class="text-[11px] italic text-slate-400" title="Pemindahan ruangan tercatat di KIR Ruangan">Tercatat di KIR Ruangan</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdminAset ? 5 : 4 }}" class="px-6 py-12 text-center text-sm text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                    <span>Belum ada riwayat mutasi barang.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer Informasi Riwayat --}}
        <div class="flex flex-col gap-1 border-t border-slate-100 bg-slate-50/50 px-6 py-3 text-[11px] text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ $riwayatMutasi->count() }} riwayat mutasi</span>
            <span class="inline-flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Terakhir disinkronkan {{ now()->translatedFormat('d M Y, H:i') }}
            </span>
        </div>
    </div>
</section>

