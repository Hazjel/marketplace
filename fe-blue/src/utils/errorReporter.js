// Sends crashes in the buyer's browser to the API (POST /client-errors), where
// ops:check emails them. Without this a broken page is only ever visible in
// that user's console.
//
// Deliberately not axiosInstance: its interceptors toast and redirect, and a
// failing report must never surface to the user or report itself.

const MAX_REPORTS_PER_PAGE = 5

// Noise every site gets that says nothing about this app.
const IGNORED = [/ResizeObserver loop/i, /^Script error\.?$/i, /extension:\/\//i]

const sent = new Set()

function endpoint() {
  const base = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'
  return `${base.replace(/\/$/, '')}/client-errors`
}

function describe(error) {
  if (error instanceof Error) {
    return { message: `${error.name}: ${error.message}`, stack: error.stack }
  }
  return { message: String(error), stack: undefined }
}

/**
 * @param {unknown} error
 * @param {string} [context] where it was caught, e.g. a Vue lifecycle hook
 */
export function reportClientError(error, context) {
  if (error === undefined || error === null) return
  // API failures are counted server-side (5xx) or are expected (4xx).
  if (error?.isAxiosError) return
  if (import.meta.env.DEV || import.meta.env.MODE === 'test') return

  const { message, stack } = describe(error)
  const text = context ? `${message} (${context})` : message
  if (!text || IGNORED.some((pattern) => pattern.test(text))) return
  if (sent.has(text) || sent.size >= MAX_REPORTS_PER_PAGE) return
  sent.add(text)

  try {
    fetch(endpoint(), {
      method: 'POST',
      keepalive: true,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        source: 'web',
        message: text.slice(0, 500),
        url: window.location.href.slice(0, 500),
        stack: stack?.slice(0, 4000),
        release: import.meta.env.VITE_APP_VERSION || undefined
      })
    }).catch(() => {})
  } catch {
    // Reporting is best effort.
  }
}

export function installErrorReporting(app) {
  const previous = app.config.errorHandler
  app.config.errorHandler = (error, instance, info) => {
    reportClientError(error, info)
    if (previous) previous(error, instance, info)
    else console.error(error)
  }

  window.addEventListener('error', (event) => {
    reportClientError(event.error ?? event.message)
  })
  window.addEventListener('unhandledrejection', (event) => {
    reportClientError(event.reason)
  })
}
