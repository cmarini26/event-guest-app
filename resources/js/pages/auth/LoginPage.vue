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

async function submit() {
    errors.value = {};
    const result = await auth.login(email.value, password.value);
    if (result.ok) {
        const redirect = route.query.redirect ?? '/dashboard';
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
