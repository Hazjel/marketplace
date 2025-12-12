<script setup lang="ts">
import Navbar from '@/components/App/Navbar.vue';
import Footer from '@/components/App/Footer.vue';
import ChatWidget from '@/components/App/ChatWidget.vue';
</script>

<template>
    <Navbar />
    <RouterView />
    <Footer />

    <ChatWidget />
</template>