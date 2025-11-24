import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";
import router from "@/router";

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
                const response = await axiosInstance.get(`product-category`, { params });

                this.productCategories = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchProductCategoriesPaginated(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`product-category/all/paginated`, { params });

                this.productCategories = response.data.data.data
                this.meta = response.data.data.meta
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
        },

        async createProductCategory(payload) {
            this.loading = true
            this.error = null

            try {
                const response = await axiosInstance.post('product-category', payload)

                this.success = response.data.message

                router.push({ name: 'admin.category' })
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        }
    },
});
