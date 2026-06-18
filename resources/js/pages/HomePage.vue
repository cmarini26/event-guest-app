<script setup>
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
</script>

<template>
    <div class="max-w-4xl mx-auto px-4 py-20 text-center">
        <h1 class="text-5xl font-bold text-gray-900 mb-6">
            Beautiful events,<br>effortless RSVPs.
        </h1>
        <p class="text-xl text-gray-600 mb-10 max-w-2xl mx-auto">
            Guest management for private events and small corporate gatherings.
            Polished landing pages, granular preferences, automated follow-ups.
        </p>
        <div class="flex gap-4 justify-center">
            <RouterLink
                v-if="auth.isAuthenticated"
                :to="{ name: 'dashboard' }"
                class="px-6 py-3 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800"
            >
                Go to Dashboard
            </RouterLink>
            <template v-else>
                <RouterLink
                    :to="{ name: 'register' }"
                    class="px-6 py-3 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800"
                >
                    Get started free
                </RouterLink>
                <RouterLink
                    :to="{ name: 'login' }"
                    class="px-6 py-3 bg-white text-gray-900 rounded-lg font-medium border border-gray-300 hover:bg-gray-50"
                >
                    Sign in
                </RouterLink>
            </template>
        </div>
    </div>
</template>
