<script setup>
import { onMounted, computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useEventsStore } from '@/stores/events.js';
import { useAuthStore } from '@/stores/auth.js';

const eventsStore = useEventsStore();
const auth = useAuthStore();

onMounted(() => eventsStore.fetchEvents());

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    published: 'bg-green-100 text-green-700',
    archived: 'bg-amber-100 text-amber-700',
};

const activeEventCount = computed(() =>
    eventsStore.events.filter(e => e.status !== 'archived').length
);

const atFreeTierLimit = computed(() =>
    auth.user?.plan === 'free' && activeEventCount.value >= 3
);

const nearFreeTierLimit = computed(() =>
    auth.user?.plan === 'free' && activeEventCount.value === 2
);

function formatDate(d) {
    return d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Your Events</h1>
                <p class="text-sm text-gray-500 mt-1">Plan: <span class="font-medium capitalize">{{ auth.user?.plan?.replace('_', ' ') }}</span></p>
            </div>
            <RouterLink
                v-if="!atFreeTierLimit"
                :to="{ name: 'events.create' }"
                class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800"
            >
                + New Event
            </RouterLink>
            <span v-else class="px-4 py-2 bg-gray-200 text-gray-400 rounded-lg text-sm font-medium cursor-not-allowed">
                + New Event
            </span>
        </div>

        <!-- Free tier warnings -->
        <div v-if="atFreeTierLimit" class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-3">
            <span class="text-amber-500 text-lg leading-none mt-0.5">⚠</span>
            <div>
                <p class="text-sm font-medium text-amber-900">Free plan limit reached</p>
                <p class="text-sm text-amber-700 mt-0.5">
                    You have 3 active events — the maximum on the free plan. Archive an event to free up a slot.
                    Need more? Pro plan with unlimited events is coming soon.
                </p>
            </div>
        </div>
        <div v-else-if="nearFreeTierLimit" class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
            <p class="text-sm text-blue-700">
                You're using 2 of 3 free active events. Pro plan with unlimited events is coming soon.
            </p>
        </div>

        <div v-if="eventsStore.fetchError" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            {{ eventsStore.fetchError }}
        </div>

        <div v-if="eventsStore.loading" class="text-center py-16 text-gray-400">Loading...</div>

        <div v-else-if="!eventsStore.events.length" class="text-center py-20">
            <p class="text-gray-500 mb-4">No events yet.</p>
            <RouterLink
                :to="{ name: 'events.create' }"
                class="inline-flex px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800"
            >
                Create your first event
            </RouterLink>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <RouterLink
                v-for="event in eventsStore.events"
                :key="event.id"
                :to="{ name: 'events.show', params: { id: event.id } }"
                class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow"
            >
                <div class="flex items-start justify-between mb-3">
                    <h2 class="font-semibold text-gray-900 text-sm leading-snug">{{ event.name }}</h2>
                    <span :class="['ml-2 shrink-0 text-xs px-2 py-0.5 rounded-full font-medium', statusColors[event.status]]">
                        {{ event.status }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 mb-4">{{ formatDate(event.starts_at) }}</p>
                <div class="flex gap-4 text-xs text-gray-600">
                    <span><strong>{{ event.guests_count }}</strong> guests</span>
                    <span><strong>{{ event.attending_count }}</strong> attending</span>
                </div>
            </RouterLink>
        </div>
    </div>
</template>
