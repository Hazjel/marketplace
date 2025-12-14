<script setup>
import DashboardAdmin from '@/components/admin/dashboard/DashboardAdmin.vue';
import DashboardBuyer from '@/components/admin/dashboard/DashboardBuyer.vue';
import DashboardStore from '@/components/admin/dashboard/DashboardStore.vue';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
</script>

<template>
    <DashboardAdmin v-if="user?.role === 'admin'" />
    <DashboardBuyer v-if="user?.role === 'buyer'" />
    <DashboardStore v-if="user?.role === 'store'" />
</template>