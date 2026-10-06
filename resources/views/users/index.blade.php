<x-app-layout>

<div class="p-4 sm:p-6 lg:p-8 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Manajemen User</h1>
            <p class="mt-1 text-sm text-muted">Kelola akun pengguna — tambah, ubah, aktifkan/nonaktifkan, atau hapus.</p>
        </div>
        <a href="{{ route('users.create') }}"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-cyan-600 dark:bg-cyan-500 text-white dark:text-slate-950 text-sm font-semibold hover:bg-cyan-700 dark:hover:bg-cyan-400 transition">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Tambah User
        </a>
    </div>

    {{-- Search --}}
    <div class="bg-surface rounded-xl shadow-sm dark:shadow-black/20 border border-border p-4">
        <form method="GET" action="{{ route('users.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-muted mb-1">Cari Nama / Email</label>
                <input type="text" name="q" value="{{ $search }}"
                    placeholder="Ketik nama atau email user..."
                    class="w-full px-3 py-2 rounded-lg border border-border dark:border-slate-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
            </div>
            <div class="flex items-center gap-2">
                <button type="submit"
                    class="px-5 py-2.5 rounded-lg bg-cyan-600 dark:bg-cyan-500 text-white dark:text-slate-950 text-sm font-semibold hover:bg-cyan-700 dark:hover:bg-cyan-400 transition">Cari</button>
                <a href="{{ route('users.index') }}"
                    class="px-4 py-2.5 rounded-lg bg-surface border border-border text-muted text-sm font-medium hover:bg-surface-subtle transition">Reset</a>
            </div>
        </form>
    </div>

    @if (session('status'))
        <div class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->has('delete'))
        <div class="p-4 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-sm">
            {{ $errors->first('delete') }}
        </div>
    @endif

    {{-- Table --}}
    <div class="bg-surface rounded-xl shadow-sm dark:shadow-black/20 border border-border overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex items-center justify-between">
            <h2 class="text-lg font-bold text-ink">Daftar User</h2>
            <p class="text-sm text-muted">{{ number_format($users->total(), 0, ',', '.') }} user</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-surface-subtle text-xs uppercase tracking-wider text-muted border-b border-border">
                        <th class="px-6 py-3 font-semibold">#</th>
                        <th class="px-6 py-3 font-semibold">Nama</th>
                        <th class="px-6 py-3 font-semibold">Email</th>
                        <th class="px-6 py-3 font-semibold">Role</th>
                        <th class="px-6 py-3 font-semibold">Status</th>
                        <th class="px-6 py-3 font-semibold">Dibuat</th>
                        <th class="px-6 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($users as $user)
                        <tr class="hover:bg-surface-subtle transition">
                            <td class="px-6 py-3.5 font-mono text-slate-400 dark:text-slate-400">{{ $user->id }}</td>
                            <td class="px-6 py-3.5 text-slate-800 dark:text-slate-200 font-medium max-w-[220px] truncate">
                                {{ $user->name }}
                                @if ($user->id === auth()->id())
                                    <span class="ml-1 text-xs text-slate-400 dark:text-slate-400">(Anda)</span>
                                @endif
                            </td>
                            <td class="px-6 py-3.5 text-muted">{{ $user->email }}</td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    {{ $user->isAdmin() ? 'bg-cyan-100 dark:bg-cyan-950 text-cyan-700 dark:text-cyan-300' : 'bg-slate-100 dark:bg-slate-800 text-muted' }}">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    {{ $user->is_active ? 'bg-green-100 dark:bg-emerald-950 text-green-700 dark:text-emerald-300' : 'bg-red-100 dark:bg-red-950 text-red-700 dark:text-red-300' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $user->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                    {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-muted">{{ $user->created_at?->format('d M Y') ?? '—' }}</td>
                            <td class="px-6 py-3.5">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('users.edit', $user) }}"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-cyan-700 dark:text-cyan-300 bg-cyan-50 dark:bg-cyan-950/50 border border-cyan-200 dark:border-cyan-900 hover:bg-cyan-100 dark:hover:bg-cyan-900/50 transition">
                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931ZM19.5 12.75v4.875a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V6.75A2.25 2.25 0 0 1 6.75 4.5h4.875" />
                                        </svg>
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('users.destroy', $user) }}"
                                        onsubmit="return confirm('Hapus user {{ addslashes($user->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-red-600 dark:text-red-300 bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 hover:bg-red-100 dark:hover:bg-red-900/50 transition">
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-slate-400 dark:text-slate-400">Belum ada user.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-border">
            {{ $users->links() }}
        </div>
    </div>
</div>

</x-app-layout>