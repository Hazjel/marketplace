import { ref } from 'vue'
import { axiosInstance } from '@/plugins/axios'
import { handleError } from '@/helpers/errorHelper'

/**
 * Fetch ringkasan dashboard (seller/buyer/admin) -- ketiganya sama persis
 * (data/loading/error/fetch), beda cuma endpoint dan ada-tidaknya filter
 * range hari (admin tidak punya range).
 */
export function useDashboardSummary(endpoint, { withRange = false } = {}) {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const range = withRange ? ref(7) : undefined

  const fetch = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.get(endpoint, {
        params: withRange ? { days: range.value } : undefined
      })
      data.value = response.data.data
    } catch (err) {
      error.value = handleError(err)
    } finally {
      loading.value = false
    }
  }

  const setRange = withRange
    ? async (days) => {
        range.value = days
        await fetch()
      }
    : undefined

  return { data, loading, error, range, fetch, setRange }
}
