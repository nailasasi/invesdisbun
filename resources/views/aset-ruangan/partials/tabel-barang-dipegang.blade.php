{{-- Barang Dipegang Pegawai: aset nonkendaraan yang penempatannya
     diturunkan dari ruangan kerja pemegang. Baca-saja — tidak ada aksi
     keluarkan/pasang di sini. --}}
<x-card :padding="false" class="mt-5">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
        <p class="text-sm font-semibold text-slate-700">Barang Dipegang Pegawai</p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Barang</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kartu Barang</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Merk</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($asetDipegang as $i => $aset)
                    <tr class="transition hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $i + 1 }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $aset->barang?->nama_barang ?? '-' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->nomor_kartu_barang ?? '-' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->merk ?? '-' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $aset->pemegangSaatIni?->pegawai?->nama_pegawai ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-400">Tidak ada aset dipegang pegawai pada ruangan ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-slate-100 px-6 py-3">
        <p class="text-xs text-slate-500">Aset dipegang pegawai tidak dapat dikelola dari halaman ini. Ruangan aset mengikuti ruangan kerja pemegangnya; perubahannya dilakukan lewat Mutasi (Pindah Ruangan) atau perubahan ruangan kerja pegawai.</p>
    </div>
</x-card>

