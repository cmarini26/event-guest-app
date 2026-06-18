import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(localStorage.getItem('token'));
    const loading = ref(false);
    const ready = ref(false);

    const isAuthenticated = computed(() => !!user.value);

    function setToken(t) {
        token.value = t;
        if (t) {
            localStorage.setItem('token', t);
            axios.defaults.headers.common['Authorization'] = `Bearer ${t}`;
        } else {
            localStorage.removeItem('token');
            delete axios.defaults.headers.common['Authorization'];
        }
    }

    let _fetchPromise = null;

    async function fetchUser() {
        if (_fetchPromise) return _fetchPromise;
        _fetchPromise = (async () => {
            if (!token.value) { ready.value = true; return; }
            setToken(token.value);
            try {
                const { data } = await axios.get('/api/auth/me');
                user.value = data;
            } catch {
                setToken(null);
                user.value = null;
            } finally {
                ready.value = true;
            }
        })();
        return _fetchPromise;
    }

    async function login(email, password) {
        loading.value = true;
        try {
            const { data } = await axios.post('/api/auth/login', { email, password });
            setToken(data.token);
            user.value = data.user;
            return { ok: true };
        } catch (err) {
            return { ok: false, errors: err.response?.data?.errors ?? {} };
        } finally {
            loading.value = false;
        }
    }

    async function register(name, email, password, password_confirmation) {
        loading.value = true;
        try {
            const { data } = await axios.post('/api/auth/register', {
                name, email, password, password_confirmation,
            });
            setToken(data.token);
            user.value = data.user;
            return { ok: true };
        } catch (err) {
            return { ok: false, errors: err.response?.data?.errors ?? {} };
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        try {
            await axios.post('/api/auth/logout');
        } finally {
            setToken(null);
            user.value = null;
            _fetchPromise = null;
        }
    }

    function clearSession() {
        setToken(null);
        user.value = null;
        _fetchPromise = null;
    }

    return { user, token, loading, ready, isAuthenticated, fetchUser, login, register, logout, clearSession };
});
