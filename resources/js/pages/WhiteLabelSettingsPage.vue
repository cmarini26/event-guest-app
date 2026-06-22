<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';
import axios from 'axios';

const auth = useAuthStore();

const settings = ref(null);
const loading = ref(true);
const saving = ref(false);
const saveError = ref('');
const saveSuccess = ref(false);
const uploadingLogo = ref(false);
const logoError = ref('');

const form = ref({
    brand_name: '',
    primary_color: '#4f46e5',
    accent_color: '#6366f1',
    email_sender_name: '',
    hide_branding: false,
});

onMounted(async () => {
    try {
        const res = await axios.get('/api/white-label');
        if (res.data && res.data.id) {
            settings.value = res.data;
            form.value = {
                brand_name: res.data.brand_name ?? '',
                primary_color: res.data.primary_color ?? '#4f46e5',
                accent_color: res.data.accent_color ?? '#6366f1',
                email_sender_name: res.data.email_sender_name ?? '',
                hide_branding: res.data.hide_branding ?? false,
            };
        }
    } finally {
        loading.value = false;
    }
});

async function save() {
    saving.value = true;
    saveError.value = '';
    saveSuccess.value = false;
    try {
        const res = await axios.put('/api/white-label', form.value);
        settings.value = res.data;
        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 2000);
    } catch (e) {
        saveError.value = e.response?.data?.message ?? 'Failed to save settings.';
    } finally {
        saving.value = false;
    }
}

async function uploadLogo(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    uploadingLogo.value = true;
    logoError.value = '';
    try {
        const fd = new FormData();
        fd.append('logo', file);
        const res = await axios.post('/api/white-label/logo', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        settings.value = res.data;
    } catch (e) {
        logoError.value = e.response?.data?.errors?.logo?.[0] ?? e.response?.data?.message ?? 'Upload failed.';
    } finally {
        uploadingLogo.value = false;
    }
}

async function removeLogo() {
    try {
        const res = await axios.delete('/api/white-label/logo');
        settings.value = res.data;
    } catch {
        // ignore
    }
}
</script>

<template>
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="flex items-center gap-3 mb-6">
            <RouterLink :to="{ name: 'settings' }" class="text-sm text-gray-500 hover:text-gray-700">
                ← Back to settings
            </RouterLink>
        </div>

        <h1 class="text-2xl font-bold mb-1">White-label Branding</h1>
        <p class="text-gray-500 mb-8">
            Customize the look of your RSVP pages and emails. Guests will see your brand, not guestlist.
        </p>

        <div v-if="loading" class="text-gray-400">Loading…</div>

        <template v-else>
            <!-- Logo -->
            <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
                <h2 class="text-base font-semibold mb-4">Logo</h2>
                <div v-if="settings?.logo_url" class="mb-4">
                    <img :src="settings.logo_url" alt="Brand logo" class="h-14 object-contain rounded" />
                </div>
                <div class="flex gap-3 items-center">
                    <label class="cursor-pointer px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition-colors">
                        {{ uploadingLogo ? 'Uploading…' : settings?.logo_url ? 'Replace logo' : 'Upload logo' }}
                        <input type="file" accept="image/*" class="hidden" @change="uploadLogo" :disabled="uploadingLogo" />
                    </label>
                    <button v-if="settings?.logo_url" @click="removeLogo"
                        class="text-sm text-red-500 hover:underline">
                        Remove
                    </button>
                </div>
                <p v-if="logoError" class="text-sm text-red-500 mt-2">{{ logoError }}</p>
                <p class="text-xs text-gray-400 mt-2">PNG, JPG, SVG, WebP — max 2 MB</p>
            </div>

            <!-- Settings form -->
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="text-base font-semibold mb-4">Brand settings</h2>

                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Brand name</label>
                        <input v-model="form.brand_name" type="text" placeholder="Acme Events"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <p class="text-xs text-gray-400 mt-1">
                            Shown in the RSVP page header when no logo is set.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Primary color</label>
                            <div class="flex items-center gap-2">
                                <input v-model="form.primary_color" type="color"
                                    class="w-10 h-10 border border-gray-300 rounded cursor-pointer" />
                                <input v-model="form.primary_color" type="text" placeholder="#4f46e5"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Accent color</label>
                            <div class="flex items-center gap-2">
                                <input v-model="form.accent_color" type="color"
                                    class="w-10 h-10 border border-gray-300 rounded cursor-pointer" />
                                <input v-model="form.accent_color" type="text" placeholder="#6366f1"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email sender name</label>
                        <input v-model="form.email_sender_name" type="text" placeholder="Acme Events"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <p class="text-xs text-gray-400 mt-1">
                            Overrides "guestlist." as the sender name in all outgoing emails.
                        </p>
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="form.hide_branding" type="checkbox"
                            class="w-4 h-4 rounded border-gray-300 text-indigo-600" />
                        <span class="text-sm text-gray-700">
                            Hide "Powered by guestlist." on RSVP pages
                        </span>
                    </label>
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <button @click="save" :disabled="saving"
                        class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                        {{ saving ? 'Saving…' : 'Save changes' }}
                    </button>
                    <span v-if="saveSuccess" class="text-sm text-green-600">Saved!</span>
                    <span v-if="saveError" class="text-sm text-red-500">{{ saveError }}</span>
                </div>
            </div>
        </template>
    </div>
</template>
