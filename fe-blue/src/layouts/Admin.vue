<script setup>
import Sidebar from '@/components/admin/Sidebar.vue';
import Topbar from '@/components/admin/Topbar.vue';

</script>

<template>
    <div id="main-container" class="flex flex-1 min-w-0 bg-custom-background">
        <Sidebar />
        <div id="Content" class="flex flex-col flex-1 p-6 pt-0 min-w-0">
            <Topbar />
            <main class="flex flex-col gap-5 flex-1">
                <RouterView />
            </main>
        </div>
    </div>
</template>