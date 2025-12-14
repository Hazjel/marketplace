<script setup lang="ts">
import Navbar from '@/components/App/Navbar.vue';
import Footer from '@/components/App/Footer.vue';
import Chatbot from '@/components/App/Chatbot.vue';
</script>

<template>
    <Navbar />
    <RouterView />
    <Chatbot />
    <Footer />

</template>