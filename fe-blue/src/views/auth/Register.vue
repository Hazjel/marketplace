<script setup>
import router from '@/router';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { ref } from 'vue';
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'

const authStore = useAuthStore()
const { loading, error } = storeToRefs(authStore)
const { register } = authStore

const form = ref({
    profile_picture: null,
    profile_picture_url: PlaceHolder,
    email: null,
    phone_number: null,
    password: null,
    role: null,
})

const handleImageChange = (e) => {
    const file = e.target.files[0]

    form.value.profile_picture = file
    form.value.profile_picture_url = URL.createObjectURL(file)
}

const handleSubmit = async () => {
    const formData = new FormData()

    if (form.value.profile_picture) {
        formData.append('profile_picture', form.value.profile_picture)
    }
    formData.append('name', form.value.name)
    formData.append('email', form.value.email)
    formData.append('phone_number', form.value.phone_number)
    formData.append('password', form.value.password)
    formData.append('role', form.value.role)

    await register(formData)

    if (form.value.role === 'store') {
        router.push({ name: 'admin.dashboard' })
    } else {
        router.push({ name: 'app.home' })
    }
}
</script>

<template>
    <form @submit.prevent="handleSubmit" autocomplete="off"
        class="flex flex-col w-[560px] h-fit shrink-0 justify-center rounded-3xl gap-10 p-6 bg-white">
        <img src="@/assets/images/logos/logo.svg" class="h-[37px] mx-auto" alt="logo">
        <div class="flex flex-col gap-[30px]">
            <div class="flex flex-col gap-3 text-center">
                <p class="font-bold text-2xl capitalize">Hey 🙌🏻, Welcome Aboard!</p>
                <p class="font-medium text-custom-grey">Create Account to continue!</p>
            </div>
            <div class="flex flex-col gap-4 w-full">
                <div class="flex flex-col gap-3">
                    <p class="font-semibold text-custom-grey">Profile Picture</p>
                    <div class="flex items-center justify-between">
                        <div
                            class="group relative flex size-[100px] rounded-full overflow-hidden items-center justify-center bg-custom-background">
                            <img id="Thumbnail" :src="form.profile_picture_url"
                                data-default="@/assets/images/icons/photo-profile-default.svg"
                                class="size-full object-cover" alt="icon" />
                        </div>
                        <label
                            class="relative flex rounded-2xl py-4 px-6 bg-custom-black h-[56px] gap-[10px] font-medium text-white text-nowrap cursor-pointer">
                            <input type="file" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer"
                                @change="handleImageChange" />
                            <img src="@/assets/images/icons/send-square-grey.svg" class="flex size-6 shrink-0"
                                alt="icon">
                            Add Photo
                        </label>
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    <p class="font-semibold text-custom-grey">Account Type</p>
                    <div class="grid grid-cols-2 gap-6 h-[76px]">
                        <label
                            class="group relative flex items-center h-full py-4 px-5 gap-4 rounded-[18px] border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full">
                            <div
                                class="flex h-[28px] shrink-0 items-center pr-4 pl-1 border-r-[1.5px] border-custom-border ">
                                <img src="@/assets/images/icons/shop-grey.svg" class="flex size-6 shrink-0" alt="icon">
                            </div>
                            <p class="font-bold leading-none w-full">
                                Seller
                            </p>
                            <div
                                class="flex size-4 shrink-0 rounded-full ring-2 ring-custom-grey border-[3px] border-white group-has-[:checked]:bg-custom-blue group-has-[:checked]:ring-custom-blue transition-300">
                            </div>
                            <input type="radio" name="type" value="store" id="" class="absolute opacity-0"
                                v-model="form.role">
                        </label>
                        <label
                            class="group relative flex items-center h-full py-4 px-5 gap-4 rounded-[18px] border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full">
                            <div
                                class="flex h-[28px] shrink-0 items-center pr-4 pl-1 border-r-[1.5px] border-custom-border ">
                                <img src="@/assets/images/icons/bag-2-grey.svg" class="flex size-6 shrink-0" alt="icon">
                            </div>
                            <p class="font-bold leading-none w-full">
                                Buyer
                            </p>
                            <div
                                class="flex size-4 shrink-0 rounded-full ring-2 ring-custom-grey border-[3px] border-white group-has-[:checked]:bg-custom-blue group-has-[:checked]:ring-custom-blue transition-300">
                            </div>
                            <input type="radio" name="type" value="buyer" id="" class="absolute opacity-0"
                                v-model="form.role">
                        </label>
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    <p class="font-semibold text-custom-grey">Complete Name</p>
                    <div class="group/errorState flex flex-col gap-2" :class="{ 'invalid': error?.name }">
                        <label class="group relative">
                            <div class="input-icon">
                                <img src="@/assets/images/icons/profile-circle-grey.svg" class="flex size-6 shrink-0"
                                    alt="icon">
                            </div>
                            <p class="input-placeholder">
                                Enter Your Full Name
                            </p>
                            <input type="text" class="custom-input" placeholder="" v-model="form.name"
                                autocomplete="off">
                        </label>
                        <span class="input-error" v-if="error?.name">{{ error?.name?.join(', ') }}</span>
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    <p class="font-semibold text-custom-grey">Email Address</p>
                    <div class="group/errorState flex flex-col gap-2" :class="{ 'invalid': error?.email }">
                        <label class="group relative">
                            <div class="input-icon">
                                <img src="@/assets/images/icons/sms-grey.svg" class="flex size-6 shrink-0" alt="icon">
                            </div>
                            <p class="input-placeholder">
                                Enter Your Email
                            </p>
                            <input type="email" class="custom-input" placeholder="" v-model="form.email"
                                autocomplete="off">
                        </label>
                        <span class="input-error" v-if="error?.email">{{ error?.email?.join(', ') }}</span>
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    <p class="font-semibold text-custom-grey">Phone Number</p>
                    <div class="group/errorState flex flex-col gap-2" :class="{ 'invalid': error?.phone_number }">
                        <label class="group relative">
                            <div class="input-icon">
                                <img src="@/assets/images/icons/call-grey.svg" class="flex size-6 shrink-0" alt="icon">
                            </div>
                            <p class="input-placeholder">
                                Enter Your Phone Number
                            </p>
                            <input type="tel" class="custom-input" placeholder="" v-model="form.phone_number"
                                autocomplete="off">
                        </label>
                        <span class="input-error" v-if="error?.phone_number">{{ error?.phone_number?.join(', ')
                        }}</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-3">
                <p class="font-semibold text-custom-grey">Password</p>
                <div class="group/errorState flex flex-col gap-2" :class="{ 'invalid': error?.password }">
                    <label class="group relative">
                        <div class="input-icon">
                            <img src="@/assets/images/icons/key-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        </div>
                        <p class="input-placeholder">
                            Enter Your Password
                        </p>
                        <input id="passwordInput" type="password" class="custom-input tracking-[0.3em]" placeholder=""
                            v-model="form.password" autocomplete="new-password">
                    </label>
                    <span class="input-error" v-if="error?.password">{{ error?.password?.join(', ') }}</span>
                </div>
            </div>
        </div>
        <div class="flex flex-col gap-3">
            <button type="submit"
                class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-[10px] bg-custom-blue font-semibold capitalize text-white">
                Create Account
            </button>
            <p class="font-medium text-custom-grey text-center">
                Already have account?
                <RouterLink :to="{ name: 'auth.login' }"
                    class="font-semibold text-custom-blue hover:underline transition-300">
                    Sign In
                </RouterLink>
            </p>
        </div>
    </form>
</template>