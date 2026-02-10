import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        checked: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
    },

    actions: {
        async fetchUser() {
            try {
                const response = await axios.get('/api/user');
                this.user = response.data;
            } catch (error) {
                this.user = null;
            } finally {
                this.checked = true;
            }
        },

        async login(credentials) {
            await axios.get('/sanctum/csrf-cookie');
            const response = await axios.post('/api/login', credentials);
            this.user = response.data.user;
            return response.data;
        },

        async logout() {
            await axios.post('/api/logout');
            this.user = null;
        },
    },
});
