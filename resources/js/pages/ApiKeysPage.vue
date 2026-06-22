<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import axios from 'axios';

const keys = ref([]);
const loading = ref(true);
const creating = ref(false);
const createError = ref('');
const newKeyName = ref('');
const newKeyExpiry = ref('');
const justCreated = ref(null); // { key, token } shown once
const copiedToken = ref(false);

onMounted(fetchKeys);

async function fetchKeys() {
    loading.value = true;
    try {
        const res = await axios.get('/api/api-keys');
        keys.value = res.data;
    } finally {
        loading.value = false;
    }
}

async function create() {
    if (!newKeyName.value.trim()) return;
    creating.value = true;
    createError.value = '';
    try {
        const payload = { name: newKeyName.value.trim() };
        if (newKeyExpiry.value) payload.expires_at = newKeyExpiry.value;
        const res = await axios.post('/api/api-keys', payload);
        justCreated.value = res.data;
        newKeyName.value = '';
        newKeyExpiry.value = '';
        await fetchKeys();
    } catch (e) {
        createError.value = e.response?.data?.message ?? 'Failed to create key.';
    } finally {
        creating.value = false;
    }
}

async function revoke(key) {
    if (!confirm(`Revoke "${key.name}"? This cannot be undone.`)) return;
    await axios.delete(`/api/api-keys/${key.id}`);
    await fetchKeys();
}

async function copyToken() {
    if (!justCreated.value?.token) return;
    try {
        await navigator.clipboard.writeText(justCreated.value.token);
        copiedToken.value = true;
        setTimeout(() => { copiedToken.value = false; }, 1500);
    } catch {
        // clipboard unavailable
    }
}

function formatDate(d) {
    return d ? new Date(d).toLocaleDateString() : '—';
}
</script>

<template>
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="flex items-center gap-3 mb-6">
            <RouterLink :to="{ name: 'settings' }" class="text-sm text-gray-500 hover:text-gray-700">
                ← Back to settings
            </RouterLink>
        </div>

        <h1 class="text-2xl font-bold mb-1">API Keys</h1>
        <p class="text-gray-500 mb-8">
            Use API keys to authenticate programmatic access to your account.
            Keys start with <code class="text-sm bg-gray-100 px-1 rounded">gl</code> and are shown in full only once.
        </p>

        <!-- Newly created key — show once -->
        <div v-if="justCreated"
            class="bg-green-50 border border-green-200 rounded-xl p-5 mb-6">
            <p class="text-sm font-semibold text-green-800 mb-2">
                Key created — copy it now, it won't be shown again.
            </p>
            <div class="flex items-center gap-2">
                <code class="flex-1 text-xs bg-white border border-green-200 rounded px-3 py-2 font-mono break-all">
                    {{ justCreated.token }}
                </code>
                <button @click="copyToken"
                    class="px-3 py-2 text-sm bg-green-700 text-white rounded-lg hover:bg-green-800 whitespace-nowrap">
                    {{ copiedToken ? 'Copied!' : 'Copy' }}
                </button>
            </div>
            <button @click="justCreated = null" class="mt-3 text-xs text-green-700 hover:underline">
                I've saved it, dismiss
            </button>
        </div>

        <!-- Create form -->
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Create a new key</h2>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Name</label>
                    <input v-model="newKeyName" type="text" placeholder="e.g. CI pipeline, Zapier"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        @keyup.enter="create" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">
                        Expires (optional)
                    </label>
                    <input v-model="newKeyExpiry" type="date"
                        :min="new Date().toISOString().slice(0,10)"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <p v-if="createError" class="text-sm text-red-500">{{ createError }}</p>
                <button @click="create" :disabled="creating || !newKeyName.trim()"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    {{ creating ? 'Creating…' : 'Create key' }}
                </button>
            </div>
        </div>

        <!-- Key list -->
        <div v-if="loading" class="text-gray-400">Loading…</div>
        <template v-else>
            <div v-if="keys.length" class="space-y-3">
                <div v-for="key in keys" :key="key.id"
                    class="bg-white border border-gray-200 rounded-xl p-4 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-medium text-sm">{{ key.name }}</span>
                            <span v-if="!key.is_active"
                                class="text-xs font-medium text-red-700 bg-red-100 px-2 py-0.5 rounded-full">
                                Revoked
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 font-mono truncate">{{ key.prefix }}…</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Created {{ formatDate(key.created_at) }}
                            <template v-if="key.last_used_at">
                                · Last used {{ formatDate(key.last_used_at) }}
                            </template>
                            <template v-if="key.expires_at">
                                · Expires {{ formatDate(key.expires_at) }}
                            </template>
                        </p>
                    </div>
                    <button v-if="key.is_active" @click="revoke(key)"
                        class="shrink-0 text-sm text-red-500 hover:underline">
                        Revoke
                    </button>
                </div>
            </div>
            <p v-else class="text-gray-400">No API keys yet.</p>
        </template>
    </div>
</template>
