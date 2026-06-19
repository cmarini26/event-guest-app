<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useEventsStore } from '@/stores/events.js';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const eventsStore = useEventsStore();

const timezones = Intl.supportedValuesOf('timeZone');

const form = ref(null);
const errors = ref({});
const submitError = ref('');
const saving = ref(false);
const loading = ref(true);
const loadError = ref(null);

onMounted(async () => {
    try {
        const { data } = await axios.get(`/api/events/${route.params.id}`);
        form.value = {
            name: data.name,
            description: data.description ?? '',
            starts_at: data.starts_at ? data.starts_at.slice(0, 16) : '',
            ends_at: data.ends_at ? data.ends_at.slice(0, 16) : '',
            timezone: data.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone,
            venue_name: data.venue_name ?? '',
            venue_address: data.venue_address ?? '',
            max_guests: data.max_guests ?? '',
            rsvp_deadline: data.rsvp_deadline ? data.rsvp_deadline.slice(0, 16) : '',
            allow_plus_ones: data.allow_plus_ones,
            max_plus_ones_per_guest: data.max_plus_ones_per_guest,
            collect_dietary: data.collect_dietary,
            collect_accessibility: data.collect_accessibility,
            collect_seating: data.collect_seating,
            require_phone: data.require_phone,
        };
    } catch (err) {
        if (err.response?.status === 403 || err.response?.status === 404) {
            router.push({ name: 'dashboard' });
        } else {
            loadError.value = 'Failed to load event. Please refresh.';
        }
    } finally {
        loading.value = false;
    }
});

async function submit() {
    errors.value = {};
    submitError.value = '';
    saving.value = true;
    try {
        await eventsStore.updateEvent(route.params.id, form.value);
        router.push({ name: 'events.show', params: { id: route.params.id } });
    } catch (err) {
        errors.value = err.response?.data?.errors ?? {};
        if (!Object.keys(errors.value).length) {
            submitError.value = err.response?.data?.message ?? 'Something went wrong. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div v-if="loading" class="text-center py-16 text-gray-400">Loading...</div>
    <div v-else-if="loadError" class="text-center py-16 text-red-500">{{ loadError }}</div>
    <div v-else-if="form" class="max-w-2xl">
        <h1 class="text-2xl font-bold text-gray-900 mb-8">Edit Event</h1>

        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Event name *</label>
                <input v-model="form.name" type="text" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea v-model="form.description" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 resize-none" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start *</label>
                    <input v-model="form.starts_at" type="datetime-local" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End</label>
                    <input v-model="form.ends_at" type="datetime-local"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                <select v-model="form.timezone"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 bg-white">
                    <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Venue name</label>
                <input v-model="form.venue_name" type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Venue address</label>
                <input v-model="form.venue_address" type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max guests <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input v-model.number="form.max_guests" type="number" min="1" placeholder="No limit"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">RSVP deadline <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input v-model="form.rsvp_deadline" type="datetime-local"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                    <p v-if="errors.rsvp_deadline" class="mt-1 text-sm text-red-600">{{ errors.rsvp_deadline[0] }}</p>
                </div>
            </div>

            <fieldset class="border border-gray-200 rounded-lg p-4 space-y-3">
                <legend class="text-sm font-medium text-gray-700 px-1">RSVP Settings</legend>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input v-model="form.allow_plus_ones" type="checkbox" class="rounded" />
                    <span class="text-sm text-gray-700">Allow plus-ones</span>
                </label>
                <div v-if="form.allow_plus_ones" class="ml-7">
                    <label class="block text-sm text-gray-600 mb-1">Max plus-ones per guest</label>
                    <input v-model.number="form.max_plus_ones_per_guest" type="number" min="1" max="10"
                        class="w-20 px-3 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                </div>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input v-model="form.collect_dietary" type="checkbox" class="rounded" />
                    <span class="text-sm text-gray-700">Collect dietary preferences</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input v-model="form.collect_accessibility" type="checkbox" class="rounded" />
                    <span class="text-sm text-gray-700">Collect accessibility needs</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input v-model="form.collect_seating" type="checkbox" class="rounded" />
                    <span class="text-sm text-gray-700">Collect seating preferences</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input v-model="form.require_phone" type="checkbox" class="rounded" />
                    <span class="text-sm text-gray-700">Require phone number on RSVP</span>
                </label>
            </fieldset>

            <p v-if="submitError" class="text-sm text-red-600">{{ submitError }}</p>

            <div class="flex gap-3">
                <button type="submit" :disabled="saving"
                    class="px-5 py-2.5 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800 disabled:opacity-50">
                    {{ saving ? 'Saving...' : 'Save changes' }}
                </button>
                <button type="button" @click="$router.back()"
                    class="px-5 py-2.5 bg-white text-gray-700 rounded-lg text-sm font-medium border border-gray-300 hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</template>
