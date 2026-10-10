@extends('layouts.app')

@section('title', 'User Management')

@section('page-title', 'User Management')

@section('content')
    <x-alert type="success" />
    <x-alert type="error" />

    <x-card :padding="false">
        <x-action-bar>
            <form method="GET" action="{{ route('user.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="relative flex-1 sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input
                        type="text"
                        name="search"
                        id="search-pegawai"
                        value="{{ request('search') }}"
                        placeholder="Cari NIP, Nama, atau Username..."
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
                <select name="role" id="filter-role"
                        class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition">
                    <option value="">Semua Role</option>
                    @foreach ($roleList as $role)
                        <option value="{{ $role->id_role }}" @selected((string) request('role') === (string) $role->id_role)>{{ $role->nama_role }}</option>
                    @endforeach
                </select>
                <select name="status" id="filter-status"
                        class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition">
                    <option value="">Semua Status</option>
                    <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
                </select>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800">
                    Cari
                </button>
            </form>

            <x-slot name="actions">
                <x-button href="{{ route('master-data.pegawai.index', ['status_akun' => 'belum']) }}" icon="M12 4v16m8-8H4">
                    Buat Akun dari Pegawai
                </x-button>
            </x-slot>
        </x-action-bar>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">NIP</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Username</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jabatan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SKPD</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($userList as $i => $akun)
                        @php
                            $isAktif = $akun->status_user === 'aktif';
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $userList->firstItem() + $i }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $akun->pegawai?->nip ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $akun->pegawai?->nama_pegawai ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-mono text-sm text-slate-600">{{ $akun->username }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $akun->role?->nama_role ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $akun->pegawai?->jabatan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $akun->pegawai?->skpd?->nama_skpd ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-block rounded-md px-2 py-0.5 text-[10px] font-bold uppercase {{ $isAktif ? 'bg-disbun-100 text-disbun-800' : 'bg-red-100 text-red-700' }}">
                                    {{ $isAktif ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                @if ($akun->pegawai)
                                    <a
                                        href="{{ route('user.show', $akun->pegawai) }}"
                                        class="inline-flex items-center gap-2 rounded-xl bg-disbun-700 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"/></svg>
                                        Detail
                                    </a>
                                @else
                                    <span class="text-xs text-slate-300">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-sm text-slate-400">Tidak ada akun yang cocok dengan pencarian/filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $userList->links() }}
        </div>
    </x-card>

    @push('scripts')
    <script>
        const searchIndexUrl = @json(route('user.index'));

        const searchInput = document.getElementById('search-pegawai');
        const filterSkpd = document.getElementById('filter-skpd');
        const filterJenis = document.getElementById('filter-jenis');
        const filterRole = document.getElementById('filter-role');
        const filterStatus = document.getElementById('filter-status');

        function goToIndex() {
            const url = new URL(searchIndexUrl, window.location.origin);
            const keyword = searchInput ? searchInput.value.trim() : '';
            const skpd = filterSkpd ? filterSkpd.value : '';
            const jenis = filterJenis ? filterJenis.value : '';
            const role = filterRole ? filterRole.value : '';
            const status = filterStatus ? filterStatus.value : '';
            if (keyword) { url.searchParams.set('search', keyword); }
            if (skpd) { url.searchParams.set('skpd', skpd); }
            if (jenis) { url.searchParams.set('jenis_skpd', jenis); }
            if (role) { url.searchParams.set('role', role); }
            if (status) { url.searchParams.set('status', status); }
            window.location.href = url.toString();
        }

        if (searchInput) {
            let searchTimer;
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(goToIndex, 300);
            });
        }

        [filterSkpd, filterJenis, filterRole, filterStatus].forEach((el) => {
            if (el) { el.addEventListener('change', goToIndex); }
        });
    </script>
    @endpush
@endsection