(() => {
    const key = 'kaido_glassy_theme';
    let saved;
    try {
        saved = localStorage.getItem(key);
    } catch {
        // The toggle still works when browser storage is unavailable.
    }
    const preference = matchMedia('(prefers-color-scheme: dark)');
    const root = document.documentElement;
    const apply = (dark) => {
        root.dataset.glassyTheme = dark ? 'dark' : 'light';
        const button = document.querySelector('.glassy-theme-toggle');
        if (button) {
            button.textContent = dark ? '☀ Chuyển sang sáng' : '☾ Chuyển sang tối';
        }
    };
    apply(saved ? saved === 'dark' : preference.matches);
    preference.addEventListener('change', (event) => {
        if (!saved) apply(event.matches);
    });
    document.addEventListener('DOMContentLoaded', () => {
        apply(root.dataset.glassyTheme === 'dark');
        document.querySelector('.glassy-theme-toggle')?.addEventListener('click', () => {
            saved = root.dataset.glassyTheme === 'dark' ? 'light' : 'dark';
            apply(saved === 'dark');
            try {
                localStorage.setItem(key, saved);
            } catch {
                // Keep the current theme for this page visit.
            }
        });
    });
})();
