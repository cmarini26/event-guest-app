import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useEventsStore = defineStore('events', () => {
    const events = ref([]);
    const loading = ref(false);
    const fetchError = ref(null);

    async function fetchEvents() {
        loading.value = true;
        fetchError.value = null;
        try {
            const { data } = await axios.get('/api/events');
            events.value = data;
        } catch {
            fetchError.value = 'Failed to load events. Please refresh.';
        } finally {
            loading.value = false;
        }
    }

    async function createEvent(payload) {
        const { data } = await axios.post('/api/events', payload);
        events.value.unshift(data);
        return data;
    }

    async function updateEvent(id, payload) {
        const { data } = await axios.put(`/api/events/${id}`, payload);
        const i = events.value.findIndex(e => e.id === id);
        if (i !== -1) events.value[i] = data;
        return data;
    }

    async function deleteEvent(id) {
        await axios.delete(`/api/events/${id}`);
        events.value = events.value.filter(e => e.id !== id);
    }

    async function publishEvent(id) {
        const { data } = await axios.post(`/api/events/${id}/publish`);
        const i = events.value.findIndex(e => e.id === id);
        if (i !== -1) events.value[i] = data;
        return data;
    }

    return { events, loading, fetchError, fetchEvents, createEvent, updateEvent, deleteEvent, publishEvent };
});
