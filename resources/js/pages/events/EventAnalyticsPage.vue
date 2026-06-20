<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useEventsStore } from '@/stores/events.js';

const route = useRoute();
const eventsStore = useEventsStore();

const data = ref(null);
const loading = ref(true);
const loadError = ref(null);

const eventId = route.params.id;

onMounted(async () => {
    try {
        data.value = await eventsStore.fetchAnalytics(eventId);
    } catch (e) {
        loadError.value = e.response?.status === 403
            ? 'You do not have access to this event.'
            : 'Failed to load analytics. Please try again.';
    } finally {
        loading.value = false;
    }
});

const statusColors = {
    attending: 'bg-green-500',
    declined: 'bg-red-400',
    pending: 'bg-gray-300',
    waitlisted: 'bg-amber-400',
};

const statusOrder = ['attending', 'pending', 'declined', 'waitlisted'];

const maxTimeline = computed(() => {
    if (!data.value?.response_timeline?.length) return 1;
    return Math.max(...data.value.response_timeline.map(d => d.cumulative), 1);
});

function pct(count) {
    const total = data.value?.totals?.invited || 0;
    return total > 0 ? Math.round((count / total) * 100) : 0;
}

function entries(obj) {
    return obj ? Object.entries(obj) : [];
}
</script>

<template>
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="flex items-center gap-3 mb-6">
            <RouterLink :to="{ name: 'events.show', params: { id: eventId } }"
                class="text-sm text-gray-500 hover:text-gray-700">
                ← Back to event
            </RouterLink>
        </div>

        <div v-if="loading" class="text-center py-12 text-gray-500">Loading analytics…</div>

        <div v-else-if="loadError" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4">
            {{ loadError }}
        </div>

        <div v-else-if="data">
            <h1 class="text-2xl font-bold mb-1">Analytics</h1>
            <p class="text-gray-500 mb-8">{{ data.event.name }}</p>

            <!-- Headline stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="text-2xl font-bold">{{ data.totals.invited }}</div>
                    <div class="text-sm text-gray-500">Invited</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="text-2xl font-bold text-green-600">{{ data.totals.expected_headcount }}</div>
                    <div class="text-sm text-gray-500">Expected headcount</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="text-2xl font-bold">{{ data.totals.response_rate }}%</div>
                    <div class="text-sm text-gray-500">Response rate</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="text-2xl font-bold">{{ data.totals.acceptance_rate }}%</div>
                    <div class="text-sm text-gray-500">Acceptance rate</div>
                </div>
            </div>

            <!-- RSVP breakdown -->
            <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
                <h2 class="font-semibold mb-4">RSVP breakdown</h2>
                <div class="space-y-3">
                    <div v-for="status in statusOrder" :key="status">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="capitalize">{{ status }}</span>
                            <span class="text-gray-500">
                                {{ data.rsvp_breakdown[status] || 0 }} ({{ pct(data.rsvp_breakdown[status] || 0) }}%)
                            </span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full" :class="statusColors[status]"
                                :style="{ width: pct(data.rsvp_breakdown[status] || 0) + '%' }"></div>
                        </div>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-4">
                    Plus-ones: <strong>{{ data.totals.plus_ones }}</strong>
                </p>
            </div>

            <!-- Response timeline -->
            <div v-if="data.response_timeline.length"
                class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
                <h2 class="font-semibold mb-4">Response timeline (cumulative)</h2>
                <div class="flex items-end gap-1 h-32">
                    <div v-for="point in data.response_timeline" :key="point.date"
                        class="flex-1 bg-indigo-500 rounded-t hover:bg-indigo-600 transition-colors"
                        :style="{ height: (point.cumulative / maxTimeline * 100) + '%' }"
                        :title="`${point.date}: ${point.cumulative} total (${point.responses} that day)`">
                    </div>
                </div>
                <div class="flex justify-between text-xs text-gray-400 mt-2">
                    <span>{{ data.response_timeline[0].date }}</span>
                    <span>{{ data.response_timeline[data.response_timeline.length - 1].date }}</span>
                </div>
            </div>

            <!-- Dietary & seating -->
            <div class="grid md:grid-cols-2 gap-6">
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="font-semibold mb-4">Dietary preferences</h2>
                    <div v-if="entries(data.dietary).length" class="space-y-2">
                        <div v-for="[label, count] in entries(data.dietary)" :key="label"
                            class="flex justify-between text-sm">
                            <span>{{ label }}</span>
                            <span class="text-gray-500">{{ count }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No dietary preferences collected.</p>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="font-semibold mb-4">Seating preferences</h2>
                    <div v-if="entries(data.seating).length" class="space-y-2">
                        <div v-for="[label, count] in entries(data.seating)" :key="label"
                            class="flex justify-between text-sm">
                            <span>{{ label }}</span>
                            <span class="text-gray-500">{{ count }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No seating preferences collected.</p>
                </div>
            </div>

            <p class="text-sm text-gray-500 mt-6">
                Guests with accessibility needs: <strong>{{ data.accessibility.with_needs }}</strong>
            </p>
        </div>
    </div>
</template>
