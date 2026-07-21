import { ref } from 'vue'
import { axiosInstance } from '@/plugins/axios'
import { handleError } from '@/helpers/errorHelper'

export function useAdminDashboard() {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const fetch = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.get('admin/dashboard/summary')
      data.value = response.data.data
    } catch (err) {
      error.value = handleError(err)
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, fetch }
}
