{{-- Informasi Kendaraan (Data teknis, legalitas, foto, kondisi, status, dan pemegang) --}}
<x-card>
    <div class="mb-6 flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
        <div>
            <h3 class="text-base font-bold text-slate-800">Informasi Kendaraan</h3>
            <p class="text-xs text-slate-400">Data teknis dan legalitas kendaraan dinas</p>
        </div>
    </div>
    @if ($kendaraan->foto)
        <div class="mb-5">
            <img src="{{ asset('storage/' . $kendaraan->foto) }}" alt="Foto kendaraan" class="h-48 w-full rounded-xl object-cover sm:w-72">
        </div>
    @endif
    <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nama Kendaraan</dt>
            <dd class="text-sm text-slate-900">{{ $kendaraan->namaTampil() ?? '-' }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Jenis Kendaraan</dt>
            <dd class="text-sm text-slate-900">{{ $kendaraan->jenis_kendaraan ?? '-' }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nomor Kartu Barang</dt>
            <dd class="text-sm text-slate-900">{{ $kendaraan->aset?->nomor_kartu_barang ?? '-' }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Merk</dt>
            <dd class="text-sm text-slate-900">{{ $kendaraan->aset?->merk ?? '-' }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tipe</dt>
            <dd class="text-sm text-slate-900">{{ $kendaraan->tipe ?? '-' }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nomor Rangka</dt>
            <dd class="text-sm text-slate-900">{{ $kendaraan->nomor_rangka ?? '-' }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nomor Mesin</dt>
            <dd class="text-sm text-slate-900">{{ $kendaraan->nomor_mesin ?? '-' }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Plat Aktif</dt>
            <dd class="text-sm font-medium text-slate-900">{{ $platAktif->nomor_plat ?? '-' }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Jatuh Tempo PKB</dt>
            @php $stPkb = $kendaraan->statusPkb(); @endphp
            <dd class="text-sm">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $stPkb['class'] }}">
                    {{ $stPkb['label'] }}
                </span>
            </dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Masa Berlaku STNK (5 Thn)</dt>
            @php $stStnk = $kendaraan->statusStnk(); @endphp
            <dd class="text-sm">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $stStnk['class'] }}">
                    {{ $stStnk['label'] }}
                </span>
            </dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kondisi</dt>
            <dd class="text-sm">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kondisiColor }}">{{ $kendaraan->aset?->kondisi ?? '-' }}</span>
            </dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Status Aset</dt>
            <dd class="text-sm">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadge }}">{{ $kendaraan->aset?->status_aset ?? '-' }}</span>
            </dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Pemegang Kendaraan</dt>
            <dd class="text-sm">
                @php
                    $pemegangPegawai = $kendaraan->aset?->pemegangSaatIni?->pegawai;
                    $namaPemegangShow = $pemegangPegawai?->nama_pegawai ?: $kendaraan->pemegang;
                    $idPemegangShow = $pemegangPegawai?->id_pegawai;
                @endphp
                @if (trim((string) $namaPemegangShow) !== '')
                    @if ($idPemegangShow && in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Admin UPT'], true))
                        <a href="{{ route('master-data.pegawai.show', $idPemegangShow) }}" class="font-medium text-disbun-700 transition hover:text-disbun-800 hover:underline">
                            {{ $namaPemegangShow }}
                        </a>
                    @else
                        <span class="font-medium text-slate-800">{{ $namaPemegangShow }}</span>
                    @endif
                @else
                    <span class="text-slate-400">-</span>
                @endif
            </dd>
        </div>
        <div class="space-y-1 sm:col-span-2 lg:col-span-3">
            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Keterangan</dt>
            <dd class="text-sm text-slate-900">{{ $kendaraan->keterangan ?: '-' }}</dd>
        </div>
        @if ($isAdmin)
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nilai Perolehan</dt>
                <dd class="text-sm text-slate-900">{{ $kendaraan->aset?->nilai_perolehan ? 'Rp ' . number_format((float) $kendaraan->aset->nilai_perolehan, 0, ',', '.') : '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Pengadaan</dt>
                <dd class="text-sm text-slate-900">{{ $kendaraan->aset?->tanggal_pengadaan?->format('d M Y') ?? '-' }}</dd>
            </div>
        @endif
    </div>
</x-card>

