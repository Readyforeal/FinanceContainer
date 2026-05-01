import './bootstrap';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';

Alpine.store('theme', {
    mode: localStorage.getItem('theme') || 'system',

    init() {
        this.apply();
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (this.mode === 'system') this.apply();
        });
    },

    set(mode) {
        this.mode = mode;
        localStorage.setItem('theme', mode);
        this.apply();
    },

    apply() {
        const isDark = this.mode === 'dark' ||
            (this.mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', isDark);
    },
});

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;

Alpine.start();
