import { handleError } from '@/helpers/errorHelper'
import { axiosInstance } from '@/plugins/axios'
// import router from "@/router"; // Removed to prevent circular dependency
import Cookies from 'js-cookie'
import { defineStore } from 'pinia'

// App ini SELALU 'buyer' atau SELALU 'store', ditentukan saat build (bukan
// toggle runtime lagi) -- dua domain terpisah (blukios.store vs
// seller.blukios.store), sama seperti Shopee App vs Seller Centre yang
// juga dua app terpisah tanpa konsep "switch mode" di dalam 1 app.
const APP_TARGET = import.meta.env.VITE_APP_TARGET === 'seller' ? 'store' : 'buyer'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: Cookies.get('token') || null,
    activeMode: APP_TARGET,
    loading: false,
    error: null,
    success: null
  }),
  getters: {
    isAuthenticated: (state) => !!state.user,
    currentMode: (state) => state.activeMode
  },
  actions: {
    // Minta exchange token dari domain ini, lalu redirect penuh (bukan
    // router.push) ke domain lain -- kedua app tidak share router instance.
    async initiateSso(targetUrl) {
      const response = await axiosInstance.post('/auth/sso/initiate')
      const exchangeToken = response.data.data.exchange_token
      window.location.href = `${targetUrl}/sso/callback?xt=${exchangeToken}`
    },
    async login(credentials) {
      this.loading = true
      this.error = null

      try {
        const response = await axiosInstance.post('/login', credentials)

        const token = response.data.data.token

        Cookies.set('token', token, {
          secure: window.location.protocol === 'https:',
          sameSite: 'Strict'
        })
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
        Cookies.set('token', token, {
          secure: window.location.protocol === 'https:',
          sameSite: 'Strict'
        })
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

        localStorage.removeItem('grouped_cart')
        localStorage.removeItem('selected_stores')

        // Return true to indicate success, let component handle redirect
        return true
      } catch (error) {
        this.error = handleError(error)
        return false
      } finally {
        this.loading = false
      }
    },

    async updateProfile(formData) {
      this.loading = true
      this.error = null
      this.success = null

      try {
        const response = await axiosInstance.post('/profile', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })

        this.user = response.data.data
        this.success = response.data.message

        return { success: true }
      } catch (error) {
        if (error.response?.status === 422) {
          this.error = error.response.data.errors
          return { success: false, errors: error.response.data.errors }
        }
        this.error = handleError(error)
        return {
          success: false,
          errors: { general: [error.response?.data?.message || 'Terjadi kesalahan.'] }
        }
      } finally {
        this.loading = false
      }
    },

    async updateSettings(payload) {
      this.loading = true
      this.error = null
      this.success = null

      try {
        const response = await axiosInstance.put('/profile/settings', payload)

        this.user = response.data.data
        this.success = response.data.message

        return true
      } catch (error) {
        this.error = handleError(error)
        return false
      } finally {
        this.loading = false
      }
    },

    async deleteAccount() {
      this.loading = true
      this.error = null

      try {
        await axiosInstance.delete('/profile')
        return { success: true }
      } catch (error) {
        return {
          success: false,
          message: error.response?.data?.message || 'Gagal menghapus akun. Silakan coba lagi.'
        }
      } finally {
        this.loading = false
      }
    },

    async forgotPassword(email) {
      this.loading = true
      this.error = null
      this.success = null

      try {
        const response = await axiosInstance.post('/password/forgot', { email })
        this.success = response.data.message
        return { success: true, message: response.data.message }
      } catch (error) {
        const status = error.response?.status
        const message = error.response?.data?.message

        if (status === 422 || status === 404) {
          this.error = { email: [message || 'Email tidak terdaftar.'] }
        } else if (status === 429) {
          this.error = { email: ['Terlalu banyak percobaan. Silakan tunggu sebentar.'] }
        } else {
          this.error = handleError(error)
        }
        return { success: false }
      } finally {
        this.loading = false
      }
    },

    async resetPassword(payload) {
      this.loading = true
      this.error = null
      this.success = null

      try {
        const response = await axiosInstance.post('/password/reset', payload)
        this.success = response.data.message
        return { success: true, message: response.data.message }
      } catch (error) {
        const status = error.response?.status
        const message = error.response?.data?.message

        if (status === 422) {
          if (
            message?.toLowerCase().includes('token') ||
            message?.toLowerCase().includes('kadaluarsa')
          ) {
            this.error = { token: [message] }
          } else {
            this.error = { password: [message || 'Validasi gagal.'] }
          }
        } else if (status === 404) {
          this.error = { email: [message || 'Email tidak ditemukan.'] }
        } else {
          this.error = handleError(error)
        }
        return { success: false }
      } finally {
        this.loading = false
      }
    }
  }
})
