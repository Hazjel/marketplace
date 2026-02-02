<script setup>
import { onMounted } from 'vue'

// Scroll to top on mount
onMounted(() => {
  window.scrollTo(0, 0)
})
</script>

<template>
  <main class="w-full bg-white pb-24 md:pb-32 overflow-hidden">
    <!-- Hero Section -->
    <section
      class="relative w-full min-h-[600px] flex items-center justify-center overflow-hidden pb-20 md:pb-0"
    >
      <!-- Background Image -->
      <div class="absolute inset-0">
        <img
          src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=2071&auto=format&fit=crop"
          class="w-full h-full object-cover scale-105 animate-slow-zoom"
          alt="About Hero"
        />
        <div
          class="absolute inset-0 bg-gradient-to-r from-custom-black/90 via-custom-black/70 to-transparent"
        ></div>
      </div>

      <!-- Content -->
      <div class="relative z-10 w-full max-w-[1280px] px-5 md:px-[52px] pt-20 pb-20 md:pb-32">
        <div class="max-w-3xl flex flex-col gap-6 animate-fade-in-up">
          <span
            class="inline-block px-4 py-2 bg-white/10 backdrop-blur-md rounded-full text-custom-blue font-bold text-sm tracking-widest border border-white/20 w-fit"
          >
            EST. 2024
          </span>
          <h1 class="text-5xl md:text-7xl font-bold text-white leading-tight">
            Connecting
            <span
              class="text-transparent bg-clip-text bg-gradient-to-r from-custom-blue to-teal-400"
              >Ambitions</span
            >,<br />
            Delivering
            <span
              class="text-transparent bg-clip-text bg-gradient-to-r from-custom-orange to-red-400"
              >Trust</span
            >.
          </h1>
          <p
            class="text-xl text-gray-300 leading-relaxed max-w-2xl border-l-4 border-custom-blue pl-6"
          >
            We are more than just a marketplace. We are the bridge between local craftsmanship and
            global opportunity.
          </p>
        </div>
      </div>
    </section>

    <!-- Stats Section (Floating) -->
    <section class="relative z-20 -mt-16 px-5 md:px-[52px]">
      <div
        class="max-w-[1280px] mx-auto bg-white rounded-3xl shadow-xl border border-gray-100 p-8 md:p-12 grid grid-cols-2 lg:grid-cols-4 gap-8"
      >
        <div class="flex flex-col items-center text-center gap-2 group">
          <span
            class="text-4xl md:text-5xl font-extrabold text-custom-black group-hover:text-custom-blue transition-colors duration-300"
            >10K+</span
          >
          <span class="text-custom-grey font-medium uppercase tracking-wider text-xs"
            >Active Sellers</span
          >
        </div>
        <div class="flex flex-col items-center text-center gap-2 group">
          <span
            class="text-4xl md:text-5xl font-extrabold text-custom-black group-hover:text-custom-orange transition-colors duration-300"
            >500K+</span
          >
          <span class="text-custom-grey font-medium uppercase tracking-wider text-xs"
            >Happy Customers</span
          >
        </div>
        <div class="flex flex-col items-center text-center gap-2 group">
          <span
            class="text-4xl md:text-5xl font-extrabold text-custom-black group-hover:text-custom-green transition-colors duration-300"
            >2M+</span
          >
          <span class="text-custom-grey font-medium uppercase tracking-wider text-xs"
            >Transactions</span
          >
        </div>
        <div class="flex flex-col items-center text-center gap-2 group">
          <span
            class="text-4xl md:text-5xl font-extrabold text-custom-black group-hover:text-purple-500 transition-colors duration-300"
            >99%</span
          >
          <span class="text-custom-grey font-medium uppercase tracking-wider text-xs"
            >Satisfaction Rate</span
          >
        </div>
      </div>
    </section>

    <!-- Our Story (Zig Zag) -->
    <section class="max-w-[1280px] mx-auto px-5 md:px-[52px] py-24 flex flex-col gap-24">
      <!-- Row 1 -->
      <div class="flex flex-col lg:flex-row gap-12 lg:gap-20 items-center">
        <div class="w-full lg:w-1/2 relative group">
          <div
            class="absolute -inset-4 bg-custom-blue/20 rounded-[40px] -rotate-3 transition-transform group-hover:rotate-0 duration-500"
          ></div>
          <img
            src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?q=80&w=1932&auto=format&fit=crop"
            class="relative rounded-3xl shadow-2xl w-full h-auto object-cover aspect-[4/3] rotate-2 transition-transform group-hover:rotate-0 duration-500"
            alt="Team"
          />
        </div>
        <div class="w-full lg:w-1/2 flex flex-col gap-6">
          <span class="text-custom-blue font-bold tracking-widest uppercase text-sm"
            >Our Origin</span
          >
          <h2 class="text-3xl md:text-5xl font-bold text-custom-black leading-tight">
            Built for the Community,<br />by the Community.
          </h2>
          <p class="text-custom-grey text-lg leading-relaxed">
            Blukios wasn't built in a boardroom. It started in a small garage in Kendal, observing
            the struggles of local sellers trying to navigate complex digital platforms.
          </p>
          <p class="text-custom-grey text-lg leading-relaxed">
            We asked a simple question:
            <span class="text-custom-black font-semibold italic"
              >"Why does selling online have to be so hard?"</span
            >
            Use that curiosity to fuel our innovation.
          </p>
        </div>
      </div>

      <!-- Row 2 -->
      <div class="flex flex-col-reverse lg:flex-row gap-12 lg:gap-20 items-center">
        <div class="w-full lg:w-1/2 flex flex-col gap-6 lg:pl-10">
          <span class="text-custom-orange font-bold tracking-widest uppercase text-sm"
            >The Vision</span
          >
          <h2 class="text-3xl md:text-5xl font-bold text-custom-black leading-tight">
            Technology with a <br />Human Touch.
          </h2>
          <p class="text-custom-grey text-lg leading-relaxed">
            We believe that technology should empower, not complicate. Every feature we build is
            designed with empathy for the user. From our seamless checkout to our intuitive seller
            dashboard, simplicity is our ultimate sophistication.
          </p>
          <ul class="flex flex-col gap-3 mt-4">
            <li class="flex items-center gap-3">
              <div
                class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center text-custom-green text-xs"
              >
                <i class="fa-solid fa-check"></i>
              </div>
              <span class="font-medium text-custom-black">Zero-Knowledge barrier entry</span>
            </li>
            <li class="flex items-center gap-3">
              <div
                class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center text-custom-green text-xs"
              >
                <i class="fa-solid fa-check"></i>
              </div>
              <span class="font-medium text-custom-black">Transparent pricing model</span>
            </li>
            <li class="flex items-center gap-3">
              <div
                class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center text-custom-green text-xs"
              >
                <i class="fa-solid fa-check"></i>
              </div>
              <span class="font-medium text-custom-black">Community-driven development</span>
            </li>
          </ul>
        </div>
        <div class="w-full lg:w-1/2 relative group">
          <div
            class="absolute -inset-4 bg-custom-orange/20 rounded-[40px] rotate-3 transition-transform group-hover:rotate-0 duration-500"
          ></div>
          <img
            src="https://images.unsplash.com/photo-1531482615713-2afd69097998?q=80&w=2070&auto=format&fit=crop"
            class="relative rounded-3xl shadow-2xl w-full h-auto object-cover aspect-[4/3] -rotate-2 transition-transform group-hover:rotate-0 duration-500"
            alt="Tech"
          />
        </div>
      </div>
    </section>

    <!-- Values Cards -->
    <section class="bg-gradient-to-b from-gray-50 to-white py-24">
      <div class="max-w-[1280px] mx-auto px-5 md:px-[52px]">
        <div class="text-center max-w-2xl mx-auto mb-20 animate-on-scroll">
          <h2 class="text-4xl font-bold text-custom-black mb-6">Our Core Values</h2>
          <p class="text-custom-grey text-lg">The principles that guide every decision we make.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <!-- Value 1 -->
          <div
            class="bg-white p-10 rounded-[32px] shadow-sm border border-gray-100 hover:shadow-xl hover:-translate-y-2 transition-all duration-300 group"
          >
            <div
              class="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center text-custom-blue text-3xl mb-8 group-hover:scale-110 transition-transform"
            >
              <i class="fa-solid fa-users"></i>
            </div>
            <h3
              class="text-2xl font-bold text-custom-black mb-4 group-hover:text-custom-blue transition-colors"
            >
              Customer Obsession
            </h3>
            <p class="text-custom-grey leading-relaxed">
              We start with the customer and work backwards. We work vigorously to earn and keep
              customer trust.
            </p>
          </div>

          <!-- Value 2 -->
          <div
            class="bg-white p-10 rounded-[32px] shadow-sm border border-gray-100 hover:shadow-xl hover:-translate-y-2 transition-all duration-300 group"
          >
            <div
              class="w-16 h-16 bg-orange-50 rounded-2xl flex items-center justify-center text-custom-orange text-3xl mb-8 group-hover:scale-110 transition-transform"
            >
              <i class="fa-solid fa-lightbulb"></i>
            </div>
            <h3
              class="text-2xl font-bold text-custom-black mb-4 group-hover:text-custom-orange transition-colors"
            >
              Invent and Simplify
            </h3>
            <p class="text-custom-grey leading-relaxed">
              We expect and require innovation and invention from our teams and always find ways to
              simplify.
            </p>
          </div>

          <!-- Value 3 -->
          <div
            class="bg-white p-10 rounded-[32px] shadow-sm border border-gray-100 hover:shadow-xl hover:-translate-y-2 transition-all duration-300 group"
          >
            <div
              class="w-16 h-16 bg-green-50 rounded-2xl flex items-center justify-center text-custom-green text-3xl mb-8 group-hover:scale-110 transition-transform"
            >
              <i class="fa-solid fa-leaf"></i>
            </div>
            <h3
              class="text-2xl font-bold text-custom-black mb-4 group-hover:text-custom-green transition-colors"
            >
              Sustainability
            </h3>
            <p class="text-custom-grey leading-relaxed">
              We are committed to building a sustainable business for our employees, customers, and
              the communities we serve.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="max-w-[1280px] mx-auto px-5 md:px-[52px] py-10 md:py-24">
      <div
        class="bg-custom-black rounded-[40px] p-10 md:p-24 text-center relative overflow-hidden group"
      >
        <!-- Background Image Overlay -->
        <div
          class="absolute inset-0 opacity-20 group-hover:scale-105 transition-transform duration-700"
        >
          <img
            src="https://images.unsplash.com/photo-1557804506-669a67965ba0?q=80&w=2074&auto=format&fit=crop"
            class="w-full h-full object-cover grayscale"
            alt="Footer BG"
          />
        </div>

        <div class="relative z-10 flex flex-col items-center gap-8">
          <h2 class="text-4xl md:text-6xl font-bold text-white tracking-tight">
            Be Part of the <br /><span
              class="text-transparent bg-clip-text bg-gradient-to-r from-custom-blue to-teal-400"
              >Revolution.</span
            >
          </h2>
          <p class="text-gray-400 text-lg max-w-xl">
            Whether you are buying your first item or opening your first store, we are here to
            support you.
          </p>
          <div class="flex flex-col sm:flex-row gap-4">
            <RouterLink
              :to="{ name: 'app.all-products' }"
              class="px-8 py-4 bg-white text-custom-black rounded-full font-bold text-lg hover:bg-gray-100 transition-all"
            >
              Start Shopping
            </RouterLink>
            <RouterLink
              :to="{ name: 'auth.open-store' }"
              class="px-8 py-4 bg-transparent border border-white/30 text-white rounded-full font-bold text-lg hover:bg-white/10 transition-all backdrop-blur-sm"
            >
              Open a Store
            </RouterLink>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.animate-slow-zoom {
  animation: zoom 20s infinite alternate;
}

@keyframes zoom {
  0% {
    transform: scale(1);
  }

  100% {
    transform: scale(1.1);
  }
}
</style>
