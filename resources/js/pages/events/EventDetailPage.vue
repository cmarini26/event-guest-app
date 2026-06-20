<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useEventsStore } from '@/stores/events.js';
import { useAuthStore } from '@/stores/auth.js';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const eventsStore = useEventsStore();
const auth = useAuthStore();

const event = ref(null);
const guests = ref([]);
const loading = ref(true);
const loadError = ref(null);
const guestLoading = ref(false);
const inviting = ref(null);
const bulkInviting = ref(false);
const bulkInviteResult = ref('');
const checkoutLoading = ref(false);

const newGuest = ref({ first_name: '', last_name: '', email: '', phone: '' });
const addingGuest = ref(false);
const guestError = ref('');

// Payment banners from Stripe redirect
const paymentStatus = ref(route.query.payment ?? null);

const statusColors = {
    pending: 'text-gray-500',
    attending: 'text-green-600',
    declined: 'text-red-500',
    waitlisted: 'text-amber-600',
};

const stats = computed(() => ({
    total: guests.value.length,
    attending: guests.value.filter(g => g.rsvp_status === 'attending').length,
    declined: guests.value.filter(g => g.rsvp_status === 'declined').length,
    pending: guests.value.filter(g => g.rsvp_status === 'pending').length,
    waitlisted: guests.value.filter(g => g.rsvp_status === 'waitlisted').length,
}));

function formatDateTime(d, tz) {
    if (!d) return '—';
    // Display in UTC (wall-clock time) + append timezone as a label.
    // starts_at is stored as the raw value the host entered (UTC app timezone),
    // so UTC display gives the intended wall-clock time.
    const opts = { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', timeZone: 'UTC' };
    const formatted = new Date(d).toLocaleString('en-US', opts);
    return tz ? `${formatted} (${tz})` : formatted;
}

const guestLimit = computed(() => {
    if (!event.value) return null;
    const planLimit = event.value.event_pass_paid_at ? 300 : (auth.user?.plan === 'free' ? 50 : null);
    const eventCap  = event.value.max_guests || null;
    if (planLimit === null) return eventCap;
    if (eventCap === null) return planLimit;
    return Math.min(planLimit, eventCap);
});

const capacityPercent = computed(() => {
    if (!guestLimit.value) return 0;
    return Math.min(100, Math.round((stats.value.attending / guestLimit.value) * 100));
});

const showUpgradeButton = computed(() => {
    return event.value && !event.value.event_pass_paid_at && auth.user?.plan === 'free';
});

async function load() {
    loading.value = true;
    loadError.value = null;
    try {
        const [{ data: e }, { data: g }] = await Promise.all([
            axios.get(`/api/events/${route.params.id}`),
            axios.get(`/api/events/${route.params.id}/guests`),
        ]);
        event.value = e;
        guests.value = g;
    } catch (err) {
        if (err.response?.status === 403 || err.response?.status === 404) {
            router.push({ name: 'dashboard' });
        } else {
            loadError.value = 'Failed to load event. Please refresh.';
        }
    } finally {
        loading.value = false;
    }
}

async function addGuest() {
    guestError.value = '';
    guestLoading.value = true;
    try {
        const { data } = await axios.post(`/api/events/${route.params.id}/guests`, newGuest.value);
        guests.value.push(data);
        newGuest.value = { first_name: '', last_name: '', email: '', phone: '' };
        addingGuest.value = false;
    } catch (err) {
        guestError.value = err.response?.data?.message ?? 'Failed to add guest.';
    } finally {
        guestLoading.value = false;
    }
}

async function removeGuest(guest) {
    if (!confirm(`Remove ${guest.first_name} ${guest.last_name}?`)) return;
    try {
        await axios.delete(`/api/events/${route.params.id}/guests/${guest.id}`);
        guests.value = guests.value.filter(g => g.id !== guest.id);
    } catch {
        alert('Failed to remove guest. Please try again.');
    }
}

async function sendInvite(guest) {
    inviting.value = guest.id;
    try {
        await axios.post(`/api/events/${route.params.id}/guests/${guest.id}/invite`);
        guest.invited_at = new Date().toISOString();
    } catch {
        alert('Failed to send invite. Please try again.');
    } finally {
        inviting.value = null;
    }
}

async function bulkInvite() {
    bulkInviting.value = true;
    bulkInviteResult.value = '';
    try {
        const { data } = await axios.post(`/api/events/${route.params.id}/guests/bulk-invite`);
        bulkInviteResult.value = data.message;
        await load();
    } finally {
        bulkInviting.value = false;
    }
}

async function publish() {
    try {
        const updated = await eventsStore.publishEvent(event.value.id);
        event.value = { ...event.value, ...updated };
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to publish event.');
    }
}

async function archive() {
    if (!confirm('Archive this event? Guests will no longer be able to RSVP.')) return;
    try {
        const updated = await eventsStore.archiveEvent(event.value.id);
        event.value = { ...event.value, ...updated };
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to archive event.');
    }
}

async function startCheckout() {
    checkoutLoading.value = true;
    try {
        const { data } = await axios.post(`/api/events/${route.params.id}/checkout`);
        window.location.href = data.checkout_url;
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to start checkout.');
        checkoutLoading.value = false;
    }
}

function dismissPaymentBanner() {
    paymentStatus.value = null;
    router.replace({ query: {} });
}

async function copyRsvpLink(guest) {
    const url = `${window.location.origin}/rsvp/${guest.rsvp_token}`;
    try {
        await navigator.clipboard.writeText(url);
    } catch {
        prompt('Copy this RSVP link:', url);
    }
}

async function exportCsv() {
    try {
        const { data } = await axios.get(`/api/events/${route.params.id}/guests/export`, { responseType: 'blob' });
        const url = URL.createObjectURL(data);
        const a = document.createElement('a');
        a.href = url;
        a.download = `guests-${event.value.name.replace(/\s+/g, '-').toLowerCase()}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    } catch {
        loadError.value = 'Export failed. Please try again.';
    }
}

const expandedGuest = ref(null);

function toggleExpand(guest) {
    expandedGuest.value = expandedGuest.value?.id === guest.id ? null : guest;
}

const hasPreferences = computed(() =>
    event.value && (event.value.collect_dietary || event.value.collect_accessibility || event.value.collect_seating)
);

onMounted(load);
</script>

<template>
    <div v-if="loading" class="text-center py-16 text-gray-400">Loading...</div>

    <div v-else-if="loadError" class="text-center py-16 text-red-500">{{ loadError }}</div>

    <div v-else-if="event">
        <!-- Payment success banner -->
        <div v-if="paymentStatus === 'success'"
            class="mb-4 flex items-start gap-3 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
            <span class="text-green-600 font-bold text-lg leading-none">✓</span>
            <div class="flex-1">
                <p class="text-sm font-semibold text-green-800">Event Pass activated!</p>
                <p class="text-xs text-green-700 mt-0.5">Your guest limit has been raised to 300 for this event.</p>
            </div>
            <button @click="dismissPaymentBanner" class="text-green-600 hover:text-green-800 text-sm leading-none">✕</button>
        </div>

        <!-- Payment cancelled banner -->
        <div v-if="paymentStatus === 'cancelled'"
            class="mb-4 flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
            <span class="text-amber-600 font-bold text-lg leading-none">!</span>
            <div class="flex-1">
                <p class="text-sm font-semibold text-amber-800">Payment cancelled</p>
                <p class="text-xs text-amber-700 mt-0.5">No charge was made. You can upgrade whenever you're ready.</p>
            </div>
            <button @click="dismissPaymentBanner" class="text-amber-600 hover:text-amber-800 text-sm leading-none">✕</button>
        </div>

        <div class="flex items-start justify-between mb-6">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <RouterLink :to="{ name: 'dashboard' }" class="text-sm text-gray-500 hover:text-gray-700">
                        ← Events
                    </RouterLink>
                </div>
                <div class="flex items-center gap-2 mb-1">
                    <h1 class="text-2xl font-bold text-gray-900">{{ event.name }}</h1>
                    <span :class="{
                        'bg-gray-100 text-gray-600': event.status === 'draft',
                        'bg-green-100 text-green-700': event.status === 'published',
                        'bg-amber-100 text-amber-700': event.status === 'archived',
                    }" class="text-xs font-medium px-2 py-0.5 rounded-full capitalize">{{ event.status }}</span>
                </div>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ formatDateTime(event.starts_at, event.timezone) }}<template v-if="event.ends_at"> – {{ formatDateTime(event.ends_at, null) }}</template>
                </p>
                <p v-if="event.venue_name" class="text-sm text-gray-500">
                    {{ event.venue_name }}<span v-if="event.venue_address"> · {{ event.venue_address }}</span>
                </p>
                <p v-if="event.description" class="text-sm text-gray-600 mt-2 max-w-xl">{{ event.description }}</p>

                <!-- Event Pass badge -->
                <span v-if="event.event_pass_paid_at"
                    class="mt-2 inline-flex items-center gap-1 text-xs font-medium bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
                    Event Pass · 300 guest limit
                </span>
            </div>
            <div class="flex gap-2 items-center">
                <!-- Upgrade button for free-plan events without a pass -->
                <button v-if="showUpgradeButton"
                    @click="startCheckout"
                    :disabled="checkoutLoading"
                    class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-1.5">
                    <span v-if="checkoutLoading">Redirecting...</span>
                    <span v-else>Upgrade · $19</span>
                </button>

                <RouterLink
                    :to="{ name: 'events.analytics', params: { id: event.id } }"
                    class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    Analytics
                </RouterLink>
                <RouterLink
                    :to="{ name: 'events.edit', params: { id: event.id } }"
                    class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    Edit
                </RouterLink>
                <button v-if="event.status === 'draft'" @click="publish"
                    class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Publish
                </button>
                <button v-if="event.status !== 'archived'" @click="archive"
                    class="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50">
                    Archive
                </button>
                <span v-else class="px-2 py-1 text-xs bg-amber-100 text-amber-700 rounded-full font-medium">Archived</span>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
                <p class="text-xs text-gray-500 mt-1">Total guests</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-green-600">{{ stats.attending }}</p>
                <p class="text-xs text-gray-500 mt-1">Attending</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-red-500">{{ stats.declined }}</p>
                <p class="text-xs text-gray-500 mt-1">Declined</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-gray-500">{{ stats.pending }}</p>
                <p class="text-xs text-gray-500 mt-1">Pending</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ stats.waitlisted }}</p>
                <p class="text-xs text-gray-500 mt-1">Waitlisted</p>
            </div>
        </div>

        <!-- Guest capacity bar (shown when there's a limit) -->
        <div v-if="guestLimit" class="mb-6 bg-white rounded-xl border border-gray-200 px-5 py-4">
            <div class="flex justify-between items-center mb-2">
                <p class="text-sm font-medium text-gray-700">
                    Guest capacity
                    <span v-if="!event.event_pass_paid_at" class="ml-2 text-xs text-gray-400 font-normal">(free plan)</span>
                </p>
                <p class="text-sm text-gray-600">{{ stats.attending }} / {{ guestLimit }}</p>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div
                    class="h-2 rounded-full transition-all"
                    :class="capacityPercent >= 90 ? 'bg-red-500' : capacityPercent >= 70 ? 'bg-amber-500' : 'bg-green-500'"
                    :style="{ width: capacityPercent + '%' }"
                ></div>
            </div>
            <p v-if="showUpgradeButton && stats.attending >= 30"
                class="mt-2 text-xs text-indigo-600">
                Upgrade to Event Pass to raise your limit to 300 guests.
            </p>
        </div>

        <!-- Guest list -->
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">Guest List</h2>
                <div class="flex gap-2">
                    <button v-if="guests.length" @click="exportCsv"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                        Export CSV
                    </button>
                    <button v-if="event.status !== 'archived'" @click="bulkInvite" :disabled="bulkInviting"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50">
                        {{ bulkInviting ? 'Sending...' : 'Invite all' }}
                    </button>
                    <button v-if="event.status !== 'archived'" @click="addingGuest = true"
                        class="px-3 py-1.5 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800">
                        + Add guest
                    </button>
                </div>
            </div>

            <!-- Bulk invite result -->
            <div v-if="bulkInviteResult" class="px-5 py-3 border-b border-gray-100 bg-green-50 flex items-center justify-between">
                <p class="text-sm text-green-700">{{ bulkInviteResult }}</p>
                <button @click="bulkInviteResult = ''" class="text-green-600 hover:text-green-800 text-sm leading-none ml-4">✕</button>
            </div>

            <!-- Add guest form -->
            <div v-if="addingGuest" class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <input v-model="newGuest.first_name" placeholder="First name" type="text"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                    <input v-model="newGuest.last_name" placeholder="Last name" type="text"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                    <input v-model="newGuest.email" placeholder="Email (optional)" type="email"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                    <input v-model="newGuest.phone" placeholder="Phone (optional)" type="tel"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                </div>
                <p v-if="guestError" class="text-sm text-red-600 mb-2">{{ guestError }}</p>
                <div class="flex gap-2">
                    <button @click="addGuest" :disabled="guestLoading || !newGuest.first_name || !newGuest.last_name"
                        class="px-4 py-1.5 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800 disabled:opacity-50">
                        Add
                    </button>
                    <button @click="addingGuest = false"
                        class="px-4 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </div>

            <div v-if="!guests.length" class="px-5 py-10 text-center text-gray-400 text-sm">
                No guests added yet.
            </div>

            <table v-else class="w-full text-sm">
                <thead class="border-b border-gray-100">
                    <tr class="text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-5 py-3 text-left font-medium">Name</th>
                        <th class="px-5 py-3 text-left font-medium">Email</th>
                        <th class="px-5 py-3 text-left font-medium">Status</th>
                        <th class="px-5 py-3 text-left font-medium">Plus-ones</th>
                        <th class="px-5 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="guest in guests" :key="guest.id">
                        <tr class="border-t border-gray-50 hover:bg-gray-50"
                            :class="{ 'bg-gray-50': expandedGuest?.id === guest.id, 'cursor-pointer': hasPreferences }"
                            @click="hasPreferences ? toggleExpand(guest) : null">
                            <td class="px-5 py-3 font-medium text-gray-900">
                                <span class="flex items-center gap-1.5">
                                    <span v-if="hasPreferences" class="text-gray-400 text-xs">
                                        {{ expandedGuest?.id === guest.id ? '▾' : '▸' }}
                                    </span>
                                    {{ guest.first_name }} {{ guest.last_name }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ guest.email ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span :class="['capitalize font-medium', statusColors[guest.rsvp_status]]">
                                    {{ guest.rsvp_status }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ guest.plus_ones?.length ?? 0 }}</td>
                            <td class="px-5 py-3 text-right" @click.stop>
                                <div class="flex gap-2 justify-end">
                                    <template v-if="event.status !== 'archived'">
                                        <button
                                            v-if="guest.email && !guest.invited_at"
                                            @click="sendInvite(guest)"
                                            :disabled="inviting === guest.id"
                                            class="text-xs text-blue-600 hover:underline disabled:opacity-50"
                                        >
                                            {{ inviting === guest.id ? 'Sending...' : 'Invite' }}
                                        </button>
                                        <span v-else-if="guest.invited_at" class="text-xs text-gray-400">Invited</span>
                                    </template>
                                    <button @click="copyRsvpLink(guest)" class="text-xs text-gray-500 hover:underline">
                                        Copy link
                                    </button>
                                    <button @click="removeGuest(guest)" class="text-xs text-red-500 hover:underline">
                                        Remove
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Expanded preferences row -->
                        <tr v-if="expandedGuest?.id === guest.id" class="border-t border-gray-50 bg-gray-50">
                            <td colspan="5" class="px-10 pb-4 pt-1">
                                <div class="flex flex-wrap gap-x-8 gap-y-2 text-xs text-gray-600">
                                    <div v-if="event.collect_dietary">
                                        <span class="font-medium text-gray-500 uppercase tracking-wide text-[10px]">Dietary</span>
                                        <p class="mt-0.5">{{ guest.dietary_preference || '—' }}</p>
                                    </div>
                                    <div v-if="event.collect_accessibility">
                                        <span class="font-medium text-gray-500 uppercase tracking-wide text-[10px]">Accessibility</span>
                                        <p class="mt-0.5">{{ guest.accessibility_needs || '—' }}</p>
                                    </div>
                                    <div v-if="event.collect_seating">
                                        <span class="font-medium text-gray-500 uppercase tracking-wide text-[10px]">Seating</span>
                                        <p class="mt-0.5">{{ guest.seating_preference || '—' }}</p>
                                    </div>
                                    <div v-if="guest.notes">
                                        <span class="font-medium text-gray-500 uppercase tracking-wide text-[10px]">Notes</span>
                                        <p class="mt-0.5">{{ guest.notes }}</p>
                                    </div>
                                    <div v-if="guest.plus_ones?.length">
                                        <span class="font-medium text-gray-500 uppercase tracking-wide text-[10px]">Plus-ones</span>
                                        <p v-for="p in guest.plus_ones" :key="p.id" class="mt-0.5">
                                            {{ p.name }}<span v-if="p.dietary_preference"> · {{ p.dietary_preference }}</span>
                                        </p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
