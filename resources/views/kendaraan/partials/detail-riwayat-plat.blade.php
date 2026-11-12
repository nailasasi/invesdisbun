{{-- Riwayat Plat Nomor (menampilkan maksimal 3 plat terbaru) --}}
<x-card :padding="false" class="min-w-0 lg:col-span-1">
    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
        <h3 class="text-base font-semibold text-slate-900">Riwayat Plat Nomor</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nomor Plat</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($kendaraan->riwayatPlat as $index => $plat)
                    <tr class="transition hover:bg-slate-50">
                        <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ $plat->nomor_plat }}</td>
                        <td class="px-6 py-4 text-right text-sm">
                            @if ($plat->status === 'Aktif')
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-disbun-50 text-disbun-800">Aktif</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-500">Riwayat</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada riwayat plat.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

