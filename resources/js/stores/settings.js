import { defineStore } from 'pinia';
import axios from 'axios';

export const useSettingsStore = defineStore('settings', {
    state: () => ({
        yandexUrl: '',
        loading: false,
        error: null,
        success: false,
    }),

    actions: {
        async fetchSettings() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/api/settings');
                this.yandexUrl = response.data.yandex_url || '';
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to load settings.';
            } finally {
                this.loading = false;
            }
        },

        async saveSettings(yandexUrl) {
            this.loading = true;
            this.error = null;
            this.success = false;
            try {
                const response = await axios.post('/api/settings', {
                    yandex_url: yandexUrl,
                });
                this.yandexUrl = response.data.yandex_url;
                this.success = true;
            } catch (error) {
                if (error.response?.status === 422) {
                    const errors = error.response.data.errors;
                    this.error = Object.values(errors).flat().join(' ');
                } else {
                    this.error = error.response?.data?.message || 'Failed to save settings.';
                }
            } finally {
                this.loading = false;
            }
        },
    },
});
