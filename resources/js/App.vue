<script setup>
import { RouterView } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';
import { onMounted } from 'vue';

const auth = useAuthStore();

onMounted(() => auth.fetchUser());
</script>

<template>
    <RouterView />
</template>
