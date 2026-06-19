<script setup>
import { ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const router = useRouter();

const name = ref('');
const email = ref('');
const password = ref('');
const password_confirmation = ref('');
const errors = ref({});

async function submit() {
    errors.value = {};
    const result = await auth.register(name.value, email.value, password.value, password_confirmation.value);
    if (result.ok) {
        router.push({ name: 'dashboard' });
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
            <h1 class="text-2xl font-bold text-gray-900 mb-8 text-center">Create your account</h1>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input
                        v-model="name"
                        type="text"
                        autocomplete="name"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900"
                    />
                    <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
                </div>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900"
                    />
                    <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password[0] }}</p>
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
                <button
                    type="submit"
                    :disabled="auth.loading"
                    class="w-full py-2.5 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 disabled:opacity-50"
                >
                    {{ auth.loading ? 'Creating account...' : 'Create account' }}
                </button>
            </form>
            <p class="mt-6 text-center text-sm text-gray-600">
                Already have an account?
                <RouterLink :to="{ name: 'login' }" class="text-gray-900 font-medium hover:underline">
                    Sign in
                </RouterLink>
            </p>
        </div>
    </div>
</template>
