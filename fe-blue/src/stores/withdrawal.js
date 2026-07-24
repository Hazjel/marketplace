import { defineStore } from 'pinia'
import { handleError } from '@/helpers/errorHelper'
import { axiosInstance } from '@/plugins/axios'
import { useResourceState } from '@/stores/createResourceStore'
import router from '@/router'

export const useWithdrawalStore = defineStore('withdrawal', () => {
  const {
    list: withdrawals,
    meta,
    loading,
    error,
    success,
    fetchPaginated,
    fetchById
  } = useResourceState({ endpoint: 'withdrawal' })

  const createWithdrawal = async (payload) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('withdrawal', payload)

      success.value = response.data.message

      router.push({ name: 'admin.my-store-balance' })
    } catch (err) {
      error.value = handleError(err)
    } finally {
      loading.value = false
    }
  }

  const approveWithdrawal = async (payload) => {
    loading.value = true
    error.value = null
    try {
      const formData = new FormData()
      formData.append('proof', payload.proof)

      const response = await axiosInstance.post(`withdrawal/${payload.id}/approve`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })

      success.value = response.data.message
    } catch (err) {
      error.value = handleError(err)
    } finally {
      loading.value = false
    }
  }

  const rejectWithdrawal = async (id, reason = null) => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.post(`withdrawal/${id}/reject`, { reason })

      success.value = response.data.message
      return response.data.data
    } catch (err) {
      error.value = handleError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    withdrawals,
    meta,
    loading,
    error,
    success,
    fetchWithdrawalsPaginated: fetchPaginated,
    fetchWithdrawalById: fetchById,
    createWithdrawal,
    approveWithdrawal,
    rejectWithdrawal
  }
})
