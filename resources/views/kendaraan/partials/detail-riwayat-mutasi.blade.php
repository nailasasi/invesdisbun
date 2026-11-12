{{-- RIWAYAT MUTASI KENDARAAN --}}
<x-card :padding="false" class="mt-6">
    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Riwayat Mutasi Kendaraan</h3>
            <p class="text-xs text-slate-400">Pergantian pemegang kendaraan beserta dokumen SPPKD &amp; BAST</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang Lama</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang Baru</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nomor Surat</th>
                    @if ($isAdmin)
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi Dokumen</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($riwayatMutasi as $rd)
                    @php
                        $pLamaTeks = $rd->pegawaiLama?->nama_pegawai
                            ?? (($rd->mutasi?->keterangan && preg_match('/Serah terima dari ([^\.]+)/', $rd->mutasi->keterangan, $m1)) ? $m1[1] : '-');
                        $nomorSurat = '';
                        if ($rd->mutasi?->keterangan && preg_match('/Nomor Surat:\s*([^\.]+)/', $rd->mutasi->keterangan, $m2)) {
                            $nomorSurat = trim($m2[1]);
                        }
                    @endphp
                    <tr class="transition hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">
                            {{ $rd->mutasi?->tanggal_mutasi ? \Carbon\Carbon::parse($rd->mutasi->tanggal_mutasi)->format('d M Y') : '-' }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">
                            @if ($rd->pegawaiLama && in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Admin UPT'], true))
                                <a href="{{ route('master-data.pegawai.show', $rd->pegawaiLama->id_pegawai) }}" class="font-medium text-disbun-700 transition hover:underline">
                                    {{ $pLamaTeks }}
                                </a>
                            @else
                                {{ $pLamaTeks }}
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                            @if ($rd->pegawaiBaru && in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Admin UPT'], true))
                                <a href="{{ route('master-data.pegawai.show', $rd->pegawaiBaru->id_pegawai) }}" class="font-medium text-disbun-700 transition hover:underline">
                                    {{ $rd->pegawaiBaru->nama_pegawai }}
                                </a>
                            @else
                                {{ $rd->pegawaiBaru?->nama_pegawai ?? '-' }}
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $nomorSurat ?: '-' }}</td>
                        @if ($isAdmin)
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <a href="{{ route('kendaraan.mutasi.sppkd.download', [$kendaraan->id_kendaraan, $rd->id_mutasi]) }}"
                                   class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">
                                    SPPKD
                                </a>
                                <a href="{{ route('kendaraan.mutasi.bast.download', [$kendaraan->id_kendaraan, $rd->id_mutasi]) }}"
                                   class="ml-1 inline-flex items-center rounded-lg bg-disbun-50 px-2.5 py-1.5 text-xs font-medium text-disbun-800 transition hover:bg-disbun-100">
                                    BAST
                                </a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 5 : 4 }}" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada riwayat mutasi kendaraan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

