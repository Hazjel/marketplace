# Design System: Blukios
**Version:** 2.0.0  
**Style:** Modern, Trusted, Approachable  
**Base:** Tailwind CSS v4  
**Brand Origin:** "Blue" + "Kios" (Indonesian: booth/kiosk) — a trusted digital marketplace

---

## 1. Color Palette

### Primary: Electric Cobalt
| Token | Hex | Usage |
|-------|-----|-------|
| `--color-primary` | `#2563EB` | Buttons, links, active states |
| `--color-primary-dark` | `#0A1628` | Dark text, dark backgrounds |
| `--color-primary-light` | `#DBEAFE` | Icon backgrounds, hover tints |

### Secondary: Warm Amber (Accent)
| Token | Hex | Usage |
|-------|-----|-------|
| `--color-secondary` | `#F59E0B` | CTA highlights, badges, promo |
| `--color-secondary-dark` | `#D97706` | Hover/active state of accent |

### Semantic (Functional)
| Color | Hex | Usage |
|-------|-----|-------|
| Success | `#10B981` | Completed, verified, available |
| Warning | `#EAB308` | Pending, awaiting action |
| Destructive | `#EF4444` | Errors, delete, cancel |
| Info | `#3B82F6` | Information, neutral actions |

### Neutrals
| Token | Light | Dark |
|-------|-------|------|
| Background | `#F8FAFC` | `#0F172A` |
| Card/Surface | `#FFFFFF` | `#1E293B` |
| Border | `#E2E8F0` | `rgba(255,255,255,0.1)` |
| Muted Text | `#64748B` | `#94A3B8` |

---

## 2. Typography

**Font Family:** `'Plus Jakarta Sans'`, `'Inter'`, system-ui, sans-serif

| Level | Size | Weight | Usage |
|-------|------|--------|-------|
| H1 | 36px | Bold (700) | Page titles |
| H2 | 30px | Bold (700) | Section headers |
| H3 | 24px | Semibold (600) | Card titles |
| H4 | 20px | Semibold (600) | Sub-section |
| Body L | 18px | Regular (400) | Content large |
| Body M | 16px | Regular (400) | Default body |
| Body S | 14px | Regular (400) | Secondary info |
| Caption | 12px | Medium (500) | Labels, meta |

**Why Plus Jakarta Sans?**
- Geometric, modern feel — matches tech/marketplace identity
- Excellent readability at small sizes (product cards, prices)
- Wide weight range (200-800) for strong visual hierarchy
- Open source, fast loading via Google Fonts

---

## 3. Gradients

```css
/* Primary Gradient — Hero sections, auth backgrounds */
.blukios-gradient {
  background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 60%, #60a5fa 100%);
}

/* Legacy gradient alias */
.blue-gradient {
  background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #3b82f6 100%);
}
```

---

## 4. Spacing & Radius

| Element | Radius | Notes |
|---------|--------|-------|
| Cards | `20px` | Main content cards |
| Buttons | `full` (pill) | Primary actions |
| Input fields | `18px` | Form elements |
| Icons background | `14px-full` | Depending on size |
| Modal/Dialog | `20px` | Popover & sheets |

---

## 5. Shadows

```css
--shadow-soft: 0 1px 3px 0 rgb(0 0 0 / 0.06);
--shadow-floating: 0 20px 25px -5px rgb(0 0 0 / 0.08);
```

---

## 6. Dark Mode

Activated via `.dark` class on `<html>`. All custom properties auto-switch.

Key differences:
- Background shifts to deep navy (`#0F172A`)
- Cards use slate-800 (`#1E293B`)  
- Primary blue lightens to `#60A5FA` for better contrast
- Borders use white alpha (`rgba(255,255,255,0.1)`)

---

## 7. Component Patterns

### Interactive Cards
```html
<div class="hover-glow-blue">...</div>
```
Adds hover: border glow, float up, text color change.

### Form Inputs
```html
<label class="group relative">
  <div class="input-icon">...</div>
  <p class="input-placeholder">Label</p>
  <input class="custom-input" />
</label>
```
76px tall, floating label, icon prefix with border separator.

---

## 8. Brand Voice (for copy/UI text)

| Attribute | Style |
|-----------|-------|
| Tone | Ramah, profesional, terpercaya |
| Language | Bahasa Indonesia (primary), English (technical/labels) |
| Personality | Helpful, modern, efficient |
| Chatbot persona | "Ri" — friendly virtual assistant |
