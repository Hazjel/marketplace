import { defineStore } from 'pinia'
import { axiosInstance } from '@/plugins/axios'
import { handleError } from '@/helpers/errorHelper'
import { useResourceState } from '@/stores/createResourceStore'

export const useStoreBalanceStore = defineStore('storeBalance', () => {
  const {
    list: storeBalances,
    meta,
    loading,
    error,
    success,
    fetchPaginated
  } = useResourceState({ endpoint: 'store-balance' })

  const fetchStoreBalanceById = async (id) => {
    loading.value = true
    try {
      const response = await axiosInstance.get(`store-balance/${id}`)
      return response.data.data
    } catch (err) {
      error.value = handleError(err)
    } finally {
      loading.value = false
    }
  }

  const fetchStoreBalanceByStore = async () => {
    loading.value = true
    try {
      const response = await axiosInstance.get('my-store-balance')
      return response.data.data
    } catch (err) {
      error.value = handleError(err)
    } finally {
      loading.value = false
    }
  }

  return {
    storeBalances,
    meta,
    loading,
    error,
    success,
    fetchStoreBalancesPaginated: fetchPaginated,
    fetchStoreBalanceById,
    fetchStoreBalanceByStore
  }
})
