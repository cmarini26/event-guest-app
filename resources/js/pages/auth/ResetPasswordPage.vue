<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const router = useRouter();

const email = ref('');
const password = ref('');
const password_confirmation = ref('');
const error = ref('');
const loading = ref(false);

onMounted(() => {
    email.value = route.query.email ?? '';
});

async function submit() {
    error.value = '';
    loading.value = true;
    try {
        await axios.post('/api/auth/reset-password', {
            token: route.params.token,
            email: email.value,
            password: password.value,
            password_confirmation: password_confirmation.value,
        });
        router.push({ name: 'login', query: { reset: '1' } });
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
            <RouterLink :to="{ name: 'home' }" class="block text-center font-bold text-gray-900 text-xl tracking-tight mb-8">
                guestlist<span class="text-indigo-600">.</span>
            </RouterLink>
            <h1 class="text-2xl font-bold text-gray-900 mb-8 text-center">Set new password</h1>
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
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
                    <input
                        v-model="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900"
                    />
                </div>
                <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full py-2.5 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 disabled:opacity-50"
                >
                    {{ loading ? 'Resetting...' : 'Reset password' }}
                </button>
            </form>
        </div>
    </div>
</template>
