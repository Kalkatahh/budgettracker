import { createRouter, createWebHistory } from 'vue-router';
import Register from '../views/RegisterView.vue';
import Login from '../views/LoginView.vue';
import Dashboard from '../views/DashboardView.vue';
import Receipts from '../views/ReceiptsView.vue';

const routes = [
  { path: '/register', name: 'Register', component: Register },
  { path: '/login', name: 'Login', component: Login },
  { path: '/dashboard', name: 'Dashboard', component: Dashboard },
  { path: '/receipts', name: 'Receipts', component: Receipts }, // Add this line
  { path: '/', redirect: '/login' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Navigation guard to protect routes
router.beforeEach((to, from, next) => {
  const isLoggedIn = !!localStorage.getItem('token');

  if ((to.path === '/dashboard' || to.path === '/receipts') && !isLoggedIn) {
    // Redirect to login if trying to access dashboard or receipts without being logged in
    next('/login');
  } else {
    next(); // Proceed to the requested route
  }
});

export default router;