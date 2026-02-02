import { handleError } from '@/helpers/errorHelper'
import { axiosInstance } from '@/plugins/axios'
import { defineStore } from 'pinia'

export const useStoreBalanceStore = defineStore('storeBalance', {
  state: () => ({
    storeBalances: [],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0
    },
    loading: false,
    error: null,
    success: null
  }),
  actions: {
    async fetchStoreBalancesPaginated(params) {
      this.loading = true

      try {
        const response = await axiosInstance.get(`store-balance/all/paginated`, { params })

        this.storeBalances = response.data.data.data
        this.meta = response.data.data.meta
      } catch (error) {
        this.error = handleError(error)
      } finally {
        this.loading = false
      }
    },

    async fetchStoreBalanceById(id) {
      this.loading = true

      try {
        const response = await axiosInstance.get(`store-balance/${id}`)

        return response.data.data
      } catch (error) {
        this.error = handleError(error)
      } finally {
        this.loading = false
      }
    },

    async fetchStoreBalanceByStore() {
      this.loading = true

      try {
        const response = await axiosInstance.get(`my-store-balance`)

        return response.data.data
      } catch (error) {
        this.error = handleError(error)
      } finally {
        this.loading = false
      }
    }
  }
})
