<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useCustomDomainsStore } from '@/stores/customDomains.js';
import { useAuthStore } from '@/stores/auth.js';

const store = useCustomDomainsStore();
const auth = useAuthStore();

const newDomain = ref('');
const adding = ref(false);
const addError = ref('');
const verifyingId = ref(null);
const verifyError = ref('');
const copiedId = ref(null);

const canUse = ['pro', 'business'].includes(auth.user?.plan);

onMounted(() => {
    if (canUse) store.fetchDomains();
});

async function add() {
    adding.value = true;
    addError.value = '';
    try {
        await store.addDomain(newDomain.value.trim().toLowerCase());
        newDomain.value = '';
    } catch (e) {
        addError.value = e.response?.data?.errors?.domain?.[0]
            ?? e.response?.data?.message
            ?? 'Failed to add domain.';
    } finally {
        adding.value = false;
    }
}

async function verify(d) {
    verifyingId.value = d.id;
    verifyError.value = '';
    try {
        await store.verifyDomain(d.id);
    } catch (e) {
        verifyError.value = e.response?.data?.message ?? 'Verification failed.';
    } finally {
        verifyingId.value = null;
    }
}

async function remove(d) {
    if (!confirm(`Remove ${d.domain}?`)) return;
    await store.deleteDomain(d.id);
}

async function copyValue(d) {
    try {
        await navigator.clipboard.writeText(d.dns_record.value);
        copiedId.value = d.id;
        setTimeout(() => { if (copiedId.value === d.id) copiedId.value = null; }, 1500);
    } catch {
        // clipboard unavailable; no-op
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

        <h1 class="text-2xl font-bold mb-1">Custom Domains</h1>
        <p class="text-gray-500 mb-8">Serve your RSVP pages from your own domain.</p>

        <!-- Plan gate -->
        <div v-if="!canUse" class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-amber-800">
            Custom domains are available on the <strong>Pro</strong> and <strong>Business</strong> plans.
            <RouterLink :to="{ name: 'settings' }" class="underline">Upgrade your plan</RouterLink> to enable them.
        </div>

        <template v-else>
            <!-- Add -->
            <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
                <label class="block text-sm font-medium mb-1">Add a domain</label>
                <div class="flex gap-2">
                    <input v-model="newDomain" type="text" placeholder="events.yourbrand.com"
                        class="flex-1 border border-gray-300 rounded-lg px-3 py-2"
                        @keyup.enter="add" />
                    <button @click="add" :disabled="adding || !newDomain.trim()"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        {{ adding ? 'Adding…' : 'Add' }}
                    </button>
                </div>
                <p v-if="addError" class="text-sm text-red-500 mt-2">{{ addError }}</p>
            </div>

            <p v-if="verifyError" class="text-sm text-red-500 mb-4">{{ verifyError }}</p>

            <!-- List -->
            <div v-if="store.loading" class="text-gray-500">Loading…</div>
            <div v-else-if="store.domains.length" class="space-y-4">
                <div v-for="d in store.domains" :key="d.id"
                    class="bg-white border border-gray-200 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{ d.domain }}</span>
                            <span v-if="d.is_verified"
                                class="text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
                                Verified
                            </span>
                            <span v-else
                                class="text-xs font-medium text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">
                                Pending
                            </span>
                        </div>
                        <button @click="remove(d)" class="text-sm text-red-500 hover:underline">Remove</button>
                    </div>

                    <!-- DNS instructions for unverified -->
                    <div v-if="!d.is_verified" class="bg-gray-50 rounded-lg p-4 text-sm">
                        <p class="text-gray-600 mb-2">
                            Add this <strong>TXT</strong> record at your DNS provider, then click Verify:
                        </p>
                        <div class="font-mono text-xs bg-white border border-gray-200 rounded p-3 space-y-1">
                            <div><span class="text-gray-400">Host:</span> {{ d.dns_record.host }}</div>
                            <div class="flex items-center gap-2">
                                <span><span class="text-gray-400">Value:</span> {{ d.dns_record.value }}</span>
                                <button @click="copyValue(d)" class="text-indigo-600 hover:underline">
                                    {{ copiedId === d.id ? 'Copied!' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                        <button @click="verify(d)" :disabled="verifyingId === d.id"
                            class="mt-3 px-3 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">
                            {{ verifyingId === d.id ? 'Checking…' : 'Verify' }}
                        </button>
                    </div>
                </div>
            </div>
            <p v-else class="text-gray-400">No custom domains yet.</p>
        </template>
    </div>
</template>
