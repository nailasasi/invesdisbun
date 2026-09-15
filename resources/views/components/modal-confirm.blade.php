@props([
    'action' => null,
    'method' => 'DELETE',
    'title' => 'Hapus Data',
    'message' => 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.',
    'buttonText' => 'Hapus',
    'id' => '',
])

<div id="{{ $id }}" data-modal-body class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-close></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-start gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $message }}</p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" data-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
            <form action="{{ $action }}" method="POST">
                @csrf
                @method($method)
                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">{{ $buttonText }}</button>
            </form>
        </div>
    </div>
</div>
