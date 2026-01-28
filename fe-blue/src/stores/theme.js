import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useThemeStore = defineStore('theme', () => {
    // State: 'light' | 'dark' | 'system'
    const theme = ref(localStorage.getItem('blukios_theme') || 'system');

    // Computed: Actual effective theme ('light' or 'dark')
    const effectiveTheme = computed(() => {
        if (theme.value === 'system') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return theme.value;
    });

    // Actions
    const applyTheme = () => {
        const root = document.documentElement;
        const isDark = effectiveTheme.value === 'dark';

        if (isDark) {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
    };

    const setTheme = (newTheme) => {
        theme.value = newTheme;
        localStorage.setItem('blukios_theme', newTheme);
        applyTheme();
    };

    const toggleTheme = () => {
        const next = effectiveTheme.value === 'light' ? 'dark' : 'light';
        setTheme(next);
    };

    // System changes listener
    const initListener = () => {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', () => {
            if (theme.value === 'system') {
                applyTheme();
            }
        });
        applyTheme(); // Initial apply
    };

    return {
        theme,
        effectiveTheme,
        setTheme,
        toggleTheme,
        initListener
    };
});
