import { describe, it, expect, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCartStore } from './cart'

// Node 26's sendiri experimental global `localStorage` menutupi
// implementasi jsdom (didefinisikan non-configurable sebelum jsdom sempat
// memasangnya) -- Object.defineProperty di sini timpa paksa dengan
// polyfill in-memory sederhana khusus file test ini, tidak menyentuh
// setup global lain.
const memoryStorage = (() => {
  let store = {}
  return {
    getItem: (key) => (key in store ? store[key] : null),
    setItem: (key, value) => {
      store[key] = String(value)
    },
    removeItem: (key) => {
      delete store[key]
    },
    clear: () => {
      store = {}
    }
  }
})()
Object.defineProperty(globalThis, 'localStorage', { value: memoryStorage, configurable: true })
Object.defineProperty(window, 'localStorage', { value: memoryStorage, configurable: true })

// _addToLocalCart sebelumnya mencocokkan baris cart cuma lewat product.id,
// jadi menambahkan Varian A lalu Varian B dari produk yang SAMA ke-merge
// jadi satu baris (quantity dijumlahkan), tetap memakai variant_id/price
// dari yang pertama kali ditambahkan -- padahal keduanya harus jadi dua
// baris cart terpisah.
describe('cart store — variant-aware line matching', () => {
  beforeEach(() => {
    window.localStorage.clear()
    setActivePinia(createPinia())
  })

  const baseProduct = (overrides = {}) => ({
    id: 'product-1',
    store: { id: 'store-1', address_id: '1', name: 'Toko', logo: null },
    quantity: 1,
    ...overrides
  })

  it('keeps two different variants of the same product as separate cart lines', async () => {
    const cart = useCartStore()

    await cart.addToCart(baseProduct({ variant_id: 'variant-a', price: 100000 }))
    await cart.addToCart(baseProduct({ variant_id: 'variant-b', price: 150000 }))

    const store = cart.carts.find((s) => s.storeId === 'store-1')
    expect(store.products).toHaveLength(2)

    const lineA = store.products.find((p) => p.variant_id === 'variant-a')
    const lineB = store.products.find((p) => p.variant_id === 'variant-b')
    expect(lineA.quantity).toBe(1)
    expect(lineA.price).toBe(100000)
    expect(lineB.quantity).toBe(1)
    expect(lineB.price).toBe(150000)
  })

  it('still merges quantity when adding the same variant twice', async () => {
    const cart = useCartStore()

    await cart.addToCart(baseProduct({ variant_id: 'variant-a', price: 100000, quantity: 1 }))
    await cart.addToCart(baseProduct({ variant_id: 'variant-a', price: 100000, quantity: 2 }))

    const store = cart.carts.find((s) => s.storeId === 'store-1')
    expect(store.products).toHaveLength(1)
    expect(store.products[0].quantity).toBe(3)
  })

  it('merges non-variant products as before (regression)', async () => {
    const cart = useCartStore()

    await cart.addToCart(baseProduct({ price: 20000, quantity: 1 }))
    await cart.addToCart(baseProduct({ price: 20000, quantity: 1 }))

    const store = cart.carts.find((s) => s.storeId === 'store-1')
    expect(store.products).toHaveLength(1)
    expect(store.products[0].quantity).toBe(2)
  })
})
