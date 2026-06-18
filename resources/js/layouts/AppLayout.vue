<script setup>
import { RouterView, RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const router = useRouter();

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <RouterLink :to="{ name: 'dashboard' }" class="text-lg font-semibold text-gray-900">
                    Event App
                </RouterLink>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600">{{ auth.user?.name }}</span>
                    <button @click="logout" class="text-sm text-gray-500 hover:text-gray-900">
                        Sign out
                    </button>
                </div>
            </div>
        </nav>
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <RouterView />
        </main>
    </div>
</template>
