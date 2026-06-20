import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useAttachmentsStore = defineStore('attachments', () => {
    const attachments = ref([]);
    const loading = ref(false);
    const fetchError = ref(null);

    async function fetchAttachments(eventId) {
        loading.value = true;
        fetchError.value = null;
        try {
            const { data } = await axios.get(`/api/events/${eventId}/attachments`);
            attachments.value = data;
        } catch {
            fetchError.value = 'Failed to load attachments.';
        } finally {
            loading.value = false;
        }
    }

    async function uploadAttachment(eventId, file) {
        const form = new FormData();
        form.append('file', file);
        const { data } = await axios.post(`/api/events/${eventId}/attachments`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        attachments.value.unshift(data);
        return data;
    }

    async function deleteAttachment(eventId, id) {
        await axios.delete(`/api/events/${eventId}/attachments/${id}`);
        attachments.value = attachments.value.filter(a => a.id !== id);
    }

    return { attachments, loading, fetchError, fetchAttachments, uploadAttachment, deleteAttachment };
});
