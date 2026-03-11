import { defineStore } from 'pinia'
import { axiosInstance } from '@/plugins/axios'
import { handleError } from '@/helpers/errorHelper'

// Module-level AbortControllers: prevents memory leaks when user navigates away
// before a response arrives. Each new call cancels the previous in-flight request.
let fetchProductsController = null
let fetchPaginatedController = null

export const useProductStore = defineStore('product', {
  state: () => ({
    storeCategories: [],
    products: [],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0
    },
    loading: false,
    error: null,
    success: null
  }),
  actions: {
    async fetchProducts(params) {
      // Cancel any in-flight request before firing a new one
      fetchProductsController?.abort()
      fetchProductsController = new AbortController()
      this.loading = true

      try {
        const response = await axiosInstance.get('product', {
          params,
          signal: fetchProductsController.signal
        })
        this.products = response.data.data
      } catch (error) {
        if (error.name !== 'CanceledError') {
          this.error = handleError(error)
        }
      } finally {
        this.loading = false
      }
    },

    async fetchCategoriesByStore(username) {
      this.loading = true
      try {
        const response = await axiosInstance.get(`store/username/${username}/categories`)
        this.storeCategories = response.data.data
      } catch (error) {
        console.error('Fetch Store Categories Error', error)
        this.error = handleError(error)
      } finally {
        this.loading = false
      }
    },

    async searchProducts(params = {}) {
      try {
        const response = await axiosInstance.get('/product', {
          params: {
            search: params.search || null,
            limit: params.limit || 5
          }
        })

        // Return langsung tanpa simpan ke state
        return response.data.data
      } catch (error) {
        console.error('Search error:', error)
        return []
      }
    },

    async loadMoreProducts(params) {
      this.loading = true

      try {
        const response = await axiosInstance.get(`product/all/paginated`, { params })

        this.products.push(...response.data.data.data)
        this.meta = response.data.data.meta
      } catch (error) {
        this.error = handleError(error)
      } finally {
        this.loading = false
      }
    },

    async fetchProductsPaginated(params) {
      fetchPaginatedController?.abort()
      fetchPaginatedController = new AbortController()
      this.loading = true

      try {
        const response = await axiosInstance.get('product/all/paginated', {
          params,
          signal: fetchPaginatedController.signal
        })
        this.products = response.data.data.data
        this.meta = response.data.data.meta
      } catch (error) {
        if (error.name !== 'CanceledError') {
          this.error = handleError(error)
        }
      } finally {
        this.loading = false
      }
    },

    async fetchProductById(id) {
      this.loading = true

      try {
        const response = await axiosInstance.get(`product/${id}`)

        return response.data.data
      } catch (error) {
        this.error = handleError(error)
      } finally {
        this.loading = false
      }
    },

    async fetchProductBySlug(slug) {
      this.loading = true

      try {
        const response = await axiosInstance.get(`product/slug/${slug}`)

        return response.data.data
      } catch (error) {
        this.error = handleError(error)
      } finally {
        this.loading = false
      }
    },

    async createProduct(payload) {
      this.loading = true
      this.error = null

      try {
        let data = payload

        // Check if payload contains images, if so convert to FormData
        if (payload.product_images && payload.product_images.length > 0) {
          data = new FormData()

          // Append regular fields
          for (const key in payload) {
            if (key !== 'product_images' && payload[key] !== null) {
              data.append(key, payload[key])
            }
          }

          // Append images
          payload.product_images.forEach((item, index) => {
            if (item.image) {
              data.append(`product_images[${index}][image]`, item.image)
              data.append(`product_images[${index}][is_thumbnail]`, item.is_thumbnail ? 1 : 0)
            }
          })
        }

        const response = await axiosInstance.post('product', data, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })

        this.success = response.data.message

        // Decoupled from UI layer: return result and let the component decide navigation
        return { success: true, message: response.data.message }
      } catch (error) {
        this.error = handleError(error)
        return { success: false }
      } finally {
        this.loading = false
      }
    },

    async updateProduct(payload) {
      this.loading = true
      this.error = null
      try {
        const response = await axiosInstance.post(`product/${payload.id}`, {
          ...payload,
          _method: 'PUT'
        })

        this.success = response.data.message

        // Decoupled from UI layer: return result and let the component decide navigation
        return { success: true, message: response.data.message }
      } catch (error) {
        this.error = handleError(error)
        return { success: false }
      } finally {
        this.loading = false
      }
    },

    async deleteProduct(id) {
      this.loading = true

      try {
        const response = await axiosInstance.delete(`product/${id}`)

        this.success = response.data.message
      } catch (error) {
        this.error = handleError(error)
      } finally {
        this.loading = false
      }
    }
  }
})
