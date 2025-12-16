import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useWishlistStore = defineStore("wishlist", {
    state: () => ({
        items: [],
        loading: false,
        error: null,
    }),
    getters: {
        totalItems: (state) => state.items.length,
        hasProduct: (state) => (productId) => state.items.some((item) => item.id === productId),
    },
    actions: {
        async fetchWishlist() {
            this.loading = true;
            try {
                const response = await axiosInstance.get("/wishlist");
                this.items = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },
        async toggleWishlist(productId) {
            this.loading = true;
            try {
                const response = await axiosInstance.post("/wishlist", { product_id: productId });
                await this.fetchWishlist(); // Refresh list
                return response.data.message;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
