<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import axios from 'axios';

const email = ref('');
const status = ref('');
const error = ref('');
const loading = ref(false);

async function submit() {
    error.value = '';
    status.value = '';
    loading.value = true;
    try {
        const { data } = await axios.post('/api/auth/forgot-password', { email: email.value });
        status.value = data.message;
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Something went wrong.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <h1 class="text-2xl font-bold text-gray-900 mb-2 text-center">Forgot your password?</h1>
            <p class="text-sm text-gray-500 text-center mb-8">
                Enter your email and we'll send a reset link.
            </p>

            <div v-if="status" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                {{ status }}
            </div>

            <form v-else @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        v-model="email"
                        type="email"
                        autocomplete="email"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900"
                    />
                    <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full py-2.5 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 disabled:opacity-50"
                >
                    {{ loading ? 'Sending...' : 'Send reset link' }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-600">
                <RouterLink :to="{ name: 'login' }" class="text-gray-900 font-medium hover:underline">
                    Back to sign in
                </RouterLink>
            </p>
        </div>
    </div>
</template>
