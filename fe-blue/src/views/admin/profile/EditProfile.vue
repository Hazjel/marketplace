<script setup>
import { ref, onMounted, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';

import { axiosInstance as axios } from '@/plugins/axios';


const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
const { checkAuth } = authStore

const isLoading = ref(false)
const errors = ref({})
const successMessage = ref(null)

const fileInput = ref(null)
const previewImage = ref(null)
const selectedFile = ref(null)

const form = ref({
    name: '',
    phone_number: '',
    current_password: '',
    password: '',
    password_confirmation: ''
})

const populateForm = () => {

    if (user.value) {
        form.value.name = user.value.name
        // Populate phone number based on role
        if (user.value.role === 'buyer' && user.value.buyer) {

            form.value.phone_number = String(user.value.buyer?.phone_number || '')
        } else if (user.value.role === 'store' && user.value.store) {
            form.value.phone_number = String(user.value.store?.phone || '')
        }
    }
}

onMounted(async () => {
    // If user is missing, OR if user is buyer/store but missing relation data, forcing a refresh
    if (!user.value || 
        (user.value.role === 'buyer' && !user.value.buyer) || 
        (user.value.role === 'store' && !user.value.store)) {

        await checkAuth()
    }
    populateForm()
})

watch(user, () => {
    populateForm()
})

const triggerFileInput = () => {
    fileInput.value.click()
}

const handleFileChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        selectedFile.value = file
        previewImage.value = URL.createObjectURL(file)
    }
}

const handleSubmit = async () => {
    isLoading.value = true
    errors.value = {}
    successMessage.value = null

    try {
        const formData = new FormData()
        formData.append('_method', 'PUT') // Method spoofing for Laravel
        formData.append('name', form.value.name)
        
        if (form.value.phone_number) {
            formData.append('phone_number', form.value.phone_number)
        }

        if (selectedFile.value) {
            formData.append('profile_picture', selectedFile.value)
        }

        if (form.value.current_password) {
            formData.append('current_password', form.value.current_password)
        }

        if (form.value.password) {
            formData.append('password', form.value.password)
            formData.append('password_confirmation', form.value.password_confirmation)
        }

        // Use POST with _method: PUT for file upload support
        const response = await axios.post('/profile', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })

        successMessage.value = 'Profile updated successfully'

        // Refresh auth user data
        await checkAuth()

        // Reset password fields
        form.value.current_password = ''
        form.value.password = ''
        form.value.password_confirmation = ''

        // Clear file selection
        selectedFile.value = null
        // keep previewImage as it is now the current user image, or reset it? 
        // Better to reset preview and show default user image from store (which is updated)
        previewImage.value = null

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
    <div v-if="user" class="flex flex-col gap-8 w-full">
        <div class="flex flex-col w-full bg-white rounded-3xl p-6 gap-8">
            <div class="flex items-center gap-6">
                <div @click="triggerFileInput"
                    class="group relative flex size-[100px] shrink-0 rounded-full bg-custom-background overflow-hidden cursor-pointer">
                    <img :src="previewImage || user?.profile_picture"
                        class="size-full object-cover transition-opacity duration-300 group-hover:opacity-75"
                        alt="profile picture">

                    <!-- Hover Overlay -->
                    <div
                        class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-black/30">
                        <img src="@/assets/images/icons/camera-white.svg" class="size-8" alt="change">
                    </div>
                </div>

                <input type="file" ref="fileInput" @change="handleFileChange" accept="image/*" class="hidden">

                <div class="flex flex-col gap-2">
                    <p class="font-bold text-xl text-custom-black capitalize">{{ user?.role || '&nbsp;' }}</p>
                    <p class="text-custom-grey">Click image to change profile picture.</p>
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
                    <p class="font-semibold text-custom-grey">Full Name</p>
                    <div class="group/errorState flex flex-col gap-2" :class="{ 'invalid': errors?.name }">
                        <label class="group relative">
                            <div class="input-icon">
                                <img src="@/assets/images/icons/profile-circle-grey.svg" class="flex size-6 shrink-0"
                                    alt="icon">
                            </div>
                            <p class="input-placeholder">
                                Enter your full name
                            </p>
                            <input type="text" v-model="form.name" autocomplete="name" class="custom-input"
                                placeholder="">
                        </label>
                        <span class="input-error" v-if="errors.name">{{ errors.name[0] }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <p class="font-semibold text-custom-grey">Phone Number</p>
                    <div class="group/errorState flex flex-col gap-2" :class="{ 'invalid': errors?.phone_number }">
                        <label class="group relative">
                            <div class="input-icon">
                                <img src="@/assets/images/icons/call-grey.svg" class="flex size-6 shrink-0"
                                    alt="icon">
                            </div>
                            <p class="input-placeholder">
                                Enter your phone number
                            </p>
                            <input type="tel" v-model="form.phone_number" autocomplete="tel" class="custom-input"
                                placeholder="" @input="form.phone_number = form.phone_number.replace(/[^0-9]/g, '').slice(0, 15)">
                        </label>
                        <span class="input-error" v-if="errors.phone_number">{{ errors.phone_number[0] }}</span>
                         <span class="input-error" v-else-if="form.phone_number && !form.phone_number.startsWith('08')">Phone number must start with 08</span>
                    </div>
                </div>

                <div class="h-[1px] w-full bg-custom-stroke my-2"></div>

                <div class="flex flex-col gap-1">
                    <h3 class="font-bold text-lg">Change Password</h3>
                    <p class="text-custom-grey text-sm">Leave blank if you don't want to change password</p>
                </div>

                <div class="flex flex-col gap-3">
                    <p class="font-semibold text-custom-grey">Current Password</p>
                    <div class="group/errorState flex flex-col gap-2" :class="{ 'invalid': errors?.current_password }">
                        <label class="group relative">
                            <div class="input-icon">
                                <img src="@/assets/images/icons/key-grey.svg" class="flex size-6 shrink-0" alt="icon">
                            </div>
                            <p class="input-placeholder">
                                Required to change password
                            </p>
                            <input type="password" v-model="form.current_password" autocomplete="current-password"
                                class="custom-input" placeholder="">
                        </label>
                        <span class="input-error" v-if="errors.current_password">{{ errors.current_password[0] }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <p class="font-semibold text-custom-grey">New Password</p>
                    <div class="group/errorState flex flex-col gap-2" :class="{ 'invalid': errors?.password }">
                        <label class="group relative">
                            <div class="input-icon">
                                <img src="@/assets/images/icons/key-grey.svg" class="flex size-6 shrink-0" alt="icon">
                            </div>
                            <p class="input-placeholder">
                                Minimum 8 characters
                            </p>
                            <input type="password" v-model="form.password" autocomplete="new-password"
                                class="custom-input" placeholder="">
                        </label>
                        <span class="input-error" v-if="errors.password">{{ errors.password[0] }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <p class="font-semibold text-custom-grey">Confirm New Password</p>
                    <div class="group/errorState flex flex-col gap-2">
                        <label class="group relative">
                            <div class="input-icon">
                                <img src="@/assets/images/icons/key-grey.svg" class="flex size-6 shrink-0" alt="icon">
                            </div>
                            <p class="input-placeholder">
                                Re-enter new password
                            </p>
                            <input type="password" v-model="form.password_confirmation" autocomplete="new-password"
                                class="custom-input" placeholder="">
                        </label>
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
