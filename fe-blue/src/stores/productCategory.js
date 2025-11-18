import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useProductCategoryStore = defineStore("productCategory", {
    state: () => ({
        productCategories: [],
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
        async fetchProductCategories(params) {
            this.loading = true;

            try {
                // use proxied relative path so Vite dev server forwards to backend and avoids CORS
                const response = await axiosInstance.get('product-category', { params });

                this.productCategories = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchProductCategoryBySlug(slug) {
            this.loading = true
            
            try {
                const response = await axiosInstance.get(`product-category/slug/${slug}`)

                return response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        }
    },
});
