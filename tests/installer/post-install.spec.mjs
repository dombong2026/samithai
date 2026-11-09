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

test('frontend page configuration dialogs, custom-page publication and notification auto-dismiss', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto('/login');
  await page.locator('#email').fill('initial-admin@example.test');
  await page.locator('#password').fill('VerySecureInitial12345');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);

  const notification = page.locator('.toast-stack .toast');
  await expect(notification).toHaveCount(1);
  await expect(notification).toHaveCount(0, { timeout: 8500 });

  await page.goto('/dashboard/appearance');
  const pages = page.locator('#frontend-pages');
  await expect(pages.getByRole('heading', { name: 'Frontend Pages' })).toBeVisible();
  await expect(pages.getByText('Global header & footer')).toBeVisible();
  await expect(pages.getByText('Browse prompts')).toBeVisible();
  await expect(pages.getByText('Published legal license')).toBeVisible();
  await page.locator('[data-frontend-dialog="frontend-config-home"]').click();
  const editor = page.locator('#frontend-config-home');
  await expect(editor).toBeVisible();
  await editor.getByLabel('Hero eyebrow').fill('CI Custom Hero Eyebrow');
  await editor.getByRole('button', { name: 'Save changes' }).click();
  await expect(page).toHaveURL(/\/dashboard\/appearance$/);
  await page.goto('/');
  await expect(page.getByText('CI Custom Hero Eyebrow')).toBeVisible();

  await page.goto('/dashboard/appearance');
  await page.locator('[data-frontend-dialog="frontend-config-home"]').click();
  await expect(page.locator('#frontend-config-home button[type="submit"]').first()).toBeEnabled();
  await editor.getByRole('button', { name: 'Reset defaults' }).click();
  await page.locator('#confirmation-modal').getByRole('button', { name: 'Confirm' }).click();
  await expect(page).toHaveURL(/\/dashboard\/appearance$/);
  await page.goto('/');
  await expect(page.getByText('Samith AI · Prompt marketplace')).toBeVisible();

  await page.goto('/dashboard/appearance');
  await pages.getByRole('button', { name: 'Add custom page' }).click();
  const create = page.locator('#frontend-create-page');
  await expect(create).toBeVisible();
  await create.getByLabel('Page title').fill('CI Custom Page');
  await create.getByLabel('Description').fill('CI page description');
  await create.getByLabel('Body HTML').fill('<p>First public page version</p>');
  await create.getByRole('button', { name: 'Create draft' }).click();
  await expect(page).toHaveURL(/\/dashboard\/appearance$/);
  const row = page.locator('.frontend-pages-table tr').filter({ hasText: 'CI Custom Page' });
  await expect(row).toBeVisible();
  await row.getByRole('button', { name: 'Configure' }).click();
  const popup = page.locator('dialog.frontend-config-dialog[open]').filter({ hasText: 'CI Custom Page' });
  await expect(popup.getByRole('button', { name: 'Publish' })).toBeVisible();
  await popup.getByRole('button', { name: 'Publish' }).click();
  await page.locator('#confirmation-modal').getByRole('button', { name: 'Confirm' }).click();
  await page.goto('/pages/ci-custom-page');
  await expect(page.getByRole('heading', { name: 'CI Custom Page' })).toBeVisible();
  await page.goto('/dashboard/appearance');
  await page.locator('.frontend-pages-table tr').filter({ hasText: 'CI Custom Page' }).getByRole('button', { name: 'Configure' }).click();
  await page.locator('dialog.frontend-config-dialog[open]').getByRole('button', { name: 'Delete' }).click();
  await page.locator('#confirmation-modal').getByRole('button', { name: 'Confirm' }).click();
  await expect(page.locator('.frontend-pages-table tr').filter({ hasText: 'CI Custom Page' })).toHaveCount(0);
});
