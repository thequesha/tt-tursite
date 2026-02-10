<template>
    <div class="w-full max-w-md">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="flex items-center justify-center gap-2 mb-6">
                <svg class="w-6 h-6 text-blue-500" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8 5v14l11-7z"/>
                </svg>
                <h1 class="text-2xl font-bold text-gray-900">Daily Grow</h1>
            </div>

            <form @submit.prevent="handleLogin" class="space-y-4">
                <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg p-3">
                    {{ error }}
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        placeholder="admin@example.com"
                    />
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Пароль</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        placeholder="password"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2.5 px-4 rounded-lg text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span v-if="loading">Загрузка...</span>
                    <span v-else>Войти</span>
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const authStore = useAuthStore();

const loading = ref(false);
const error = ref('');

const form = reactive({
    email: '',
    password: '',
});

const handleLogin = async () => {
    loading.value = true;
    error.value = '';

    try {
        await authStore.login(form);
        router.push({ name: 'reviews' });
    } catch (err) {
        if (err.response?.status === 401) {
            error.value = 'Неверный email или пароль.';
        } else if (err.response?.status === 422) {
            const errors = err.response.data.errors;
            error.value = Object.values(errors).flat().join(' ');
        } else {
            error.value = 'Произошла ошибка. Попробуйте позже.';
        }
    } finally {
        loading.value = false;
    }
};
</script>
