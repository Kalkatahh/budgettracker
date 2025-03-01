<template>
    <div>
        <h1>Dashboard</h1>
        <input type="file" @change="previewFile" ref="fileInput" />
        <img v-if="preview" :src="preview" alt="Preview" />
        <button @click="upload">Upload Receipt</button>
        <button @click="logout">Logout</button>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return { preview: null };
    },
    methods: {
        previewFile(event) {
            this.preview = URL.createObjectURL(event.target.files[0]);
        },
        async upload() {
            const formData = new FormData();
            formData.append('receipt', this.$refs.fileInput.files[0]);
            await axios.post('http://127.0.0.1:8000/api/receipts', formData, {
                headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
            });
            alert('Receipt uploaded!');
        },
        async logout() {
            await axios.post('http://127.0.0.1:8000/api/logout', {}, {
                headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
            });
            localStorage.removeItem('token');
            this.$router.push('/login');
        },
    },
};
</script>