<script setup>
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

onMounted(async () => {
    const token = route.query.token;
    if (token) {
        await auth.loginWithToken(token);
        if (auth.isAuthenticated) {
            router.replace({ name: 'dashboard' });
            return;
        }
    }
    router.replace({ name: 'login', query: { error: 'google_failed' } });
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center">
        <p class="text-sm text-gray-400">Signing you in...</p>
    </div>
</template>
