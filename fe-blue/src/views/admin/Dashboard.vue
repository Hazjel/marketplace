<script setup>
    import { useAuthStore } from '@/stores/auth';
    import { storeToRefs } from 'pinia';
    import DashboardAdmin from '@/components/admin/dashboard/DashboardAdmin.vue';
    import DashboardStore from '@/components/admin/dashboard/DashboardStore.vue';
    import DashboardBuyer from '@/components/admin/dashboard/DashboardBuyer.vue';
    
    const authStore = useAuthStore()
    const { user } = storeToRefs(authStore)
</script>
<template>
    <DashboardAdmin v-if="user?.role === 'admin'" />
    <DashboardStore v-else-if="user?.role === 'store'" />
    <DashboardBuyer v-else-if="user?.role === 'buyer'" />
</template>