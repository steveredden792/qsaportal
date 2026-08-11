import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Body copy (default `font-sans`)
                sans: ['Albert Sans', ...defaultTheme.fontFamily.sans],
                // Headings — apply `font-heading` explicitly where needed
                heading: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Q Score Analysis brand palette (matches qsanalysis.co.uk).
                // `brand.light` is used throughout the app as the teal accent
                // tone against navy (badges, hero subtitle, focus rings) —
                // not a tint of navy, despite the name.
                brand: {
                    DEFAULT: '#002842',
                    light: '#00c7c3',
                },
                qsa: {
                    grey: '#dcdfe1',
                },
            },
        },
    },

    plugins: [forms],
};
