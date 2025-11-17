import axios from 'axios';
import Cookies from 'js-cookie';

const token = Cookies.get('token');

axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Content-type'] = 'multipart/form-data';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

axios.interceptors.response.use(
    config => {
        const token = Cookies.get('token');
        if (token) {
            config.headers['Authorization'] = `Bearer ${token}`;
        }

        return config;
    },
)

export const axiosInstance = axios;