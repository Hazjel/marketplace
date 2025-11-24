import { useAuthStore } from '@/stores/auth'

export const can = permission => {
    const authStore = useAuthStore()

    const userPermissions = authStore.user?.permissions || []

    return userPermissions.includes(permission)
}

export const hasRole = role => {
    if (!role) return true

    const authStore = useAuthStore()
    const userRole = authStore.user?.role || ''

    return userRole === role
}
