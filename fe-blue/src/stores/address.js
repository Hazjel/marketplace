import { defineStore } from 'pinia'
import { axiosInstance } from '@/plugins/axios'
import { handleError } from '@/helpers/errorHelper'
import { useResourceState } from '@/stores/createResourceStore'

export const useAddressStore = defineStore('address', () => {
  const {
    list: addresses,
    loading,
    error,
    fetchList,
    fetchById,
    create,
    update,
    remove
  } = useResourceState({ endpoint: 'address' })

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
    fetchAddresses: fetchList,
    fetchAddressById: fetchById,
    createAddress: create,
    updateAddress: update,
    deleteAddress: remove,
    searchCity
  }
})
