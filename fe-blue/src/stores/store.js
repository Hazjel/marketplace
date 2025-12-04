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

        async fetchStoresPaginated(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`store/all/paginated`, { params });

                this.stores = response.data.data.data
                this.meta = response.data.data.meta
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
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

        async fetchStoresByUserId(userId) {
            this.loading = true
            
            try {
                const response = await axiosInstance.get(`store/user/${userId}`)
                return response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async fetchStoreById(id) {
            this.loading = true
            
            try {
                const response = await axiosInstance.get(`store/${id}`)

                return response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async createStore(payload) {
            this.loading = true
            this.error = null

            try {
                const response = await axiosInstance.post('store', payload)

                this.success = response.data.message

                router.push({ name: 'admin.my-store' })
                
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async approveStore(id) {
            this.loading = true

            try {
                const response = await axiosInstance.post(`/store/${id}/verified`)

                this.success = response.data.message
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async deleteStore(id){
            this.loading = true
            
            try {
                const response = await axiosInstance.delete(`store/${id}`)

                this.success = response.data.message
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        }
    }
});