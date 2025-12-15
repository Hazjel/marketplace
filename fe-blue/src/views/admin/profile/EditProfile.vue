<script setup>
import { computed, ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter()
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
const { checkAuth } = authStore

const isLoading = ref(false)
const errors = ref({})
const successMessage = ref(null)

const form = ref({
    name: '',
    current_password: '',
    password: '',
    password_confirmation: ''
})

onMounted(() => {
    if (user.value) {
        form.value.name = user.value.name
    }
})

const handleSubmit = async () => {
    isLoading.value = true
    errors.value = {}
    successMessage.value = null

    try {
        const response = await axios.put('/profile', form.value)
        successMessage.value = 'Profile updated successfully'

        // Refresh auth user data
        await checkAuth()

        // Reset password fields
        form.value.current_password = ''
        form.value.password = ''
        form.value.password_confirmation = ''

    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors
        } else {
            console.error(error)
            errors.value = {
                general: [error.response?.data?.message || 'Something went wrong']
            }
        }
    } finally {
        isLoading.value = false
    }
}
</script>

<template>
    <div class="flex flex-col gap-8 w-full">
        <div class="flex flex-col w-full bg-white rounded-[20px] p-8 gap-8">
            <div class="flex items-center gap-6">
                <div class="flex size-[100px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                    <img :src="user?.profile_picture" class="size-full object-cover" alt="profile picture">
                </div>
                <div class="flex flex-col gap-2">
                    <p class="font-bold text-xl text-custom-black capitalize">{{ user?.role }}</p>
                    <p class="text-custom-grey">Profile picture cannot be changed yet.</p>
                </div>
            </div>

            <form @submit.prevent="handleSubmit" class="flex flex-col gap-6 w-full max-w-[600px]">
                <!-- Success Message -->
                <div v-if="successMessage" class="p-4 rounded-xl bg-green-50 text-green-600 font-medium">
                    {{ successMessage }}
                </div>

                <!-- General Error -->
                <div v-if="errors.general" class="p-4 rounded-xl bg-red-50 text-custom-red font-medium">
                    {{ errors.general[0] }}
                </div>

                <div class="flex flex-col gap-3">
                    <label class="font-semibold text-custom-black">Full Name</label>
                    <div class="flex flex-col gap-2">
                        <input type="text" v-model="form.name" autocomplete="name"
                            class="flex w-full h-14 rounded-xl border border-custom-stroke px-5 bg-white focus:border-custom-blue outline-none transition-300"
                            placeholder="Enter your full name">
                        <span v-if="errors.name" class="text-custom-red text-sm">{{ errors.name[0] }}</span>
                    </div>
                </div>

                <div class="h-[1px] w-full bg-custom-stroke my-2"></div>

                <div class="flex flex-col gap-1">
                    <h3 class="font-bold text-lg">Change Password</h3>
                    <p class="text-custom-grey text-sm">Leave blank if you don't want to change password</p>
                </div>

                <div class="flex flex-col gap-3">
                    <label class="font-semibold text-custom-black">Current Password</label>
                    <div class="flex flex-col gap-2">
                        <input type="password" v-model="form.current_password" autocomplete="current-password"
                            class="flex w-full h-14 rounded-xl border border-custom-stroke px-5 bg-white focus:border-custom-blue outline-none transition-300"
                            placeholder="Required to change password">
                        <span v-if="errors.current_password" class="text-custom-red text-sm">{{
                            errors.current_password[0] }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <label class="font-semibold text-custom-black">New Password</label>
                    <div class="flex flex-col gap-2">
                        <input type="password" v-model="form.password" autocomplete="new-password"
                            class="flex w-full h-14 rounded-xl border border-custom-stroke px-5 bg-white focus:border-custom-blue outline-none transition-300"
                            placeholder="Minimum 8 characters">
                        <span v-if="errors.password" class="text-custom-red text-sm">{{ errors.password[0] }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <label class="font-semibold text-custom-black">Confirm New Password</label>
                    <div class="flex flex-col gap-2">
                        <input type="password" v-model="form.password_confirmation" autocomplete="new-password"
                            class="flex w-full h-14 rounded-xl border border-custom-stroke px-5 bg-white focus:border-custom-blue outline-none transition-300"
                            placeholder="Re-enter new password">
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" :disabled="isLoading"
                        class="flex items-center justify-center h-14 px-8 rounded-full bg-custom-blue text-white font-semibold hover:shadow-lg hover:shadow-custom-blue/30 transition-300 disabled:opacity-50 disabled:cursor-not-allowed">
                        <div v-if="isLoading"
                            class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2">
                        </div>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
