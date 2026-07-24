import { defineStore } from 'pinia'
import { useResourceState } from '@/stores/createResourceStore'

export const useUserStore = defineStore('user', () => {
  const {
    list: users,
    meta,
    loading,
    error,
    success,
    fetchPaginated
  } = useResourceState({
    endpoint: 'user'
  })

  return { users, meta, loading, error, success, fetchUsersPaginated: fetchPaginated }
})
