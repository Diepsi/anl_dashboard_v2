<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white dark:bg-slate-800 border-gray-300 dark:border-slate-500 rounded-md font-semibold text-xs text-gray-700 dark:text-slate-200 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-surface disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
