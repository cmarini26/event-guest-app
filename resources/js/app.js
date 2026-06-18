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

// Redirect to login on expired token; skip /auth/me (handled by fetchUser)
axios.interceptors.response.use(
    res => res,
    err => {
        const url = err.config?.url ?? '';
        if (err.response?.status === 401 && !url.includes('/auth/me') && !url.includes('/auth/login')) {
            const auth = useAuthStore();
            auth.clearSession();
            if (router.currentRoute.value.meta?.requiresAuth) {
                router.push({ name: 'login' });
            }
        }
        return Promise.reject(err);
    }
);

app.mount('#app');
