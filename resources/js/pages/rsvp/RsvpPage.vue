<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();

const data = ref(null);
const loading = ref(true);
const error = ref('');
const submitted = ref(false);
const submitting = ref(false);
const submitResult = ref(null);

const form = ref({
    status: '',
    phone: '',
    notes: '',
    dietary_preference: '',
    accessibility_needs: '',
    seating_preference: '',
    plus_ones: [],
});

const event = computed(() => data.value?.event);
const guest = computed(() => data.value?.guest);

const rsvpClosed = computed(() => {
    if (!event.value?.rsvp_deadline) return false;
    return new Date(event.value.rsvp_deadline) < new Date();
});

onMounted(async () => {
    try {
        const { data: d } = await axios.get(`/api/rsvp/${route.params.token}`);
        data.value = d;
        form.value.status = d.guest.rsvp_status !== 'pending' ? d.guest.rsvp_status : '';
        if (d.guest.plus_ones?.length) {
            form.value.plus_ones = d.guest.plus_ones.map(p => ({ name: p.name, dietary_preference: p.dietary_preference ?? '' }));
        }
    } catch {
        error.value = 'This RSVP link is invalid or has expired.';
    } finally {
        loading.value = false;
    }
});

function addPlusOne() {
    if (form.value.plus_ones.length < event.value.max_plus_ones_per_guest) {
        form.value.plus_ones.push({ name: '', dietary_preference: '' });
    }
}

function removePlusOne(i) {
    form.value.plus_ones.splice(i, 1);
}

async function submit() {
    submitting.value = true;
    try {
        const payload = { ...form.value };
        if (payload.status === 'declined' || !event.value.allow_plus_ones) {
            payload.plus_ones = [];
        }
        const { data: res } = await axios.post(`/api/rsvp/${route.params.token}`, payload);
        submitResult.value = res;
        submitted.value = true;
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Something went wrong. Please try again.';
    } finally {
        submitting.value = false;
    }
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', {
        weekday: 'long', month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit',
    });
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <div v-if="loading" class="flex items-center justify-center min-h-screen text-gray-400">
            Loading...
        </div>

        <div v-else-if="error" class="flex items-center justify-center min-h-screen">
            <div class="text-center">
                <p class="text-gray-600">{{ error }}</p>
            </div>
        </div>

        <div v-else-if="submitted" class="flex items-center justify-center min-h-screen px-4">
            <div class="text-center max-w-md">
                <div class="text-4xl mb-4">{{ submitResult.status === 'attending' ? '🎉' : submitResult.status === 'waitlisted' ? '⏳' : '👋' }}</div>
                <h1 class="text-2xl font-bold text-gray-900 mb-3">{{ submitResult.message }}</h1>
                <p class="text-gray-600">{{ event.name }}</p>
                <p v-if="event.starts_at && submitResult.status === 'attending'" class="text-sm text-gray-500 mt-1">
                    {{ formatDate(event.starts_at) }}
                </p>
            </div>
        </div>

        <div v-else class="max-w-lg mx-auto px-4 py-12">
            <!-- Event header -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ event.name }}</h1>
                <p v-if="event.starts_at" class="text-sm text-gray-500 mb-1">{{ formatDate(event.starts_at) }}</p>
                <p v-if="event.venue_name" class="text-sm text-gray-500">{{ event.venue_name }}</p>
                <p v-if="event.venue_address" class="text-sm text-gray-400">{{ event.venue_address }}</p>
                <p v-if="event.description" class="mt-3 text-sm text-gray-700">{{ event.description }}</p>
                <p v-if="event.is_at_capacity && !rsvpClosed" class="mt-3 text-sm text-amber-600 font-medium">
                    This event is currently at capacity. You may be added to the waitlist.
                </p>
                <p v-if="event.rsvp_deadline && !rsvpClosed" class="mt-3 text-xs text-gray-400">
                    RSVP by {{ formatDate(event.rsvp_deadline) }}
                </p>
            </div>

            <!-- RSVP closed -->
            <div v-if="rsvpClosed" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 text-center">
                <p class="text-2xl mb-3">🔒</p>
                <h2 class="font-semibold text-gray-900 mb-1">RSVPs are closed</h2>
                <p class="text-sm text-gray-500">The RSVP deadline for this event has passed.</p>
            </div>

            <!-- RSVP form -->
            <div v-else class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h2 class="font-semibold text-gray-900 mb-1">Hi {{ guest.first_name }}!</h2>
                <p class="text-sm text-gray-500 mb-6">Will you be joining us?</p>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            @click="form.status = 'attending'"
                            :class="[
                                'py-3 rounded-xl text-sm font-medium border-2 transition-colors',
                                form.status === 'attending'
                                    ? 'border-green-600 bg-green-50 text-green-700'
                                    : 'border-gray-200 text-gray-600 hover:border-gray-300'
                            ]"
                        >
                            Attending
                        </button>
                        <button
                            type="button"
                            @click="form.status = 'declined'"
                            :class="[
                                'py-3 rounded-xl text-sm font-medium border-2 transition-colors',
                                form.status === 'declined'
                                    ? 'border-red-500 bg-red-50 text-red-600'
                                    : 'border-gray-200 text-gray-600 hover:border-gray-300'
                            ]"
                        >
                            Can't make it
                        </button>
                    </div>

                    <template v-if="form.status === 'attending'">
                        <div v-if="event.require_phone">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone number *</label>
                            <input v-model="form.phone" type="tel" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                        </div>

                        <div v-if="event.collect_dietary">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Dietary preferences</label>
                            <input v-model="form.dietary_preference" type="text" placeholder="e.g. vegetarian, nut allergy"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                        </div>

                        <div v-if="event.collect_accessibility">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Accessibility needs</label>
                            <input v-model="form.accessibility_needs" type="text" placeholder="e.g. wheelchair access"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                        </div>

                        <div v-if="event.collect_seating">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Seating preference</label>
                            <input v-model="form.seating_preference" type="text" placeholder="e.g. near the stage"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                        </div>

                        <div v-if="event.allow_plus_ones && event.max_plus_ones_per_guest > 0">
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-sm font-medium text-gray-700">Plus-ones</label>
                                <button
                                    v-if="form.plus_ones.length < event.max_plus_ones_per_guest"
                                    type="button"
                                    @click="addPlusOne"
                                    class="text-xs text-blue-600 hover:underline"
                                >
                                    + Add plus-one
                                </button>
                            </div>
                            <div v-for="(po, i) in form.plus_ones" :key="i" class="mb-3 p-3 border border-gray-200 rounded-lg">
                                <div class="flex gap-2 mb-2">
                                    <input v-model="po.name" placeholder="Name *" type="text" required
                                        class="flex-1 px-3 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                                    <button type="button" @click="removePlusOne(i)" class="text-xs text-red-500 hover:underline px-2">
                                        Remove
                                    </button>
                                </div>
                                <div v-if="event.collect_dietary">
                                    <input v-model="po.dietary_preference" placeholder="Dietary preference (optional)" type="text"
                                        class="w-full px-3 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-gray-900" />
                                </div>
                            </div>
                            <button
                                v-if="!form.plus_ones.length"
                                type="button"
                                @click="addPlusOne"
                                class="text-sm text-gray-500 hover:text-gray-700"
                            >
                                + Add a plus-one
                            </button>
                        </div>
                    </template>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message (optional)</label>
                        <textarea v-model="form.notes" rows="2" placeholder="Any notes for the host..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 resize-none" />
                    </div>

                    <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

                    <button
                        type="submit"
                        :disabled="!form.status || submitting"
                        class="w-full py-3 bg-gray-900 text-white rounded-xl text-sm font-medium hover:bg-gray-800 disabled:opacity-40"
                    >
                        {{ submitting ? 'Submitting...' : 'Submit RSVP' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
