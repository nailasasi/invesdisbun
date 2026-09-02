@extends('layouts.app')

@section('title', 'Master Data - Pegawai')

@section('content')
    <x-page-header title="Pegawai" subtitle="Kelola daftar pegawai">
        <x-slot:actions>
            <x-button href="{{ route('master-data.pegawai.create') }}" icon="M12 4v16m8-8H4">
                Tambah Pegawai
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-alert type="success" />
    <x-alert type="error" />

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">NIP</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Pegawai</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jabatan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SKPD</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($pegawaiList as $i => $pegawai)
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $pegawaiList->firstItem() + $i }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $pegawai->nip }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->nama_pegawai }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->jabatan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->skpd?->nama_skpd ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <x-button href="{{ route('master-data.pegawai.edit', $pegawai) }}" variant="secondary" size="sm">Edit</x-button>
                                    <button type="button" data-modal-target="delete-pegawai-{{ $pegawai->id_pegawai }}" class="inline-flex items-center gap-2 rounded-xl bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada data pegawai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $pegawaiList->links() }}
        </div>
    </x-card>

    @foreach ($pegawaiList as $pegawai)
        <x-modal-confirm
            id="delete-pegawai-{{ $pegawai->id_pegawai }}"
            action="{{ route('master-data.pegawai.destroy', $pegawai) }}"
            title="Hapus Pegawai"
            message="Apakah Anda yakin ingin menghapus '{{ $pegawai->nama_pegawai }}'? Tindakan ini tidak dapat dibatalkan."
        />
    @endforeach
@endsection
