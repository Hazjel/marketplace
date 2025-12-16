
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { axiosInstance } from './axios';

window.Pusher = Pusher;

console.log('Reverb Config:', {
    key: import.meta.env.VITE_REVERB_APP_KEY || 'reverbkey',
    host: import.meta.env.VITE_REVERB_HOST || 'localhost',
    port: import.meta.env.VITE_REVERB_PORT || 8080
});

const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'reverbkey',
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT) : 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT) : 443, // Reverb usually uses same port for local
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel, options) => {
        return {
            authorize: (socketId, callback) => {
                axiosInstance.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name
                })
                    .then(response => {
                        callback(false, response.data);
                    })
                    .catch(error => {
                        callback(true, error);
                    });
            }
        };
    },
});

export default echo;
