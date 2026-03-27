# Mobile Blue Design System

**Version:** 1.0.0
**Platform:** Flutter (Mobile)
**Style:** Modern, Clean, Trustworthy (Adapted from Web)

---

## 1. Color Palette (`AppColors`)

### Primary
- **Blue**: `0xFF0F52BA` (Primary Brand)
- **Midnight**: `0xFF000926` (Text/Dark Background)
- **Soft Blue**: `0xFFD6E6F3` (Accents)

### Semantic
- **Success**: `0xFF10B981`
- **Warning**: `0xFFEAB308`
- **Error**: `0xFFEF4444`
- **Info**: `0xFF3B82F6`

### Neutrals
- **Background**: `0xFFF8FAFC`
- **Surface**: `0xFFFFFFFF`
- **Border**: `0xFFE2E8F0`
- **Muted Text**: `0xFF64748B`

---

## 2. Typography (`AppTypography`)

**Font Family:** 'Lexend Deca'

| Role | Flutter Style | Size | Weight | Line Height |
|------|---------------|------|--------|-------------|
| **H1** | `displayLarge` | 36.0 | Bold (700) | 1.1 |
| **H2** | `displayMedium` | 30.0 | Bold (700) | 1.2 |
| **H3** | `displaySmall` | 24.0 | SemiBold (600) | 1.2 |
| **H4** | `headlineMedium` | 20.0 | SemiBold (600) | 1.3 |
| **Body L**| `bodyLarge` | 18.0 | Regular (400) | 1.6 |
| **Body M**| `bodyMedium` | 16.0 | Regular (400) | 1.5 |
| **Body S**| `bodySmall` | 14.0 | Regular (400) | 1.5 |
| **Caption**| `labelSmall` | 12.0 | Medium (500) | 1.4 |

---

## 3. Components

### Buttons (`CustomButton`)
- **Primary**: Solid Blue background, White text. Rounded 12px.
- **Outlined**: Transparent background, Blue border, Blue text. Rounded 12px.
- **States**: Loading state replaces text with `CircularProgressIndicator`.

### Inputs (`CustomTextField`)
- **Style**: Filled `0xFFFFFFFF`, Rounded 12px border.
- **Focus**: Blue border (2px width).
- **Error**: Red border.
- **Label**: Top outside label for better readability on mobile.

### Cards
- **Style**: White background, Soft Shadow (`Blur Radius: 4, Offset: 0,2`).
- **Radius**: Rounded 12px.

---

## 4. Spacing
- **Standard Padding**: 16.0 (Mobile), 24.0 (Tablet).
- **Gaps**: 8.0, 16.0, 24.0, 32.0.
