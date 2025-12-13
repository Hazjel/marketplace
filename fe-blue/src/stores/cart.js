import { defineStore } from 'pinia'

const STORAGE_KEY = 'grouped_cart'
const SELECTED_STORES_KEY = 'selected_stores'

/**
 * Cart store, menyimpan data cart dalam bentuk array of objects.
 * Setiap object dalam array mewakili satu toko, dan memiliki properti:
 * - storeId: id toko
 * - storeName: nama toko
 * - storeLogo: logo toko
 * - products: array of objects, mewakili produk-produk dalam toko.
 *   Setiap object dalam array memiliki properti:
 *   - id: id produk
 *   - name: nama produk
 *   - price: harga produk
 *   - quantity: jumlah produk yang dibeli
 */
export const useCartStore = defineStore('cart', {
    state: () => ({
        carts: JSON.parse(localStorage.getItem(STORAGE_KEY)) || [],
        /**
         * Set of storeIds yang dipilih oleh user.
         * Digunakan untuk menghitung subtotal, ppn, dan grand total.
         */
        selectedStores: new Set(JSON.parse(localStorage.getItem(SELECTED_STORES_KEY)) || [])
    }),

    getters: {
        /**
         * Menghitung total item yang dibeli.
         * @returns {number}
         */
        totalItems: (state) =>
            state.carts.reduce((sum, store) =>
                sum + store.products.reduce((s, p) => s + p.quantity, 0), 0),

        /**
         * Menghitung total harga yang dibeli.
         * @returns {number}
         */
        totalPrice: (state) =>
            state.carts.reduce((sum, store) =>
                sum + store.products.reduce((s, p) => s + p.price * p.quantity, 0), 0),

        /**
         * Mengembalikan array of objects, mewakili toko-toko yang dipilih.
         * @returns {Array}
         */
        selectedCarts: (state) => {
            return state.carts.filter(store => state.selectedStores.has(store.storeId))
        },

        /**
         * Menghitung total item yang dibeli dari toko-toko yang dipilih.
         * @returns {number}
         */
        totalSelectedItems: (state) => {
            return state.selectedCarts.reduce((sum, store) =>
                sum + store.products.length, 0)
        },

        /**
         * Menghitung total quantity yang dibeli dari toko-toko yang dipilih.
         * @returns {number}
         */
        totalSelectedQuantity: (state) => {
            return state.selectedCarts.reduce((sum, store) =>
                sum + store.products.reduce((s, p) => s + p.quantity, 0), 0)
        },

        /**
         * Menghitung subtotal dari toko-toko yang dipilih.
         * @returns {number}
         */
        subtotalSelected: (state) => {
            return state.selectedCarts.reduce((sum, store) =>
                sum + store.products.reduce((s, p) => s + p.price * p.quantity, 0), 0)
        },

        /**
         * Menghitung ppn dari toko-toko yang dipilih.
         * @returns {number}
         */
        ppnSelected: (state) => {
            return state.subtotalSelected * 0.11
        },

        /**
         * Menghitung discount dari toko-toko yang dipilih.
         * @returns {number}
         */
        discountSelected: (state) => {
            return 0 // Bisa diubah sesuai dengan logika discount
        },

        /**
         * Menghitung grand total dari toko-toko yang dipilih.
         * @returns {number}
         */
        grandTotalSelected: (state) => {
            return state.subtotalSelected + state.ppnSelected - state.discountSelected
        },

        /**
         * Check apakah ada toko yang dipilih.
         * @returns {boolean}
         */
        hasSelectedStores: (state) => {
            return state.selectedStores.size > 0
        }
    },

    actions: {
        /**
         * Toggle selection untuk toko.
         * @param {string} storeId - id toko yang akan di-toggle
         */
        toggleStoreSelection(storeId) {
            this.selectedStores.clear() // Clear existing selections first
            if (this.selectedStores.has(storeId)) {
                this.selectedStores.delete(storeId)
            } else {
                this.selectedStores.add(storeId)
            }
            this.saveSelectedStores()
        },

        /**
         * Clear selected stores.
         */
        clearSelectedStores() {
            this.selectedStores.clear()
            this.saveSelectedStores()
        },

        /**
         * Save selected stores to localStorage.
         */
        saveSelectedStores() {
            localStorage.setItem(SELECTED_STORES_KEY, JSON.stringify([...this.selectedStores]))
        },

        /**
         * Add product to cart.
         * @param {Object} product - product yang akan di-add
         */
        addToCart(product) {
            const storeId = product.store.id
            const storeAddressId = product.store.address_id
            const storeName = product.store.name
            const storeLogo = product.store.logo
            const qty = product.quantity || 1

            let storeCart = this.carts.find(s => s.storeId === storeId)

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

            const existing = storeCart.products.find(p => p.id === product.id)

            if (existing) {
                existing.quantity += qty
            } else {
                const fullProductData = JSON.parse(JSON.stringify(product))
                fullProductData.quantity = qty
                storeCart.products.push(fullProductData)
            }

            this.save()
        },
        
        clearSelectedItems() {
            // Filter out carts from selected stores
            this.carts = this.carts.filter(cart => !this.selectedStores.has(cart.storeId))
            
            // Clear selected stores
            this.selectedStores.clear()
            
            // Save to localStorage
            this.save()
            this.saveSelectedStores()
        },

        /**
         * Remove product from cart.
         * @param {string} storeId - id toko
         * @param {string} productId - id produk
         */
        removeFromCart(storeId, productId) {
            const store = this.carts.find(s => s.storeId === storeId)
            if (!store) return

            store.products = store.products.filter(p => p.id !== productId)

            if (store.products.length === 0) {
                this.carts = this.carts.filter(s => s.storeId !== storeId)
                // Remove from selected stores if store is empty
                this.selectedStores.delete(storeId)
                this.saveSelectedStores()
            }

            this.save()
        },

        /**
         * Decrease quantity of product in cart.
         * @param {string} storeId - id toko
         * @param {string} productId - id produk
         */
        decreaseQuantity(storeId, productId) {
            const store = this.carts.find(s => s.storeId === storeId)
            const product = store?.products.find(p => p.id === productId)
            if (product) product.quantity--

            this.save()
        },

        /**
         * Increase quantity of product in cart.
         * @param {string} storeId - id toko
         * @param {string} productId - id produk
         */
        increaseQuantity(storeId, productId) {
            const store = this.carts.find(s => s.storeId === storeId)
            const product = store?.products.find(p => p.id === productId)
            if (product) product.quantity++

            this.save()
        },

        /**
         * Update quantity of product in cart.
         * @param {string} storeId - id toko
         * @param {string} productId - id produk
         * @param {number} qty - jumlah produk yang akan di-update
         */
        updateQuantity(storeId, productId, qty) {
            const store = this.carts.find(s => s.storeId === storeId)
            const product = store?.products.find(p => p.id === productId)
            if (product) product.quantity = qty

            this.save()
        },

        /**
         * Clear cart.
         */
        clearCart() {
            this.carts = []
            this.selectedStores.clear()
            this.save()
            this.saveSelectedStores()
        },

        /**
         * Save cart to localStorage.
         */
        save() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(this.carts))
        }
    }
})

