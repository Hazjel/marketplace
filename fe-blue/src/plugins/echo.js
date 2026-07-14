import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { axiosInstance } from './axios'

window.Pusher = Pusher

// Host/port mengikuti halaman (nginx proxy path /app ke Reverb),
// jadi jalan di localhost, LAN, maupun Tailscale tanpa rebuild.
const isSecure = window.location.protocol === 'https:'
const wsHost = import.meta.env.VITE_REVERB_HOST || window.location.hostname
const wsPort = Number(import.meta.env.VITE_REVERB_PORT || window.location.port || (isSecure ? 443 : 80))

const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY || 'reverbkey',
  wsHost,
  wsPort,
  wssPort: wsPort,
  forceTLS: isSecure,
  enabledTransports: isSecure ? ['wss'] : ['ws'],
  authorizer: (channel, options) => {
    return {
      authorize: (socketId, callback) => {
        axiosInstance
          .post('/broadcasting/auth', {
            socket_id: socketId,
            channel_name: channel.name
          })
          .then((response) => {
            callback(false, response.data)
          })
          .catch((error) => {
            callback(true, error)
          })
      }
    }
  }
})

window.Echo = echo

export default echo
