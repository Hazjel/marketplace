import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";
import router from "@/router";

export const useProductStore = defineStore("product", {
    state: () => ({
        products: [],
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
        async fetchProducts(params) {
            this.loading = true;

            try {
                // use proxied relative path so Vite dev server forwards to backend and avoids CORS
                const response = await axiosInstance.get('product', { params });

                this.products = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchProductsPaginated(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`product/all/paginated`, { params });

                this.products = response.data.data.data
                this.meta = response.data.data.meta
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async fetchProductById(id) {
            this.loading = true
            
            try {
                const response = await axiosInstance.get(`product/${id}`)

                return response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async fetchProductBySlug(slug) {
            this.loading = true
            
            try {
                const response = await axiosInstance.get(`product/slug/${slug}`)

                return response.data.data
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },

        async createProduct(payload) {
            this.loading = true
            this.error = null

            try {
                const response = await axiosInstance.post('product', payload)

                this.success = response.data.message
                
                router.push({ name: 'admin.product' })
                
                
            } catch (error) {
                this.error = handleError(error)
            } finally {
                this.loading = false
            }
        },
    },
});
