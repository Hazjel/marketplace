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

        async fetchStoreByUsername(username) {
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

        async fetchStoreByUserId(userId) {
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

        async fetchStoreByUser() {
            this.loading = true

            try {
                const response = await axiosInstance.get(`my-store`)

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

        async updateStore(id, payload) {
            this.loading = true
            this.error = null

            try {
                const formData = new FormData();

                formData.append('user_id', payload.user_id);
                formData.append('name', payload.name || '');
                formData.append('about', payload.about || '');
                formData.append('phone', payload.phone || '');
                formData.append('address_id', payload.address_id || 0);
                formData.append('city', payload.city || '');
                formData.append('address', payload.address || '');
                formData.append('postal_code', payload.postal_code || '');
                formData.append('_method', 'PUT'); // ✅ Laravel method spoofing

                // Append file hanya jika ada file baru
                if (payload.logo instanceof File) {
                    formData.append('logo', payload.logo);
                }

                const response = await axiosInstance.post(`store/${id}`, formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                });

                this.success = response.data.message;

                return response.data.data;

            } catch (error) {
                console.error('Update store error:', error.response?.data);
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
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

        async deleteStore(id) {
            this.loading = true

            try {
                const response = await axiosInstance.delete(`store/${id}`)

                this.success = response.data.message
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async fetchStoreLocations() {
            try {
                const response = await axiosInstance.get('store/locations');
                return response.data.data;
            } catch (error) {
                console.error("Error fetching locations:", error);
                return [];
            }
        }
    }
});