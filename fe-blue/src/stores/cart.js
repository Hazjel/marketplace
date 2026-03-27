import { defineStore } from 'pinia'
import { axiosInstance } from '@/plugins/axios'
import Cookies from 'js-cookie'

const STORAGE_KEY = 'grouped_cart'
const SELECTED_STORES_KEY = 'selected_stores'

/**
 * Hybrid cart store:
 * - Guest: localStorage only (same as before)
 * - Authenticated: API-first with localStorage as fallback cache
 */
export const useCartStore = defineStore('cart', {
  state: () => ({
    carts: JSON.parse(localStorage.getItem(STORAGE_KEY)) || [],
    selectedStores: new Set(JSON.parse(localStorage.getItem(SELECTED_STORES_KEY)) || []),
    stockValidation: null,
    syncLoading: false
  }),

  getters: {
    isAuthenticated: () => !!Cookies.get('token'),

    totalItems: (state) =>
      state.carts.reduce(
        (sum, store) => sum + store.products.reduce((s, p) => s + p.quantity, 0),
        0
      ),

    totalPrice: (state) =>
      state.carts.reduce(
        (sum, store) => sum + store.products.reduce((s, p) => s + p.price * p.quantity, 0),
        0
      ),

    selectedCarts(state) {
      return state.carts.filter((store) => state.selectedStores.has(store.storeId))
    },

    totalSelectedItems() {
      return this.selectedCarts.reduce((sum, store) => sum + store.products.length, 0)
    },

    totalSelectedQuantity() {
      return this.selectedCarts.reduce(
        (sum, store) => sum + store.products.reduce((s, p) => s + p.quantity, 0),
        0
      )
    },

    subtotalSelected() {
      return this.selectedCarts.reduce(
        (sum, store) => sum + store.products.reduce((s, p) => s + p.price * p.quantity, 0),
        0
      )
    },

    ppnSelected() {
      return this.subtotalSelected * 0.11
    },

    discountSelected() {
      return 0
    },

    grandTotalSelected() {
      return this.subtotalSelected + this.ppnSelected - this.discountSelected
    },

    hasSelectedStores: (state) => {
      return state.selectedStores.size > 0
    }
  },

  actions: {
    // ─── Selection ────────────────────────────────────────
    toggleStoreSelection(storeId) {
      if (this.selectedStores.has(storeId)) {
        this.selectedStores.delete(storeId)
      } else {
        this.selectedStores.add(storeId)
      }
      this.saveSelectedStores()
    },

    clearSelectedStores() {
      this.selectedStores.clear()
      this.saveSelectedStores()
    },

    saveSelectedStores() {
      localStorage.setItem(SELECTED_STORES_KEY, JSON.stringify([...this.selectedStores]))
    },

    // ─── Add to Cart ──────────────────────────────────────
    async addToCart(product) {
      const storeId = product.store.id
      const storeAddressId = product.store.address_id
      const storeName = product.store.name
      const storeLogo = product.store.logo
      const qty = product.quantity || 1

      // Optimistic local update first
      this._addToLocalCart(product, storeId, storeAddressId, storeName, storeLogo, qty)

      // Sync to server if authenticated
      if (this.isAuthenticated) {
        try {
          await axiosInstance.post('/cart', {
            product_id: product.id,
            variant_id: product.variant_id || null,
            quantity: qty,
            note: product.note || null
          })
        } catch {
          // Server sync failed — local state already updated, will sync later
          console.warn('Cart server sync failed, will retry on next fetch')
        }
      }
    },

    _addToLocalCart(product, storeId, storeAddressId, storeName, storeLogo, qty) {
      let storeCart = this.carts.find((s) => s.storeId === storeId)

      if (!storeCart) {
        storeCart = {
          storeId,
          storeAddressId,
          storeName,
          storeLogo,
          products: []
        }
        this.carts.push(storeCart)
      }

      const existing = storeCart.products.find((p) => p.id === product.id)

      if (existing) {
        existing.quantity += qty
      } else {
        const fullProductData = JSON.parse(JSON.stringify(product))
        fullProductData.quantity = qty
        storeCart.products.push(fullProductData)
      }

      this.save()
    },

    // ─── Remove ───────────────────────────────────────────
    async removeFromCart(storeId, productId) {
      const store = this.carts.find((s) => s.storeId === storeId)
      if (!store) return

      const product = store.products.find((p) => p.id === productId)
      const variantId = product?.variant_id || null

      store.products = store.products.filter((p) => p.id !== productId)

      if (store.products.length === 0) {
        this.carts = this.carts.filter((s) => s.storeId !== storeId)
        this.selectedStores.delete(storeId)
        this.saveSelectedStores()
      }

      this.save()

      if (this.isAuthenticated) {
        try {
          const params = variantId ? `?variant_id=${variantId}` : ''
          await axiosInstance.delete(`/cart/${productId}${params}`)
        } catch {
          console.warn('Cart server remove failed')
        }
      }
    },

    // ─── Quantity ─────────────────────────────────────────
    async decreaseQuantity(storeId, productId) {
      const store = this.carts.find((s) => s.storeId === storeId)
      const product = store?.products.find((p) => p.id === productId)

      if (product && product.quantity > 1) {
        product.quantity--
        this.save()
        await this._syncQuantity(productId, product.variant_id, product.quantity)
      }
    },

    async increaseQuantity(storeId, productId) {
      const store = this.carts.find((s) => s.storeId === storeId)
      const product = store?.products.find((p) => p.id === productId)
      if (product) {
        product.quantity++
        this.save()
        await this._syncQuantity(productId, product.variant_id, product.quantity)
      }
    },

    async updateQuantity(storeId, productId, qty) {
      const store = this.carts.find((s) => s.storeId === storeId)
      const product = store?.products.find((p) => p.id === productId)
      if (product) {
        product.quantity = qty
        this.save()
        await this._syncQuantity(productId, product.variant_id, qty)
      }
    },

    async _syncQuantity(productId, variantId, quantity) {
      if (!this.isAuthenticated) return

      try {
        await axiosInstance.put(`/cart/${productId}`, {
          variant_id: variantId || null,
          quantity
        })
      } catch {
        console.warn('Cart server quantity sync failed')
      }
    },

    // ─── Clear ────────────────────────────────────────────
    async clearSelectedItems() {
      this.carts = this.carts.filter((cart) => !this.selectedStores.has(cart.storeId))
      this.selectedStores.clear()
      this.save()
      this.saveSelectedStores()
    },

    async clearCart() {
      this.carts = []
      this.selectedStores.clear()
      this.save()
      this.saveSelectedStores()

      if (this.isAuthenticated) {
        try {
          await axiosInstance.delete('/cart/clear')
        } catch {
          console.warn('Cart server clear failed')
        }
      }
    },

    // ─── Server Sync (login flow) ─────────────────────────
    /**
     * Called after login: merges localStorage cart → server, then replaces local with server truth.
     */
    async syncAfterLogin() {
      if (!this.isAuthenticated) return

      this.syncLoading = true

      try {
        const localItems = this._extractItemsForSync()

        if (localItems.length > 0) {
          // Merge local → server
          const response = await axiosInstance.post('/cart/sync', { items: localItems })
          this._applyServerCart(response.data.data)
        } else {
          // No local items — just fetch server cart
          await this.fetchCart()
        }
      } catch {
        console.warn('Cart sync after login failed')
      } finally {
        this.syncLoading = false
      }
    },

    /**
     * Fetch cart from server and replace local state.
     */
    async fetchCart() {
      if (!this.isAuthenticated) return

      try {
        const response = await axiosInstance.get('/cart')
        this._applyServerCart(response.data.data)
      } catch {
        console.warn('Cart fetch from server failed')
      }
    },

    /**
     * Transform server grouped response into local cart format.
     */
    _applyServerCart(serverGroups) {
      if (!serverGroups || !Array.isArray(serverGroups)) return

      this.carts = serverGroups.map((group) => ({
        storeId: group.store_id,
        storeAddressId: group.store_address_id,
        storeName: group.store_name,
        storeLogo: group.store_logo,
        products: group.items.map((item) => ({
          id: item.product_id,
          variant_id: item.variant_id,
          quantity: item.quantity,
          note: item.note,
          name: item.product?.name,
          price: item.product?.price,
          stock: item.product?.stock,
          weight: item.product?.weight,
          slug: item.product?.slug,
          condition: item.product?.condition,
          product_category: item.product?.product_category,
          product_images: item.product?.product_images,
          store: item.product?.store,
          thumbnail: item.product?.product_images?.find((i) => i.is_thumbnail)?.image || null
        }))
      }))

      this.save()
    },

    /**
     * Extract flat item list from grouped carts for sync API payload.
     */
    _extractItemsForSync() {
      const items = []
      for (const store of this.carts) {
        for (const product of store.products) {
          items.push({
            product_id: product.id,
            variant_id: product.variant_id || null,
            quantity: product.quantity || 1,
            note: product.note || null
          })
        }
      }
      return items
    },

    // ─── Stock Validation ─────────────────────────────────
    /**
     * Validate stock for all cart items before checkout.
     * Returns validation result and flags invalid items.
     */
    async validateCartStock() {
      const items = this._extractItemsForSync().map((item) => ({
        product_id: item.product_id,
        variant_id: item.variant_id,
        quantity: item.quantity
      }))

      if (items.length === 0) {
        this.stockValidation = { all_valid: true, items: [] }
        return this.stockValidation
      }

      try {
        const response = await axiosInstance.post('/cart/validate-stock', { items })
        this.stockValidation = response.data.data

        // Update local stock info from validation response
        if (this.stockValidation?.items) {
          for (const result of this.stockValidation.items) {
            for (const store of this.carts) {
              const product = store.products.find((p) => p.id === result.product_id)
              if (product) {
                product.stock = result.available
                product._stockValid = result.valid
              }
            }
          }
          this.save()
        }

        return this.stockValidation
      } catch {
        console.warn('Stock validation failed')
        return null
      }
    },

    // ─── Logout cleanup ───────────────────────────────────
    onLogout() {
      this.carts = []
      this.selectedStores.clear()
      this.stockValidation = null
      this.save()
      this.saveSelectedStores()
    },

    // ─── Persistence ──────────────────────────────────────
    save() {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(this.carts))
    }
  }
})
