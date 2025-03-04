<template>
    <div class="container max-w-2xl my-6 p-4 bg-white rounded shadow">
        <h1 class="display-5 fw-bold text-center mb-4">Dashboard</h1>
        <div class="space-y-4">
            <div>
                <label class="form-label fw-medium text-muted">Choose Files</label>
                <input type="file" @change="previewFiles" ref="fileInput" accept="image/jpeg,image/png" multiple
                    class="form-control mb-2" />
            </div>
            <div v-if="previews.length > 0" class="mt-4">
                <p class="text-muted mb-2">Previews:</p>
                <div class="row row-cols-1 row-cols-md-2 g-3">
                    <div v-for="(preview, index) in previews" :key="index" class="col">
                        <img :src="preview" alt="Receipt Preview" class="img-fluid rounded shadow-sm" />
                    </div>
                </div>
            </div>
            <button @click="uploadReceipts" :disabled="!hasValidFiles"
                class="btn btn-primary w-100 py-2 rounded-pill mt-3" v-if="isPremium">
                Upload Receipts
            </button>
            <button @click="download" :disabled="!hasValidFiles && !processedFiles.length"
                class="btn btn-success w-100 py-2 rounded-pill mt-2">
                Download
            </button>
            <button @click="connectGoogleDrive" class="btn btn-outline-primary w-100 py-2 rounded-pill mt-2"
                v-if="!isGoogleDriveConnected && isPremium">
                Connect Google Drive
            </button>
            <button @click="uploadToGoogleDrive" :disabled="!processedFiles.length || !isGoogleDriveConnected"
                class="btn btn-outline-success w-100 py-2 rounded-pill mt-2" v-if="isGoogleDriveConnected && isPremium">
                Upload to Google Drive
            </button>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return {
            previews: [],
            errorMessage: '',
            selectedFiles: [], // Track multiple selected files
            processedFiles: [], // Track processed file names from the server
            isGoogleDriveConnected: false, // Track Google Drive connection status
        };
    },
    computed: {
        hasValidFiles() {
            console.log('hasValidFiles:', this.selectedFiles.length > 0 && this.selectedFiles.every(file =>
                file.type === 'image/jpeg' || file.type === 'image/png'));
            return this.selectedFiles.length > 0 && this.selectedFiles.every(file =>
                file.type === 'image/jpeg' || file.type === 'image/png');
        },
        isPremium() {
            const user = JSON.parse(localStorage.getItem('user') || '{}');
            return user.is_premium || false;
        },
    },
    methods: {
        previewFiles(event) {
            const files = event.target.files;
            console.log('Selected files:', files);
            this.selectedFiles = [];
            this.previews = [];
            this.errorMessage = '';

            for (let file of files) {
                if (file.type === 'image/jpeg' || file.type === 'image/png') {
                    this.previews.push(URL.createObjectURL(file));
                    this.selectedFiles.push(file);
                } else {
                    this.errorMessage = 'Please upload only JPG or PNG files.';
                    this.previews = [];
                    this.selectedFiles = [];
                    return;
                }
            }
        },
        async uploadReceipts() {
            const token = localStorage.getItem('token');
            if (!token) {
                this.errorMessage = 'Please log in first.';
                this.$router.push('/login');
                return;
            }

            if (!this.isPremium) {
                this.errorMessage = 'This feature is only available for premium users.';
                return;
            }

            const formData = new FormData();
            this.selectedFiles.forEach(file => formData.append('receipts[]', file));

            console.log('Uploading files:', {
                token,
                fileNames: this.selectedFiles.map(f => f.name),
                fileTypes: this.selectedFiles.map(f => f.type),
                formData,
            });

            try {
                const response = await axios.post('http://127.0.0.1:8000/api/receipts', formData, {
                    headers: {
                        Authorization: `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                    withCredentials: true,
                });

                this.errorMessage = '';
                this.previews = [];
                this.selectedFiles = [];
                this.$refs.fileInput.value = '';
                this.processedFiles = response.data.processed_files || [];
            } catch (error) {
                this.errorMessage = `Upload failed: ${error.response?.data?.message || error.message}`;
                console.error('Upload error:', error.response);
            }
        },
        async download() {
            const token = localStorage.getItem('token');
            if (!token) {
                this.errorMessage = 'Please log in first.';
                this.$router.push('/login');
                return;
            }

            if (!this.hasValidFiles && !this.processedFiles.length) {
                this.errorMessage = 'No files selected or processed for download.';
                return;
            }

            try {
                let response;
                if (this.hasValidFiles) {
                    // Process and download immediately for all users
                    const formData = new FormData();
                    this.selectedFiles.forEach(file => formData.append('receipts[]', file));

                    response = await axios.post('http://127.0.0.1:8000/api/receipts/download', formData, {
                        headers: {
                            Authorization: `Bearer ${token}`,
                            'Accept': 'application/json',
                        },
                        withCredentials: true,
                        responseType: 'blob', // Handle ZIP download
                    });
                } else if (this.processedFiles.length) {
                    // Download previously processed files for all users
                    response = await axios.post('http://127.0.0.1:8000/api/receipts/download', {}, {
                        headers: {
                            Authorization: `Bearer ${token}`,
                            'Accept': 'application/json',
                        },
                        withCredentials: true,
                        responseType: 'blob',
                    });
                }

                this.errorMessage = '';
                const blob = new Blob([response.data]);
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'receipts_' + new Date().toISOString().replace(/[:.-]/g, '') + '.zip';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);

                // Clear selected files and processed files after download
                this.previews = [];
                this.selectedFiles = [];
                this.processedFiles = [];
                this.$refs.fileInput.value = '';
            } catch (error) {
                this.errorMessage = `Download failed: ${error.response?.data?.message || error.message}`;
                console.error('Download error:', error.response);
            }
        },
        async uploadToGoogleDrive() {
            const token = localStorage.getItem('token');
            if (!token) {
                this.errorMessage = 'Please log in first.';
                this.$router.push('/login');
                return;
            }

            if (!this.isPremium) {
                this.errorMessage = 'This feature is only available for premium users.';
                return;
            }

            if (!this.isGoogleDriveConnected) {
                this.errorMessage = 'Please connect your Google Drive first.';
                return;
            }

            if (!this.processedFiles.length) {
                this.errorMessage = 'No processed files available for upload.';
                return;
            }

            const formData = new FormData();
            this.processedFiles.forEach(file => formData.append('files[]', file));

            try {
                const response = await axios.post('http://127.0.0.1:8000/api/receipts/upload-to-drive', formData, {
                    headers: {
                        Authorization: `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                    withCredentials: true,
                });

                this.errorMessage = '';
                this.processedFiles = []; // Clear processed files after upload
                alert('Receipts uploaded to your Google Drive successfully!'); // Simple alert instead of toast
            } catch (error) {
                this.errorMessage = `Upload to Google Drive failed: ${error.response?.data?.message || error.message}`;
                console.error('Google Drive upload error:', error.response);
            }
        },
        async connectGoogleDrive() {
            const token = localStorage.getItem('token');
            if (!token) {
                this.errorMessage = 'Please log in first.';
                this.$router.push('/login');
                return;
            }

            if (!this.isPremium) {
                this.errorMessage = 'This feature is only available for premium users.';
                return;
            }

            try {
                const response = await axios.get('http://127.0.0.1:8000/api/auth/google', {
                    headers: { Authorization: `Bearer ${token}` },
                    withCredentials: true,
                });
                window.location.href = response.data.authUrl; // Redirect to Google OAuth
            } catch (error) {
                this.errorMessage = `Failed to connect Google Drive: ${error.response?.data?.message || error.message}`;
                console.error('Google Drive connection error:', error.response);
            }
        },
        async checkGoogleDriveConnection() {
            const token = localStorage.getItem('token');
            if (!token) return;

            try {
                const response = await axios.get('http://127.0.0.1:8000/api/user', {
                    headers: { Authorization: `Bearer ${token}` },
                    withCredentials: true,
                });
                this.isGoogleDriveConnected = !!response.data.google_drive_refresh_token;
            } catch (error) {
                console.error('Failed to check Google Drive connection:', error.response);
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
                localStorage.removeItem('user'); // Clear user data
                this.$router.push('/login');
                this.errorMessage = 'Logged out successfully.';
                this.processedFiles = [];
                this.isGoogleDriveConnected = false;
            } catch (error) {
                this.errorMessage = `Logout failed: ${error.response?.data?.message || error.message}`;
                console.error('Logout error:', error.response);
            }
        },
        async fetchUser() {
            const token = localStorage.getItem('token');
            if (!token) return;

            try {
                const response = await axios.get('http://127.0.0.1:8000/api/user', {
                    headers: { Authorization: `Bearer ${token}` },
                    withCredentials: true,
                });
                localStorage.setItem('user', JSON.stringify(response.data));
            } catch (error) {
                console.error('Failed to fetch user:', error.response);
            }
        },
    },
    created() {
        this.checkGoogleDriveConnection();
        const token = localStorage.getItem('token');
        if (token) {
            this.fetchUser();
        }
    },
};
</script>