<template>
    <div class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-4 text-center">Uploaded Receipts</h1>
        <div v-if="receipts.length > 0" class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-2 px-4 border-b text-left">Date</th>
                        <th class="py-2 px-4 border-b text-left">Store</th>
                        <th class="py-2 px-4 border-b text-left">Payment Method</th>
                        <th class="py-2 px-4 border-b text-left">Cost</th>
                        <th class="py-2 px-4 border-b text-left">Google Drive Link</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="receipt in receipts" :key="receipt.id" class="border-b">
                        <td class="py-2 px-4">{{ receipt.date }}</td>
                        <td class="py-2 px-4">{{ receipt.store }}</td>
                        <td class="py-2 px-4">{{ receipt.payment_method }}</td>
                        <td class="py-2 px-4">${{ receipt.cost }}</td>
                        <td class="py-2 px-4">
                            <a :href="receipt.google_drive_link" target="_blank" class="text-blue-500 hover:underline">
                                View on Google Drive
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="text-center text-gray-500">No receipts uploaded yet.</p>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return {
            receipts: [],
        };
    },
    created() {
        this.fetchReceipts();
    },
    methods: {
        async fetchReceipts() {
            const token = localStorage.getItem('token');
            if (!token) {
                this.$router.push('/login');
                return;
            }

            try {
                const response = await axios.get('http://127.0.0.1:8000/api/receipts', {
                    headers: { Authorization: `Bearer ${token}` },
                    withCredentials: true,
                });
                this.receipts = response.data.data || response.data; // Adjust based on backend response
            } catch (error) {
                console.error('Failed to fetch receipts:', error.response);
                alert(`Failed to load receipts: ${error.response?.data?.message || error.message}`);
            }
        },
    },
};
</script>

<style scoped></style>