<template>
    <div class="flex min-h-screen bg-white">
        <AppSidebar />
        <div class="flex-1 flex flex-col">
            <header class="flex items-center justify-end px-6 py-3 border-b border-gray-100">
                <button
                    @click="handleLogout"
                    class="text-gray-400 hover:text-gray-600 transition-colors"
                    title="Logout"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </header>
            <main class="flex-1 p-6">
                <router-view />
            </main>
        </div>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppSidebar from '@/components/AppSidebar.vue';

const router = useRouter();
const authStore = useAuthStore();

const handleLogout = async () => {
    await authStore.logout();
    router.push({ name: 'login' });
};
</script>
