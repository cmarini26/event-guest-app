<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth.js';
import axios from 'axios';

const auth = useAuthStore();

const profile = reactive({ name: '', email: '', current_password: '' });
const profileErrors = ref({});
const profileSuccess = ref('');
const profileLoading = ref(false);

const passwords = reactive({ current_password: '', password: '', password_confirmation: '' });
const passwordErrors = ref({});
const passwordSuccess = ref('');
const passwordLoading = ref(false);

onMounted(() => {
    profile.name = auth.user?.name ?? '';
    profile.email = auth.user?.email ?? '';
});

async function saveProfile() {
    profileErrors.value = {};
    profileSuccess.value = '';
    profileLoading.value = true;
    try {
        const payload = { name: profile.name, email: profile.email };
        if (profile.email !== auth.user?.email) {
            payload.current_password = profile.current_password;
        }
        const { data } = await axios.put('/api/auth/profile', payload);
        auth.user.name = data.name;
        auth.user.email = data.email;
        profile.current_password = '';
        profileSuccess.value = 'Profile updated.';
    } catch (err) {
        profileErrors.value = err.response?.data?.errors ?? {};
        if (!Object.keys(profileErrors.value).length) {
            profileErrors.value._general = err.response?.data?.message ?? 'Something went wrong.';
        }
    } finally {
        profileLoading.value = false;
    }
}

async function savePassword() {
    passwordErrors.value = {};
    passwordSuccess.value = '';
    passwordLoading.value = true;
    try {
        await axios.put('/api/auth/password', {
            current_password: passwords.current_password,
            password: passwords.password,
            password_confirmation: passwords.password_confirmation,
        });
        passwords.current_password = '';
        passwords.password = '';
        passwords.password_confirmation = '';
        passwordSuccess.value = 'Password updated. Other sessions have been signed out.';
    } catch (err) {
        passwordErrors.value = err.response?.data?.errors ?? {};
        if (!Object.keys(passwordErrors.value).length) {
            passwordErrors.value._general = err.response?.data?.message ?? 'Something went wrong.';
        }
    } finally {
        passwordLoading.value = false;
    }
}
</script>

<template>
    <div class="max-w-lg">
        <h1 class="text-2xl font-bold text-gray-900 mb-8">Account settings</h1>

        <!-- Profile -->
        <section class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-900 mb-5">Profile</h2>
            <form @submit.prevent="saveProfile" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input v-model="profile.name" type="text" autocomplete="name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                    <p v-if="profileErrors.name" class="mt-1 text-sm text-red-600">{{ profileErrors.name[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input v-model="profile.email" type="email" autocomplete="email" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                    <p v-if="profileErrors.email" class="mt-1 text-sm text-red-600">{{ profileErrors.email[0] }}</p>
                </div>
                <div v-if="profile.email !== auth.user?.email">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current password <span class="text-gray-400">(required to change email)</span></label>
                    <input v-model="profile.current_password" type="password" autocomplete="current-password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                    <p v-if="profileErrors.current_password" class="mt-1 text-sm text-red-600">{{ profileErrors.current_password[0] }}</p>
                </div>
                <p v-if="profileErrors._general" class="text-sm text-red-600">{{ profileErrors._general }}</p>
                <p v-if="profileSuccess" class="text-sm text-green-600">{{ profileSuccess }}</p>
                <button type="submit" :disabled="profileLoading"
                    class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-50">
                    {{ profileLoading ? 'Saving...' : 'Save changes' }}
                </button>
            </form>
        </section>

        <!-- Password -->
        <section class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Change password</h2>
            <p class="text-sm text-gray-500 mb-5">Changing your password will sign out all other devices.</p>
            <form @submit.prevent="savePassword" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current password</label>
                    <input v-model="passwords.current_password" type="password" autocomplete="current-password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                    <p v-if="passwordErrors.current_password" class="mt-1 text-sm text-red-600">{{ passwordErrors.current_password[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                    <input v-model="passwords.password" type="password" autocomplete="new-password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                    <p class="mt-1 text-xs text-gray-400">Min 8 characters, must include letters and numbers.</p>
                    <p v-if="passwordErrors.password" class="mt-1 text-sm text-red-600">{{ passwordErrors.password[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm new password</label>
                    <input v-model="passwords.password_confirmation" type="password" autocomplete="new-password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                </div>
                <p v-if="passwordErrors._general" class="text-sm text-red-600">{{ passwordErrors._general }}</p>
                <p v-if="passwordSuccess" class="text-sm text-green-600">{{ passwordSuccess }}</p>
                <button type="submit" :disabled="passwordLoading"
                    class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-50">
                    {{ passwordLoading ? 'Updating...' : 'Update password' }}
                </button>
            </form>
        </section>

        <!-- Connected accounts -->
        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Connected accounts</h2>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    <div>
                        <div class="text-sm font-medium text-gray-800">Google</div>
                        <div class="text-xs text-gray-500">{{ auth.user?.has_google ? 'Connected' : 'Not connected' }}</div>
                    </div>
                </div>
                <a v-if="!auth.user?.has_google" href="/auth/google/redirect"
                    class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                    Connect
                </a>
                <span v-else class="text-xs text-green-600 font-medium">✓ Active</span>
            </div>
        </section>
    </div>
</template>
