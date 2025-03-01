<template>
    <div>
        <h1>Register</h1>
        <form @submit.prevent="register">
            <input v-model="form.name" type="text" placeholder="Name" required />
            <input v-model="form.email" type="email" placeholder="Email" required />
            <input v-model="form.password" type="password" placeholder="Password" required />
            <input v-model="form.password_confirmation" type="password" placeholder="Confirm Password" required />
            <button type="submit">Register</button>
        </form>
        <p>{{ message }}</p>
        <p>Already have an account? <router-link to="/login">Login here</router-link></p>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return {
            form: { name: '', email: '', password: '', password_confirmation: '' },
            message: '',
        };
    },
    methods: {
        async register() {
            try {
                const response = await axios.post('http://127.0.0.1:8000/api/register', this.form, {
                    headers: { 'Accept': 'application/json' }
                });
                localStorage.setItem('token', response.data.token);
                this.$router.push('/dashboard');
            } catch (error) {
                this.message = error.response?.data?.message || `Error: ${error.message} (Status: ${error.response?.status})`;
                console.error('Registration error:', error.response);
            }
        },
    },
};
</script>