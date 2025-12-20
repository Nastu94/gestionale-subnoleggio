import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import daisyui from 'daisyui'; // ✅ Aggiunta

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './app/**/*.php',
    ],

    // ✅ IMPORTANTISSIMO: classi usate dinamicamente in PHP (Livewire/array)
    safelist: [
        // draft - grigio
        'bg-gray-100', 'border-gray-300', 'text-gray-800',

        // reserved - giallo
        'bg-yellow-100', 'border-yellow-300', 'text-yellow-900',

        // in_use - rosso chiaro
        'bg-red-100', 'border-red-300', 'text-red-900',

        // checked_in - rosso scuro
        'bg-red-200', 'border-red-400', 'text-red-950',

        // closed - verde
        'bg-green-100', 'border-green-300', 'text-green-900',

        // cancelled - bordeaux
        'bg-rose-200', 'border-rose-400', 'text-rose-950',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    // ✅ DaisyUI attivato (manteniamo gli altri plugin invariati)
    plugins: [forms, typography, daisyui],

    // Opzionale: lasciamo i temi di default per evitare side-effects
    daisyui: {
        themes: true,
    },
};
