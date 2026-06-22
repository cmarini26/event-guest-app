<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { scanRsvpToken } from '@/composables/useScanner.js';
import axios from 'axios';

const route = useRoute();

const scanning = ref(false);
const result = ref(null);  // { name, status, message, error }
const recentCheckIns = ref([]);

async function scan() {
    scanning.value = true;
    result.value = null;
    try {
        const token = await scanRsvpToken();
        if (!token) { scanning.value = false; return; }

        const { data } = await axios.post(`/api/rsvp/${token}/check-in`);
        result.value = { success: true, message: data.message, checkedInAt: data.checked_in_at };
        recentCheckIns.value.unshift({ message: data.message, time: new Date() });
        if (recentCheckIns.value.length > 10) recentCheckIns.value.pop();
    } catch (err) {
        const msg = err.response?.data?.message ?? 'Check-in failed.';
        result.value = { success: false, message: msg };
    } finally {
        scanning.value = false;
    }
}

function formatTime(d) {
    return new Date(d).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}
</script>

<template>
    <div class="max-w-md mx-auto px-4 py-8">
        <div class="flex items-center gap-3 mb-6">
            <RouterLink :to="{ name: 'events.show', params: { id: route.params.id } }"
                class="text-sm text-gray-500 hover:text-gray-700">
                ← Back to event
            </RouterLink>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-1">Check-in</h1>
        <p class="text-gray-500 mb-8">Scan a guest's QR code to mark them checked in.</p>

        <!-- Scan button -->
        <button @click="scan" :disabled="scanning"
            class="w-full py-4 bg-indigo-600 text-white rounded-2xl text-base font-semibold hover:bg-indigo-700 disabled:opacity-50 transition-colors mb-6 flex items-center justify-center gap-3">
            <svg v-if="!scanning" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
            </svg>
            {{ scanning ? 'Scanning…' : 'Scan QR code' }}
        </button>

        <!-- Result -->
        <transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 translate-y-2">
            <div v-if="result" :class="[
                    'rounded-2xl p-5 mb-6 text-center',
                    result.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'
                ]">
                <p class="text-2xl mb-2">{{ result.success ? '✓' : '✕' }}</p>
                <p :class="['font-semibold', result.success ? 'text-green-800' : 'text-red-700']">
                    {{ result.message }}
                </p>
            </div>
        </transition>

        <!-- Recent check-ins -->
        <div v-if="recentCheckIns.length">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Recent</h2>
            <div class="space-y-2">
                <div v-for="(ci, i) in recentCheckIns" :key="i"
                    class="flex items-center justify-between bg-white border border-gray-200 rounded-xl px-4 py-3">
                    <span class="text-sm text-gray-800">{{ ci.message }}</span>
                    <span class="text-xs text-gray-400">{{ formatTime(ci.time) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
