<script setup>
import DashboardAdmin from '@/components/admin/dashboard/DashboardAdmin.vue';
import DashboardBuyer from '@/components/admin/dashboard/DashboardBuyer.vue';
import DashboardStore from '@/components/admin/dashboard/DashboardStore.vue';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { onMounted, watchEffect } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
const router = useRouter()
const route = useRoute()

watchEffect(() => {
    if (user.value?.role === 'store' && route.name === 'admin.dashboard') {
        router.replace({ 
            name: 'user.dashboard', 
            params: { username: user.value.username } 
        })
    }
})
</script>

<template>
    <DashboardAdmin v-if="user?.role === 'admin'" />
    <DashboardBuyer v-if="user?.role === 'buyer'" />
    <DashboardStore v-if="user?.role === 'store'" />
</template>