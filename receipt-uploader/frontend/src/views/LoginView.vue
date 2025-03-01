<template>
    <div>
        <h1>Login</h1>
        <form @submit.prevent="login">
            <input v-model="form.email" type="email" placeholder="Email" required />
            <input v-model="form.password" type="password" placeholder="Password" required />
            <button type="submit">Login</button>
        </form>
        <p>{{ message }}</p>
        <p>Don't have an account? <router-link to="/register">Register here</router-link></p>
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
                const response = await axios.post('http://127.0.0.1:8000/api/login', this.form);
                localStorage.setItem('token', response.data.token);
                this.$router.push('/dashboard');
            } catch (error) {
                this.message = error.response.data.message || 'Login failed';
            }
        },
    },
};
</script>