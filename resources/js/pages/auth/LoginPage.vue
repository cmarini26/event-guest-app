<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const errors = ref({});

const justReset = computed(() => route.query.reset === '1');
const googleError = computed(() => route.query.error === 'google_failed');

async function submit() {
    errors.value = {};
    const result = await auth.login(email.value, password.value);
    if (result.ok) {
        const raw = route.query.redirect;
        const redirect = raw && raw.startsWith('/') && !raw.startsWith('//') ? raw : '/dashboard';
        router.push(redirect);
    } else {
        errors.value = result.errors;
    }
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <RouterLink :to="{ name: 'home' }" class="block text-center font-bold text-gray-900 text-xl tracking-tight mb-8">
                guestlist<span class="text-indigo-600">.</span>
            </RouterLink>
            <h1 class="text-2xl font-bold text-gray-900 mb-8 text-center">Sign in</h1>

            <div v-if="justReset" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                Password reset successfully. Sign in with your new password.
            </div>
            <div v-if="googleError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                Google sign-in failed. Please try again or use email and password.
            </div>

            <a
                href="/auth/google/redirect"
                class="flex items-center justify-center gap-3 w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors mb-5"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Continue with Google
            </a>

            <div class="relative mb-5">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200" />
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-3 text-xs text-gray-400">or sign in with email</span>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        v-model="email"
                        type="email"
                        autocomplete="email"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900"
                    />
                    <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email[0] }}</p>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700">Password</label>
                        <RouterLink :to="{ name: 'forgot-password' }" class="text-xs text-gray-500 hover:text-gray-700">
                            Forgot password?
                        </RouterLink>
                    </div>
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="auth.loading"
                    class="w-full py-2.5 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 disabled:opacity-50"
                >
                    {{ auth.loading ? 'Signing in...' : 'Sign in' }}
                </button>
            </form>
            <p class="mt-6 text-center text-sm text-gray-600">
                No account?
                <RouterLink :to="{ name: 'register' }" class="text-gray-900 font-medium hover:underline">
                    Sign up free
                </RouterLink>
            </p>
        </div>
    </div>
</template>
