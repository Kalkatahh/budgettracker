<template>
    <div class="min-h-screen bg-gray-100">
        <nav class="bg-white shadow-md p-4">
            <div class="max-w-4xl mx-auto flex justify-between items-center">
                <router-link to="/dashboard" class="text-blue-500 hover:text-blue-700">Dashboard</router-link>
                <div class="space-x-4">
                    <div v-if="!isLoggedIn">
                        <router-link to="/login" class="text-blue-500 hover:text-blue-700">Login</router-link>
                        <router-link to="/register" class="text-blue-500 hover:text-blue-700">Register</router-link>
                    </div>
                    <div v-else class="flex items-center space-x-4">
                        <span class="text-gray-700">Welcome, {{ username || 'User' }}!</span>
                        <button @click="logout" class="text-red-500 hover:text-red-700">Logout</button>
                    </div>
                </div>
            </div>
        </nav>
        <main class="max-w-4xl mx-auto p-6">
            <router-view />
        </main>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return {
            username: '', // Store the username of the logged-in user
        };
    },
    computed: {
        isLoggedIn() {
            const token = localStorage.getItem('token');
            console.log('Checking login status, token:', token); // Debug log
            return !!token;
        },
    },
    methods: {
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
                this.username = '';
                this.$router.push('/login');
            } catch (error) {
                alert(`Logout failed: ${error.response?.data?.message || error.message}`);
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
                this.username = response.data.name; // Assuming the response includes the user's name
                console.log('Fetched username:', this.username); // Debug log
            } catch (error) {
                console.error('Failed to fetch user:', error.response);
                this.username = ''; // Clear username on failure to prevent stale data
            }
        },
    },
    created() {
        if (this.isLoggedIn) {
            this.fetchUser();
        }
    },
    watch: {
        isLoggedIn(newValue) {
            console.log('Login status changed to:', newValue); // Debug log
            if (newValue) {
                this.fetchUser();
            } else {
                this.username = '';
            }
        },
    },
};
</script>