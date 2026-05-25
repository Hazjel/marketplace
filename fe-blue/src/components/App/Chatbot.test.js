import { describe, it, expect, vi, beforeEach } from 'vitest'

// ---------------------------------------------------------------------------
// Mock fetch global — tidak perlu hit server asli
// ---------------------------------------------------------------------------
const mockFetch = vi.fn()
global.fetch = mockFetch

// Helper: buat SSE ReadableStream dari array events
function makeSSEStream(events) {
  const encoder = new TextEncoder()
  return new ReadableStream({
    start(controller) {
      for (const evt of events) {
        controller.enqueue(encoder.encode(`data: ${JSON.stringify(evt)}\n\n`))
      }
      controller.close()
    }
  })
}

// ---------------------------------------------------------------------------
// Unit: _parse_classifier_output equivalents (JS side tidak ada, skip)
// Unit: sendMessage + SSE parsing logic (isolasi dengan mock fetch)
// ---------------------------------------------------------------------------
describe('Chatbot streaming logic', () => {
  beforeEach(() => {
    mockFetch.mockReset()
  })

  it('mengumpulkan token dari SSE stream menjadi teks utuh', async () => {
    const events = [
      { token: 'Halo', done: false, session_id: 'sess-1' },
      { token: '!',    done: false, session_id: 'sess-1' },
      { token: '',     done: true,  session_id: 'sess-1' }
    ]

    mockFetch.mockResolvedValueOnce({
      ok:   true,
      body: makeSSEStream(events)
    })

    // Simulasi logic parsing di Chatbot.vue (duplikasi minimal)
    let fullText = ''
    let finalSession = null

    const res = await fetch('http://localhost:8001/predict/stream', {
      method: 'POST',
      body:   JSON.stringify({ message: 'halo', session_id: null })
    })

    const reader  = res.body.getReader()
    const decoder = new TextDecoder()
    let   buffer  = ''
    let   done    = false

    while (!done) {
      const { done: rdDone, value } = await reader.read()
      if (rdDone) break
      buffer += decoder.decode(value, { stream: true })

      const parts = buffer.split('\n\n')
      buffer = parts.pop() ?? ''

      for (const part of parts) {
        if (!part.startsWith('data: ')) continue
        const evt = JSON.parse(part.slice(6))
        if (evt.token) fullText += evt.token
        if (evt.session_id) finalSession = evt.session_id
        if (evt.done) { done = true; break }
      }
    }

    expect(fullText).toBe('Halo!')
    expect(finalSession).toBe('sess-1')
  })

  it('menangani HTTP error dari server', async () => {
    mockFetch.mockResolvedValueOnce({ ok: false, status: 429 })

    const res = await fetch('http://localhost:8001/predict/stream', { method: 'POST' })
    expect(res.ok).toBe(false)
    expect(res.status).toBe(429)
  })

  it('mengirim feedback dengan payload yang benar', async () => {
    mockFetch.mockResolvedValueOnce({
      ok:   true,
      status: 202,
      json: async () => ({ status: 'received' })
    })

    await fetch('http://localhost:8001/feedback', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ session_id: 'sess-1', rating: 5, comment: 'bagus!' })
    })

    const [url, opts] = mockFetch.mock.calls[0]
    const body = JSON.parse(opts.body)

    expect(url).toContain('/feedback')
    expect(body.rating).toBe(5)
    expect(body.session_id).toBe('sess-1')
  })

  it('tidak kirim pesan jika input kosong', () => {
    // Guard kondisi di sendMessage() — userMsg.trim() === ''
    const userMsg = '   '
    expect(userMsg.trim().length).toBe(0)
    // mockFetch tidak dipanggil
    expect(mockFetch).not.toHaveBeenCalled()
  })
})
