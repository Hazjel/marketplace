import { handleError } from "@/helpers/errorHelper";
import { axiosInstance } from "@/plugins/axios";
import { defineStore } from "pinia";

export const useUserStore = defineStore("user", {
    state: () => ({
        users: [],
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
        async fetchUsersPaginated(params) {
            this.loading = true;

            try {
                const response = await axiosInstance.get(`user/all/paginated`, { params });

                this.users = response.data.data.data
                this.meta = response.data.data.meta
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        }
    }
})