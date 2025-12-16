<script setup>
import Sidebar from '@/components/admin/Sidebar.vue';
import Topbar from '@/components/admin/Topbar.vue';

</script>

<template>
    <div id="main-container" class="flex h-screen w-full bg-custom-background overflow-hidden">
        <Sidebar />
        <div id="Content" class="flex flex-col flex-1 h-full min-w-0 overflow-hidden">
            <!-- Topbar Container with padding to match design -->
            <div class="px-6 pt-0 shrink-0">
                <Topbar />
            </div>
            
            <!-- Main Content Area: Scrollable -->
            <main class="flex flex-col gap-5 flex-1 overflow-y-auto p-6 pt-0">
                <RouterView />
            </main>
        </div>
    </div>
</template>