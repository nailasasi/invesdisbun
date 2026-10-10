@extends('layouts.app')

@section('title', 'Data Pegawai')

@section('page-title', 'Data Pegawai')

@section('content')
    <x-alert type="success" />
    <x-alert type="error" />

    <x-card :padding="false">
        <x-action-bar>
            <form method="GET" action="{{ route('master-data.pegawai.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="relative flex-1 sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input
                        type="text"
                        name="search"
                        id="search-pegawai"
                        value="{{ request('search') }}"
                        placeholder="Cari NIP, Nama, atau Jabatan..."
                        autocomplete="off"
                        class="block w-full rounded-xl border border-slate-300 py-2 pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition"
                    >
                </div>

                <select name="skpd" id="filter-skpd"
                        class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition">
                    <option value="">Semua SKPD</option>
                    @foreach ($skpdList as $skpd)
                        <option value="{{ $skpd->id_skpd }}" @selected((string) request('skpd') === (string) $skpd->id_skpd)>
                            {{ $skpd->nama_skpd }}
                        </option>
                    @endforeach
                </select>

                <select name="jenis_skpd" id="filter-jenis"
                        class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition">
                    <option value="">Semua Jenis SKPD</option>
                    <option value="Sekretariat" @selected(request('jenis_skpd') === 'Sekretariat')>Sekretariat</option>
                    <option value="Bidang" @selected(request('jenis_skpd') === 'Bidang')>Bidang</option>
                    <option value="UPT" @selected(request('jenis_skpd') === 'UPT')>UPT</option>
                </select>

                <select name="status_akun" id="filter-status-akun"
                        class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition">
                    <option value="">Semua Status Akun</option>
                    <option value="belum" @selected(request('status_akun') === 'belum')>Belum Ada Akun</option>
                    <option value="aktif" @selected(request('status_akun') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status_akun') === 'nonaktif')>Nonaktif</option>
                </select>

                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800">
                    Cari
                </button>
            </form>

            <x-slot name="actions">
                <x-button href="{{ route('master-data.pegawai.create') }}" icon="M12 4v16m8-8H4">
                    Tambah Pegawai
                </x-button>
            </x-slot>
        </x-action-bar>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">NIP</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jabatan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SKPD</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ruangan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status Akun</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($pegawaiList as $i => $pegawai)
                        @php
                            $statusUser = $pegawai->user?->status_user;
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $pegawaiList->firstItem() + $i }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $pegawai->nip ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->nama_pegawai }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->jabatan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->skpd?->nama_skpd ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->skpd?->jenis_skpd ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->ruangan?->nama_ruangan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($statusUser === null)
                                    <span class="inline-block rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500">Belum Ada Akun</span>
                                @elseif ($statusUser === 'aktif')
                                    <span class="inline-block rounded-md bg-disbun-100 px-2 py-0.5 text-[10px] font-bold uppercase text-disbun-800">Aktif</span>
                                @else
                                    <span class="inline-block rounded-md bg-red-100 px-2 py-0.5 text-[10px] font-bold uppercase text-red-700">Nonaktif</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <x-button href="{{ route('master-data.pegawai.show', $pegawai) }}" variant="secondary" size="sm">Detail</x-button>
                                    <x-button href="{{ route('master-data.pegawai.edit', $pegawai) }}" variant="secondary" size="sm">Edit</x-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-sm text-slate-400">Tidak ada data pegawai yang cocok dengan pencarian/filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $pegawaiList->links() }}
        </div>
    </x-card>

    @push('scripts')
    <script>
        const searchInput = document.getElementById('search-pegawai');
        const filterSkpd = document.getElementById('filter-skpd');
        const filterJenis = document.getElementById('filter-jenis');
        const filterStatus = document.getElementById('filter-status-akun');
        const indexUrl = @json(route('master-data.pegawai.index'));

        function goToIndex() {
            const url = new URL(indexUrl, window.location.origin);
            const keyword = searchInput ? searchInput.value.trim() : '';
            const skpd = filterSkpd ? filterSkpd.value : '';
            const jenis = filterJenis ? filterJenis.value : '';
            const status = filterStatus ? filterStatus.value : '';
            if (keyword) { url.searchParams.set('search', keyword); }
            if (skpd) { url.searchParams.set('skpd', skpd); }
            if (jenis) { url.searchParams.set('jenis_skpd', jenis); }
            if (status) { url.searchParams.set('status_akun', status); }
            window.location.href = url.toString();
        }

        if (searchInput) {
            let timer;
            searchInput.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(goToIndex, 300);
            });
        }

        [filterSkpd, filterJenis, filterStatus].forEach((el) => {
            if (el) { el.addEventListener('change', goToIndex); }
        });
    </script>
    @endpush
@endsection
