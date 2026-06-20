import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useSubEventsStore = defineStore('subEvents', () => {
    const subEvents = ref([]);
    const loading = ref(false);
    const fetchError = ref(null);

    async function fetchSubEvents(eventId) {
        loading.value = true;
        fetchError.value = null;
        try {
            const { data } = await axios.get(`/api/events/${eventId}/sub-events`);
            subEvents.value = data;
        } catch {
            fetchError.value = 'Failed to load sessions. Please refresh.';
        } finally {
            loading.value = false;
        }
    }

    async function createSubEvent(eventId, payload) {
        const { data } = await axios.post(`/api/events/${eventId}/sub-events`, payload);
        subEvents.value.push(data);
        return data;
    }

    async function updateSubEvent(eventId, id, payload) {
        const { data } = await axios.put(`/api/events/${eventId}/sub-events/${id}`, payload);
        const i = subEvents.value.findIndex(s => s.id === id);
        if (i !== -1) subEvents.value[i] = data;
        return data;
    }

    async function deleteSubEvent(eventId, id) {
        await axios.delete(`/api/events/${eventId}/sub-events/${id}`);
        subEvents.value = subEvents.value.filter(s => s.id !== id);
    }

    return { subEvents, loading, fetchError, fetchSubEvents, createSubEvent, updateSubEvent, deleteSubEvent };
});
