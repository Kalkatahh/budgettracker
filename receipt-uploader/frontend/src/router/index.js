import { createRouter, createWebHistory } from "vue-router";
import Register from "../views/RegisterView.vue";
import Login from "../views/LoginView.vue";
import Dashboard from "../views/DashboardView.vue";

const routes = [
    { path: "/register", name: "Register", component: Register },
    { path: "/login", name: "Login", component: Login },
    { path: "/dashboard", name: "Dashboard", component: Dashboard },
    { path: "/", redirect: "/login" },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
