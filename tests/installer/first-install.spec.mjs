import { test, expect } from '@playwright/test';
import { readFileSync, existsSync } from 'node:fs';

test('a fresh MySQL installation creates an admin once and locks installer', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveURL(/\/installer$/);
  await expect(page.getByRole('heading', { name:'Install Samith AI' })).toBeVisible();
  await expect(page.getByRole('button', { name:'Install Samith AI' })).toBeEnabled();

  const contents = readFileSync('.env','utf8');
  const token = contents.match(/^INSTALL_TOKEN=([a-f0-9]{48})$/m)?.[1];
  expect(token).toBeTruthy();

  await page.locator('#installation_token').fill(token);
  await page.locator('#app_url').fill('http://127.0.0.1:8010');
  await page.locator('#db_host').fill('127.0.0.1');
  await page.locator('#db_port').fill('3306');
  await page.locator('#db_database').fill('samith_installer');
  await page.locator('#db_username').fill('root');
  await page.locator('#db_password').fill('test_only_password');
  await page.locator('#admin_name').fill('CI Initial Administrator');
  await page.locator('#admin_email').fill('initial-admin@example.test');
  await page.locator('#admin_password').fill('VerySecureInitial12345');
  await page.locator('#admin_password_confirmation').fill('VerySecureInitial12345');
  await page.getByRole('button',{ name:'Install Samith AI' }).click();

  await expect(page).toHaveURL(/\/login$/);
  expect(existsSync('storage/app/installed.lock')).toBe(true);

  await page.goto('/installer');
  await expect(page).toHaveURL('http://127.0.0.1:8010/');
  await page.goto('/login');
  await page.locator('#email').fill('initial-admin@example.test');
  await page.locator('#password').fill('VerySecureInitial12345');
  await page.getByRole('button',{ name:'Sign in' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole('heading',{ name:'Marketplace overview' })).toBeVisible();
});
