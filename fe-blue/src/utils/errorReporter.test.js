import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// Fresh module per test: the reporter remembers what it already sent.
async function loadReporter() {
  vi.resetModules()
  return import('./errorReporter')
}

describe('reportClientError', () => {
  let fetchMock

  beforeEach(() => {
    vi.stubEnv('DEV', false)
    vi.stubEnv('MODE', 'production')
    vi.stubEnv('VITE_API_BASE_URL', 'https://blukios.store/api')
    fetchMock = vi.fn(() => Promise.resolve({ ok: true }))
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.unstubAllEnvs()
    vi.unstubAllGlobals()
  })

  it('posts the error to the API', async () => {
    const { reportClientError } = await loadReporter()

    reportClientError(new TypeError("Cannot read properties of undefined (reading 'id')"), 'setup function')

    expect(fetchMock).toHaveBeenCalledTimes(1)
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toBe('https://blukios.store/api/client-errors')
    const body = JSON.parse(options.body)
    expect(body.source).toBe('web')
    expect(body.message).toBe("TypeError: Cannot read properties of undefined (reading 'id') (setup function)")
    expect(body.stack).toContain('TypeError')
  })

  it('sends the same error only once per page', async () => {
    const { reportClientError } = await loadReporter()

    reportClientError(new Error('boom'))
    reportClientError(new Error('boom'))

    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('caps reports per page', async () => {
    const { reportClientError } = await loadReporter()

    for (let i = 0; i < 10; i++) reportClientError(new Error(`boom ${i}`))

    expect(fetchMock).toHaveBeenCalledTimes(5)
  })

  it('skips API failures and browser noise', async () => {
    const { reportClientError } = await loadReporter()

    reportClientError({ isAxiosError: true, message: 'Request failed with status code 500' })
    reportClientError(new Error('ResizeObserver loop completed with undelivered notifications.'))
    reportClientError('Script error.')
    reportClientError(undefined)

    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('stays silent in development', async () => {
    vi.stubEnv('DEV', true)
    const { reportClientError } = await loadReporter()

    reportClientError(new Error('boom'))

    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('never throws when the report itself fails', async () => {
    fetchMock.mockImplementation(() => Promise.reject(new Error('offline')))
    const { reportClientError } = await loadReporter()

    expect(() => reportClientError(new Error('boom'))).not.toThrow()
  })
})
