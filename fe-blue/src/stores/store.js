import { handleError } from "@/helpers/errorHelper";
import { axiosInstance } from "@/plugins/axios";
import { defineStore } from "pinia";

export const useStoreStore = defineStore("store", {
    state: () => ({
        stores: [],
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
        async fetchStores(params) {
            this.loading = true

            try {
                const response = await axiosInstance.get('store', { params })

                this.stores = response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async fetchStoresByUsername(username) {
            this.loading = true
            
            try {
                const response = await axiosInstance.get(`store/username/${username}`)

                return response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },
    }
});