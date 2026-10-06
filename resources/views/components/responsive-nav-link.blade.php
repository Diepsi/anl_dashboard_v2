@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-cyan-500 text-start text-base font-medium text-cyan-700 dark:text-cyan-300 bg-cyan-50 dark:bg-cyan-950/50 focus:outline-none focus:text-cyan-800 dark:focus:text-cyan-200 focus:bg-cyan-100 dark:focus:bg-cyan-900/50 focus:border-cyan-700 dark:focus:border-cyan-400 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-muted hover:text-ink hover:bg-surface-subtle hover:border-slate-300 focus:outline-none focus:text-ink focus:bg-surface-subtle focus:border-slate-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
