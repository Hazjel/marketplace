<script setup>
import { onMounted, ref } from 'vue'

const activeSection = ref('introduction')

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
        <h1 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-4 font-['Plus_Jakarta_Sans']">Privacy Policy</h1>
        <p class="text-gray-600 dark:text-gray-400 text-lg max-w-2xl">
          Kami berkomitmen melindungi privasi Anda dan memastikan data Anda ditangani dengan transparansi dan kepedulian.
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
              activeSection === 'introduction'
                ? 'bg-blue-50 dark:bg-[#2563EB]/20 text-[#2563EB] font-bold border-[#2563EB]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('introduction')"
          >
            1. Introduction
          </button>
          <button
            :class="
              activeSection === 'collection'
                ? 'bg-blue-50 dark:bg-[#2563EB]/20 text-[#2563EB] font-bold border-[#2563EB]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('collection')"
          >
            2. Data We Collect
          </button>
          <button
            :class="
              activeSection === 'usage'
                ? 'bg-blue-50 dark:bg-[#2563EB]/20 text-[#2563EB] font-bold border-[#2563EB]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('usage')"
          >
            3. How We Use Data
          </button>
          <button
            :class="
              activeSection === 'security'
                ? 'bg-blue-50 dark:bg-[#2563EB]/20 text-[#2563EB] font-bold border-[#2563EB]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('security')"
          >
            4. Data Security
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
          <section id="introduction" class="scroll-mt-32">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">1. Introduction</h2>
            <p class="text-base leading-relaxed text-gray-600 dark:text-gray-400">
              Welcome to Blukios. We respect your privacy and are committed to protecting your
              personal data. This privacy policy will inform you as to how we look after your
              personal data when you visit our website (regardless of where you visit it from) and
              tell you about your privacy rights and how the law protects you.
            </p>
          </section>

          <section id="collection" class="scroll-mt-32">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">2. Data We Collect</h2>
            <p class="text-base leading-relaxed mb-4 text-gray-600 dark:text-gray-400">
              We may collect, use, store and transfer different kinds of personal data about you
              which we have grouped together follows:
            </p>
            <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-2xl border border-gray-100 dark:border-white/10">
              <ul class="space-y-3 list-none p-0 m-0">
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#2563EB] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    ><strong class="text-gray-900 dark:text-white">Identity Data:</strong> First name, maiden name, last name, username or
                    similar identifier.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#2563EB] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    ><strong class="text-gray-900 dark:text-white">Contact Data:</strong> Billing address, delivery address, email address
                    and telephone numbers.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#2563EB] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    ><strong class="text-gray-900 dark:text-white">Financial Data:</strong> Bank account and payment card details
                    (processed securely).</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#2563EB] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    ><strong class="text-gray-900 dark:text-white">Transaction Data:</strong> Details about payments to and from
                    you.</span
                  >
                </li>
              </ul>
            </div>
          </section>

          <section id="usage" class="scroll-mt-32">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">3. How We Use Your Data</h2>
            <p class="text-base leading-relaxed mb-4 text-gray-600 dark:text-gray-400">
              We will only use your personal data when the law allows us to. Most commonly, we will
              use your personal data in the following circumstances:
            </p>
            <ul class="space-y-2 list-disc pl-5 text-gray-600 dark:text-gray-400">
              <li class="pl-2">
                Where we need to perform the contract we are about to enter into or have entered
                into with you.
              </li>
              <li class="pl-2">
                Where it is necessary for our legitimate interests (or those of a third party) and
                your interests and fundamental rights do not override those interests.
              </li>
              <li class="pl-2">Where we need to comply with a legal or regulatory obligation.</li>
            </ul>
          </section>

          <section id="security" class="scroll-mt-32">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">4. Data Security</h2>
            <p class="text-base leading-relaxed text-gray-600 dark:text-gray-400">
              We have put in place appropriate security measures to prevent your personal data from
              being accidentally lost, used or accessed in an unauthorized way, altered or
              disclosed. In addition, we limit access to your personal data to those employees,
              agents, contractors and other third parties who have a business need to know.
            </p>
          </section>

          <section id="contact" class="scroll-mt-32">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">5. Contact Us</h2>
            <div
              class="bg-blue-50 dark:bg-[#2563EB]/10 border border-blue-100 dark:border-[#2563EB]/30 rounded-2xl p-6 flex flex-col md:flex-row items-center gap-6"
            >
              <div
                class="w-12 h-12 rounded-full bg-white dark:bg-gray-800 flex items-center justify-center text-[#2563EB] shadow-sm shrink-0"
              >
                <i class="fa-solid fa-envelope"></i>
              </div>
              <div>
                <h3 class="font-bold text-gray-900 dark:text-white text-lg">Have questions?</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                  Our data protection officer is ready to help you.
                </p>
                <a
                  href="mailto:privacy@blukios.com"
                  class="text-[#2563EB] font-semibold hover:underline"
                  >privacy@blukios.com</a
                >
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </main>
</template>
