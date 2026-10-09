import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.ts',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                app: 'var(--bg-app)',
                surface: {
                    DEFAULT: 'var(--bg-surface)',
                    hover: 'var(--bg-surface-hover)',
                    active: 'var(--bg-surface-active)',
                    subdued: 'var(--bg-surface-subdued)',
                },
                border: {
                    subtle: 'var(--border-subtle)',
                    strong: 'var(--border-strong)',
                },
                content: {
                    primary: 'var(--text-primary)',
                    secondary: 'var(--text-secondary)',
                    muted: 'var(--text-muted)',
                },
                primary: {
                    DEFAULT: 'var(--primary)',
                    hover: 'var(--primary-hover)',
                    active: 'var(--primary-active)',
                    subdued: 'var(--primary-subdued)',
                    text: 'var(--primary-text)',
                },
                accent: {
                    DEFAULT: 'var(--accent)',
                    hover: 'var(--accent-hover)',
                    subdued: 'var(--accent-subdued)',
                },
                status: {
                    success: 'var(--success)',
                    'success-subdued': 'var(--success-subdued)',
                    warning: 'var(--warning)',
                    'warning-subdued': 'var(--warning-subdued)',
                    danger: 'var(--danger)',
                    'danger-subdued': 'var(--danger-subdued)',
                    info: 'var(--info)',
                    'info-subdued': 'var(--info-subdued)',
                },
            },
            borderRadius: {
                sm: '0.375rem',
                DEFAULT: '0.5rem',
                md: '0.625rem',
                lg: '0.75rem',
                xl: '1rem',
                '2xl': '1.25rem',
            },
            boxShadow: {
                sm: '0 1px 2px 0 rgba(0, 0, 0, 0.04)',
                DEFAULT: '0 1px 3px 0 rgba(0, 0, 0, 0.07), 0 1px 2px -1px rgba(0, 0, 0, 0.05)',
                md: '0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.05)',
                lg: '0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.05)',
                glow: '0 0 20px -3px rgba(155, 138, 251, 0.25)',
            },
        },
    },
    plugins: [],
};
