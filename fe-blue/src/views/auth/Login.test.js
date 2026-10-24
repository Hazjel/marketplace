import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Login from './Login.vue'

const { post } = vi.hoisted(() => ({ post: vi.fn() }))

vi.mock('@/plugins/axios', () => ({ axiosInstance: { post } }))
vi.mock('@/router', () => ({ default: { push: vi.fn() } }))
vi.mock('vue-router', () => ({ useRoute: () => ({ query: {} }) }))

async function submitLogin(failure) {
  post.mockRejectedValueOnce(failure)
  const wrapper = mount(Login, { global: { stubs: ['RouterLink'] } })
  await wrapper.get('input[type="email"]').setValue('buyer@example.test')
  await wrapper.get('#passwordInput').setValue('wrong-password')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  return wrapper
}

describe('Login errors', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    post.mockReset()
    localStorage.clear()
  })

  it('shows the API message when credentials are rejected', async () => {
    const wrapper = await submitLogin({
      response: { status: 401, data: { message: 'Email atau password salah.' } }
    })

    expect(wrapper.get('[role="alert"]').text()).toBe('Email atau password salah.')
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined()
    wrapper.unmount()
  })

  it('shows connection failures so the user can retry', async () => {
    const wrapper = await submitLogin(new Error('Network Error'))

    expect(wrapper.get('[role="alert"]').text()).toBe(
      'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.'
    )
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined()
    wrapper.unmount()
  })

  it('keeps field validation errors next to their inputs', async () => {
    const wrapper = await submitLogin({
      response: {
        status: 422,
        data: {
          message: 'Validasi gagal.',
          errors: { email: ['Email wajib diisi.'], password: ['Password wajib diisi.'] }
        }
      }
    })

    expect(wrapper.text()).toContain('Email wajib diisi.')
    expect(wrapper.text()).toContain('Password wajib diisi.')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('keeps the localized fallback for legacy unauthorized responses', async () => {
    const wrapper = await submitLogin({
      response: { status: 401, data: { message: 'Unauthorized' } }
    })

    expect(wrapper.text()).toContain('Email atau password salah')
    wrapper.unmount()
  })
})
