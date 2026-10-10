<script setup>
import { computed, nextTick, onUnmounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { DateTime } from 'luxon'
import { formatRupiah, formatToClientTimeZone } from '@/helpers/format'
import { handleError } from '@/helpers/errorHelper'
import { useTransactionStore } from '@/stores/transaction'
import { COMPLAINT_REASONS, resolveComplaintStatus } from '@/composables/useTransactionStatus'

// Buyer complaint on a delivering order. The buyer files and can withdraw it,
// the order's seller accepts (full refund, no restock) or rejects within 2
// days, then an admin decides escalated ones. Limits mirror api-blue
// ComplaintController.
const props = defineProps({
  transaction: { type: Object, required: true },
  isBuyer: { type: Boolean, default: false },
  isSeller: { type: Boolean, default: false },
  isAdmin: { type: Boolean, default: false }
})

const emit = defineEmits(['updated'])

const MAX_PHOTOS = 3
const MAX_PHOTO_BYTES = 2 * 1024 * 1024
const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp']

const toast = useToast()
const transactionStore = useTransactionStore()

const complaint = computed(() => props.transaction.complaint)
const status = computed(() => resolveComplaintStatus(complaint.value))
const isActive = computed(() => ['open', 'escalated'].includes(complaint.value?.status))
const canComplain = computed(
  () =>
    props.isBuyer &&
    !complaint.value &&
    props.transaction.delivery_status === 'delivering' &&
    props.transaction.payment_status === 'paid'
)

// "12 Okt 2026, 14:30" in the viewer's time zone.
const deadline = computed(() =>
  complaint.value?.deadline_at
    ? DateTime.fromISO(complaint.value.deadline_at).setLocale('id').toFormat('d LLL yyyy, HH:mm')
    : ''
)

// Remaining time, refreshed once a minute.
const now = ref(Date.now())
const clock = setInterval(() => (now.value = Date.now()), 60_000)
onUnmounted(() => clearInterval(clock))

const deadlineText = computed(() => {
  if (complaint.value?.status !== 'open' || !complaint.value.deadline_at) return ''
  const hours = Math.floor((new Date(complaint.value.deadline_at) - now.value) / 3_600_000)
  if (hours < 0) return 'Batas waktu lewat, komplain segera diteruskan ke admin.'
  if (hours < 1) return 'Sisa kurang dari 1 jam.'
  const days = Math.floor(hours / 24)
  return days ? `Sisa ${days} hari ${hours % 24} jam.` : `Sisa ${hours} jam.`
})

const submitting = ref(false)
const alertMessage = ref('')
const statusHeading = ref(null)
const alertEl = ref(null)

// Runs one complaint action, then re-fetches the order. A failed re-fetch
// does not make the action fail: the action's own response is already shown.
const run = async (action, fallback) => {
  if (submitting.value) return false
  alertMessage.value = ''
  submitting.value = true
  try {
    const { data, message } = await action()
    emit('updated', data)
    toast.success(message || 'Komplain diperbarui')
  } catch (error) {
    const apiMessage = error?.response?.data?.message
    const handled = handleError(error)
    alertMessage.value =
      typeof apiMessage === 'string' && apiMessage
        ? apiMessage
        : typeof handled === 'string'
          ? handled
          : fallback
    submitting.value = false
    await nextTick()
    alertEl.value?.focus()
    return false
  }

  try {
    const fresh = await transactionStore.fetchTransactionById(props.transaction.id, {
      quiet: true
    })
    if (fresh) emit('updated', fresh)
  } catch {
    // fetchTransactionById reports its own errors.
  }
  submitting.value = false
  await nextTick()
  statusHeading.value?.focus()
  return true
}

// --- Buyer: new complaint -------------------------------------------------
const formOpen = ref(false)
const form = ref({ reason: '', description: '' })
const photos = ref([])
const errors = ref({})
const reasonInput = ref(null)
const descriptionInput = ref(null)
const photoInput = ref(null)

const clearPhotos = () => {
  photos.value.forEach((photo) => URL.revokeObjectURL(photo.url))
  photos.value = []
}
onUnmounted(clearPhotos)

const openForm = async () => {
  formOpen.value = true
  await nextTick()
  reasonInput.value?.focus()
}

const closeForm = () => {
  formOpen.value = false
  form.value = { reason: '', description: '' }
  errors.value = {}
  clearPhotos()
}

const addPhotos = (event) => {
  const files = [...event.target.files]
  event.target.value = ''
  errors.value.photos = ''

  if (photos.value.length + files.length > MAX_PHOTOS) {
    errors.value.photos = `Maksimal ${MAX_PHOTOS} foto`
  } else if (files.some((file) => !PHOTO_TYPES.includes(file.type))) {
    errors.value.photos = 'Foto harus berformat JPG, PNG, atau WEBP'
  } else if (files.some((file) => file.size > MAX_PHOTO_BYTES)) {
    errors.value.photos = 'Ukuran tiap foto maksimal 2 MB'
  } else {
    photos.value.push(...files.map((file) => ({ file, url: URL.createObjectURL(file) })))
  }
}

const removePhoto = (index) => {
  URL.revokeObjectURL(photos.value[index].url)
  photos.value.splice(index, 1)
  photoInput.value?.focus()
}

const submitComplaint = async () => {
  const length = form.value.description.trim().length
  errors.value = {}
  if (!form.value.reason) errors.value.reason = 'Pilih alasan komplain'
  if (length < 10) errors.value.description = 'Keterangan minimal 10 karakter'
  else if (length > 1000) errors.value.description = 'Keterangan maksimal 1000 karakter'

  if (errors.value.reason) return reasonInput.value?.focus()
  if (errors.value.description) return descriptionInput.value?.focus()

  const done = await run(
    () =>
      transactionStore.createComplaint(props.transaction.id, {
        reason: form.value.reason,
        description: form.value.description.trim(),
        photos: photos.value.map((photo) => photo.file)
      }),
    'Gagal mengirim komplain'
  )
  if (done) closeForm()
}

// --- Buyer: withdraw ------------------------------------------------------
const withdraw = () => {
  if (submitting.value) return
  if (
    !confirm(
      'Tarik komplain ini? Komplain untuk pesanan ini tidak bisa diajukan lagi, dan pesanan bisa diselesaikan seperti biasa.'
    )
  )
    return
  run(() => transactionStore.withdrawComplaint(complaint.value.id), 'Gagal menarik komplain')
}

// --- Seller ---------------------------------------------------------------
const rejecting = ref(false)
const sellerResponse = ref('')
const responseError = ref('')
const responseInput = ref(null)

const accept = () => {
  if (submitting.value) return
  const amount = formatRupiah(props.transaction.grand_total)
  if (
    !confirm(
      `Setujui komplain? Seluruh dana pesanan (Rp ${amount}) dikembalikan ke pembeli dan stok produk TIDAK dikembalikan.`
    )
  )
    return
  run(() => transactionStore.acceptComplaint(complaint.value.id), 'Gagal menyetujui komplain')
}

const startRejecting = async () => {
  rejecting.value = true
  await nextTick()
  responseInput.value?.focus()
}

const submitReject = async () => {
  responseError.value = ''
  if (sellerResponse.value.trim().length < 5) {
    responseError.value = 'Tanggapan minimal 5 karakter'
    return responseInput.value?.focus()
  }
  const done = await run(
    () => transactionStore.rejectComplaint(complaint.value.id, sellerResponse.value.trim()),
    'Gagal menolak komplain'
  )
  if (done) {
    rejecting.value = false
    sellerResponse.value = ''
  }
}

// --- Admin ----------------------------------------------------------------
const outcome = ref('')
const adminNote = ref('')
const resolveErrors = ref({})
const noteInput = ref(null)
const outcomeInput = ref(null)

const submitResolve = async () => {
  if (submitting.value) return
  resolveErrors.value = {}
  if (!outcome.value) resolveErrors.value.outcome = 'Pilih keputusan'
  if (adminNote.value.trim().length < 5) resolveErrors.value.note = 'Catatan minimal 5 karakter'
  if (resolveErrors.value.outcome) return outcomeInput.value?.focus()
  if (resolveErrors.value.note) return noteInput.value?.focus()

  const amount = formatRupiah(props.transaction.grand_total)
  const message =
    outcome.value === 'approve'
      ? `Setujui komplain? Seluruh dana pesanan (Rp ${amount}) dikembalikan ke pembeli dan stok produk tidak dikembalikan.`
      : 'Tolak komplain? Pesanan tetap berjalan dan dana tetap untuk penjual.'
  if (!confirm(message)) return

  const done = await run(
    () =>
      transactionStore.resolveComplaint(complaint.value.id, outcome.value, adminNote.value.trim()),
    'Gagal menyimpan keputusan'
  )
  if (done) {
    outcome.value = ''
    adminNote.value = ''
  }
}

const inputClass =
  'w-full px-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-sm text-custom-black dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/10 aria-[invalid=true]:border-red-500'
const primaryButton =
  'h-11 px-5 rounded-xl bg-custom-blue text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50'
const secondaryButton =
  'h-11 px-5 rounded-xl border border-gray-200 dark:border-white/10 text-sm font-medium text-custom-black dark:text-white hover:bg-gray-50 dark:hover:bg-white/5 disabled:opacity-50'
const dangerButton =
  'h-11 px-5 rounded-xl border border-red-200 dark:border-red-900/40 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50'
const errorClass = 'text-sm text-red-600 dark:text-red-400'
</script>

<template>
  <section
    v-if="complaint || canComplain"
    class="flex flex-col w-full min-w-0 rounded-2xl p-5 gap-4 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm"
    aria-labelledby="complaint-title"
  >
    <h2 id="complaint-title" class="font-medium text-lg dark:text-white">Komplain Pesanan</h2>

    <!-- Buyer, no complaint yet -->
    <template v-if="canComplain && !formOpen">
      <p class="text-sm text-custom-grey dark:text-gray-400">
        Ada masalah dengan pesanan ini? Ajukan komplain sebelum menandai pesanan diterima. Penjual
        punya 2 hari untuk menanggapi.
      </p>
      <button type="button" :class="[secondaryButton, 'self-start']" @click="openForm">
        Ajukan Komplain
      </button>
    </template>

    <form
      v-if="canComplain && formOpen"
      class="flex flex-col gap-4"
      novalidate
      aria-label="Formulir komplain"
      @submit.prevent="submitComplaint"
    >
      <div class="flex flex-col gap-1.5">
        <label for="complaint-reason" class="text-sm font-medium text-custom-black dark:text-white">
          Alasan
        </label>
        <select
          id="complaint-reason"
          ref="reasonInput"
          v-model="form.reason"
          :class="[inputClass, 'h-11']"
          :aria-invalid="!!errors.reason"
          :aria-describedby="errors.reason ? 'complaint-reason-error' : undefined"
        >
          <option value="" disabled>Pilih alasan</option>
          <option v-for="(label, value) in COMPLAINT_REASONS" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
        <p v-if="errors.reason" id="complaint-reason-error" :class="errorClass">
          {{ errors.reason }}
        </p>
      </div>

      <div class="flex flex-col gap-1.5">
        <label
          for="complaint-description"
          class="text-sm font-medium text-custom-black dark:text-white"
        >
          Keterangan
        </label>
        <textarea
          id="complaint-description"
          ref="descriptionInput"
          v-model="form.description"
          rows="4"
          maxlength="1000"
          :class="[inputClass, 'py-3']"
          placeholder="Jelaskan masalahnya, minimal 10 karakter"
          :aria-invalid="!!errors.description"
          :aria-describedby="
            ['complaint-description-count', errors.description && 'complaint-description-error']
              .filter(Boolean)
              .join(' ')
          "
        ></textarea>
        <div class="flex justify-between gap-3">
          <p v-if="errors.description" id="complaint-description-error" :class="errorClass">
            {{ errors.description }}
          </p>
          <p
            id="complaint-description-count"
            class="ml-auto text-xs text-custom-grey dark:text-gray-400"
          >
            {{ form.description.length }}/1000
          </p>
        </div>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="complaint-photos" class="text-sm font-medium text-custom-black dark:text-white">
          Foto bukti (opsional)
        </label>
        <p id="complaint-photos-hint" class="text-xs text-custom-grey dark:text-gray-400">
          <template v-if="photos.length >= MAX_PHOTOS">
            Maksimal {{ MAX_PHOTOS }} foto. Hapus salah satu untuk menggantinya.
          </template>
          <template v-else>
            Maksimal {{ MAX_PHOTOS }} foto JPG, PNG, atau WEBP, masing-masing 2 MB.
          </template>
        </p>
        <ul v-if="photos.length" class="grid grid-cols-3 gap-2">
          <li
            v-for="(photo, index) in photos"
            :key="photo.url"
            class="relative aspect-square rounded-xl overflow-hidden border border-gray-100 dark:border-white/10"
          >
            <img :src="photo.url" :alt="`Foto ${index + 1}`" class="size-full object-cover" />
            <button
              type="button"
              class="absolute top-1 right-1 size-7 rounded-full bg-black/60 text-white text-sm leading-none"
              :aria-label="`Hapus foto ${index + 1}`"
              @click="removePhoto(index)"
            >
              ×
            </button>
          </li>
        </ul>
        <input
          id="complaint-photos"
          ref="photoInput"
          :disabled="photos.length >= MAX_PHOTOS"
          type="file"
          multiple
          accept="image/jpeg,image/png,image/webp"
          class="block w-full min-w-0 text-sm text-custom-grey dark:text-gray-400 file:mr-3 file:h-9 file:px-4 file:rounded-full file:border-0 file:bg-custom-blue/10 file:text-custom-blue file:font-medium disabled:opacity-50"
          :aria-invalid="!!errors.photos"
          :aria-describedby="
            ['complaint-photos-hint', errors.photos && 'complaint-photos-error']
              .filter(Boolean)
              .join(' ')
          "
          @change="addPhotos"
        />
        <p v-if="errors.photos" id="complaint-photos-error" :class="errorClass">
          {{ errors.photos }}
        </p>
      </div>

      <div class="flex flex-col-reverse sm:flex-row gap-3">
        <button type="button" :class="secondaryButton" :disabled="submitting" @click="closeForm">
          Batal
        </button>
        <button type="submit" :class="[primaryButton, 'sm:flex-1']" :disabled="submitting">
          {{ submitting ? 'Mengirim...' : 'Kirim Komplain' }}
        </button>
      </div>
    </form>

    <!-- Existing complaint -->
    <template v-if="complaint">
      <div
        class="flex flex-col gap-3 p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10"
      >
        <div class="flex flex-wrap items-center gap-2">
          <h3
            ref="statusHeading"
            tabindex="-1"
            class="font-medium text-custom-black dark:text-white focus:outline-none"
          >
            Status:
            <span
              class="ml-1 rounded-full px-2.5 py-1 text-xs font-medium"
              :class="status.style"
              data-testid="complaint-status"
            >
              {{ status.label }}
            </span>
          </h3>
        </div>

        <p
          v-if="complaint.status === 'open' && complaint.deadline_at"
          class="text-sm text-custom-grey dark:text-gray-400"
        >
          Batas tanggapan penjual: {{ deadline }}. {{ deadlineText }}
        </p>
        <p
          v-if="complaint.status === 'escalated' && !complaint.seller_response"
          class="text-sm text-custom-grey dark:text-gray-400"
        >
          Penjual tidak menanggapi sebelum batas waktu.
        </p>
        <p v-if="isBuyer && isActive" class="text-sm text-amber-700 dark:text-amber-400">
          Pesanan belum bisa diselesaikan selama komplain berjalan.
        </p>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
          <div class="flex flex-col gap-0.5">
            <dt class="text-custom-grey dark:text-gray-400">Alasan</dt>
            <dd class="font-medium text-custom-black dark:text-white">
              {{ COMPLAINT_REASONS[complaint.reason] ?? complaint.reason }}
            </dd>
          </div>
          <div class="flex flex-col gap-0.5">
            <dt class="text-custom-grey dark:text-gray-400">Diajukan</dt>
            <dd class="font-medium text-custom-black dark:text-white">
              {{ formatToClientTimeZone(complaint.created_at) }}
            </dd>
          </div>
          <div class="flex flex-col gap-0.5 sm:col-span-2">
            <dt class="text-custom-grey dark:text-gray-400">Keterangan pembeli</dt>
            <dd class="text-custom-black dark:text-white break-words whitespace-pre-line">
              {{ complaint.description }}
            </dd>
          </div>
          <div v-if="complaint.photos?.length" class="flex flex-col gap-1.5 sm:col-span-2">
            <dt class="text-custom-grey dark:text-gray-400">Foto</dt>
            <dd>
              <ul class="flex flex-wrap gap-2">
                <li v-for="(url, index) in complaint.photos" :key="url">
                  <a
                    :href="url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block size-20 rounded-xl overflow-hidden border border-gray-100 dark:border-white/10 focus:outline-none focus:ring-2 focus:ring-custom-blue"
                  >
                    <img
                      :src="url"
                      :alt="`Foto komplain ${index + 1} (buka ukuran penuh)`"
                      class="size-full object-cover"
                    />
                  </a>
                </li>
              </ul>
            </dd>
          </div>
          <div v-if="complaint.seller_response" class="flex flex-col gap-0.5 sm:col-span-2">
            <dt class="text-custom-grey dark:text-gray-400">Tanggapan penjual</dt>
            <dd class="text-custom-black dark:text-white break-words whitespace-pre-line">
              {{ complaint.seller_response }}
            </dd>
          </div>
          <div v-if="complaint.admin_note" class="flex flex-col gap-0.5 sm:col-span-2">
            <dt class="text-custom-grey dark:text-gray-400">Catatan admin</dt>
            <dd class="text-custom-black dark:text-white break-words whitespace-pre-line">
              {{ complaint.admin_note }}
            </dd>
          </div>
        </dl>
      </div>

      <!-- Buyer -->
      <button
        v-if="isBuyer && isActive"
        type="button"
        :class="[dangerButton, 'self-start']"
        :disabled="submitting"
        @click="withdraw"
      >
        Tarik Komplain
      </button>

      <!-- Seller -->
      <template v-if="isSeller && complaint.status === 'open'">
        <div v-if="!rejecting" class="flex flex-col sm:flex-row gap-3">
          <button type="button" :class="primaryButton" :disabled="submitting" @click="accept">
            Setujui &amp; Refund
          </button>
          <button
            type="button"
            :class="dangerButton"
            :disabled="submitting"
            @click="startRejecting"
          >
            Tolak
          </button>
        </div>
        <form v-else class="flex flex-col gap-3" novalidate @submit.prevent="submitReject">
          <label
            for="complaint-response"
            class="text-sm font-medium text-custom-black dark:text-white"
          >
            Tanggapan untuk pembeli
          </label>
          <p id="complaint-response-hint" class="text-xs text-custom-grey dark:text-gray-400">
            Komplain akan diteruskan ke admin untuk diputuskan.
          </p>
          <textarea
            id="complaint-response"
            ref="responseInput"
            v-model="sellerResponse"
            rows="3"
            maxlength="1000"
            :class="[inputClass, 'py-3']"
            :aria-invalid="!!responseError"
            :aria-describedby="
              ['complaint-response-hint', responseError && 'complaint-response-error']
                .filter(Boolean)
                .join(' ')
            "
          ></textarea>
          <p v-if="responseError" id="complaint-response-error" :class="errorClass">
            {{ responseError }}
          </p>
          <div class="flex flex-col-reverse sm:flex-row gap-3">
            <button
              type="button"
              :class="secondaryButton"
              :disabled="submitting"
              @click="rejecting = false"
            >
              Batal
            </button>
            <button type="submit" :class="[primaryButton, 'sm:flex-1']" :disabled="submitting">
              Kirim Tanggapan &amp; Tolak
            </button>
          </div>
        </form>
      </template>

      <!-- Admin -->
      <form
        v-if="isAdmin && complaint.status === 'escalated'"
        class="flex flex-col gap-3"
        novalidate
        @submit.prevent="submitResolve"
      >
        <fieldset
          class="flex flex-col gap-2"
          :aria-describedby="resolveErrors.outcome ? 'complaint-outcome-error' : undefined"
        >
          <legend class="mb-1 text-sm font-medium text-custom-black dark:text-white">
            Keputusan admin
          </legend>
          <label class="flex items-center gap-2 text-sm text-custom-black dark:text-white">
            <input
              ref="outcomeInput"
              v-model="outcome"
              type="radio"
              name="complaint-outcome"
              value="approve"
              class="size-4 accent-custom-blue"
            />
            Refund penuh ke pembeli
          </label>
          <label class="flex items-center gap-2 text-sm text-custom-black dark:text-white">
            <input
              v-model="outcome"
              type="radio"
              name="complaint-outcome"
              value="reject"
              class="size-4 accent-custom-blue"
            />
            Tolak komplain
          </label>
          <p v-if="resolveErrors.outcome" id="complaint-outcome-error" :class="errorClass">
            {{ resolveErrors.outcome }}
          </p>
        </fieldset>
        <label for="complaint-note" class="text-sm font-medium text-custom-black dark:text-white">
          Catatan keputusan
        </label>
        <textarea
          id="complaint-note"
          ref="noteInput"
          v-model="adminNote"
          rows="3"
          maxlength="1000"
          :class="[inputClass, 'py-3']"
          :aria-invalid="!!resolveErrors.note"
          :aria-describedby="resolveErrors.note ? 'complaint-note-error' : undefined"
        ></textarea>
        <p v-if="resolveErrors.note" id="complaint-note-error" :class="errorClass">
          {{ resolveErrors.note }}
        </p>
        <button type="submit" :class="primaryButton" :disabled="submitting">
          Simpan Keputusan
        </button>
      </form>
    </template>

    <!-- Last in the panel, so right under whichever action buttons are shown. -->
    <p
      v-if="alertMessage"
      ref="alertEl"
      role="alert"
      tabindex="-1"
      :class="[errorClass, 'focus:outline-none']"
    >
      {{ alertMessage }}
    </p>
  </section>
</template>
