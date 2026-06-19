<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';
import axios from 'axios';

const auth = useAuthStore();
const router = useRouter();

const deletePassword = ref('');
const deleteError = ref('');
const deleteLoading = ref(false);
const showDeleteConfirm = ref(false);

const profile = reactive({ name: '', email: '', current_password: '' });
const profileErrors = ref({});
const profileSuccess = ref('');
const profileLoading = ref(false);

const passwords = reactive({ current_password: '', password: '', password_confirmation: '' });
const passwordErrors = ref({});
const passwordSuccess = ref('');
const passwordLoading = ref(false);

const setPasswordForm = reactive({ password: '', password_confirmation: '' });
const setPasswordErrors = ref({});
const setPasswordSuccess = ref('');
const setPasswordLoading = ref(false);

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

async function setPassword() {
    setPasswordErrors.value = {};
    setPasswordSuccess.value = '';
    setPasswordLoading.value = true;
    try {
        await axios.post('/api/auth/set-password', {
            password: setPasswordForm.password,
            password_confirmation: setPasswordForm.password_confirmation,
        });
        auth.user.has_password = true;
        setPasswordForm.password = '';
        setPasswordForm.password_confirmation = '';
        setPasswordSuccess.value = 'Password set. You can now sign in with email and password.';
    } catch (err) {
        setPasswordErrors.value = err.response?.data?.errors ?? {};
        if (!Object.keys(setPasswordErrors.value).length) {
            setPasswordErrors.value._general = err.response?.data?.message ?? 'Something went wrong.';
        }
    } finally {
        setPasswordLoading.value = false;
    }
}

async function deleteAccount() {
    deleteError.value = '';
    deleteLoading.value = true;
    try {
        await axios.delete('/api/auth/account', {
            data: auth.user?.has_password ? { password: deletePassword.value } : {},
        });
        auth.clearSession();
        router.push({ name: 'home' });
    } catch (err) {
        deleteError.value = err.response?.data?.errors?.password?.[0]
            ?? err.response?.data?.message
            ?? 'Something went wrong.';
        deleteLoading.value = false;
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
        <section v-if="auth.user?.has_password" class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
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
        <section class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
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

        <!-- Set password (Google-only accounts) -->
        <section v-if="!auth.user?.has_password" class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Set a password</h2>
            <p class="text-sm text-gray-500 mb-5">Add email/password sign-in to your account alongside Google.</p>
            <form @submit.prevent="setPassword" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                    <input v-model="setPasswordForm.password" type="password" autocomplete="new-password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                    <p class="mt-1 text-xs text-gray-400">Min 8 characters, must include letters and numbers.</p>
                    <p v-if="setPasswordErrors.password" class="mt-1 text-sm text-red-600">{{ setPasswordErrors.password[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
                    <input v-model="setPasswordForm.password_confirmation" type="password" autocomplete="new-password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                </div>
                <p v-if="setPasswordErrors._general" class="text-sm text-red-600">{{ setPasswordErrors._general }}</p>
                <p v-if="setPasswordSuccess" class="text-sm text-green-600">{{ setPasswordSuccess }}</p>
                <button type="submit" :disabled="setPasswordLoading"
                    class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-50">
                    {{ setPasswordLoading ? 'Setting...' : 'Set password' }}
                </button>
            </form>
        </section>

        <!-- Plan -->
        <section class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Plan</h2>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 capitalize">
                        {{ auth.user?.plan?.replace('_', ' ') ?? 'free' }}
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <template v-if="auth.user?.plan === 'free'">50 guests · 3 active events</template>
                        <template v-else-if="auth.user?.plan === 'event_pass'">300 guests per pass · Unlimited events</template>
                        <template v-else>Unlimited guests · Unlimited events</template>
                    </p>
                </div>
                <span v-if="auth.user?.plan === 'free'" class="text-xs text-indigo-600 font-medium">
                    Pro plan coming soon
                </span>
            </div>
        </section>

        <!-- Danger zone -->
        <section class="rounded-xl border border-red-200 p-6">
            <h2 class="text-base font-semibold text-red-700 mb-1">Danger zone</h2>
            <p class="text-sm text-gray-500 mb-4">
                Permanently delete your account and all associated events and guest data. This cannot be undone.
            </p>

            <div v-if="!showDeleteConfirm">
                <button @click="showDeleteConfirm = true"
                    class="px-4 py-2 border border-red-300 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                    Delete my account
                </button>
            </div>

            <div v-else class="space-y-3">
                <div v-if="auth.user?.has_password">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Confirm with your password
                    </label>
                    <input v-model="deletePassword" type="password" placeholder="Enter your password"
                        class="w-full px-3 py-2 border border-red-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
                </div>
                <p v-if="deleteError" class="text-sm text-red-600">{{ deleteError }}</p>
                <div class="flex gap-3">
                    <button @click="deleteAccount" :disabled="deleteLoading"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
                        {{ deleteLoading ? 'Deleting...' : 'Yes, delete my account' }}
                    </button>
                    <button @click="showDeleteConfirm = false; deletePassword = ''; deleteError = ''"
                        class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
