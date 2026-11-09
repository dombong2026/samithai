import { test, expect } from '@playwright/test';
import { existsSync } from 'node:fs';

test('after process reload, the persisted MySQL admin can sign in and installer stays locked', async ({ page }) => {
  expect(existsSync('storage/app/installed.lock')).toBe(true);
  await page.goto('/installer');
  await expect(page).toHaveURL('http://127.0.0.1:8010/');
  await page.goto('/login');
  await page.locator('#email').fill('initial-admin@example.test');
  await page.locator('#password').fill('VerySecureInitial12345');
  await page.getByRole('button',{ name:'Sign in' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole('heading',{ name:'Marketplace dashboard' })).toBeVisible();
  const groups = page.locator('nav[aria-label="Administration navigation"] [data-nav-group]');
  await expect(groups).toHaveCount(5);
  const sidebarLinks = await page.locator('nav[aria-label="Administration navigation"] a[href]').evaluateAll(
    links => links.map(a => a.getAttribute('href'))
  );
  expect(sidebarLinks.length).toBe(new Set(sidebarLinks).size);
  const interest = page.locator('main.workspace-body #marketplace-interest');
  await expect(interest).toBeVisible();
  const insideMain = await interest.evaluate(element => Boolean(element.closest('main.workspace-body')));
  expect(insideMain).toBe(true);

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/dashboard');
  const mobileMenu = page.locator('[data-workspace-mobile-nav]');
  await expect(mobileMenu).toHaveJSProperty('open', false);
  await page.locator('.workspace-nav-trigger').click();
  await expect(mobileMenu).toHaveJSProperty('open', true);
  const adminNav = page.locator('nav[aria-label="Administration navigation"]');
  await expect(adminNav.getByRole('link', { name:'Overview' })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(393);
});