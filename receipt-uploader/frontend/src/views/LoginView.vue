<template>
    <div class="max-w-md mx-auto p-6 bg-white rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-4 text-center">Login</h1>
        <form @submit.prevent="login" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input v-model="form.email" type="email" placeholder="Email" required
                    class="mt-1 block w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input v-model="form.password" type="password" placeholder="Password" required
                    class="mt-1 block w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600 transition">
                Login
            </button>
        </form>
        <p class="mt-4 text-red-500 text-center">{{ message }}</p>
        <p class="mt-2 text-center">
            Don't have an account? <router-link to="/register" class="text-blue-500 hover:underline">Register
                here</router-link>
        </p>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return {
            form: { email: '', password: '' },
            message: '',
        };
    },
    methods: {
        async login() {
            try {
                const response = await axios.post('http://127.0.0.1:8000/api/login', this.form, {
                    headers: { 'Accept': 'application/json' },
                    withCredentials: true,  // Allow cookies and credentials for Sanctum
                });
                if (response.data && response.data.token) {
                    localStorage.setItem('token', response.data.token);
                    this.$router.push('/dashboard');
                } else {
                    throw new Error('Invalid response from server: No token found');
                }
            } catch (error) {
                this.message = error.response?.data?.message || `Error: ${error.message} (Status: ${error.response?.status})`;
                console.error('Login error:', error.response);
            }
        },
    },
};
</script>

<style scoped>
/* Optional: Add scoped styles if needed */
</style>