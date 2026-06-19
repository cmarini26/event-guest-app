import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import router from './router/index.js';
import App from './App.vue';
import './bootstrap.js';
import { useAuthStore } from '@/stores/auth.js';

const pinia = createPinia();
const app = createApp(App);
app.use(pinia);
app.use(router);

// Global response interceptor: 401 → clear session; 429 → annotate error
axios.interceptors.response.use(
    res => res,
    err => {
        const url = err.config?.url ?? '';
        const status = err.response?.status;
        if (status === 401 && !url.includes('/auth/me') && !url.includes('/auth/login')) {
            const auth = useAuthStore();
            auth.clearSession();
            if (router.currentRoute.value.meta?.requiresAuth) {
                router.push({ name: 'login' });
            }
        }
        if (status === 429 && err.response) {
            err.response.data = { message: 'Too many requests. Please wait a moment and try again.' };
        }
        return Promise.reject(err);
    }
);

app.mount('#app');
