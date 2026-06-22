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
            { path: 'privacy', name: 'privacy', component: () => import('@/pages/PrivacyPage.vue') },
            { path: 'terms', name: 'terms', component: () => import('@/pages/TermsPage.vue') },
        ],
    },
    {
        path: '/dashboard',
        component: () => import('@/layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'dashboard', component: () => import('@/pages/events/DashboardPage.vue') },
            { path: '/settings', name: 'settings', component: () => import('@/pages/AccountSettingsPage.vue') },
            { path: '/settings/domains', name: 'custom-domains', component: () => import('@/pages/CustomDomainsPage.vue') },
            { path: '/settings/white-label', name: 'white-label', component: () => import('@/pages/WhiteLabelSettingsPage.vue') },
            { path: '/settings/api-keys', name: 'api-keys', component: () => import('@/pages/ApiKeysPage.vue') },
            { path: '/events/create', name: 'events.create', component: () => import('@/pages/events/CreateEventPage.vue') },
            { path: '/events/:id', name: 'events.show', component: () => import('@/pages/events/EventDetailPage.vue') },
            { path: '/events/:id/edit', name: 'events.edit', component: () => import('@/pages/events/EditEventPage.vue') },
            { path: '/events/:id/analytics', name: 'events.analytics', component: () => import('@/pages/events/EventAnalyticsPage.vue') },
            { path: '/events/:id/schedule', name: 'events.schedule', component: () => import('@/pages/events/EventSchedulePage.vue') },
            { path: '/events/:id/files', name: 'events.files', component: () => import('@/pages/events/EventFilesPage.vue') },
            { path: '/events/:id/check-in', name: 'events.checkin', component: () => import('@/pages/events/EventCheckInPage.vue') },
            { path: '/admin', name: 'admin', meta: { requiresAdmin: true }, component: () => import('@/pages/admin/AdminDashboardPage.vue') },
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
    if (to.meta.requiresAdmin && !auth.user?.is_admin) {
        return { name: 'dashboard' };
    }
    if ((to.name === 'login' || to.name === 'register') && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }
});

export default router;
