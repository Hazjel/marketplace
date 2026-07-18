import { defineStore } from 'pinia'
import { logger } from '@/utils/logger'
import { recoAxiosInstance, axiosInstance } from '@/plugins/axios'

// Module-level AbortControllers — cancel request in-flight sebelum fetch baru,
// sama pola dengan product.js
let similarController = null
let personalizedController = null

export const useRecommendationStore = defineStore('recommendation', {
  state: () => ({
    similarProducts: [],
    personalizedProducts: [],
    personalizedSource: null, // "collaborative" | "trending_fallback" | null
    loadingSimilar: false,
    loadingPersonalized: false
  }),
  actions: {
    // Rekomendasi gak boleh pernah nge-block/ganggu UX halaman -- gagal diam-diam,
    // cukup log ke console buat debugging, gak nge-set state error apa pun
    async fetchSimilarProducts(productId, topK = 8) {
      similarController?.abort()
      similarController = new AbortController()
      this.loadingSimilar = true

      try {
        const response = await recoAxiosInstance.get(`product/${productId}/similar`, {
          params: { top_k: topK },
          signal: similarController.signal
        })
        this.similarProducts = response.data.data
      } catch (error) {
        if (error.name !== 'CanceledError') {
          logger.error('Fetch Similar Products Error', error)
          this.similarProducts = []
        }
      } finally {
        this.loadingSimilar = false
      }
    },

    async fetchPersonalizedProducts(userId, topK = 10) {
      personalizedController?.abort()
      personalizedController = new AbortController()
      this.loadingPersonalized = true

      try {
        const response = await recoAxiosInstance.get(`user/${userId}`, {
          params: { top_k: topK },
          signal: personalizedController.signal
        })
        this.personalizedProducts = response.data.data
        this.personalizedSource = response.data.source
      } catch (error) {
        if (error.name !== 'CanceledError') {
          logger.error('Fetch Personalized Products Error', error)
          this.personalizedProducts = []
          this.personalizedSource = null
        }
      } finally {
        this.loadingPersonalized = false
      }
    },

    // Product view tracking -- lewat axiosInstance biasa (Laravel API, bukan
    // recommendation-service), dedup 10 menit dihandle backend. Fire-and-forget,
    // gagal gak perlu ditangani apa pun di sisi FE
    async trackProductView(productId, sessionId) {
      try {
        await axiosInstance.post(`product/${productId}/view`, { session_id: sessionId })
      } catch (error) {
        logger.error('Track Product View Error', error)
      }
    }
  }
})
