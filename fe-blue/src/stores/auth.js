import { handleError } from "@/helpers/errorHelper";
import { axiosInstance } from "@/plugins/axios";
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

                console.log(this.success)
                // router.push({ name: 'admin.dashboard' })
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },
    }
})