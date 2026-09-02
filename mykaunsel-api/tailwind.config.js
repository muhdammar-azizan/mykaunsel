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
            colors: {
                navy: '#0E2A33',
                cream: '#FAF8F5',
                teal: '#0F6B7D',
                amber: '#D98F4A',
                ralat: '#C4574A',
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            letterSpacing: {
                tightest: '-0.035em',
            },
        },
    },

    plugins: [forms],
};
