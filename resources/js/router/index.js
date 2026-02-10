import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

import AuthLayout from '@/layouts/AuthLayout.vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

import LoginPage from '@/pages/LoginPage.vue';
import ReviewsPage from '@/pages/ReviewsPage.vue';
import SettingsPage from '@/pages/SettingsPage.vue';

const routes = [
    {
        path: '/login',
        component: AuthLayout,
        children: [
            {
                path: '',
                name: 'login',
                component: LoginPage,
                meta: { guest: true },
            },
        ],
    },
    {
        path: '/',
        component: DashboardLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: '/reviews',
            },
            {
                path: 'reviews',
                name: 'reviews',
                component: ReviewsPage,
            },
            {
                path: 'settings',
                name: 'settings',
                component: SettingsPage,
            },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    if (!authStore.checked) {
        await authStore.fetchUser();
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return next({ name: 'login' });
    }

    if (to.meta.guest && authStore.isAuthenticated) {
        return next({ name: 'reviews' });
    }

    next();
});

export default router;
