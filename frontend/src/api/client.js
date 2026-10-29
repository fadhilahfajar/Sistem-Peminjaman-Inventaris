import axios from 'axios'

// Satu instance untuk seluruh aplikasi. Permintaan relatif ke origin yang sama
// (Vite meneruskan /api dan /sanctum ke backend), jadi cookie sesi Sanctum
// dan CSRF bekerja tanpa CORS. Interceptor galat ditambahkan pada Task 4.2.
const client = axios.create({
  baseURL: '/',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export default client
