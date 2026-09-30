import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{jsx,tsx}',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                surface: {
                    DEFAULT: '#121414',
                    dim: '#121414',
                    bright: '#383939',
                    'container-lowest': '#0d0e0f',
                    'container-low': '#1b1c1c',
                    container: '#1f2020',
                    'container-high': '#292a2a',
                    'container-highest': '#343535',
                    tint: '#c6c6c7',
                    variant: '#343535',
                },
                'on-surface': {
                    DEFAULT: '#e3e2e2',
                    variant: '#c4c7c8',
                },
                primary: {
                    DEFAULT: '#ffffff',
                    container: '#e2e2e2',
                    fixed: '#e2e2e2',
                    'fixed-dim': '#c6c6c7',
                },
                'on-primary': {
                    DEFAULT: '#2f3131',
                    container: '#636565',
                    fixed: '#1a1c1c',
                    'fixed-variant': '#454747',
                },
                secondary: {
                    DEFAULT: '#c8c6c5',
                    container: '#474746',
                    fixed: '#e4e2e1',
                    'fixed-dim': '#c8c6c5',
                },
                'on-secondary': {
                    DEFAULT: '#303030',
                    container: '#b7b5b4',
                    fixed: '#1b1c1c',
                    'fixed-variant': '#474746',
                },
                tertiary: {
                    DEFAULT: '#ffffff',
                    container: '#e5e2e1',
                    fixed: '#e5e2e1',
                    'fixed-dim': '#c8c6c5',
                },
                'on-tertiary': {
                    DEFAULT: '#313030',
                    container: '#656464',
                    fixed: '#1c1b1b',
                    'fixed-variant': '#474646',
                },
                error: {
                    DEFAULT: '#ffb4ab',
                    container: '#93000a',
                },
                'on-error': {
                    DEFAULT: '#690005',
                    container: '#ffdad6',
                },
                outline: {
                    DEFAULT: '#8e9192',
                    variant: '#444748',
                },
                background: '#121414',
                'on-background': '#e3e2e2',
                'inverse-surface': '#e3e2e2',
                'inverse-on-surface': '#2f3031',
                'inverse-primary': '#5d5f5f',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', 'monospace'],
                display: ['BitcountGridDouble', 'sans-serif'],
                'headline-sm': ['Manrope'],
                'body-md': ['Manrope'],
                'label-sm': ['Manrope'],
                'label-md': ['Manrope'],
                'body-sm': ['Manrope'],
            },
            fontSize: {
                'headline-sm': ['18px', { lineHeight: '24px', fontWeight: '600' }],
                'body-md': ['15px', { lineHeight: '22px', fontWeight: '400' }],
                'headline-sm-mobile': ['16px', { lineHeight: '22px', fontWeight: '600' }],
                'label-sm': ['11px', { lineHeight: '14px', letterSpacing: '0.02em', fontWeight: '500' }],
                'label-md': ['14px', { lineHeight: '20px', fontWeight: '500' }],
                'body-sm': ['13px', { lineHeight: '18px', fontWeight: '400' }],
            },
            spacing: {
                'stack-sm': '0.5rem',
                'stack-xs': '0.25rem',
                'gutter-md': '1rem',
                'container-margin': '1rem',
                'bubble-padding-x': '1rem',
                'bubble-padding-y': '0.75rem',
            },
            borderRadius: {
                DEFAULT: '0.125rem',
                lg: '0.25rem',
                xl: '0.5rem',
                full: '0.75rem',
            },
        },
    },
    plugins: [forms],
};
