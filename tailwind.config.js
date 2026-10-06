import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    darkMode: 'selector',

    theme: {
        extend: {
            colors: {
                canvas: 'rgb(var(--anl-canvas) / <alpha-value>)',
                surface: 'rgb(var(--anl-surface) / <alpha-value>)',
                'surface-subtle': 'rgb(var(--anl-surface-subtle) / <alpha-value>)',
                border: 'rgb(var(--anl-border) / <alpha-value>)',
                ink: 'rgb(var(--anl-ink) / <alpha-value>)',
                muted: 'rgb(var(--anl-muted) / <alpha-value>)',
                brand: 'rgb(var(--anl-brand) / <alpha-value>)',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
