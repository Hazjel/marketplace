import { ref } from 'vue'
import { axiosInstance } from '@/plugins/axios'
import { handleError } from '@/helpers/errorHelper'

/**
 * State + actions generik buat resource CRUD sederhana: list+meta+loading/error/
 * success plus fetchPaginated & fetchById. Dipanggil DI DALAM setup function dari
 * defineStore() milik pemanggil (bukan defineStore terpisah yang di-nest/spread) --
 * storeToRefs() di komponen butuh state hidup di store yang sama, spread dari store
 * lain membekukan ref jadi value biasa dan reactivity-nya lepas.
 *
 * @param {object} config
 * @param {string} config.endpoint - path API, tanpa leading slash (mis. 'user')
 */
export function useResourceState({ endpoint }) {
  const list = ref([])
  const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0
  })
  const loading = ref(false)
  const error = ref(null)
  const success = ref(null)

  const fetchPaginated = async (params) => {
    loading.value = true
    try {
      const response = await axiosInstance.get(`${endpoint}/all/paginated`, { params })
      const payload = response.data?.data || {}
      list.value = payload.data || []
      meta.value = payload.meta || meta.value
    } catch (err) {
      error.value = handleError(err)
    } finally {
      loading.value = false
    }
  }

  const fetchById = async (resourceId) => {
    loading.value = true
    try {
      const response = await axiosInstance.get(`${endpoint}/${resourceId}`)
      return response.data.data
    } catch (err) {
      error.value = handleError(err)
    } finally {
      loading.value = false
    }
  }

  // Non-paginated list CRUD (mis. address.js) -- daftar penuh, bukan halaman.
  const fetchList = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.get(endpoint)
      list.value = response.data.data
      return list.value
    } catch (err) {
      error.value = handleError(err)
      return []
    } finally {
      loading.value = false
    }
  }

  const create = async (payload) => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.post(endpoint, payload)
      return response.data.data
    } catch (err) {
      error.value = handleError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (resourceId, payload) => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.put(`${endpoint}/${resourceId}`, payload)
      return response.data.data
    } catch (err) {
      error.value = handleError(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const remove = async (resourceId) => {
    loading.value = true
    error.value = null
    try {
      await axiosInstance.delete(`${endpoint}/${resourceId}`)
      list.value = list.value.filter((item) => item.id !== resourceId)
      return true
    } catch (err) {
      error.value = handleError(err)
      return false
    } finally {
      loading.value = false
    }
  }

  return {
    list,
    meta,
    loading,
    error,
    success,
    fetchPaginated,
    fetchById,
    fetchList,
    create,
    update,
    remove
  }
}
