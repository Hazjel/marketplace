<script setup>
import router from '@/router';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { onMounted, ref } from 'vue';

const authStore = useAuthStore()
const { loading, error } = storeToRefs(authStore)
const { login } = authStore

const rememberMe = ref(false);

const form = ref({
    email: null,
    password: null
});

const handleSubmit = async () => {
    const response = await login(form.value)

    // Cek apakah login berhasil (response ada dan memiliki data)
    if (!response) {
        // Login gagal, error sudah di-set oleh store
        if (error.value === 'Unauthorized') {
            error.value = {
                email: ['Email atau password salah']
            }
        }
        return // Stop eksekusi
    }

    if (rememberMe.value) {
        localStorage.setItem('remembered_email', form.value.email);
        localStorage.setItem('remembered_password', form.value.password);
    } else {
        localStorage.removeItem('remembered_email');
        localStorage.removeItem('remembered_password');
    }

    // Login berhasil, redirect berdasarkan role
    if (response.role === 'admin') {
        router.push({ name: 'admin.dashboard' })
    } else {
        router.push({
            name: 'user.dashboard',
            params: { username: response.username }
        })
    }
}

onMounted(() => {
    const savedEmail = localStorage.getItem('remembered_email');
    const savedPassword = localStorage.getItem('remembered_password');

    if (savedEmail && savedPassword) {
        form.value.email = savedEmail;
        form.value.password = savedPassword;
        rememberMe.value = true;
    }
});
</script>

<template>
    <form @submit.prevent="handleSubmit" autocomplete="off"
        class="flex flex-col w-[560px] h-full max-h-[772px] shrink-0 justify-center rounded-3xl gap-10 p-6 bg-white">
        <img src="@/assets/images/logos/logo.svg" class="h-[37px] mx-auto" alt="logo">
        <div class="flex flex-col gap-[30px]">
            <div class="flex flex-col gap-3 text-center">
                <p class="font-bold text-2xl capitalize">Hey🙌🏻, Welcome Back!</p>
                <p class="font-medium text-custom-grey">Login to your account to continue!</p>
            </div>
            <div class="flex flex-col gap-4 w-full">
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
                    <p class="font-semibold text-custom-grey">Password</p>
                    <div class="group/errorState flex flex-col gap-2" :class="{ 'invalid': error?.password }">
                        <label class="group relative">
                            <div class="input-icon">
                                <img src="@/assets/images/icons/key-grey.svg" class="flex size-6 shrink-0" alt="icon">
                            </div>
                            <p class="input-placeholder">
                                Enter Your Password
                            </p>
                            <input id="passwordInput" type="password" class="custom-input tracking-[0.3em]"
                                placeholder="" v-model="form.password" autocomplete="new-password">
                        </label>
                        <span class="input-error" v-if="error?.password">{{ error?.password?.join(', ') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="group flex items-center gap-1 relative">
                            <input type="checkbox" name="remember" autocomplete="off" v-model="rememberMe"
                                class="-z-10 absolute">
                            <div class="flex size-6 overflow-hidden relative">
                                <img src="@/assets/images/icons/checkbox-unchecked.svg"
                                    class="size-full object-contain absolute group-has-[:checked]:opacity-0 transition-300"
                                    alt="icon">
                                <img src="@/assets/images/icons/checkbox.svg"
                                    class="size-full object-contain absolute opacity-0 group-has-[:checked]:opacity-100 transition-300"
                                    alt="icon">
                            </div>
                            <span
                                class="font-semibold text-custom-grey leading-none group-has-[:checked]:text-custom-blue transition-300">Remember
                                me</span>
                        </label>
                        <a href="#"
                            class="font-semibold text-custom-grey hover:text-custom-blue hover:underline transition-300">Reset
                            Password</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex flex-col gap-3">
            <button type="submit"
                class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-[10px] bg-custom-blue font-semibold capitalize text-white">
                Sign In
            </button>
            <p class="font-medium text-custom-grey text-center">
                Don't have account?
                <RouterLink :to="{ name: 'auth.register' }"
                    class="font-semibold text-custom-blue hover:underline transition-300">
                    Create Account
                </RouterLink>
            </p>
        </div>
    </form>
</template>