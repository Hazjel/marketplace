<script setup>
import { onMounted, ref } from 'vue'

const activeSection = ref('agreement')

const scrollTo = (id) => {
  activeSection.value = id
  const element = document.getElementById(id)
  if (element) {
    const offset = 100 // Header height + padding
    const bodyRect = document.body.getBoundingClientRect().top
    const elementRect = element.getBoundingClientRect().top
    const elementPosition = elementRect - bodyRect
    const offsetPosition = elementPosition - offset

    window.scrollTo({
      top: offsetPosition,
      behavior: 'smooth'
    })
  }
}

onMounted(() => {
  window.scrollTo(0, 0)
})
</script>

<template>
  <main class="w-full bg-white dark:bg-gray-950 pb-20">
    <!-- Header -->
    <header class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-white/10 py-16 md:py-20 px-5 md:px-[52px]">
      <div class="max-w-[1280px] mx-auto">
        <span class="text-[#2563EB] font-bold tracking-widest uppercase text-sm mb-4 block"
          >Legal</span
        >
        <h1 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-4 font-['Plus_Jakarta_Sans']">Terms & Conditions</h1>
        <p class="text-gray-600 dark:text-gray-400 text-lg max-w-2xl">
          Silakan baca syarat dan ketentuan ini dengan seksama sebelum menggunakan layanan kami.
        </p>
        <div
          class="mt-8 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-4 py-2 rounded-full w-fit border border-gray-200 dark:border-white/10 shadow-sm"
        >
          <i class="fa-regular fa-clock"></i>
          <span>Terakhir Diperbarui: 01 Januari 2026</span>
        </div>
      </div>
    </header>

    <div
      class="max-w-[1280px] mx-auto px-5 md:px-[52px] py-12 flex flex-col md:flex-row gap-12 relative"
    >
      <!-- Sidebar Navigation -->
      <aside class="w-full md:w-1/4 hidden md:block">
        <div class="sticky top-32 flex flex-col gap-2">
          <p class="font-bold text-gray-900 dark:text-white mb-2 px-4 uppercase text-xs tracking-wider">
            Daftar Isi
          </p>
          <button
            :class="
              activeSection === 'agreement'
                ? 'bg-blue-50 dark:bg-[#2563EB]/20 text-[#2563EB] font-bold border-[#2563EB]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('agreement')"
          >
            1. Agreement
          </button>
          <button
            :class="
              activeSection === 'representations'
                ? 'bg-blue-50 dark:bg-[#2563EB]/20 text-[#2563EB] font-bold border-[#2563EB]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('representations')"
          >
            2. User Representations
          </button>
          <button
            :class="
              activeSection === 'products'
                ? 'bg-blue-50 dark:bg-[#2563EB]/20 text-[#2563EB] font-bold border-[#2563EB]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('products')"
          >
            3. Products & Purchases
          </button>
          <button
            :class="
              activeSection === 'prohibited'
                ? 'bg-blue-50 dark:bg-[#2563EB]/20 text-[#2563EB] font-bold border-[#2563EB]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('prohibited')"
          >
            4. Prohibited Activities
          </button>
          <button
            :class="
              activeSection === 'contact'
                ? 'bg-blue-50 dark:bg-[#2563EB]/20 text-[#2563EB] font-bold border-[#2563EB]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('contact')"
          >
            5. Contact Us
          </button>
        </div>
      </aside>

      <!-- Content -->
      <div class="w-full md:w-3/4 max-w-3xl">
        <div
          class="prose prose-lg prose-headings:text-gray-900 dark:prose-headings:text-white prose-p:text-gray-600 dark:prose-p:text-gray-400 prose-li:text-gray-600 dark:prose-li:text-gray-400 max-w-none flex flex-col gap-12"
        >
          <section id="agreement" class="scroll-mt-32">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">1. Agreement to Terms</h2>
            <p class="text-base leading-relaxed text-gray-600 dark:text-gray-400">
              These Terms and Conditions constitute a legally binding agreement made between you,
              whether personally or on behalf of an entity ("you") and Blukios ("we," "us" or
              "our"), concerning your access to and use of the Blukios website as well as any other
              media form, media channel, mobile website or mobile application related, linked, or
              otherwise connected thereto (collectively, the "Site").
            </p>
          </section>

          <section id="representations" class="scroll-mt-32">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">2. User Representations</h2>
            <p class="text-base leading-relaxed mb-4 text-gray-600 dark:text-gray-400">
              By using the Site, you represent and warrant that:
            </p>
            <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-2xl border border-gray-100 dark:border-white/10">
              <ul class="space-y-3 list-none p-0 m-0">
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#2563EB] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >All registration information you submit will be true, accurate, current, and
                    complete.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#2563EB] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >You will maintain the accuracy of such information and promptly update such
                    registration information as necessary.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#2563EB] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >You have the legal capacity and you agree to comply with these Terms and
                    Conditions.</span
                  >
                </li>
              </ul>
            </div>
          </section>

          <section id="products" class="scroll-mt-32">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">3. Products and Purchases</h2>
            <p class="text-base leading-relaxed text-gray-600 dark:text-gray-400">
              We make every effort to display as accurately as possible the colors, features,
              specifications, and details of the products available on the Site. However, we do not
              guarantee that the colors, features, specifications, and details of the products will
              be accurate, complete, reliable, current, or free of other errors.
            </p>
          </section>

          <section id="prohibited" class="scroll-mt-32">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">4. Prohibited Activities</h2>
            <p class="text-base leading-relaxed mb-4 text-gray-600 dark:text-gray-400">
              You may not access or use the Site for any purpose other than that for which we make
              the Site available. As a user of the Site, you agree not to:
            </p>
            <ul class="space-y-2 list-disc pl-5 text-gray-600 dark:text-gray-400">
              <li class="pl-2">
                Systematically retrieve data or other content from the Site to create or compile,
                directly or indirectly, a collection, compilation, database, or directory without
                written permission from us.
              </li>
              <li class="pl-2">
                Make any unauthorized use of the Site, including collecting usernames and/or email
                addresses of users by electronic or other means for the purpose of sending
                unsolicited email.
              </li>
            </ul>
          </section>

          <section id="contact" class="scroll-mt-32">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">5. Contact Us</h2>
            <div
              class="bg-blue-50 dark:bg-[#2563EB]/10 border border-blue-100 dark:border-[#2563EB]/30 rounded-2xl p-6 flex flex-col md:flex-row items-center gap-6"
            >
              <div
                class="w-12 h-12 rounded-full bg-white dark:bg-gray-800 flex items-center justify-center text-[#2563EB] shadow-sm shrink-0"
              >
                <i class="fa-solid fa-scale-balanced"></i>
              </div>
              <div>
                <h3 class="font-bold text-gray-900 dark:text-white text-lg">Legal Concerns?</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Our legal team is here to assist you.</p>
                <a
                  href="mailto:legal@blukios.com"
                  class="text-[#2563EB] font-semibold hover:underline"
                  >legal@blukios.com</a
                >
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </main>
</template>
