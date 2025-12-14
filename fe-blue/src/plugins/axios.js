import axios from 'axios';
import Cookies from 'js-cookie';

// Buat instance axios
export const axiosInstance = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api', // URL backend Laravel
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    }
});

// Tambahkan interceptor ke axiosInstance (BUKAN axios global)
axiosInstance.interceptors.request.use(
    (config) => {
        const token = Cookies.get('token');
        
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Optional: Handle response errors
axiosInstance.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            Cookies.remove('token');
            // Redirect ke login jika perlu
            // window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);