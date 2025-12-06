import { handleError } from "@/helpers/errorHelper";
import { axiosInstance } from "@/plugins/axios";
import { defineStore } from "pinia";

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

                this.withdrawals = response.data.data
                this.meta = response.data.meta
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
    }
})