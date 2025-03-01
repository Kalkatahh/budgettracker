<template>
    <div id="app">
        <nav>
            <router-link to="/login">Login</router-link> |
            <router-link to="/register">Register</router-link> |
            <router-link to="/dashboard" v-if="isLoggedIn">Dashboard</router-link>
            <button @click="logout" v-if="isLoggedIn">Logout</button>
        </nav>
        <router-view />
    </div>
</template>

<script>
import axios from "axios";

export default {
    computed: {
        isLoggedIn() {
            return !!localStorage.getItem("token");
        },
    },
    methods: {
        async logout() {
            await axios.post(
                "http://127.0.0.1:8000/api/logout",
                {},
                {
                    headers: {
                        Authorization: `Bearer ${localStorage.getItem(
                            "token"
                        )}`,
                    },
                }
            );
            localStorage.removeItem("token");
            this.$router.push("/login");
        },
    },
};
</script>

<style>
nav {
    padding: 10px;
}

nav a {
    margin-right: 10px;
}
</style>
