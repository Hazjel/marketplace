import { handleError } from "@/helpers/errorHelper";
import { axiosInstance } from "@/plugins/axios";
import { defineStore } from "pinia";

export const useTransactionStore = defineStore("transaction", {
    state: () => ({
        transactions: [],
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
        async fetchTransactionsPaginated(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`transaction/all/paginated`, { params });

                this.transactions = response.data.data.data
                this.meta = response.data.data.meta
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchTransactionById(id) {
            this.loading = true
            
            try {
                const response = await axiosInstance.get(`transaction/${id}`)

                return response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async createTransaction(payload) {
            this.loading = true
            this.error = null
            
            try {
                const response = await axiosInstance.post(`transaction`, payload)

                this.success = response.data.message

                return response.data.data
            } catch (error) {
                this.error = handleError(error)
                // ✅ Throw error agar bisa di-catch di component
                throw error
            } finally {
                this.loading = false
            }
        },

            async updateTransaction(payload) {
                this.loading = true
                this.error = null
                try {
                    const formData = new FormData()
                    formData.append('_method', 'PUT')
                    formData.append('delivery_status', payload.delivery_status)
                    
                    if (payload.tracking_number) {
                        formData.append('tracking_number', payload.tracking_number)
                    }
                    
                    if (payload.delivery_proof instanceof File) {
                        formData.append('delivery_proof', payload.delivery_proof)
                    }

                    const response = await axiosInstance.post(
                        `transaction/${payload.id}`, 
                        formData,
                        { headers: { 'Content-Type': 'multipart/form-data' } }
                    )

                    this.success = response.data.message
                    return response.data.data
                } catch (error) {
                    this.error = handleError(error)
                    throw error
                } finally {
                    this.loading = false
                }
            }
    }
})