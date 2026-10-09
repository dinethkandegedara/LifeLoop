import { ref, watch, onMounted } from 'vue';

export type ThemePreference = 'light' | 'dark' | 'system';
export type ResolvedTheme = 'light' | 'dark';

const STORAGE_KEY = 'lifeloop_theme';

const themePreference = ref<ThemePreference>('system');
const resolvedTheme = ref<ResolvedTheme>('light');

function applyTheme(isDark: boolean) {
    resolvedTheme.value = isDark ? 'dark' : 'light';
    if (typeof document !== 'undefined') {
        if (isDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }
}

function computeTheme(preference: ThemePreference): boolean {
    if (preference === 'dark') return true;
    if (preference === 'light') return false;
    if (typeof window !== 'undefined') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    return false;
}

export function useTheme() {
    function initTheme() {
        if (typeof window === 'undefined') return;

        const stored = localStorage.getItem(STORAGE_KEY) as ThemePreference | null;
        if (stored === 'light' || stored === 'dark' || stored === 'system') {
            themePreference.value = stored;
        } else {
            themePreference.value = 'system';
        }

        applyTheme(computeTheme(themePreference.value));

        // Listen for system changes if system mode is active
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const listener = (e: MediaQueryListEvent) => {
            if (themePreference.value === 'system') {
                applyTheme(e.matches);
            }
        };

        try {
            mediaQuery.addEventListener('change', listener);
        } catch {
            mediaQuery.addListener(listener);
        }
    }

    function setTheme(pref: ThemePreference) {
        themePreference.value = pref;
        if (typeof window !== 'undefined') {
            localStorage.setItem(STORAGE_KEY, pref);
        }
        applyTheme(computeTheme(pref));
    }

    function toggleTheme() {
        if (resolvedTheme.value === 'dark') {
            setTheme('light');
        } else {
            setTheme('dark');
        }
    }

    onMounted(() => {
        initTheme();
    });

    return {
        theme: themePreference,
        resolvedTheme,
        setTheme,
        toggleTheme,
    };
}
