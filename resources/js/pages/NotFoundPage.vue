<script setup>
import { RouterLink } from 'vue-router';
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
        <div class="text-center">
            <p class="text-5xl font-bold text-gray-200 mb-4">404</p>
            <h1 class="text-xl font-semibold text-gray-900 mb-2">Page not found</h1>
            <p class="text-sm text-gray-500 mb-6">The page you're looking for doesn't exist.</p>
            <RouterLink to="/" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800">
                Go home
            </RouterLink>
        </div>
    </div>
</template>
