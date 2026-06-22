<script setup>
import { RouterView } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';
import { registerPush } from '@/composables/usePush.js';
import { onMounted, watch } from 'vue';

const auth = useAuthStore();

onMounted(() => auth.fetchUser());

// Register push once the user is authenticated (native only; no-op on web)
watch(() => auth.isAuthenticated, (authed) => {
    if (authed) registerPush();
});
</script>

<template>
    <div v-if="!auth.ready" class="min-h-screen bg-white" />
    <RouterView v-else />
</template>
