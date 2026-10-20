import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AuthCallback from './AuthCallback.vue'

const { push, post, checkAuth, syncAfterLogin, authStore, cookiesSet, routeQuery } = vi.hoisted(() => {
  const checkAuth = vi.fn().mockResolvedValue(undefined)

  return {
    push: vi.fn(),
    post: vi.fn(),
    checkAuth,
    syncAfterLogin: vi.fn().mockResolvedValue(undefined),
    authStore: { token: null, user: { role: 'buyer' }, checkAuth },
    cookiesSet: vi.fn(),
    routeQuery: { code: 'one-time-code' }
  }
})

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: routeQuery }),
  useRouter: () => ({ push })
}))
vi.mock('@/plugins/axios', () => ({ axiosInstance: { post } }))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => authStore }))
vi.mock('@/stores/cart', () => ({ useCartStore: () => ({ syncAfterLogin }) }))
vi.mock('js-cookie', () => ({ default: { set: cookiesSet } }))

describe('AuthCallback', () => {
  beforeEach(() => {
    push.mockReset()
    post.mockReset()
    cookiesSet.mockReset()
    post.mockResolvedValue({ data: { data: { token: 'sanctum-token', role: 'buyer' } } })
    authStore.token = null
    routeQuery.code = 'one-time-code'
  })

  it('exchanges code instead of reading a bearer token from URL', async () => {
    const wrapper = mount(AuthCallback)
    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(post).toHaveBeenCalledWith('/auth/google/exchange', { code: 'one-time-code' })
    expect(authStore.token).toBe('sanctum-token')
    expect(cookiesSet).toHaveBeenCalledWith('token', 'sanctum-token', expect.any(Object))
    wrapper.unmount()
  })

  it('redirects to login without exchanging when code is missing', async () => {
    routeQuery.code = undefined
    const wrapper = mount(AuthCallback)
    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(post).not.toHaveBeenCalled()
    expect(push).toHaveBeenCalledWith({ name: 'auth.login' })
    wrapper.unmount()
  })

  it('redirects to login when code exchange fails', async () => {
    post.mockRejectedValue(new Error('exchange failed'))
    const wrapper = mount(AuthCallback)
    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(post).toHaveBeenCalledWith('/auth/google/exchange', { code: 'one-time-code' })
    expect(push).toHaveBeenCalledWith({ name: 'auth.login' })
    expect(cookiesSet).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
