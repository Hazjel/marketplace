import { handleError } from '@/helpers/errorHelper'
import { axiosInstance } from '@/plugins/axios'
// import router from "@/router"; // Removed to prevent circular dependency
import Cookies from 'js-cookie'
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: Cookies.get('token') || null,
    activeMode: Cookies.get('activeMode') || 'buyer', // 'buyer' or 'store'
    loading: false,
    error: null,
    success: null
  }),
  getters: {
    isAuthenticated: (state) => !!state.user,
    currentMode: (state) => state.activeMode
  },
  actions: {
    setMode(mode) {
      this.activeMode = mode
      Cookies.set('activeMode', mode)
    },
    async login(credentials) {
      this.loading = true
      this.error = null

      try {
        const response = await axiosInstance.post('/login', credentials)

        const token = response.data.data.token

        Cookies.set('token', token)
        this.token = token // Update reactive state
        this.user = response.data.data // Langsung set user dari respons login

        this.success = response.data.message

        return response.data.data // Mengembalikan data user beserta role
      } catch (error) {
        this.error = handleError(error)
        return null // Penting: kembalikan null saat gagal
      } finally {
        this.loading = false
      }
    },

    async register(payload) {
      this.loading = true
      this.error = null

      try {
        const response = await axiosInstance.post('/register', payload, {
          headers: {
            'Content-Type': 'multipart/form-data' // ✅ Tambahkan ini
          }
        })

        const token = response.data.data.token
        Cookies.set('token', token)
        this.token = token
        this.user = response.data.data // Langsung set user dari respons register
        this.success = response.data.message

        return response.data.data // ✅ Return data
      } catch (error) {
        this.error = handleError(error)
        throw error // ✅ Throw error agar bisa dicatch di component
      } finally {
        this.loading = false
      }
    },

    async checkAuth() {
      this.loading = true

      try {
        const response = await axiosInstance.get('/me')

        this.user = response.data.data

        return this.user
      } catch (error) {
        if (error.response?.status === 401) {
          Cookies.remove('token')
          this.token = null
          this.user = null
          return null
        }
        this.error = handleError(error)
        return null
      } finally {
        this.loading = false
      }
    },

    async logout() {
      this.loading = true
      try {
        await axiosInstance.post('/logout')

        Cookies.remove('token')
        this.token = null
        this.user = null
        this.error = null

        // Return true to indicate success, let component handle redirect
        return true
      } catch (error) {
        this.error = handleError(error)
        return false
      } finally {
        this.loading = false
      }
    }
  }
})
