// Session ID persisten per browser buat tracking produk view guest (belum
// login) -- disimpan di localStorage, bukan sessionStorage, biar dedup 10
// menit di backend tetap konsisten walau tab baru dibuka
const STORAGE_KEY = 'blukios_guest_session_id'

export function getGuestSessionId() {
  let sessionId = localStorage.getItem(STORAGE_KEY)

  if (!sessionId) {
    sessionId = crypto.randomUUID()
    localStorage.setItem(STORAGE_KEY, sessionId)
  }

  return sessionId
}
