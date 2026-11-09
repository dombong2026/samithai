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
});