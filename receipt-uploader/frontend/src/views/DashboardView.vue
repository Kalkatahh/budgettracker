<template>
    <div class="max-w-2xl mx-auto p-6 bg-white rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-4 text-center">Dashboard</h1>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Upload Receipt</label>
                <input type="file" @change="previewFile" ref="fileInput" accept="image/jpeg,image/png,application/pdf"
                    class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
            </div>
            <div v-if="preview" class="mt-4">
                <p class="text-sm text-gray-600">Preview:</p>
                <img v-if="previewType === 'image'" :src="preview" alt="Receipt Preview"
                    class="max-w-full rounded-lg shadow-md" />
                <p v-else class="text-gray-500">PDF preview not available. Please upload to proceed.</p>
            </div>
            <button @click="upload" :disabled="!preview"
                class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600 transition disabled:bg-gray-400 disabled:cursor-not-allowed">
                Upload Receipt
            </button>
            <button @click="logout" class="w-full bg-red-500 text-white p-2 rounded hover:bg-red-600 transition mt-2">
                Logout
            </button>
            <p v-if="errorMessage" class="mt-2 text-red-500 text-center">{{ errorMessage }}</p>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return {
            preview: null,
            previewType: 'image', // Default to image, update if PDF
            errorMessage: '',
            selectedFile: null, // Track the selected file
        };
    },
    computed: {
        hasValidFile() {
            return !!this.selectedFile && (this.selectedFile.type === 'image/jpeg' ||
                this.selectedFile.type === 'image/png' ||
                this.selectedFile.type === 'application/pdf');
        },
    },
    methods: {
        previewFile(event) {
            const file = event.target.files[0];
            if (!file) {
                this.selectedFile = null;
                this.preview = null;
                this.previewType = 'image';
                this.errorMessage = '';
                return;
            }

            if (file.type === 'application/pdf') {
                this.previewType = 'pdf';
                this.preview = null; // No preview for PDF, just proceed
                this.errorMessage = '';
            } else if (file.type === 'image/jpeg' || file.type === 'image/png') {
                this.previewType = 'image';
                this.preview = URL.createObjectURL(file);
                this.errorMessage = '';
            } else {
                this.errorMessage = 'Please upload a JPG, PNG, or PDF file.';
                this.preview = null;
                this.previewType = 'image';
                this.selectedFile = null;
                return;
            }

            this.selectedFile = file; // Store the valid file
        },
        async upload() {
            const token = localStorage.getItem('token');
            if (!token) {
                this.errorMessage = 'Please log in first.';
                this.$router.push('/login');
                return;
            }

            const formData = new FormData();
            formData.append('receipt', this.selectedFile);

            try {
                const response = await axios.post('http://127.0.0.1:8000/api/receipts', formData, {
                    headers: {
                        Authorization: `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                    withCredentials: true,
                });
                this.errorMessage = 'Receipt uploaded successfully!';
                this.preview = null;
                this.previewType = 'image';
                this.selectedFile = null;
                this.$refs.fileInput.value = '';
            } catch (error) {
                this.errorMessage = `Upload failed: ${error.response?.data?.message || error.message}`;
                console.error('Upload error:', error.response);
            }
        },
        async logout() {
            const token = localStorage.getItem('token');
            if (!token) {
                this.$router.push('/login');
                return;
            }

            try {
                await axios.post('http://127.0.0.1:8000/api/logout', {}, {
                    headers: { Authorization: `Bearer ${token}` },
                    withCredentials: true,
                });
                localStorage.removeItem('token');
                this.$router.push('/login');
                this.errorMessage = 'Logged out successfully.';
            } catch (error) {
                this.errorMessage = `Logout failed: ${error.response?.data?.message || error.message}`;
                console.error('Logout error:', error.response);
            }
        },
    },
};
</script>