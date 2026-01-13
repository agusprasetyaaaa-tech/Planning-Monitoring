import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            animation: {
                'glow-red': 'glow-red 1.5s ease-in-out infinite',
                'glow-warning': 'glow-warning 1.5s ease-in-out infinite',
            },
            keyframes: {
                'glow-red': {
                    '0%, 100%': {
                        boxShadow: '0 0 5px 2px rgba(239, 68, 68, 0.5)',
                        opacity: '1',
                    },
                    '50%': {
                        boxShadow: '0 0 20px 5px rgba(239, 68, 68, 0.8)',
                        opacity: '0.8',
                    },
                },
                'glow-warning': {
                    '0%, 100%': {
                        boxShadow: '0 0 5px 2px rgba(245, 158, 11, 0.5)',
                        opacity: '1',
                    },
                    '50%': {
                        boxShadow: '0 0 20px 5px rgba(245, 158, 11, 0.8)',
                        opacity: '0.8',
                    },
                },
            },
        },
    },

    plugins: [forms],
};
