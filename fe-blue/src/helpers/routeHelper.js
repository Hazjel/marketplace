import { useAuthStore } from '@/stores/auth'

// Bikin route object yang otomatis pakai prefix sesuai role user:
// admin → `admin.{name}`, selain itu → `user.{name}` + inject username param.
// Ganti pola hardcode `admin.*` yang putus saat seller/user mode.
export const dashboardRoute = (name, params = {}) => {
  const authStore = useAuthStore()
  const user = authStore.user

  if (user?.role === 'admin') {
    return { name: `admin.${name}`, params }
  }

  return {
    name: `user.${name}`,
    params: { username: user?.username, ...params }
  }
}
