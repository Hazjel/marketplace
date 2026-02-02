import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { axiosInstance } from '@/plugins/axios'
import { useToast } from 'vue-toastification'

export const useWishlistStore = defineStore('wishlist', () => {
  const items = ref([])
  const loading = ref(false)
  const toast = useToast()

  // Get list of product IDs in wishlist for quick lookup
  const wishlistIds = computed(() => items.value.map((item) => item.id))

  const fetchWishlist = async () => {
    loading.value = true
    try {
      const response = await axiosInstance.get('/wishlist')
      items.value = response.data.data
    } catch (error) {
      console.error('Failed to fetch wishlist', error)
    } finally {
      loading.value = false
    }
  }

  const toggleWishlist = async (product) => {
    try {
      const response = await axiosInstance.post('/wishlist', {
        product_id: product.id
      })

      const status = response.data.data.status // 'added' or 'removed'

      if (status === 'added') {
        items.value.push(product)
        toast.success('Added to wishlist')
      } else {
        items.value = items.value.filter((item) => item.id !== product.id)
        toast.success('Removed from wishlist')
      }

      return status
    } catch (error) {
      toast.error(error.response?.data?.message || 'Failed to update wishlist')
      throw error
    }
  }

  const isInWishlist = (productId) => {
    return items.value.some((item) => item.id === productId)
  }

  return {
    items,
    loading,
    wishlistIds,
    fetchWishlist,
    toggleWishlist,
    isInWishlist
  }
})
