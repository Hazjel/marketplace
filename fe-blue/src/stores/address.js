import { defineStore } from 'pinia'
import { ref } from 'vue'
import { axiosInstance } from '@/plugins/axios'
import { handleError } from '@/helpers/errorHelper'

export const useAddressStore = defineStore('address', () => {
  const addresses = ref([])
  const loading = ref(false)
  const error = ref(null)

  const fetchAddresses = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.get('/address')
      addresses.value = response.data.data
      return addresses.value
    } catch (err) {
      error.value = handleError(err)
      return []
    } finally {
      loading.value = false
    }
  }

  const fetchAddressById = async (id) => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.get(`/address/${id}`)
      return response.data.data
    } catch (err) {
      error.value = handleError(err)
      return null
    } finally {
      loading.value = false
    }
  }

  const createAddress = async (payload) => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.post('/address', payload)
      return response.data.data
    } catch (err) {
      error.value = handleError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateAddress = async (id, payload) => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.put(`/address/${id}`, payload)
      return response.data.data
    } catch (err) {
      error.value = handleError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteAddress = async (id) => {
    loading.value = true
    error.value = null
    try {
      await axiosInstance.delete(`/address/${id}`)
      addresses.value = addresses.value.filter((address) => address.id !== id)
      return true
    } catch (err) {
      error.value = handleError(err)
      return false
    } finally {
      loading.value = false
    }
  }

  // Dipakai form alamat & form toko (StoreCreate/StoreEdit) untuk cari kota/kecamatan.
  const searchCity = async (keyword) => {
    try {
      const response = await axiosInstance.get('/shipment/destination', {
        params: { keyword }
      })
      return response.data.data
    } catch (err) {
      error.value = handleError(err)
      return []
    }
  }

  return {
    addresses,
    loading,
    error,
    fetchAddresses,
    fetchAddressById,
    createAddress,
    updateAddress,
    deleteAddress,
    searchCity
  }
})
