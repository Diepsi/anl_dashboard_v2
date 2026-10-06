<x-app-layout>

<div class="p-4 sm:p-6 lg:p-8 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ isset($user) ? 'Edit User' : 'Tambah User' }}</h1>
            <p class="mt-1 text-sm text-muted">
                @isset($user)
                    Ubah data akun untuk <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $user->email }}</span>.
                @else
                    Buat akun baru untuk akses dashboard.
                @endisset
            </p>
        </div>
        <a href="{{ route('users.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-surface border border-border text-muted text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition">
            Kembali ke Daftar
        </a>
    </div>

    {{-- Form --}}
    <div class="bg-surface rounded-xl shadow-sm dark:shadow-black/20 border border-border p-6">
        <form method="POST" action="{{ isset($user) ? route('users.update', $user) : route('users.store') }}" class="space-y-6">
            @csrf
            @isset($user)
                @method('PUT')
            @endisset

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-muted mb-1">Nama Lengkap</label>
                    <input type="text" name="name" value="{{ old('name', $user?->name ?? '') }}"
                        class="w-full px-3 py-2 rounded-lg border border-border dark:border-slate-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-muted mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user?->email ?? '') }}"
                        class="w-full px-3 py-2 rounded-lg border border-border dark:border-slate-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                    @error('email')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-muted mb-1">Role</label>
                    <select name="role" class="w-full px-3 py-2 rounded-lg border border-border dark:border-slate-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm">
                        <option value="user" @selected(old('role', $user?->role ?? 'user') === 'user')>User</option>
                        <option value="admin" @selected(old('role', $user?->role ?? 'user') === 'admin')>Admin</option>
                    </select>
                    @error('role')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="is_active" value="1"
                            @checked(old('is_active', $user?->is_active ?? true))
                            class="w-4 h-4 rounded border-slate-300 dark:border-slate-500 text-cyan-600 dark:text-cyan-400 focus:ring-cyan-500/40">
                        <span class="text-sm text-slate-700 dark:text-slate-300 font-medium">Akun aktif</span>
                    </label>
                </div>

                @isset($user)
                    <div>
                        <label class="block text-xs font-semibold text-muted mb-1">Password Baru <span class="text-slate-400 dark:text-slate-400 font-normal">(opsional)</span></label>
                        <input type="password" name="password" autocomplete="new-password" placeholder="Kosongkan jika tidak diubah"
                            class="w-full px-3 py-2 rounded-lg border border-border dark:border-slate-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                        @error('password')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-muted mb-1">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" autocomplete="new-password"
                            class="w-full px-3 py-2 rounded-lg border border-border dark:border-slate-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                    </div>
                @else
                    <div>
                        <label class="block text-xs font-semibold text-muted mb-1">Password</label>
                        <input type="password" name="password" required autocomplete="new-password"
                            class="w-full px-3 py-2 rounded-lg border border-border dark:border-slate-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                        @error('password')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-muted mb-1">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" required autocomplete="new-password"
                            class="w-full px-3 py-2 rounded-lg border border-border dark:border-slate-500 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                    </div>
                @endisset
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit"
                    class="px-5 py-2 rounded-lg bg-cyan-600 dark:bg-cyan-500 text-white dark:text-slate-950 text-sm font-semibold hover:bg-cyan-700 dark:hover:bg-cyan-400 transition">
                    {{ isset($user) ? 'Simpan Perubahan' : 'Buat User' }}
                </button>
                <a href="{{ route('users.index') }}"
                    class="px-4 py-2 rounded-lg bg-surface border border-border text-muted text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition">Batal</a>
            </div>
        </form>
    </div>
</div>

</x-app-layout>