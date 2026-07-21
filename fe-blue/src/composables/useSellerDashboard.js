import { ref } from 'vue'
import { axiosInstance } from '@/plugins/axios'
import { handleError } from '@/helpers/errorHelper'

export function useSellerDashboard() {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const range = ref(7)

  const fetch = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.get('seller/dashboard/summary', {
        params: { days: range.value }
      })
      data.value = response.data.data
    } catch (err) {
      error.value = handleError(err)
    } finally {
      loading.value = false
    }
  }

  const setRange = async (days) => {
    range.value = days
    await fetch()
  }

  return { data, loading, error, range, fetch, setRange }
}
