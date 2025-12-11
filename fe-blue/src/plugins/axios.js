import axios from 'axios';
import Cookies from 'js-cookie';


const token = Cookies.get('token');

axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL || '/api/';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

// Do not set a global Content-Type here. Some requests use JSON, others use
// multipart/form-data. Setting it globally prevents axios from correctly
// building request boundaries for FormData and may cause server-side errors.

axios.interceptors.request.use(
    config => {
        const token = Cookies.get('token');
        if (token) {
            config.headers['Authorization'] = `Bearer ${token}`;
        }

        return config;
    },
)

export const axiosInstance = axios.create({
    baseURL: 'http://localhost:5173/api/', // sesuaikan
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
})
