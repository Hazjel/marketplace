import { test, expect } from './fixtures'

test('buyer finds a seeded product, signs in and adds it to the cart', async ({ page }) => {
  await page.goto('/')
  const search = page.getByPlaceholder('Search any products')
  await search.fill('E2E Notebook')
  await search.press('Enter')
  await expect(page).toHaveURL(/\/search\?q=/)
  await page.getByRole('link', { name: /E2E Notebook/ }).first().click()
  await expect(page).toHaveURL(/\/product\/e2e-notebook$/)
  await expect(page.getByRole('heading', { name: 'E2E Notebook', exact: true })).toBeVisible()

  await page.goto('/auth/login')
  await page.getByPlaceholder('Masukkan email kamu').fill('buyer@e2e.test')
  await page.locator('#passwordInput').fill('password')
  await page.getByRole('button', { name: 'Masuk', exact: true }).click()
  await expect(page).toHaveURL(/\/e2e-buyer\/dashboard$/)

  await page.goto('/product/e2e-notebook')
  await page.getByRole('button', { name: 'Keranjang', exact: true }).click()
  await expect(page.getByText('Produk berhasil ditambahkan ke keranjang')).toBeVisible()
  await page.goto('/cart')
  await expect(page.getByText('E2E Notebook', { exact: true })).toBeVisible()
  await page.reload()
  await expect(page.getByText('E2E Notebook', { exact: true })).toBeVisible()
})
