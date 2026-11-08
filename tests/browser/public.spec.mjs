import { test, expect } from '@playwright/test';

test('the storefront and authentication screens render on the actual browser', async ({ page }) => {
  await page.goto('/');
  await expect(page.getByRole('heading', { name: /Find the right prompt/i })).toBeVisible();
  await expect(page.locator('header .brand')).toBeVisible();
  await expect(page.locator('form[role="search"] input')).toBeVisible();

  await page.goto('/prompts');
  await expect(page.getByRole('heading', { name: /AI prompt marketplace/i })).toBeVisible();

  await page.goto('/login');
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  await expect(page.locator('#password')).toHaveAttribute('type','password');
  await page.locator('[data-password-toggle="password"]').click();
  await expect(page.locator('#password')).toHaveAttribute('type','text');

  await page.goto('/register');
  await expect(page.getByRole('heading', { name: 'Create an account' })).toBeVisible();
});

test('a new customer can register, browse account, switch theme and create a support ticket', async ({ page }) => {
  await page.goto('/register');
  const email = 'acceptance-' + Date.now() + '-' + Math.floor(Math.random()*1000000) + '@example.test';
  await page.locator('#name').fill('CI Customer');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill('SecurePass12345');
  await page.locator('#password_confirmation').fill('SecurePass12345');
  await page.getByRole('button', { name: 'Create account' }).click();

  await expect(page).toHaveURL(/\/account$/);
  await expect(page.getByRole('heading', { name:'Account dashboard' })).toBeVisible();
  const screenWidth = await page.evaluate(() => ({ viewport: window.innerWidth, body: document.body.scrollWidth, html: document.documentElement.scrollWidth }));
  expect(screenWidth.html).toBeLessThanOrEqual(screenWidth.viewport + 3);
  await page.locator('[data-theme-toggle]').first().click();
  await expect(page.locator('html')).toHaveAttribute('data-theme','dark');

  await page.goto('/account/support');
  await page.getByRole('button',{ name:/Open ticket/i }).click();
  await page.locator('#ticket-subject').fill('Browser acceptance question');
  await page.locator('#ticket-body').fill('Can I access an approved free prompt after checkout?');
  await page.getByRole('button',{name:'Open ticket'}).last().click();
  await expect(page).toHaveURL(/\/support\/tickets\/\d+$/);
  await expect(page.getByText('Browser acceptance question')).toBeVisible();
});

test('locale URLs retain supported languages and block unknown codes', async ({ page }) => {
  await page.goto('/km');
  await expect(page.locator('html')).toHaveAttribute('lang','km');
  await page.goto('/ar/prompts');
  await expect(page.locator('html')).toHaveAttribute('lang','ar');
  await page.goto('/xx/prompts');
  await expect(page.getByText(/404|not found/i).first()).toBeVisible();
});

test('password visibility uses accessible eye and eye-off icons on all auth screens', async ({ page }) => {
  for (const [route, fields] of [
    ['/login', ['password']],
    ['/register', ['password', 'password_confirmation']],
    ['/reset-password/ci-dummy-token', ['password', 'password_confirmation']],
  ]) {
    await page.goto(route);
    for (const fieldName of fields) {
      const field = page.locator('#' + fieldName);
      const toggle = page.locator('[data-password-toggle="' + fieldName + '"]');
      await expect(field).toHaveAttribute('type', 'password');
      await expect(toggle).toHaveAttribute('aria-controls', fieldName);
      await expect(toggle).toHaveAttribute('aria-label', 'Show password');
      await expect(toggle).toHaveAttribute('aria-pressed', 'false');
      await expect(toggle.locator('svg.eye-icon-show')).toBeVisible();
      await expect(toggle.locator('svg.eye-icon-hide')).toBeHidden();

      await field.fill('ExampleSecure12345');
      await toggle.click();
      await expect(field).toHaveAttribute('type', 'text');
      await expect(toggle).toHaveAttribute('aria-label', 'Hide password');
      await expect(toggle).toHaveAttribute('aria-pressed', 'true');
      await expect(toggle.locator('svg.eye-icon-show')).toBeHidden();
      await expect(toggle.locator('svg.eye-icon-hide')).toBeVisible();

      await toggle.focus();
      await page.keyboard.press('Enter');
      await expect(field).toHaveAttribute('type', 'password');
      await expect(toggle).toHaveAttribute('aria-label', 'Show password');
      await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    }
  }
});

test('login recovery link shares the remember-me row above the sign-in button on desktop and mobile', async ({ page }) => {
  for (const width of [1280, 375]) {
    await page.setViewportSize({ width, height: 850 });
    await page.goto('/login');
    const options = page.locator('.auth-options');
    const remember = options.getByRole('checkbox', { name: 'Remember me' });
    const recovery = options.getByRole('link', { name: 'Forgot your password?' });
    const submit = page.getByRole('button', { name: 'Sign in' });
    await expect(remember).toBeVisible();
    await expect(recovery).toHaveAttribute('href', /\/forgot-password$/);
    await expect(recovery).toBeVisible();
    const a = await remember.boundingBox();
    const b = await recovery.boundingBox();
    const c = await submit.boundingBox();
    expect(a).not.toBeNull();
    expect(b).not.toBeNull();
    expect(c).not.toBeNull();
    expect(Math.abs(a.y - b.y)).toBeLessThan(12);
    expect(a.x + a.width).toBeLessThan(b.x);
    expect(b.y + b.height).toBeLessThan(c.y);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width + 2);
  }
});
