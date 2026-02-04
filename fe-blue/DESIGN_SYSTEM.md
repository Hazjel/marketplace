# Design System: Blue Marketplace
**Version:** 1.0.0
**Style:** Modern, Clean, Trustworthy
**Base:** Tailwind CSS v4

---

## 1. Color Palette
Using CSS Variables defined in `src/assets/style.css` for runtime flexibility and Dark Mode support.

### Primary (Trust & Professionalism)
*   **Blue**: `#0F52BA` (Primary Brand) -> `bg-custom-blue` / `text-custom-blue`
*   **Midnight**: `#000926` (Text/Dark Background) -> `bg-custom-black` / `text-custom-black`
*   **Soft Blue**: `#D6E6F3` (Accents/Backgrounds) -> `bg-custom-icon-background`

### Semantic (Functional)
*   **Success**: `#10B981` (Emerald-500) - For completed states, verified badges.
*   **Warning**: `#EAB308` (Yellow-500) - For awaiting payment, reviews.
*   **Destructive**: `#EF4444` (Red-500) - For errors, deletion, favorite (heart).
*   **Info**: `#3B82F6` (Blue-500) - For information alerts.

### Neutrals (Structure)
*   **Background**: `#F8FAFC` (Slate-50) (Light) / `#0B1120` (Dark)
*   **Surface/Card**: `#FFFFFF` (White) (Light) / `#151E32` (Dark)
*   **Border**: `#E2E8F0` (Slate-200) (Light) / `#334155` (Slate-700) (Dark)
*   **Muted Text**: `#64748B` (Slate-500) (Light) / `#94A3B8` (Slate-400) (Dark)

---

## 2. Typography
**Font Family:** 'Lexend Deca', sans-serif (Geometric, legible, friendly)

### Scale
| Role | Class | Size | Weight | Line Height |
|------|-------|------|--------|-------------|
| **H1** | `text-4xl` | 36px | Bold (700) | 1.1 |
| **H2** | `text-3xl` | 30px | Bold (700) | 1.2 |
| **H3** | `text-2xl` | 24px | Semibold (600) | 1.2 |
| **H4** | `text-xl` | 20px | Semibold (600) | 1.3 |
| **Body L**| `text-lg` | 18px | Regular (400) | 1.6 |
| **Body M**| `text-base` | 16px | Regular (400) | 1.5 |
| **Body S**| `text-sm` | 14px | Regular (400) | 1.5 |
| **Caption**| `text-xs` | 12px | Medium (500) | 1.4 |

---

## 3. Spacing & Layout
**Grid Base:** 4px (Tailwind standard)

### Container
*   **Max Width**: `max-w-[1240px]` (Standard Desktop)
*   **Padding**: `px-4` (Mobile), `px-6` (Tablet), `px-8` (Desktop)

### Spacing
*   **Section Gap**: `gap-8` (32px) or `gap-12` (48px)
*   **Card Gap**: `gap-4` (16px) or `gap-6` (24px)
*   **Internal Padding**: `p-4` or `p-6` for cards.

### Radius
*   **Small**: `rounded-lg` (8px) - Buttons, Inputs
*   **Medium**: `rounded-xl` (12px) - internal cards
*   **Large**: `rounded-2xl` (16px) - Main Cards (Product, Store)
*   **Full**: `rounded-full` - Avatars, Badges, Pills

---

## 4. Shadows & Effects
**Goal:** Elevation without clutter.

*   **Soft Shadow**: `shadow-sm` (`0 1px 2px 0 rgb(0 0 0 / 0.05)`) - Cards default.
*   **Float**: `shadow-floating` (Custom) - Hover states.
*   **Glow**: `hover-glow-blue` (Custom class) - Blue border + slight lift on hover.
*   **Glass**: `backdrop-blur-sm bg-white/60` - Overlays (Stock habis, badges).

---

## 5. Components & UX Patterns

### Buttons (`Button.vue`)
*   **Primary**: Solid Blue. Used for "Buy Now", "Checkout", specific CTAs.
*   **Secondary**: Outline or Ghost. Used for "Cancel", "View Detail".
*   **States**: Must have `:hover`, `:active` (scale-95), and `:disabled` (opacity-50 cursor-not-allowed).

### Cards (`Card.vue`)
Use the `Card` primitive.
*   **Interactive**: Add `cursor-pointer hover-glow-blue transition-all duration-300`.
*   **Static**: Simple `border border-border`.

### Forms (`Input.vue`)
*   **Style**: Floating Label or Top Label.
*   **Height**: Large (`h-[50px]+`) for touch targets.
*   **Feedback**: Invalid state shows Red border + Error message below input.

### Navigation
*   **Mobile**: Bottom Tab Bar (Sticky).
*   **Desktop**: Top Navbar (Sticky preferred or static).

---

## 6. Accessibility (A11y) Checklist
*   [ ] **Contrast**: Text ratio > 4.5:1.
*   [ ] **Focus**: All interactive elements must have visible focus rings (`focus-visible:ring-2`).
*   [ ] **Labels**: Inputs must have labels (visible or `sr-only`).
*   [ ] **Alt Text**: Images must have meaningful `alt` attributes.
*   [ ] **Touch Targets**: Min 44x44px for buttons/links on mobile.
