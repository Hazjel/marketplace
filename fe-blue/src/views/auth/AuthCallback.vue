<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

import Cookies from 'js-cookie'

onMounted(async () => {
  const token = route.query.token
  const username = route.query.username

  if (token && username) {
    // Set token in Cookies (required by axios instance)
    Cookies.set('token', token)

    // Remove read-only assignment to store
    // authStore.token = token

    try {
      // Fetch full user profile to populate pinia state correctly
      const user = await authStore.checkAuth()

      // Check for missing profile info (e.g. phone number)
      const buyerProfile = user.buyer
      if (buyerProfile && !buyerProfile.phone_number) {
        // Redirect to edit profile with a notification query param or state
        // Assuming 'user.edit-profile' exists
        router.push({
          name: 'user.edit-profile',
          params: { username: username },
          query: { alert: 'complete_profile' }
        })
      } else {
        // Redirect to dashboard normally
        router.push({ name: 'user.dashboard', params: { username: username } })
      }
    } catch (e) {
      console.error('Auth Check Failed', e)
      router.push({ name: 'auth.login' })
    }
  } else {
    // Failed, go back to login
    router.push({ name: 'auth.login' })
  }
})
</script>

<template>
  <div class="flex items-center justify-center min-h-screen">
    <p class="font-bold text-xl">Processing Login...</p>
  </div>
</template>
