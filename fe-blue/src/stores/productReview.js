import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useProductReviewStore = defineStore("productReview", {
    state: () => ({
        reviews: [],
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
        async fetchReviewsPaginated(params) {
            this.loading = true;
            try {
                const response = await axiosInstance.get('product-review/all/paginated', { params });
                this.reviews = response.data.data.data;
                this.meta = response.data.data.meta;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async createReview(payload) {
            this.loading = true;
            this.error = null;
            this.success = null;
            try {
                const formData = new FormData();
                formData.append('transaction_id', payload.transaction_id);
                formData.append('product_id', payload.product_id);
                formData.append('rating', payload.rating);
                formData.append('review', payload.review);

                if (payload.is_anonymous) {
                    formData.append('is_anonymous', 1);
                } else {
                    formData.append('is_anonymous', 0);
                }

                if (payload.attachments && payload.attachments.length) {
                    payload.attachments.forEach(file => {
                        formData.append('attachments[]', file);
                    });
                }

                const response = await axiosInstance.post('product-review', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                this.success = response.data.message;
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        }
    }
});
