<template>
    <main class="flex flex-col gap-8 w-full max-w-[600px] px-6 mt-20 mb-24 mx-auto">
        <div class="flex flex-col items-center justify-center text-center gap-6">
            <!-- Icon -->
            <div class="flex items-center justify-center w-[120px] h-[120px] rounded-full bg-red-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <!-- Title -->
            <div class="flex flex-col gap-2">
                <h1 class="text-4xl font-bold text-gray-900">403</h1>
                <h2 class="text-2xl font-semibold text-gray-800">Access Forbidden</h2>
            </div>

            <!-- Description -->
            <p class="text-gray-600 text-lg max-w-md">
                Sorry, you don't have permission to access this page. Please contact your administrator if you believe this is an error.
            </p>

            <!-- Actions -->
            <!-- <div class="flex items-center gap-4 mt-4">
                <button 
                    @click="$router.go(-1)"
                    class="px-6 py-3 rounded-full border-2 border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition-all duration-200"
                >
                    Go Back
                </button>
                <button 
                    @click="$router.push({ name: 'admin.dashboard' })"
                    class="px-6 py-3 rounded-full bg-blue-600 text-white font-semibold hover:bg-blue-700 transition-all duration-200 shadow-lg hover:shadow-xl"
                >
                    Go to Dashboard
                </button>
            </div> -->
        </div>
    </main>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()
</script>