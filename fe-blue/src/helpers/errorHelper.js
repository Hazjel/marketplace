// Map-based error handler: O(1) lookup, Open/Closed Principle compliant.
// Add new HTTP status codes here without modifying the core resolution logic.
const ERROR_HANDLERS = {
  422: (err) => err.response.data.errors,
  401: (err) => err.response.data.message,
  400: (err) => err.response.data.message,
  403: () => 'Anda tidak memiliki izin untuk melakukan tindakan ini.',
  404: (err) => err.response.data.message || 'Data tidak ditemukan.',
  429: () => 'Terlalu banyak permintaan. Silakan tunggu sebentar.',
  500: (err) => err.response?.data?.message || 'Terjadi kesalahan pada server.',
}

export function handleError(error) {
  if (!error.response) {
    // Network error (timeout, no connection, CORS, etc.)
    return 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.'
  }

  const handler = ERROR_HANDLERS[error.response.status]
  return handler ? handler(error) : 'Terjadi kesalahan yang tidak diketahui.'
}
