<script setup>
import { ref, watch } from 'vue';
import { RouterView, RouterLink, useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';
import axios from 'axios';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const resendState = ref('idle'); // idle | sending | sent
const verifiedBanner = ref(false);

watch(() => route.query.verified, (val) => {
    if (val === '1') {
        verifiedBanner.value = true;
        auth.user && (auth.user.email_verified = true);
        router.replace({ ...route, query: { ...route.query, verified: undefined } });
        setTimeout(() => { verifiedBanner.value = false; }, 6000);
    }
}, { immediate: true });

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}

async function resendVerification() {
    resendState.value = 'sending';
    try {
        await axios.post('/api/auth/resend-verification');
        resendState.value = 'sent';
        setTimeout(() => { resendState.value = 'idle'; }, 5000);
    } catch {
        resendState.value = 'idle';
    }
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <RouterLink :to="{ name: 'dashboard' }" class="font-bold text-gray-900 text-lg tracking-tight">
                    guestlist<span class="text-indigo-600">.</span>
                </RouterLink>
                <div class="flex items-center gap-4">
                    <RouterLink v-if="auth.user?.is_admin" :to="{ name: 'admin' }" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 px-2 py-0.5 bg-indigo-50 rounded-full">
                        Admin
                    </RouterLink>
                    <RouterLink :to="{ name: 'settings' }" class="text-sm text-gray-600 hover:text-gray-900">
                        {{ auth.user?.name }}
                    </RouterLink>
                    <button @click="logout" class="text-sm text-gray-500 hover:text-gray-900">
                        Sign out
                    </button>
                </div>
            </div>
        </nav>

        <!-- Email not verified banner -->
        <div v-if="auth.user && auth.user.email_verified === false" class="bg-amber-50 border-b border-amber-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center gap-3 text-sm">
                <span class="text-amber-800">Please verify your email address to confirm your account.</span>
                <button @click="resendVerification" :disabled="resendState !== 'idle'"
                    class="shrink-0 text-xs font-medium text-amber-700 hover:text-amber-900 underline underline-offset-2 disabled:opacity-50">
                    {{ resendState === 'sent' ? 'Sent!' : resendState === 'sending' ? 'Sending...' : 'Resend email' }}
                </button>
            </div>
        </div>

        <!-- Email verified success banner -->
        <div v-if="verifiedBanner" class="bg-green-50 border-b border-green-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 text-sm text-green-800">
                Your email has been verified. Welcome!
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <RouterView />
        </main>
    </div>
</template>
