import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useCustomDomainsStore = defineStore('customDomains', () => {
    const domains = ref([]);
    const loading = ref(false);
    const fetchError = ref(null);

    async function fetchDomains() {
        loading.value = true;
        fetchError.value = null;
        try {
            const { data } = await axios.get('/api/custom-domains');
            domains.value = data;
        } catch {
            fetchError.value = 'Failed to load custom domains.';
        } finally {
            loading.value = false;
        }
    }

    async function addDomain(domain) {
        const { data } = await axios.post('/api/custom-domains', { domain });
        domains.value.unshift(data);
        return data;
    }

    async function verifyDomain(id) {
        const { data } = await axios.post(`/api/custom-domains/${id}/verify`);
        const i = domains.value.findIndex(d => d.id === id);
        if (i !== -1) domains.value[i] = data;
        return data;
    }

    async function deleteDomain(id) {
        await axios.delete(`/api/custom-domains/${id}`);
        domains.value = domains.value.filter(d => d.id !== id);
    }

    return { domains, loading, fetchError, fetchDomains, addDomain, verifyDomain, deleteDomain };
});
