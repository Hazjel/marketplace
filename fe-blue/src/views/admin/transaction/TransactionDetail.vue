<script setup>
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { formatRupiah, formatToClientTimeZone } from '@/helpers/format';
import { useTransactionStore } from '@/stores/transaction';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import StarPointy from '@/assets/images/icons/Star-pointy.svg'
import StarPointyOutline from '@/assets/images/icons/Star-pointy-outline.svg'
import { useProductReviewStore } from '@/stores/productReview';
import { useToast } from "vue-toastification";

const route = useRoute()
const toast = useToast()

const transaction = ref({})

const transactionStore = useTransactionStore()
const authStore = useAuthStore()
const { user, activeMode } = storeToRefs(authStore)
const { loading } = storeToRefs(transactionStore)
const { fetchTransactionById, updateTransaction, checkTransactionStatus } = transactionStore

const handleCheckStatus = async () => {
    try {
        const updatedTransaction = await checkTransactionStatus(transaction.value.id)
        transaction.value = updatedTransaction
        if(updatedTransaction.payment_status === 'paid') {
            toast.success("Status pembayaran terverifikasi: PAID")
        } else {
             toast.info("Status pembayaran saat ini: " + updatedTransaction.payment_status)
        }
    } catch (error) {
         toast.error("Gagal memverifikasi status pembayaran")
    }
}

onMounted(async () => {
    await fetchData()
    initReviewForm()
    loadMidtransScript().catch(console.error)
})

const fetchData = async () => {
    try {
        const response = await fetchTransactionById(route.params.id)
        if (!response) throw new Error("Transaction not found")
        
        transaction.value = response
        transaction.value.delivery_proof_url = response.delivery_proof ? getImageUrl(response.delivery_proof) : PlaceHolder
    } catch (error) {
        console.error("Error fetching transaction:", error)
        toast.error("Gagal memuat data transaksi. Terjadi kesalahan atau data tidak ditemukan.")
    }
}



const handleUpdateData = async () => {
    try {
        const payload = {
            id: transaction.value.id,
            delivery_status: transaction.value.delivery_status,
        }

        if (transaction.value.tracking_number) {
            payload.tracking_number = transaction.value.tracking_number
        }

        if (transaction.value.delivery_proof instanceof File) {
            payload.delivery_proof = transaction.value.delivery_proof
        }

        await updateTransaction(payload)
        await fetchData()
        toast.success('Status transaksi berhasil diperbarui')
    } catch (err) {
        console.error('Update failed:', err)
        toast.error('Gagal memperbarui transaksi')
    }
}

const handleAcceptOrder = () => {
    transaction.value.delivery_proof = null
    transaction.value.delivery_status = 'processing'
    handleUpdateData()
}

const fileInput = ref(null)

const handleDeliverySubmit = () => {
    transaction.value.delivery_status = 'delivering'

    handleUpdateData()
}

const handleImageChange = (e) => {
    if (!e.target.files || !e.target.files[0]) return;
    
    const file = e.target.files[0]

    transaction.value.delivery_proof = file
    transaction.value.delivery_proof_url = URL.createObjectURL(file)
}


const getImageUrl = (path) => {
    if (!path) return PlaceHolder
    let laravelBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
    // Remove '/api' suffix and trailing slashes to get the root URL
    laravelBaseUrl = laravelBaseUrl.replace(/\/api\/?$/, '').replace(/\/+$/, '')
    
    // Ensure path doesn't start with /
    const cleanPath = path.toString().startsWith('/') ? path.substring(1) : path
    
    return `${laravelBaseUrl}/storage/${cleanPath}`
}

// Review Logic
const productReviewStore = useProductReviewStore()
const showReviewModal = ref(false)
const submittingReview = ref(false)
const reviewForm = ref({})
const isAnonymous = ref(false)

const receivingProofFile = ref(null)
const receivingProofInput = ref(null)

const initReviewForm = () => {
    if (transaction.value?.transaction_details) {
        transaction.value.transaction_details.forEach(detail => {
            if (!reviewForm.value[detail.product_id]) {
                reviewForm.value[detail.product_id] = {
                    rating: 5,
                    review: '',
                    attachments: []
                }
            }
        })
    }
}

const openReviewModal = () => {
    showReviewModal.value = true
    isAnonymous.value = false
    initReviewForm()
}

// Helper for stars
const getStarIcon = (productId, starIndex) => {
    const rating = reviewForm.value[productId]?.rating || 0
    return rating >= starIndex ? StarPointy : StarPointyOutline
}

const createBlobUrl = (file) => {
    if (!file) return ''
    return URL.createObjectURL(file)
}

const handleReviewFileChange = (event, productId) => {
    const files = Array.from(event.target.files)
    if (reviewForm.value[productId]) {
        reviewForm.value[productId].attachments = [...reviewForm.value[productId].attachments, ...files]
    }
}

const removeAttachment = (productId, index) => {
    if (reviewForm.value[productId]) {
        reviewForm.value[productId].attachments.splice(index, 1)
    }
}

const handleSubmitReview = async () => {
    submittingReview.value = true
    try {
        const promises = Object.keys(reviewForm.value)
            .filter(productId => {
                // Submit review for all products in the form
                return true
            })
            .map(productId => {
                const data = reviewForm.value[productId]
                return productReviewStore.createReview({
                    transaction_id: transaction.value.id,
                    product_id: productId,
                    rating: data.rating,
                    review: data.review,
                    is_anonymous: isAnonymous.value,
                    attachments: data.attachments || [] 
                })
            })

        await Promise.all(promises)
        showReviewModal.value = false
        await fetchData()
        toast.success('Ulasan berhasil dikirim')
    } catch (error) {
        console.error('Failed to submit reviews', error)
        // Show specific validation message if available
        if (error.response && error.response.data && error.response.data.message) {
             toast.error('Gagal mengirim ulasan: ' + error.response.data.message)
        } else {
             toast.error('Gagal mengirim ulasan. Mohon cek input Anda.')
        }
    } finally {
        submittingReview.value = false
    }
}

// Buyer Complete Order
const handleCompleteOrderClick = () => {
    receivingProofInput.value.click()
}

const handleReceivingProofChange = async (event) => {
    const file = event.target.files[0]
    if (!file) return

    try {
        await transactionStore.completeTransaction(transaction.value.id, { receiving_proof: file })
        await fetchData()
        toast.success('Pesanan diterima & diselesaikan')
    } catch (error) {
        console.error('Failed to complete order', error)
        toast.error('Gagal menyelesaikan pesanan')
    }
}

const getReviewsForProduct = (productId) => {
    return transaction.value?.product_reviews?.filter(r => r.product_id === productId) || []
}

const isProcessingPayment = ref(false)

const loadMidtransScript = () => {
    return new Promise((resolve, reject) => {
        if (window.snap) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = 'https://app.sandbox.midtrans.com/snap/snap.js';
        script.setAttribute('data-client-key', import.meta.env.VITE_MIDTRANS_CLIENT_KEY || 'YOUR_MIDTRANS_CLIENT_KEY');
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load Midtrans'));
        document.head.appendChild(script);
    });
};

const handleRepayment = () => {
    if (!transaction.value.snap_token) {
        toast.error("Tidak dapat memproses pembayaran. Token hilang.");
        return;
    }

    isProcessingPayment.value = true;
    window.snap.pay(transaction.value.snap_token, {
        onSuccess: async function (result) {
            toast.success("Pembayaran berhasil! Memverifikasi status...");
            await handleCheckStatus();
            isProcessingPayment.value = false;
        },
        onPending: function (result) {
            toast.info("Menunggu pembayaran...");
            isProcessingPayment.value = false;
        },
        onError: function (result) {
            toast.error("Pembayaran gagal.");
            isProcessingPayment.value = false;
        },
        onClose: function () {
            isProcessingPayment.value = false;
        }
    });
}

onMounted(async () => {
    await fetchData()
    initReviewForm()
    loadMidtransScript().catch(console.error)
})
</script>

<template>
    <div v-if="loading" class="flex flex-1 items-center justify-center min-h-[400px]">
        <div class="size-10 border-4 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
    </div>
    
    <div v-else-if="!transaction || !transaction.id" class="flex flex-col flex-1 items-center justify-center min-h-[400px] gap-4">
         <img src="@/assets/images/icons/bag-grey.svg" class="size-20 opacity-50" alt="Not Found">
         <p class="font-bold text-xl text-custom-grey">Transaction Not Found</p>
         <RouterLink :to="{ name: 'admin.my-transaction' }" class="text-custom-blue font-semibold hover:underline">
            Back to My Transactions
         </RouterLink>
    </div>

    <div v-else class="flex flex-col md:flex-row flex-1 gap-5">
        <div class="flex flex-col gap-5 w-full min-w-0 animate-fade-in-up">
            <div class="relative w-full rounded-[20px] bg-custom-yellow overflow-hidden"
                v-if="transaction?.delivery_status === 'pending'">
                <img src="@/assets/images/backgrounds/round-ornament.svg"
                    class="size-full object-contain object-right opacity-55 absolute" alt="icon">
                <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
                    <img src="@/assets/images/icons/timer-chocolate.svg" class="flex size-9 shrink-0" alt="icon">
                    <p class="font-bold text-lg text-[#544607]">Order pending. Kindly wait for review 🙌</p>
                </div>
            </div>
            <div class="relative w-full rounded-[20px] bg-custom-blue overflow-hidden"
                v-if="transaction?.delivery_status === 'processing'">
                <img src="@/assets/images/backgrounds/round-ornament.svg"
                    class="size-full object-contain object-right opacity-55 absolute" alt="icon">
                <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
                    <img src="@/assets/images/icons/truck-time-white-fill.svg" class="flex size-9 shrink-0" alt="icon">
                    <p class="font-bold text-lg text-white">Prepare the item for pickup by the courier</p>
                </div>
            </div>
            <div class="relative w-full rounded-[20px] bg-custom-orange overflow-hidden"
                v-if="transaction?.delivery_status === 'delivering'">
                <img src="@/assets/images/backgrounds/round-ornament.svg"
                    class="size-full object-contain object-right opacity-55 absolute" alt="icon">
                <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
                    <img src="@/assets/images/icons/truck-time-white-fill.svg" class="flex size-9 shrink-0" alt="icon">
                    <p class="font-bold text-lg text-white">The order is heading to customer address</p>
                </div>
            </div>
            <div class="relative w-full rounded-[20px] bg-custom-green overflow-hidden"
                v-if="transaction?.delivery_status === 'completed'">
                <img src="@/assets/images/backgrounds/round-ornament.svg"
                    class="size-full object-contain object-right opacity-55 absolute" alt="icon">
                <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
                    <img src="@/assets/images/icons/truck-time-white-fill.svg" class="flex size-9 shrink-0" alt="icon">
                    <p class="font-bold text-lg text-white">The order is arrived to customer address</p>
                </div>
            </div>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white">
                <p class="font-bold text-xl">Order Reviews</p>
                <div class="flex items-center gap-[14px] w-full min-w-0">
                    <div class="flex size-[92px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                        <img :src="transaction?.store?.logo" class="size-full object-cover" alt="photo">
                    </div>
                    <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                        <p class="font-bold text-[22px] leading-tight w-full truncate">
                            {{ transaction?.store?.name }}
                        </p>
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon">
                            {{ transaction?.store?.user?.name }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/shopping-cart-black.svg" class="flex size-6 shrink-0"
                                alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ transaction?.transaction_details?.length }}</p>
                            <p class="font-semibold text-custom-grey">Total Products</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/box-black.svg" class="flex size-6 shrink-0" alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{
                                transaction?.transaction_details?.reduce((total, detail) => total + detail.qty, 0)}}
                            </p>
                            <p class="font-semibold text-custom-grey">Total Quantity</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/calendar-2-black.svg" class="flex size-6 shrink-0"
                                alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ formatToClientTimeZone(transaction?.created_at)
                            }}</p>
                            <p class="font-semibold text-custom-grey">Date Transaction</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 bg-white">
                <button data-accordion-type="content" data-expand="Products" class="flex justify-between">
                    <div class="flex flex-col gap-2">
                        <p class="font-bold text-xl">Product Details</p>
                        <div class="flex items-center gap-1">
                            <img src="@/assets/images/icons/shopping-cart-grey.svg" class="flex size-6 shrink-0"
                                alt="icon">
                            <p class="font-semibold text-custom-grey">{{ transaction?.transaction_details?.length }}
                                Total Products</p>
                        </div>
                    </div>
                    <img src="@/assets/images/icons/arrow-circle-up.svg" class="size-6 flex shrink-0 transition-300"
                        alt="icon">
                </button>
                <div id="Products" class="flex flex-col gap-4 mt-5">
                    <div class="card flex flex-col rounded-2xl border border-custom-stroke p-4 gap-5"
                        v-for="product in transaction?.transaction_details">
                        <div class="flex flex-col sm:flex-row w-full gap-5">
                            <div class="flex items-center gap-[14px] w-full min-w-0 overflow-hidden">
                                <div class="flex size-[92px] rounded-2xl bg-custom-background overflow-hidden shrink-0">
                                    <img :src="product?.product?.product_images?.find(image => image.is_thumbnail)?.image ?? PlaceHolder"
                                        class="size-full object-contain" alt="thumbnail">
                                </div>
                                <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                    <p class="font-bold text-lg leading-tight w-full truncate">
                                        {{ product?.product?.name }}
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                        <img src="@/assets/images/icons/bag-grey.svg" class="size-5" alt="icon">
                                        {{ product?.product?.product_category?.name }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-row sm:flex-col gap-2 shrink-0 justify-between sm:justify-end sm:text-right w-full sm:w-auto mt-2 sm:mt-0 ml-[106px] sm:ml-0">
                                <p class="font-bold text-custom-blue">Rp {{ formatRupiah(product?.product?.price) }}</p>
                                <p class="font-semibold leading-none text-custom-grey">{{ product.qty }}</p>
                            </div>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                <img src="@/assets/images/icons/shopping-cart-grey.svg" class="size-5" alt="icon">
                                Subtotal
                            </p>
                            <p class="font-bold text-lg text-custom-blue">Rp {{ formatRupiah(product.subtotal) }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="flex flex-col gap-5 w-full md:w-[470px] shrink-0 animate-fade-in-up delay-200">
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white">
                <p class="font-bold text-xl">Customer Details</p>
                <div class="flex items-center gap-[10px] w-full min-w-0">
                    <div class="flex size-[92px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                        <img :src="transaction?.buyer?.user?.profile_picture" class="size-full object-cover"
                            alt="photo">
                    </div>
                    <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                        <p class="font-bold text-[22px] leading-tight w-full truncate">
                            {{ transaction?.buyer?.user?.name }}
                        </p>
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/call-grey.svg" class="size-5" alt="icon">
                            {{ transaction?.buyer?.user?.buyer?.phone_number ?? transaction?.buyer?.phone_number }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/sms-black.svg" class="flex size-6 shrink-0" alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ transaction?.buyer?.user?.email }}</p>
                            <p class="font-semibold text-custom-grey">Email Address</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/buildings-black.svg" class="flex size-6 shrink-0"
                                alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ transaction?.city }}</p>
                            <p class="font-semibold text-custom-grey">City Location</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/routing-black.svg" class="flex size-6 shrink-0" alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ transaction?.address }}</p>
                            <p class="font-semibold text-custom-grey">Street Address</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/keyboard-black.svg" class="flex size-6 shrink-0" alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ transaction?.postal_code }}</p>
                            <p class="font-semibold text-custom-grey">Post Code</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white">
                <p class="font-bold text-xl">Transaction Details</p>
                <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-4">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/bag-grey.svg" class="size-6" alt="icon">
                            Subtotal
                        </p>
                        <p class="font-bold text-lg leading-none">Rp {{
                            formatRupiah(transaction?.transaction_details?.reduce((total, detail) => total +
                                detail.subtotal, 0))}}</p>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/car-delivery-grey.svg" class="size-6" alt="icon">
                            Delivery Fee
                        </p>
                        <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(transaction?.shipping_cost) }}</p>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/receipt-2-grey.svg" class="size-6" alt="icon">
                            PPN 11%
                        </p>
                        <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(transaction?.tax) }}</p>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/discount-shape-grey.svg" class="size-6" alt="icon">
                            Discount
                        </p>
                        <p class="font-bold text-lg leading-none">Rp 0</p>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/money-grey.svg" class="size-6" alt="icon">
                            Grand Total
                        </p>
                        <p class="font-bold text-lg leading-none text-custom-blue">Rp {{
                            formatRupiah(transaction?.grand_total) }}</p>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <hr class="border-custom-stroke last:hidden">
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/money-grey.svg" class="size-6" alt="icon">
                            Payment Status
                        </p>
                        <p class="font-bold text-lg leading-none text-custom-blue"> {{ transaction?.payment_status }}
                        </p>
                    </div>

                    <!-- Repayment Button -->
                    <button v-if="activeMode === 'buyer' && transaction?.payment_status === 'unpaid'" 
                        @click="handleRepayment"
                        :disabled="isProcessingPayment"
                        class="flex items-center justify-center h-12 w-full rounded-2xl bg-custom-blue disabled:bg-custom-stroke transition-300">
                        <span class="font-bold text-white">{{ isProcessingPayment ? 'Processing...' : 'Pay Now' }}</span>
                    </button>
                    

                </div>
                    <hr class="border-custom-stroke last:hidden">
                </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white"
                v-if="transaction?.delivery_status === 'pending'">
                <p class="font-bold text-xl">Order Status</p>
                <div class="grid grid-cols-3 relative min-h-[90px] w-full">
                    <div id="Progress-Bar"
                        class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke overflow-hidden">
                        <div class="w-1/3 h-full bg-custom-lime-green"></div>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
                            <span class="font-bold">1</span>
                        </div>
                        <p class="font-bold text-center">Book Review</p>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-stroke overflow-hidden items-center justify-center">
                            <span class="font-bold">2</span>
                        </div>
                        <p class="font-bold text-center">Processing</p>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-stroke overflow-hidden items-center justify-center">
                            <span class="font-bold">?</span>
                        </div>
                        <p class="font-bold text-center">2+ More</p>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                        <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon">
                        Delivery Status
                    </p>
                    <p
                        class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-yellow text-[#544607]">
                        pending
                    </p>
                </div>
                <div class="flex flex-col text-center gap-4"
                    v-if="transaction?.payment_status === 'paid' && activeMode === 'store' && user?.store?.id == transaction?.store?.id">
                    <button @click="handleAcceptOrder"
                        class="h-14 w-full rounded-full flex items-center justify-center py-4 px-6 bg-custom-blue disabled:bg-custom-stroke transition-300">
                        <span class="font-semibold text-lg text-white">Accept Order</span>
                    </button>
                    <div class="flex items-center justify-center gap-[6px]">
                        <p class="font-semibold text-custom-grey">Why can't I decline the order?</p>
                        <img src="@/assets/images/icons/info-circle-grey.svg" class="size-[18px]" alt="icon">
                    </div>
                </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white"
                v-if="transaction?.delivery_status === 'processing'">
                <p class="font-bold text-xl">Order Status</p>
                <div class="grid grid-cols-3 relative min-h-[90px] w-full">
                    <div id="Progress-Bar"
                        class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke overflow-hidden">
                        <div class="w-2/3 h-full bg-custom-lime-green"></div>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
                            <span class="font-bold">1</span>
                        </div>
                        <p class="font-bold text-center">Book Review</p>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
                            <span class="font-bold">2</span>
                        </div>
                        <p class="font-bold text-center">Processing</p>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-stroke overflow-hidden items-center justify-center">
                            <span class="font-bold">?</span>
                        </div>
                        <p class="font-bold text-center">2+ More</p>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                        <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon">
                        Delivery Status
                    </p>
                    <p
                        class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-blue/10 text-custom-blue">
                        processing
                    </p>
                </div>
                <template v-if="activeMode === 'store' && user?.store?.id === transaction?.store?.id">
                    <div class="flex items-center justify-between w-full">
                        <div
                            class="group relative flex size-[100px] rounded-2xl overflow-hidden items-center justify-center bg-custom-background">
                            <img id="Thumbnail" :src="transaction.delivery_proof_url"
                                data-default="@/assets/images/icons/gallery-default.svg"
                                class="size-full object-contain" alt="icon" />
                            <input type="file" id="File-Input" accept="image/*" ref="fileInput"
                                class="absolute inset-0 opacity-0 cursor-pointer" @change="handleImageChange" />
                        </div>
                        <button type="button" id="Add-Photo" @click="fileInput.click()"
                            class="flex items-center justify-center rounded-2xl py-4 px-6 bg-custom-black text-white font-semibold text-lg">
                            Add Photo
                        </button>
                    </div>
                    <div class="flex flex-col gap-3">
                        <p class="font-semibold text-custom-grey">Tracking Number</p>
                        <div class="group/errorState flex flex-col gap-2">
                            <label class="group relative">
                                <div class="input-icon">
                                    <img src="@/assets/images/icons/barcode-grey.svg" class="flex size-6 shrink-0"
                                        alt="icon">
                                </div>
                                <p class="input-placeholder">
                                    Enter Tracking Number
                                </p>
                                <input type="string" id="Tracking" class="custom-input" placeholder=""
                                    v-model="transaction.tracking_number">
                            </label>
                            <span class="input-error">Lorem dolor error message here</span>
                        </div>
                    </div>
                    <button type="submit" id="Update-Status"
                        class="h-14 w-full rounded-full flex items-center justify-center py-4 px-6 bg-custom-blue disabled:bg-custom-stroke transition-300"
                        @click="handleDeliverySubmit">
                        <span class="font-semibold text-lg text-white">Update Status</span>
                    </button>
                </template>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white"
                v-if="transaction?.delivery_status === 'delivering'">
                <p class="font-bold text-xl">Order Status</p>
                <div class="grid grid-cols-3 relative min-h-[90px] w-full">
                    <div id="Progress-Bar"
                        class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke overflow-hidden">
                        <div class="w-2/3 h-full bg-custom-lime-green"></div>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
                            <span class="font-bold">2</span>
                        </div>
                        <p class="font-bold text-center">Processing</p>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
                            <span class="font-bold">3</span>
                        </div>
                        <p class="font-bold text-center">Delivering</p>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-stroke overflow-hidden items-center justify-center">
                            <span class="font-bold">4</span>
                        </div>
                        <p class="font-bold text-center">Completed</p>
                    </div>
                </div>
                <div class="h-[260px] w-full rounded-2xl overflow-hidden bg-custom-background">
                    <img :src="transaction?.delivery_proof_url || PlaceHolder" class="size-full object-cover" alt="thumbnail"
                        @error="(e) => e.target.src = PlaceHolder">
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex flex-col gap-2">
                        <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                            <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon">
                            Delivery Status
                        </p>
                        <p
                            class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-orange/10 text-custom-orange w-fit">
                            Delivering
                        </p>
                    </div>

                    <input type="file" ref="receivingProofInput" class="hidden" accept="image/*"
                        @change="handleReceivingProofChange">

                    <button v-if="activeMode === 'buyer'" @click="handleCompleteOrderClick"
                        class="flex items-center justify-center h-12 px-6 rounded-full bg-custom-blue text-white font-semibold shadow-lg hover:bg-blue-600 transition-300">
                        Order Received
                    </button>
                </div>
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                        <img src="@/assets/images/icons/routing-grey.svg" class="size-6" alt="icon">
                        Tracking Number
                    </p>
                    <p class="font-semibold text-lg leading-none">{{ transaction?.tracking_number }}({{
                        transaction?.shipping }})</p>
                </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white"
                v-if="transaction?.delivery_status === 'completed'">
                <p class="font-bold text-xl">Order Status</p>
                <div class="grid grid-cols-3 relative min-h-[90px] w-full">
                    <div id="Progress-Bar"
                        class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke overflow-hidden">
                        <div class="w-full h-full bg-custom-lime-green"></div>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
                            <span class="font-bold">2</span>
                        </div>
                        <p class="font-bold text-center">Processing</p>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
                            <span class="font-bold">3</span>
                        </div>
                        <p class="font-bold text-center">Delivering</p>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
                            <span class="font-bold">4</span>
                        </div>
                        <p class="font-bold text-center">Completed</p>
                    </div>
                </div>
                <div class="h-[260px] w-full rounded-2xl overflow-hidden bg-custom-background">
                    <img :src="transaction?.delivery_proof_url || PlaceHolder" class="size-full object-cover" alt="thumbnail"
                        @error="(e) => e.target.src = PlaceHolder">
                </div>
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                        <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon">
                        Delivery Status
                    </p>
                    <p
                        class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-green/10 text-custom-green">
                        completed
                    </p>
                </div>
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                        <img src="@/assets/images/icons/routing-grey.svg" class="size-6" alt="icon">
                        Tracking Number
                    </p>
                    <p class="font-semibold text-lg leading-none">{{ transaction?.tracking_number }} </p>
                </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white">
                <p class="font-bold text-xl">Customer Reviews</p>

                <div v-if="transaction?.product_reviews?.length > 0"
                    class="flex flex-col gap-4">
                    <div v-for="detail in transaction.transaction_details" :key="detail.id">
                        <div v-for="review in getReviewsForProduct(detail.product_id)" :key="review.id"
                            class="flex flex-col rounded-2xl border border-custom-stroke p-4 gap-4">
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <p class="font-bold text-lg">{{ detail.product.name }}</p>
                                    <div class="flex items-center gap-2">
                                        <p class="font-bold tracking-tight text-xl leading-none">
                                            <span class="text-[32px]">{{ review.rating }}.0</span>/5.0
                                        </p>
                                        <div class="flex">
                                            <template v-for="i in 5" :key="i">
                                                <img v-if="i <= review.rating"
                                                    src="@/assets/images/icons/Star-pointy.svg"
                                                    class="flex size-8 shrink-0 p-0.5" alt="star">
                                                <img v-else src="@/assets/images/icons/Star-pointy-outline.svg"
                                                    class="flex size-8 shrink-0 p-0.5" alt="star">
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr class="border-custom-stroke">
                            <p class="font-medium text-lg text-custom-grey">“{{ review.review }}”</p>
                        </div>
                    </div>
                </div>

                <div v-else class="flex flex-col items-center justify-center py-8 gap-4">
                    <p class="font-medium text-custom-grey"
                        v-if="activeMode === 'buyer' && transaction?.delivery_status === 'completed'">
                        You haven't reviewed this order yet.
                    </p>
                    <p class="font-medium text-custom-grey" v-else>
                        No reviews yet.
                    </p>

                    <button v-if="activeMode === 'buyer' && transaction?.delivery_status === 'completed'"
                        @click="openReviewModal"
                        class="flex items-center justify-center h-12 px-6 rounded-full bg-custom-blue text-white font-semibold">
                        Write a Review
                    </button>
                </div>
            </section>
        </div>
    </div>

    <!-- Review Modal -->
    <div v-if="showReviewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-[20px] p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <p class="font-bold text-2xl">Write a Review</p>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" v-model="isAnonymous" class="w-5 h-5 rounded border-custom-stroke text-custom-blue focus:ring-custom-blue">
                        <span class="font-medium text-custom-grey">Hide my name</span>
                    </label>
                    <button @click="showReviewModal = false">
                        <img src="@/assets/images/icons/close-circle-black.svg" class="size-8" alt="close">
                    </button>
                </div>
            </div>

            <form @submit.prevent="handleSubmitReview" class="flex flex-col gap-6">
                <div v-for="(detail, index) in transaction.transaction_details" :key="detail.id"
                    class="flex flex-col gap-4">
                    <div class="flex items-center gap-4 bg-custom-background p-3 rounded-xl">
                        <img :src="detail.product?.product_images?.find(i => i.is_thumbnail)?.image || PlaceHolder"
                            class="size-16 object-cover rounded-lg">
                        <p class="font-bold text-lg">{{ detail.product.name }}</p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <p class="font-semibold">Rating</p>
                        <div class="flex gap-2">
                            <button type="button" v-for="star in 5" :key="star"
                                @click="reviewForm[detail.product_id].rating = star">
                                <img :src="getStarIcon(detail.product_id, star)" class="size-8 cursor-pointer">
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <p class="font-semibold">Review</p>
                        <textarea v-model="reviewForm[detail.product_id].review"
                            class="w-full rounded-xl border border-custom-stroke p-4 focus:outline-none focus:border-custom-blue"
                            rows="3" placeholder="How was the product?"></textarea>
                    </div>

                    <div class="flex flex-col gap-2">
                        <p class="font-semibold">Add Photos/Videos</p>
                        <div class="flex flex-wrap gap-2">
                             <div v-for="(file, fIndex) in reviewForm[detail.product_id]?.attachments || []" :key="fIndex" 
                                class="relative rounded-lg overflow-hidden border border-custom-stroke bg-custom-background shrink-0"
                                style="width: 80px; height: 80px;">
                                <img v-if="file.type.startsWith('image/')" :src="createBlobUrl(file)" class="w-full h-full object-cover">
                                <video v-else :src="createBlobUrl(file)" class="w-full h-full object-cover"></video>
                                <button type="button" @click="removeAttachment(detail.product_id, fIndex)" 
                                    class="absolute top-1 right-1 bg-white/80 rounded-full p-0.5 hover:bg-white">
                                    <img src="@/assets/images/icons/close-circle-black.svg" class="size-4">
                                </button>
                             </div>
                             
                             <label class="flex flex-col items-center justify-center rounded-lg border border-dashed border-custom-stroke cursor-pointer hover:border-custom-blue hover:bg-custom-blue/5 transition-all shrink-0"
                                style="width: 80px; height: 80px;">
                                <input type="file" class="hidden" accept="image/*,video/*" multiple @change="(e) => handleReviewFileChange(e, detail.product_id)">
                                <span class="text-2xl text-custom-grey">+</span>
                             </label>
                        </div>
                    </div>

                    <hr class="border-custom-stroke" v-if="index < transaction.transaction_details.length - 1">
                </div>

                <button type="submit"
                    class="h-14 w-full rounded-full bg-custom-blue text-white font-bold text-lg hover:shadow-lg transition-all"
                    :disabled="submittingReview">
                    {{ submittingReview ? 'Submitting...' : 'Submit Reviews' }}
                </button>
            </form>
        </div>
    </div>
</template>