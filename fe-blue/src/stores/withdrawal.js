import { handleError } from "@/helpers/errorHelper";
import { axiosInstance } from "@/plugins/axios";
import { defineStore } from "pinia";
import router from "@/router";

export const useWithdrawalStore = defineStore("withdrawal", {
    state: () => ({
        withdrawals: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        },
        loading: false,
        error: null,
        success: null,
    }),
    actions: {
        async fetchWithdrawalsPaginated(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`withdrawal/all/paginated`, { params });

                // API response shape: { success, message, data: { data: [...], meta: {...} } }
                const payload = response.data?.data || {}
                this.withdrawals = payload.data || []
                this.meta = payload.meta || this.meta
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchWithdrawalById(id) {
            this.loading = true
            
            try {
                const response = await axiosInstance.get(`withdrawal/${id}`)

                return response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async createWithdrawal(payload) {
            this.loading = true
            this.error = null

            try {
                const response = await axiosInstance.post('withdrawal', payload)

                this.success = response.data.message

                router.push({ name: 'admin.my-store-balance' })
                
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async approveWithdrawal(payload) {
            this.loading = true
            this.error = null
            try {
                const response = await axiosInstance.post(`withdrawal/${payload.id}/approve`, payload)

                this.success = response.data.message
                
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        }
    }
})