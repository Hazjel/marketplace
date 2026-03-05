import axios from 'axios'
import Cookies from 'js-cookie'

// Buat instance axios
export const axiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api', // URL backend Laravel
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }
})

// Tambahkan interceptor ke axiosInstance (BUKAN axios global)
axiosInstance.interceptors.request.use(
  (config) => {
    const token = Cookies.get('token')

    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    // Inject Socket ID for broadcasting toOthers()
    if (window.Echo && window.Echo.socketId()) {
      config.headers['X-Socket-Id'] = window.Echo.socketId()
    }

    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Menggunakan fallback sederhana karena useToast() dari vue-toastification hanya berjalan di dalam script setup Vue.
// Solusi: dispatch custom event yang nanti ditangkap oleh App.vue untuk memunculkan toast.
axiosInstance.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const message = error.response?.data?.message || 'Terjadi kesalahan pada server'
    const originalRequest = error.config

    // Jika 401 dari endpoint /login (salah password), biarkan komponen Login.vue yang handle
    if (status === 401 && originalRequest.url && originalRequest.url.includes('/login')) {
      return Promise.reject(error)
    }

    if (status === 401) {
      Cookies.remove('token')

      // Kirim event global ke App.vue untuk memunculkan toast
      window.dispatchEvent(new CustomEvent('api-error', {
        detail: { message: 'Sesi Anda telah berakhir. Silakan login kembali.' }
      }))

      // Delay redirect so user can read the toast message
      setTimeout(() => {
        window.location.href = '/auth/login'
      }, 1500)
    } else if (status >= 500) {
      window.dispatchEvent(new CustomEvent('api-error', {
        detail: { message: 'Kesalahan Sistem (500). Silakan coba lagi nanti.' }
      }))
    } else if (status !== 422) {
      window.dispatchEvent(new CustomEvent('api-error', {
        detail: { message: message }
      }))
    }

    return Promise.reject(error)
  }
)
