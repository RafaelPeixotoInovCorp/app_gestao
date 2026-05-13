import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

/**
 * CSRF: confiar no cookie XSRF-TOKEN + header X-XSRF-TOKEN (withXSRFToken).
 * Não injectar X-CSRF-TOKEN a partir da meta: se a meta estiver desactualizada, o Laravel
 * usa na mesma esse header (antes do cookie) e devolve 419 mesmo com cookie válido.
 * @inertiajs/core usa esta instância de axios; a meta continua a ser actualizada em app.js
 * para formulários Blade ou código que leia csrf_token do DOM.
 */
