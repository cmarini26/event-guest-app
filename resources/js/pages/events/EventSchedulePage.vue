<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useSubEventsStore } from '@/stores/subEvents.js';
import { useEventsStore } from '@/stores/events.js';
import axios from 'axios';

const route = useRoute();
const subStore = useSubEventsStore();
const eventsStore = useEventsStore();

const eventId = route.params.id;
const event = ref(null);
const loading = ref(true);

const form = ref({ name: '', description: '', starts_at: '', ends_at: '', location: '', capacity: null });
const editingId = ref(null);
const saving = ref(false);
const formError = ref('');
const fieldErrors = ref({});

onMounted(async () => {
    try {
        const [, ev] = await Promise.all([
            subStore.fetchSubEvents(eventId),
            axios.get(`/api/events/${eventId}`).then(r => r.data),
        ]);
        event.value = ev;
    } catch {
        // event load failure is non-fatal for the session list
    } finally {
        loading.value = false;
    }
});

function resetForm() {
    form.value = { name: '', description: '', starts_at: '', ends_at: '', location: '', capacity: null };
    editingId.value = null;
    formError.value = '';
    fieldErrors.value = {};
}

function startEdit(s) {
    editingId.value = s.id;
    form.value = {
        name: s.name,
        description: s.description ?? '',
        starts_at: toLocalInput(s.starts_at),
        ends_at: toLocalInput(s.ends_at),
        location: s.location ?? '',
        capacity: s.capacity,
    };
    fieldErrors.value = {};
    formError.value = '';
}

// Convert stored UTC datetime to a value for <input type="datetime-local"> (wall-clock semantics)
function toLocalInput(d) {
    if (!d) return '';
    return new Date(d).toISOString().slice(0, 16);
}

async function save() {
    saving.value = true;
    formError.value = '';
    fieldErrors.value = {};
    try {
        const payload = { ...form.value };
        if (!payload.ends_at) delete payload.ends_at;
        if (!payload.capacity) delete payload.capacity;

        if (editingId.value) {
            await subStore.updateSubEvent(eventId, editingId.value, payload);
        } else {
            await subStore.createSubEvent(eventId, payload);
        }
        resetForm();
    } catch (e) {
        if (e.response?.status === 422) {
            fieldErrors.value = e.response.data.errors ?? {};
            formError.value = e.response.data.message ?? 'Please fix the errors below.';
        } else {
            formError.value = 'Something went wrong. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}

async function remove(s) {
    if (!confirm(`Delete session "${s.name}"?`)) return;
    try {
        await subStore.deleteSubEvent(eventId, s.id);
    } catch {
        formError.value = 'Failed to delete session.';
    }
}

function formatDateTime(d) {
    if (!d) return '—';
    const opts = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZone: 'UTC' };
    return new Date(d).toLocaleString('en-US', opts);
}
</script>

<template>
    <div class="max-w-3xl mx-auto px-4 py-8">
        <div class="flex items-center gap-3 mb-6">
            <RouterLink :to="{ name: 'events.show', params: { id: eventId } }"
                class="text-sm text-gray-500 hover:text-gray-700">
                ← Back to event
            </RouterLink>
        </div>

        <h1 class="text-2xl font-bold mb-1">Schedule & Sessions</h1>
        <p class="text-gray-500 mb-8" v-if="event">Breakouts and sessions within {{ event.name }}</p>

        <!-- Session list -->
        <div v-if="loading" class="text-center py-8 text-gray-500">Loading…</div>
        <div v-else>
            <div v-if="subStore.subEvents.length" class="space-y-3 mb-8">
                <div v-for="s in subStore.subEvents" :key="s.id"
                    class="bg-white border border-gray-200 rounded-xl p-4 flex justify-between items-start">
                    <div>
                        <div class="font-semibold">{{ s.name }}</div>
                        <div class="text-sm text-gray-500">
                            {{ formatDateTime(s.starts_at) }}<span v-if="s.ends_at"> – {{ formatDateTime(s.ends_at) }}</span>
                            <span v-if="s.location"> · {{ s.location }}</span>
                            <span v-if="s.capacity"> · cap {{ s.capacity }}</span>
                        </div>
                        <p v-if="s.description" class="text-sm text-gray-600 mt-1">{{ s.description }}</p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button @click="startEdit(s)" class="text-sm text-indigo-600 hover:underline">Edit</button>
                        <button @click="remove(s)" class="text-sm text-red-500 hover:underline">Delete</button>
                    </div>
                </div>
            </div>
            <p v-else class="text-gray-400 mb-8">No sessions yet. Add one below.</p>

            <!-- Form -->
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="font-semibold mb-4">{{ editingId ? 'Edit session' : 'Add session' }}</h2>

                <div v-if="formError" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm">
                    {{ formError }}
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Name</label>
                        <input v-model="form.name" type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2" />
                        <p v-if="fieldErrors.name" class="text-sm text-red-500 mt-1">{{ fieldErrors.name[0] }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Starts at</label>
                            <input v-model="form.starts_at" type="datetime-local"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2" />
                            <p v-if="fieldErrors.starts_at" class="text-sm text-red-500 mt-1">{{ fieldErrors.starts_at[0] }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Ends at <span class="text-gray-400">(optional)</span></label>
                            <input v-model="form.ends_at" type="datetime-local"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2" />
                            <p v-if="fieldErrors.ends_at" class="text-sm text-red-500 mt-1">{{ fieldErrors.ends_at[0] }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Location <span class="text-gray-400">(optional)</span></label>
                            <input v-model="form.location" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Capacity <span class="text-gray-400">(optional)</span></label>
                            <input v-model.number="form.capacity" type="number" min="1"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Description <span class="text-gray-400">(optional)</span></label>
                        <textarea v-model="form.description" rows="2"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
                    </div>

                    <div class="flex gap-2">
                        <button @click="save" :disabled="saving"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {{ saving ? 'Saving…' : (editingId ? 'Update session' : 'Add session') }}
                        </button>
                        <button v-if="editingId" @click="resetForm"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
