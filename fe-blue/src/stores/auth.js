import { handleError } from "@/helpers/errorHelper";
import { axiosInstance } from "@/plugins/axios";
import router from "@/router";
import Cookies from "js-cookie";
import { defineStore } from "pinia";

export const useAuthStore = defineStore("auth", {
    state: () => ({
        user: null,
        loading: false,
        error: null,
        success: null,
    }),
    getters: {
        token: () => Cookies.get('token'),
        isAuthenticated: (state) => !!state.user,
    },
    actions: {
        async login(credentials) {
            this.loading = true
            this.error = null

            try {
                const response = await axiosInstance.post('/login', credentials)

                const token = response.data.data.token

                Cookies.set('token', token)

                this.success = response.data.message

                router.push({ name: 'admin.dashboard' })
            } catch (error) {
                this.error = handleError(error)
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
                    router.push({ name: 'auth.login' })
                }
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async logout() {
            this.loading = true
            try {
                await axiosInstance.post('/logout')

                Cookies.remove('token')
                this.user = null
                this.error = null

                router.push({ name: 'auth.login' })
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        }
    }
});