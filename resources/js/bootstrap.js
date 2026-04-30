import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

const csrf = document.head.querySelector('meta[name="csrf-token"]');
if (csrf) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.content;
}
