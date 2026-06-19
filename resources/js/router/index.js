import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';

const routes = [
    {
        path: '/',
        component: () => import('@/layouts/GuestLayout.vue'),
        children: [
            { path: '', name: 'home', component: () => import('@/pages/HomePage.vue') },
            { path: 'login', name: 'login', component: () => import('@/pages/auth/LoginPage.vue') },
            { path: 'register', name: 'register', component: () => import('@/pages/auth/RegisterPage.vue') },
            { path: 'forgot-password', name: 'forgot-password', component: () => import('@/pages/auth/ForgotPasswordPage.vue') },
            { path: 'reset-password/:token', name: 'reset-password', component: () => import('@/pages/auth/ResetPasswordPage.vue') },
            { path: 'rsvp/:token', name: 'rsvp', component: () => import('@/pages/rsvp/RsvpPage.vue') },
            { path: 'auth/callback', name: 'auth.callback', component: () => import('@/pages/auth/AuthCallbackPage.vue') },
        ],
    },
    {
        path: '/dashboard',
        component: () => import('@/layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'dashboard', component: () => import('@/pages/events/DashboardPage.vue') },
            { path: '/events/create', name: 'events.create', component: () => import('@/pages/events/CreateEventPage.vue') },
            { path: '/events/:id', name: 'events.show', component: () => import('@/pages/events/EventDetailPage.vue') },
            { path: '/events/:id/edit', name: 'events.edit', component: () => import('@/pages/events/EditEventPage.vue') },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/NotFoundPage.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.ready) {
        await auth.fetchUser();
    }
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    if ((to.name === 'login' || to.name === 'register') && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }
});

export default router;
