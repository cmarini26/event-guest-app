<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const stats = ref(null);
const users = ref([]);
const loading = ref(true);
const error = ref('');

const expandedUser = ref(null);
const expandedEvents = ref([]);
const expandedLoading = ref(false);
const togglingAdmin = ref(null);

onMounted(async () => {
    try {
        const [{ data: s }, { data: u }] = await Promise.all([
            axios.get('/api/admin/stats'),
            axios.get('/api/admin/users'),
        ]);
        stats.value = s;
        users.value = u;
    } catch {
        error.value = 'Failed to load admin data.';
    } finally {
        loading.value = false;
    }
});

async function toggleExpand(user) {
    if (expandedUser.value?.id === user.id) {
        expandedUser.value = null;
        expandedEvents.value = [];
        return;
    }
    expandedUser.value = user;
    expandedEvents.value = [];
    expandedLoading.value = true;
    try {
        const { data } = await axios.get(`/api/admin/users/${user.id}/events`);
        expandedEvents.value = data.events;
    } catch {
        expandedEvents.value = [];
    } finally {
        expandedLoading.value = false;
    }
}

async function toggleAdmin(user) {
    if (togglingAdmin.value) return;
    togglingAdmin.value = user.id;
    try {
        const { data } = await axios.post(`/api/admin/users/${user.id}/toggle-admin`);
        user.is_admin = data.is_admin;
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to update admin status.');
    } finally {
        togglingAdmin.value = null;
    }
}

function formatDate(d) {
    return d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }) : '—';
}

function formatRevenue(cents) {
    return '$' + (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 0 });
}

const planLabels = { free: 'Free', event_pass: 'Event Pass', pro: 'Pro', business: 'Business' };
const statusColors = {
    draft:     'bg-gray-100 text-gray-600',
    published: 'bg-green-100 text-green-700',
    archived:  'bg-amber-100 text-amber-700',
};
</script>

<template>
    <div>
        <div class="flex items-center gap-3 mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Admin</h1>
            <span class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Internal</span>
        </div>

        <div v-if="loading" class="text-center py-16 text-gray-400">Loading...</div>

        <div v-else-if="error" class="text-center py-16 text-red-500">{{ error }}</div>

        <template v-else>
            <!-- Failed jobs alert -->
            <div v-if="stats.failed_jobs > 0" class="mb-6 bg-red-50 border border-red-200 rounded-xl px-5 py-3 flex items-center gap-3">
                <span class="text-sm font-medium text-red-800">{{ stats.failed_jobs }} failed job{{ stats.failed_jobs !== 1 ? 's' : '' }} in queue</span>
                <span class="text-xs text-red-600">Run <code class="bg-red-100 px-1 rounded">php artisan queue:retry all</code> or inspect <code class="bg-red-100 px-1 rounded">php artisan queue:failed</code></span>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-4 mb-8">
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ stats.total_users }}</p>
                    <p class="text-xs text-gray-500 mt-1">Users</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ stats.total_events }}</p>
                    <p class="text-xs text-gray-500 mt-1">Events</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ stats.active_events }}</p>
                    <p class="text-xs text-gray-500 mt-1">Active</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ stats.total_guests }}</p>
                    <p class="text-xs text-gray-500 mt-1">Guests</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-indigo-600">{{ stats.event_passes }}</p>
                    <p class="text-xs text-gray-500 mt-1">Event Passes</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ formatRevenue(stats.revenue_cents) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Revenue</p>
                </div>
                <div class="bg-white rounded-xl border border-red-200 p-4 text-center">
                    <p class="text-2xl font-bold" :class="stats.failed_jobs > 0 ? 'text-red-600' : 'text-gray-900'">{{ stats.failed_jobs }}</p>
                    <p class="text-xs text-gray-500 mt-1">Failed Jobs</p>
                </div>
            </div>

            <!-- Users -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-900">Users <span class="text-sm font-normal text-gray-400">({{ users.length }})</span></h2>
                </div>

                <div v-if="!users.length" class="px-5 py-10 text-center text-sm text-gray-400">No users yet.</div>

                <table v-else class="w-full text-sm">
                    <thead class="border-b border-gray-100">
                        <tr class="text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-5 py-3 text-left font-medium">Name</th>
                            <th class="px-5 py-3 text-left font-medium">Email</th>
                            <th class="px-5 py-3 text-left font-medium">Plan</th>
                            <th class="px-5 py-3 text-right font-medium">Events</th>
                            <th class="px-5 py-3 text-left font-medium">Joined</th>
                            <th class="px-5 py-3 text-left font-medium">Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="user in users" :key="user.id">
                            <tr class="border-t border-gray-50 hover:bg-gray-50 cursor-pointer"
                                @click="toggleExpand(user)">
                                <td class="px-5 py-3 font-medium text-gray-900">
                                    <span class="flex items-center gap-1.5">
                                        {{ user.name }}
                                        <span v-if="!user.email_verified" class="text-xs text-amber-500" title="Email not verified">●</span>
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-gray-500">{{ user.email }}</td>
                                <td class="px-5 py-3">
                                    <span :class="{
                                        'bg-gray-100 text-gray-600': user.plan === 'free',
                                        'bg-indigo-100 text-indigo-700': user.plan === 'event_pass',
                                        'bg-purple-100 text-purple-700': user.plan === 'pro' || user.plan === 'business',
                                    }" class="text-xs font-medium px-2 py-0.5 rounded-full">
                                        {{ planLabels[user.plan] ?? user.plan }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right text-gray-600">{{ user.events_count }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ formatDate(user.created_at) }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <span v-if="user.is_admin" class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Admin</span>
                                        <span v-else class="text-xs text-gray-400">User</span>
                                        <button @click.stop="toggleAdmin(user)" :disabled="togglingAdmin === user.id"
                                            class="text-xs text-gray-400 hover:text-gray-700 underline disabled:opacity-50">
                                            {{ user.is_admin ? 'Revoke' : 'Grant' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Expanded events row -->
                            <tr v-if="expandedUser?.id === user.id" class="border-t border-gray-100 bg-gray-50">
                                <td colspan="6" class="px-8 py-4">
                                    <div v-if="expandedLoading" class="text-sm text-gray-400">Loading events...</div>
                                    <div v-else-if="!expandedEvents.length" class="text-sm text-gray-400">No events yet.</div>
                                    <div v-else class="space-y-1">
                                        <div v-for="event in expandedEvents" :key="event.id"
                                            class="flex items-center gap-3 text-sm py-1">
                                            <span :class="statusColors[event.status] ?? 'bg-gray-100 text-gray-600'"
                                                class="text-xs font-medium px-2 py-0.5 rounded-full shrink-0">
                                                {{ event.status }}
                                            </span>
                                            <span class="font-medium text-gray-800">{{ event.name }}</span>
                                            <span class="text-gray-500 text-xs">{{ formatDate(event.starts_at) }}</span>
                                            <span class="text-gray-500 text-xs ml-auto">{{ event.attending_count }}/{{ event.guests_count }} attending</span>
                                            <span v-if="event.event_pass" class="text-xs text-indigo-600 font-medium">Pass</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
