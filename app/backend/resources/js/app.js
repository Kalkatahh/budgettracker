import './bootstrap';
import '../css/app.css'
import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import axios from 'axios'

const app = createApp(App)
app.use(router)
app.mount('#app')
// axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
// axios.defaults.withCredentials = true

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
console.log("CSRF Token:", csrfToken);

axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
axios.defaults.withCredentials = true;
