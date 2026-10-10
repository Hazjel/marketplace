import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

const post = vi.fn()
const get = vi.fn()
vi.mock('@/plugins/axios', () => ({
  axiosInstance: { post: (...args) => post(...args), get: (...args) => get(...args) }
}))
const toastSuccess = vi.fn()
vi.mock('vue-toastification', () => ({
  useToast: () => ({ success: (...args) => toastSuccess(...args), error: vi.fn() })
}))

import ComplaintPanel from './ComplaintPanel.vue'

const order = (extra = {}) => ({
  id: 'trx-1',
  delivery_status: 'delivering',
  payment_status: 'paid',
  grand_total: 216000,
  complaint: null,
  ...extra
})

const complaint = (extra = {}) => ({
  id: 'cmp-1',
  reason: 'damaged',
  description: 'Layar retak saat paket dibuka',
  photos: ['http://api.test/storage/assets/complaint/a.jpg'],
  status: 'open',
  seller_response: null,
  admin_note: null,
  deadline_at: new Date(Date.now() + 26 * 3_600_000).toISOString(),
  escalated_at: null,
  resolved_at: null,
  created_at: new Date().toISOString(),
  ...extra
})

const mountPanel = (props) => mount(ComplaintPanel, { props, attachTo: document.body })

const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text)

const file = (name, type, size = 1000) => {
  const f = new File(['x'], name, { type })
  Object.defineProperty(f, 'size', { value: size })
  return f
}

const choosePhotos = async (wrapper, files) => {
  const input = wrapper.get('#complaint-photos')
  Object.defineProperty(input.element, 'files', { value: files, configurable: true })
  await input.trigger('change')
}

const openBuyerForm = async () => {
  const wrapper = mountPanel({ transaction: order(), isBuyer: true })
  await button(wrapper, 'Ajukan Komplain').trigger('click')
  return wrapper
}

describe('ComplaintPanel', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    post.mockReset()
    get.mockReset()
    toastSuccess.mockReset()
    get.mockResolvedValue({ data: { data: order({ complaint: complaint() }) } })
    vi.stubGlobal(
      'confirm',
      vi.fn(() => true)
    )
    URL.createObjectURL = vi.fn((f) => `blob:${f.name}`)
    URL.revokeObjectURL = vi.fn()
  })

  describe('buyer form', () => {
    it('is offered only on a paid, delivering order without a complaint', () => {
      expect(
        button(mountPanel({ transaction: order(), isBuyer: true }), 'Ajukan Komplain')
      ).toBeTruthy()
      expect(
        mountPanel({ transaction: order({ delivery_status: 'processing' }), isBuyer: true }).html()
      ).toBe('<!--v-if-->')
      expect(mountPanel({ transaction: order(), isSeller: true }).html()).toBe('<!--v-if-->')
      expect(mountPanel({ transaction: order(), isAdmin: true }).html()).toBe('<!--v-if-->')
    })

    it('focuses the reason field when opened', async () => {
      const wrapper = await openBuyerForm()
      expect(document.activeElement).toBe(wrapper.get('#complaint-reason').element)
      wrapper.unmount()
    })

    it('requires a reason and a 10 character description', async () => {
      const wrapper = await openBuyerForm()
      await wrapper.get('#complaint-description').setValue('pendek')
      await wrapper.get('form').trigger('submit')

      expect(wrapper.get('#complaint-reason-error').text()).toBe('Pilih alasan komplain')
      expect(wrapper.get('#complaint-reason').attributes('aria-describedby')).toBe(
        'complaint-reason-error'
      )
      expect(wrapper.get('#complaint-description-error').text()).toContain('minimal 10')
      expect(wrapper.get('#complaint-description').attributes('aria-describedby')).toContain(
        'complaint-description-error'
      )
      expect(wrapper.text()).toContain('6/1000')
      expect(post).not.toHaveBeenCalled()
      wrapper.unmount()
    })

    it('rejects more than 3 photos', async () => {
      const wrapper = await openBuyerForm()
      await choosePhotos(
        wrapper,
        [1, 2, 3, 4].map((n) => file(`${n}.jpg`, 'image/jpeg'))
      )

      expect(wrapper.get('#complaint-photos-error').text()).toBe('Maksimal 3 foto')
      expect(wrapper.findAll('li img')).toHaveLength(0)
      wrapper.unmount()
    })

    it('rejects the wrong file type and photos over 2 MB', async () => {
      const wrapper = await openBuyerForm()
      await choosePhotos(wrapper, [file('doc.pdf', 'application/pdf')])
      expect(wrapper.get('#complaint-photos-error').text()).toContain('JPG, PNG, atau WEBP')

      await choosePhotos(wrapper, [file('big.jpg', 'image/jpeg', 3 * 1024 * 1024)])
      expect(wrapper.get('#complaint-photos-error').text()).toContain('2 MB')
      wrapper.unmount()
    })

    it('previews photos, hides the picker at 3 and lets one be removed', async () => {
      const wrapper = await openBuyerForm()
      await choosePhotos(wrapper, [file('a.jpg', 'image/jpeg'), file('b.png', 'image/png')])
      await choosePhotos(wrapper, [file('c.webp', 'image/webp')])

      expect(wrapper.findAll('li img').map((img) => img.attributes('src'))).toEqual([
        'blob:a.jpg',
        'blob:b.png',
        'blob:c.webp'
      ])
      expect(wrapper.get('#complaint-photos').attributes('disabled')).toBeDefined()
      expect(wrapper.get('#complaint-photos-hint').text()).toContain('Maksimal 3 foto')

      await wrapper.get('[aria-label="Hapus foto 2"]').trigger('click')
      expect(wrapper.findAll('li img')).toHaveLength(2)
      expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:b.png')
      wrapper.unmount()
    })

    it('sends reason, description and photos[] as multipart, then re-fetches', async () => {
      const created = order({ complaint: complaint() })
      let resolve
      post.mockReturnValueOnce(new Promise((r) => (resolve = r)))
      const wrapper = await openBuyerForm()
      await wrapper.get('#complaint-reason').setValue('damaged')
      await wrapper.get('#complaint-description').setValue('  Layar retak saat paket dibuka  ')
      const photo = file('a.jpg', 'image/jpeg')
      await choosePhotos(wrapper, [photo])

      await wrapper.get('form').trigger('submit')
      await wrapper.get('form').trigger('submit')
      expect(post).toHaveBeenCalledTimes(1)

      const [url, body, config] = post.mock.calls[0]
      expect(url).toBe('transaction/trx-1/complaint')
      expect(body).toBeInstanceOf(FormData)
      expect(body.get('reason')).toBe('damaged')
      expect(body.get('description')).toBe('Layar retak saat paket dibuka')
      expect(body.getAll('photos[]')).toEqual([photo])
      expect(config.headers['Content-Type']).toBe('multipart/form-data')
      expect(button(wrapper, 'Mengirim...').attributes('disabled')).toBeDefined()

      resolve({ data: { data: created, message: 'Komplain terkirim' } })
      await flushPromises()
      expect(wrapper.emitted('updated')[0]).toEqual([created])
      expect(toastSuccess).toHaveBeenCalledWith('Komplain terkirim')
      expect(get).toHaveBeenCalledWith('transaction/trx-1')
      expect(wrapper.emitted('updated')).toHaveLength(2)
      wrapper.unmount()
    })

    it('counts the action as done when the re-fetch fails', async () => {
      const created = order({ complaint: complaint() })
      post.mockResolvedValueOnce({ data: { data: created } })
      get.mockRejectedValueOnce({ response: { status: 500, data: {} } })
      const wrapper = await openBuyerForm()
      await wrapper.get('#complaint-reason').setValue('other')
      await wrapper.get('#complaint-description').setValue('Isi paket tidak sesuai foto')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(wrapper.emitted('updated')).toEqual([[created]])
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      wrapper.unmount()
    })

    it('shows the API 422 message as an alert', async () => {
      post.mockRejectedValueOnce({
        response: { status: 422, data: { message: 'Pesanan ini sudah pernah dikomplain' } }
      })
      const wrapper = await openBuyerForm()
      await wrapper.get('#complaint-reason').setValue('not_received')
      await wrapper.get('#complaint-description').setValue('Paket belum sampai sama sekali')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toBe('Pesanan ini sudah pernah dikomplain')
      expect(document.activeElement).toBe(wrapper.get('[role="alert"]').element)
      expect(button(wrapper, 'Kirim Komplain').attributes('disabled')).toBeUndefined()
      wrapper.unmount()
    })
  })

  describe('status card', () => {
    it.each([
      ['open', 'Menunggu tanggapan penjual'],
      ['escalated', 'Ditinjau admin'],
      ['approved', 'Disetujui – dana dikembalikan'],
      ['rejected', 'Ditolak'],
      ['withdrawn', 'Ditarik']
    ])('labels %s as "%s"', (status, label) => {
      const wrapper = mountPanel({ transaction: order({ complaint: complaint({ status }) }) })
      expect(wrapper.get('[data-testid="complaint-status"]').text()).toBe(label)
    })

    it('shows the deadline, reason, description, photo link, response and note', () => {
      const wrapper = mountPanel({
        transaction: order({
          complaint: complaint({ seller_response: 'Barang dikirim utuh', admin_note: 'Cek foto' })
        }),
        isSeller: true
      })

      expect(wrapper.text()).toContain('Batas tanggapan penjual')
      expect(wrapper.text()).toMatch(/Sisa 1 hari (1|2) jam/)
      expect(wrapper.text()).toContain('Barang rusak')
      expect(wrapper.text()).toContain('Layar retak saat paket dibuka')
      expect(wrapper.text()).toContain('Barang dikirim utuh')
      expect(wrapper.text()).toContain('Cek foto')
      const link = wrapper.get('a[target="_blank"]')
      expect(link.attributes('href')).toBe('http://api.test/storage/assets/complaint/a.jpg')
      expect(link.attributes('rel')).toContain('noopener')
    })

    it('shows the absolute deadline and refreshes the time left every minute', async () => {
      vi.useFakeTimers()
      vi.setSystemTime(new Date('2026-10-10T07:00:00Z'))
      try {
        const wrapper = mountPanel({
          transaction: order({ complaint: complaint({ deadline_at: '2026-10-10T08:30:00Z' }) })
        })
        expect(wrapper.text()).toMatch(/Batas tanggapan penjual: 10 Okt 2026, \d\d:30/)
        expect(wrapper.text()).toContain('Sisa 1 jam.')

        vi.setSystemTime(new Date('2026-10-10T07:31:00Z'))
        await vi.advanceTimersByTimeAsync(60_000)
        expect(wrapper.text()).toContain('Sisa kurang dari 1 jam.')

        wrapper.unmount()
        expect(vi.getTimerCount()).toBe(0)
      } finally {
        vi.useRealTimers()
      }
    })

    it('says when the seller let the deadline pass', () => {
      const wrapper = mountPanel({
        transaction: order({ complaint: complaint({ status: 'escalated' }) })
      })
      expect(wrapper.text()).toContain('Penjual tidak menanggapi sebelum batas waktu')
    })
  })

  describe('role actions', () => {
    const buttons = (props) =>
      mountPanel(props)
        .findAll('button')
        .map((b) => b.text())

    it('shows each role only its own actions', () => {
      const open = order({ complaint: complaint() })
      const escalated = order({ complaint: complaint({ status: 'escalated' }) })

      expect(buttons({ transaction: open, isBuyer: true })).toEqual(['Tarik Komplain'])
      expect(buttons({ transaction: escalated, isBuyer: true })).toEqual(['Tarik Komplain'])
      expect(buttons({ transaction: open, isSeller: true })).toEqual(['Setujui & Refund', 'Tolak'])
      expect(buttons({ transaction: escalated, isSeller: true })).toEqual([])
      expect(buttons({ transaction: open, isAdmin: true })).toEqual([])
      expect(buttons({ transaction: escalated, isAdmin: true })).toEqual(['Simpan Keputusan'])
      expect(
        buttons({
          transaction: order({ complaint: complaint({ status: 'approved' }) }),
          isBuyer: true
        })
      ).toEqual([])
    })

    it('lets the buyer withdraw after confirming', async () => {
      post.mockResolvedValueOnce({
        data: { data: order({ complaint: complaint({ status: 'withdrawn' }) }) }
      })
      const wrapper = mountPanel({ transaction: order({ complaint: complaint() }), isBuyer: true })

      await button(wrapper, 'Tarik Komplain').trigger('click')
      expect(confirm).toHaveBeenCalled()
      expect(post).toHaveBeenCalledWith('complaint/cmp-1/withdraw', {})
    })

    it('does nothing when the buyer cancels the withdraw confirmation', async () => {
      confirm.mockReturnValueOnce(false)
      const wrapper = mountPanel({ transaction: order({ complaint: complaint() }), isBuyer: true })

      await button(wrapper, 'Tarik Komplain').trigger('click')
      expect(post).not.toHaveBeenCalled()
    })

    it('warns the seller that the full amount is refunded without restock', async () => {
      post.mockResolvedValueOnce({ data: { data: order() } })
      const wrapper = mountPanel({ transaction: order({ complaint: complaint() }), isSeller: true })

      await button(wrapper, 'Setujui & Refund').trigger('click')
      expect(confirm.mock.calls[0][0]).toMatch(/Seluruh dana pesanan \(Rp 216[.,]000\)/)
      expect(confirm.mock.calls[0][0]).toContain('stok produk TIDAK dikembalikan')
      expect(post).toHaveBeenCalledWith('complaint/cmp-1/accept', {})
    })

    it('does not ask to confirm again while an action is running', async () => {
      post.mockReturnValueOnce(new Promise(() => {}))
      const wrapper = mountPanel({
        transaction: order({ complaint: complaint({ status: 'escalated' }) }),
        isAdmin: true
      })
      await wrapper.get('input[value="approve"]').setValue()
      await wrapper.get('#complaint-note').setValue('Foto membuktikan rusak')

      await wrapper.get('form').trigger('submit')
      await wrapper.get('form').trigger('submit')
      expect(confirm).toHaveBeenCalledTimes(1)
      expect(post).toHaveBeenCalledTimes(1)
    })

    it('shows a non-string API error body as readable text', async () => {
      post.mockRejectedValueOnce({ response: { status: 422, data: { message: { x: 1 } } } })
      const wrapper = mountPanel({ transaction: order({ complaint: complaint() }), isBuyer: true })

      await button(wrapper, 'Tarik Komplain').trigger('click')
      await flushPromises()
      expect(wrapper.get('[role="alert"]').text()).toBe('Gagal menarik komplain')
    })

    it('requires a seller response before rejecting', async () => {
      post.mockResolvedValueOnce({ data: { data: order() } })
      const wrapper = mountPanel({ transaction: order({ complaint: complaint() }), isSeller: true })

      await button(wrapper, 'Tolak').trigger('click')
      await wrapper.get('form').trigger('submit')
      expect(wrapper.get('#complaint-response-error').text()).toContain('minimal 5')
      expect(post).not.toHaveBeenCalled()

      await wrapper.get('#complaint-response').setValue('Barang dikirim utuh')
      await wrapper.get('form').trigger('submit')
      expect(post).toHaveBeenCalledWith('complaint/cmp-1/reject', {
        response: 'Barang dikirim utuh'
      })
    })

    it('makes the admin pick an outcome and write a note, then confirms', async () => {
      post.mockResolvedValue({ data: { data: order() } })
      const wrapper = mountPanel({
        transaction: order({ complaint: complaint({ status: 'escalated' }) }),
        isAdmin: true
      })

      await wrapper.get('form').trigger('submit')
      expect(wrapper.get('#complaint-outcome-error').text()).toBe('Pilih keputusan')
      expect(wrapper.get('#complaint-note-error').text()).toContain('minimal 5')

      await wrapper.get('input[value="reject"]').setValue()
      await wrapper.get('#complaint-note').setValue('Bukti tidak cukup')
      await wrapper.get('form').trigger('submit')
      expect(confirm.mock.calls[0][0]).toContain('Tolak komplain?')
      expect(post).toHaveBeenCalledWith('complaint/cmp-1/resolve', {
        outcome: 'reject',
        note: 'Bukti tidak cukup'
      })
      await flushPromises()

      await wrapper.get('input[value="approve"]').setValue()
      await wrapper.get('#complaint-note').setValue('Foto membuktikan rusak')
      await wrapper.get('form').trigger('submit')
      expect(confirm.mock.calls[1][0]).toMatch(/Seluruh dana pesanan \(Rp 216[.,]000\)/)
      expect(post).toHaveBeenLastCalledWith('complaint/cmp-1/resolve', {
        outcome: 'approve',
        note: 'Foto membuktikan rusak'
      })
    })

    it('shows a 422 from a seller action as an alert and re-enables the buttons', async () => {
      post.mockRejectedValueOnce({
        response: { status: 422, data: { message: 'Komplain ini sudah ditanggapi' } }
      })
      const wrapper = mountPanel({ transaction: order({ complaint: complaint() }), isSeller: true })

      await button(wrapper, 'Setujui & Refund').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toBe('Komplain ini sudah ditanggapi')
      expect(document.activeElement).toBe(wrapper.get('[role="alert"]').element)
      expect(button(wrapper, 'Setujui & Refund').attributes('disabled')).toBeUndefined()
      expect(wrapper.emitted('updated')).toBeUndefined()
    })

    it('moves focus to the status heading after an action', async () => {
      const withdrawn = order({ complaint: complaint({ status: 'withdrawn' }) })
      post.mockResolvedValueOnce({ data: { data: withdrawn } })
      get.mockResolvedValueOnce({ data: { data: withdrawn } })
      let wrapper = null
      wrapper = mountPanel({
        transaction: order({ complaint: complaint() }),
        isBuyer: true,
        onUpdated: (updated) => wrapper.setProps({ transaction: updated })
      })

      await button(wrapper, 'Tarik Komplain').trigger('click')
      await flushPromises()

      expect(document.activeElement).toBe(wrapper.get('h3').element)
      wrapper.unmount()
    })
  })
})
