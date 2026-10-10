import { handleError } from '@/helpers/errorHelper'
import { logger } from '@/utils/logger'
import { axiosInstance } from '@/plugins/axios'
import { useAuthStore } from '@/stores/auth'
import { defineStore } from 'pinia'

// Complaint actions: no page spinner, and the API's message for the toast.
const COMPLAINT = { quiet: true, withMessage: true }

export const useTransactionStore = defineStore('transaction', {
  state: () => ({
    transactions: [],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0
    },
    loading: false,
    error: null,
    success: null,
    _fetchSeq: 0 // cegah response request lama nimpa hasil request lebih baru
  }),
  actions: {
    // path 'complaint': admin queue of orders by complaint status (default escalated).
    async fetchTransactionsPaginated(params, path = 'transaction/all/paginated') {
      this.loading = true
      const authStore = useAuthStore()
      const mode = authStore.activeMode

      // Tandai request ini sebagai yang terbaru — kalau ada request lebih
      // baru menyusul sebelum ini selesai, hasil request ini dibuang saat
      // resolve (mencegah search/filter lama flicker menimpa hasil terbaru).
      const seq = ++this._fetchSeq

      try {
        // Merge params with mode
        const queryParams = { ...params, mode }
        const response = await axiosInstance.get(path, {
          params: queryParams
        })

        if (seq !== this._fetchSeq) return

        this.transactions = response.data.data.data
        this.meta = response.data.data.meta
      } catch (error) {
        if (seq !== this._fetchSeq) return
        this.error = handleError(error)
      } finally {
        if (seq === this._fetchSeq) {
          this.loading = false
        }
      }
    },

    async fetchChartData() {
      try {
        const response = await axiosInstance.get('transaction/chart-data')
        return response.data.data
      } catch (error) {
        logger.error('Failed to fetch chart data:', error)
        return []
      }
    },

    // quiet: leave `loading` alone -- TransactionDetail swaps the whole page
    // for a spinner while it is true, unmounting the panel that asked.
    async fetchTransactionById(id, { quiet = false } = {}) {
      if (!quiet) this.loading = true

      try {
        const response = await axiosInstance.get(`transaction/${id}`)

        return response.data.data
      } catch (error) {
        this.error = handleError(error)
      } finally {
        if (!quiet) this.loading = false
      }
    },

    // path 'transaction/checkout': one order per store, paid together (returns a list).
    async createTransaction(payload, path = 'transaction') {
      this.loading = true
      this.error = null

      try {
        // Idempotency: generate unique key per checkout attempt to prevent double-charge
        const idempotencyKey = crypto.randomUUID()

        const response = await axiosInstance.post(path, payload, {
          headers: {
            'X-Idempotency-Key': idempotencyKey
          }
        })

        this.success = response.data.message

        return response.data.data
      } catch (error) {
        this.error = handleError(error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateTransaction(payload) {
      this.loading = true
      this.error = null
      try {
        const formData = new FormData()
        formData.append('_method', 'PUT')
        formData.append('delivery_status', payload.delivery_status)

        if (payload.tracking_number) {
          formData.append('tracking_number', payload.tracking_number)
        }

        if (payload.delivery_proof instanceof File) {
          formData.append('delivery_proof', payload.delivery_proof)
        }

        const response = await axiosInstance.post(`transaction/${payload.id}`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })

        this.success = response.data.message
        return response.data.data
      } catch (error) {
        this.error = handleError(error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async checkTransactionStatus(id) {
      this.loading = true
      try {
        const response = await axiosInstance.post(`transaction/${id}/check-status`)
        return response.data.data
      } catch (error) {
        this.error = handleError(error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async completeTransaction(id, payload) {
      this.loading = true
      try {
        const formData = new FormData()
        if (payload && payload.receiving_proof) {
          formData.append('receiving_proof', payload.receiving_proof)
        }

        const response = await axiosInstance.post(`transaction/${id}/complete`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        this.success = response.data.message
        return response.data.data
      } catch (error) {
        this.error = handleError(error)
        throw error
      } finally {
        this.loading = false
      }
    },

    // Seller rejects a paid order; the API refunds the buyer.
    async cancelTransaction(id, reason) {
      return this.postAction(`transaction/${id}/cancel`, { reason })
    },

    // Buyer's bank account for a manual (virtual account) refund.
    async submitRefundAccount(id, payload) {
      return this.postAction(`transaction/${id}/refund-account`, payload, { quiet: true })
    },

    // Admin records a manual refund transfer.
    async markRefunded(id, note) {
      return this.postAction(`transaction/${id}/mark-refunded`, { note }, { quiet: true })
    },

    // Admin moves a legacy manual refund to the buyer's Saldo Blukios.
    async refundToBalance(id) {
      return this.postAction(`transaction/${id}/refund-to-balance`, {}, { quiet: true })
    },

    async fetchComplaints(params) {
      return this.fetchTransactionsPaginated(params, 'complaint')
    },

    // Buyer files a complaint on a delivering order (photos optional, max 3).
    async createComplaint(id, { reason, description, photos = [] }) {
      const formData = new FormData()
      formData.append('reason', reason)
      formData.append('description', description)
      photos.forEach((photo) => formData.append('photos[]', photo))

      // Keep the header: with the instance's JSON default axios JSON-encodes the
      // FormData and drops the files. In the browser axios then removes it so the
      // browser sets multipart with the boundary.
      return this.postAction(`transaction/${id}/complaint`, formData, {
        ...COMPLAINT,
        config: { headers: { 'Content-Type': 'multipart/form-data' } }
      })
    },

    async withdrawComplaint(complaintId) {
      return this.postAction(`complaint/${complaintId}/withdraw`, {}, COMPLAINT)
    },

    // Seller: full refund, stock is not returned.
    async acceptComplaint(complaintId) {
      return this.postAction(`complaint/${complaintId}/accept`, {}, COMPLAINT)
    },

    // Seller: escalates to admin.
    async rejectComplaint(complaintId, response) {
      return this.postAction(`complaint/${complaintId}/reject`, { response }, COMPLAINT)
    },

    // Admin on an escalated complaint: outcome approve | reject.
    async resolveComplaint(complaintId, outcome, note) {
      return this.postAction(`complaint/${complaintId}/resolve`, { outcome, note }, COMPLAINT)
    },

    // quiet: see fetchTransactionById. withMessage: resolve { data, message }.
    async postAction(url, payload, { quiet = false, withMessage = false, config } = {}) {
      if (!quiet) this.loading = true
      this.error = null
      try {
        const response = await axiosInstance.post(url, payload, ...(config ? [config] : []))
        this.success = response.data.message
        return withMessage
          ? { data: response.data.data, message: response.data.message }
          : response.data.data
      } catch (error) {
        this.error = handleError(error)
        throw error
      } finally {
        if (!quiet) this.loading = false
      }
    }
  }
})
