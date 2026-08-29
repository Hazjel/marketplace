import js from '@eslint/js'
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting'
import globals from 'globals'

export default [
  {
    name: 'app/files-to-lint',
    files: ['**/*.{js,mjs,jsx}']
  },

  {
    name: 'app/ignores',
    // *.vue tidak di-lint sama sekali -- permintaan eksplisit user.
    ignores: ['**/dist/**', '**/dist-ssr/**', '**/coverage/**', '**/*.vue']
  },

  {
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node
      }
    }
  },

  js.configs.recommended,

  skipFormatting,

  {
    rules: {
      'no-unused-vars': 'warn'
    }
  }
]
