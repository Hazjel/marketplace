import { test as base, expect } from '@playwright/test'

export const test = base.extend<{ networkGuard: void }>({
  networkGuard: [async ({ context, baseURL }, use) => {
    const origin = new URL(baseURL!).origin
    await context.routeWebSocket('**', socket => socket.close())
    await context.route('**/*', async (route) => {
      if (new URL(route.request().url()).origin === origin) {
        await route.continue()
      } else {
        await route.abort('blockedbyclient')
      }
    })
    await use()
  }, { auto: true }],
})

export { expect }
