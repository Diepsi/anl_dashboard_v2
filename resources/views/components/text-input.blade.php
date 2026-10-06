@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-500 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-400 focus:border-cyan-500 dark:focus:border-cyan-400 focus:ring-cyan-500 dark:focus:ring-cyan-400 rounded-md shadow-sm']) }}>
