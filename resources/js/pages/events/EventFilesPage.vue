<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useAttachmentsStore } from '@/stores/attachments.js';

const route = useRoute();
const store = useAttachmentsStore();
const eventId = route.params.id;

const uploading = ref(false);
const uploadError = ref('');
const fileInput = ref(null);

onMounted(() => store.fetchAttachments(eventId));

async function onFileSelected(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    uploading.value = true;
    uploadError.value = '';
    try {
        await store.uploadAttachment(eventId, file);
    } catch (err) {
        if (err.response?.status === 422) {
            uploadError.value = err.response.data.errors?.file?.[0]
                ?? err.response.data.message
                ?? 'Upload failed.';
        } else {
            uploadError.value = 'Upload failed. Please try again.';
        }
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

async function remove(a) {
    if (!confirm(`Delete "${a.original_name}"?`)) return;
    try {
        await store.deleteAttachment(eventId, a.id);
    } catch {
        uploadError.value = 'Failed to delete file.';
    }
}

function formatSize(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB'];
    let i = 0;
    let n = bytes;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return `${n.toFixed(n < 10 && i > 0 ? 1 : 0)} ${units[i]}`;
}
</script>

<template>
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="flex items-center gap-3 mb-6">
            <RouterLink :to="{ name: 'events.show', params: { id: eventId } }"
                class="text-sm text-gray-500 hover:text-gray-700">
                ← Back to event
            </RouterLink>
        </div>

        <h1 class="text-2xl font-bold mb-1">Files & Attachments</h1>
        <p class="text-gray-500 mb-8">Agendas, maps, menus — up to 10 files (10 MB each).</p>

        <div v-if="uploadError" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm">
            {{ uploadError }}
        </div>

        <!-- Upload -->
        <div class="mb-8">
            <label class="inline-block">
                <input ref="fileInput" type="file" class="hidden" @change="onFileSelected"
                    accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt" />
                <span
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 cursor-pointer inline-block"
                    :class="{ 'opacity-50 pointer-events-none': uploading }">
                    {{ uploading ? 'Uploading…' : 'Upload file' }}
                </span>
            </label>
        </div>

        <!-- List -->
        <div v-if="store.loading" class="text-gray-500">Loading…</div>
        <div v-else-if="store.attachments.length" class="space-y-2">
            <div v-for="a in store.attachments" :key="a.id"
                class="bg-white border border-gray-200 rounded-xl p-4 flex items-center justify-between">
                <div class="min-w-0">
                    <a :href="a.url" target="_blank" rel="noopener"
                        class="font-medium text-indigo-600 hover:underline truncate block">
                        {{ a.original_name }}
                    </a>
                    <div class="text-sm text-gray-400">{{ formatSize(a.size) }}</div>
                </div>
                <button @click="remove(a)" class="text-sm text-red-500 hover:underline shrink-0 ml-4">
                    Delete
                </button>
            </div>
        </div>
        <p v-else class="text-gray-400">No files uploaded yet.</p>
    </div>
</template>
