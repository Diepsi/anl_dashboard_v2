import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

const themeStorageKey = 'anl_theme';
const validThemePreferences = ['light', 'dark', 'system'];
const systemThemeQuery = window.matchMedia('(prefers-color-scheme: dark)');

const normalizeThemePreference = (preference) => (
    validThemePreferences.includes(preference) ? preference : 'system'
);

const getThemePreference = () => {
    try {
        return normalizeThemePreference(window.localStorage.getItem(themeStorageKey));
    } catch {
        return 'system';
    }
};

const resolveTheme = (preference) => (
    preference === 'system' ? systemThemeQuery.matches : preference === 'dark'
);

const applyTheme = (preference, persist = true) => {
    const normalizedPreference = normalizeThemePreference(preference);
    const isDark = resolveTheme(normalizedPreference);

    if (persist) {
        try {
            window.localStorage.setItem(themeStorageKey, normalizedPreference);
        } catch {
        }
    }

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.dataset.theme = normalizedPreference;
    document.documentElement.dataset.resolvedTheme = isDark ? 'dark' : 'light';
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';

    window.dispatchEvent(new CustomEvent('anl-theme-changed', {
        detail: {
            preference: normalizedPreference,
            isDark,
        },
    }));
};

const AnlTheme = {
    getPreference: getThemePreference,
    isDark: () => resolveTheme(getThemePreference()),
    set: applyTheme,
    toggle() {
        const nextPreference = resolveTheme(getThemePreference()) ? 'light' : 'dark';
        applyTheme(nextPreference);
        return nextPreference;
    },
};

window.Alpine = Alpine;
window.Chart = Chart;
window.AnlTheme = AnlTheme;

Alpine.data('themeToggle', () => ({
    dark: false,
    init() {
        this.dark = AnlTheme.isDark();
        this.themeListener = (event) => {
            this.dark = event.detail.isDark;
        };
        window.addEventListener('anl-theme-changed', this.themeListener);
    },
    destroy() {
        window.removeEventListener('anl-theme-changed', this.themeListener);
    },
    toggle() {
        AnlTheme.toggle();
    },
}));

systemThemeQuery.addEventListener('change', () => {
    if (getThemePreference() === 'system') {
        applyTheme('system', false);
    }
});

window.addEventListener('storage', (event) => {
    if (event.key === themeStorageKey) {
        applyTheme(event.newValue, false);
    }
});

applyTheme(getThemePreference(), false);
Alpine.start();
