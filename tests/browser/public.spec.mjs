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
  // The compact mobile workspace menu must be closed by default,
  // keyboard/touch accessible when opened, and reset on desktop.
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/account');
  const mobileNavigation = page.locator('[data-workspace-mobile-nav]');
  await expect(mobileNavigation).toHaveJSProperty('open', false);
  await page.locator('.workspace-nav-trigger').click();
  await expect(mobileNavigation).toHaveJSProperty('open', true);
  const accountNav = page.locator('nav[aria-label="Account navigation"]');
  const collection = accountNav.locator('[data-nav-group="collection"]');
  const purchases = accountNav.locator('[data-nav-group="purchases"]');
  const help = accountNav.locator('[data-nav-group="account-care"]');
  await expect(collection).toHaveAttribute('open', '');
  await expect(accountNav.locator('[data-nav-group]')).toHaveCount(3);
  await expect(accountNav.getByRole('link', { name: 'Saved prompts' })).toBeVisible();
  await purchases.locator('summary').click();
  await expect(purchases).toHaveAttribute('open', '');
  await expect(accountNav.getByRole('link', { name: 'My orders' })).toBeVisible();
  await help.locator('summary').click();
  await expect(help).toHaveAttribute('open', '');
  await expect(accountNav.getByRole('link', { name: 'Account settings' })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(393);
  await page.setViewportSize({ width: 1280, height: 850 });
  await expect(mobileNavigation).toHaveJSProperty('open', true);
  await page.goto('/account');
  await expect(page.locator('.workspace-nav-trigger')).toBeHidden();
  await expect(page.locator('nav[aria-label="Account navigation"]').getByRole('link', { name: 'Saved prompts' })).toBeVisible();

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

test('Samith AI icon and theme-safe header branding render on storefront and auth screens', async ({ page, request }) => {
  for (const file of ['/favicon.svg','/branding/samith-ai-logo-light.svg','/branding/samith-ai-logo-dark.svg']) {
    const response = await request.get(file);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('svg');
    expect(await response.text()).toContain('<svg');
  }
  for (const route of ['/','/login','/register','/forgot-password']) {
    await page.goto(route);
    await expect(page.locator('link[rel="icon"][type="image/svg+xml"]')).toHaveAttribute('href', /\/favicon\.svg$/);
    const brand=page.locator('a.brand-with-logo').first();
    await expect(brand).toBeVisible();
    await expect(brand.locator('img.brand-logo-light')).toHaveAttribute('src', /samith-ai-logo-light\.svg$/);
    await expect(brand.locator('img.brand-logo-dark')).toHaveAttribute('src', /samith-ai-logo-dark\.svg$/);
    await expect(brand).toHaveAttribute('aria-label', 'Samith AI');
    await page.evaluate(() => document.documentElement.setAttribute('data-theme','dark'));
    await expect(brand.locator('img.brand-logo-dark')).toBeVisible();
    await expect(brand.locator('img.brand-logo-light')).toBeHidden();
    await page.evaluate(() => document.documentElement.setAttribute('data-theme','light'));
    await expect(brand.locator('img.brand-logo-light')).toBeVisible();
  }
});


test('trending discovery is responsive and saved prompts require authentication', async ({ page }) => {
  for (const width of [1280, 390]) {
    await page.setViewportSize({ width, height: 800 });
    await page.goto('/prompts?sort=most_saved');
    await expect(page.locator('select[name="sort"]')).toHaveValue('most_saved');
    await expect(page.getByRole('heading', { name: 'AI prompt marketplace' })).toBeVisible();

    const overflow = await page.evaluate(() => ({
      html: document.documentElement.scrollWidth,
      viewport: window.innerWidth,
    }));
    expect(overflow.html).toBeLessThanOrEqual(overflow.viewport + 3);

    await page.goto('/account/saved');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  }
});


test('shared workspace header search, sidebar visibility and icon profile menu work', async ({ page }) => {
  await page.goto('/register');
  const email = 'header-qa-' + Date.now() + '-' + Math.floor(Math.random() * 1000000) + '@example.test';
  await page.locator('#name').fill('Header QA Customer');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill('SecurePass12345');
  await page.locator('#password_confirmation').fill('SecurePass12345');
  await page.getByRole('button', { name: 'Create account' }).click();
  await expect(page).toHaveURL(/\/account$/);

  const sidebar = page.locator('#workspace-sidebar');
  const sidebarToggle = page.locator('[data-workspace-sidebar-toggle]');
  const search = page.locator('[data-workspace-search-input]');
  const profile = page.locator('.workspace-profile-trigger');

  await expect(sidebarToggle).toBeVisible();
  await expect(search).toBeVisible();
  await expect(profile).toBeVisible();
  await expect(page.locator('header.workspace-top .workspace-breadcrumb')).toHaveCount(0);
  await expect(page.locator('.workspace-aside-footer')).toHaveCount(0);

  await search.fill('my library');
  const libraryResult = page.locator('[data-workspace-search-results] a[href$="/account/library"]');
  await expect(libraryResult).toBeVisible();
  await search.press('Escape');
  await expect(page.locator('[data-workspace-search-results]')).toBeHidden();

  await sidebarToggle.click();
  await expect(page.locator('.workspace.workspace-sidebar-hidden')).toHaveCount(1);
  await expect(sidebarToggle).toHaveAttribute('aria-label', 'Show sidebar');
  await expect(sidebar).toBeHidden();
  await sidebarToggle.click();
  await expect(sidebar).toBeVisible();
  await expect(sidebarToggle).toHaveAttribute('aria-label', 'Hide sidebar');

  await profile.click();
  const dropdown = page.locator('.workspace-profile-dropdown');
  await expect(dropdown).toBeVisible();
  await expect(dropdown.locator('a svg')).toHaveCount(6);
  await expect(dropdown.getByRole('button', { name: 'Sign out' }).locator('svg')).toHaveCount(1);
  await page.keyboard.press('Escape');
  await expect(dropdown).toBeHidden();
});


test('workspace header refinement has no stray character and flags all available languages', async ({ page }) => {
  await page.goto('/register');
  const email = 'toolbar-qa-' + Date.now() + '-' + Math.floor(Math.random() * 1000000) + '@example.test';
  await page.locator('#name').fill('Header Refinement QA');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill('SecurePass12345');
  await page.locator('#password_confirmation').fill('SecurePass12345');
  await page.getByRole('button', { name: 'Create account' }).click();
  await expect(page).toHaveURL(/\/account$/);

  for (const width of [1280, 390]) {
    await page.setViewportSize({ width, height: 800 });
    await page.goto('/account');
    expect(await page.locator('.workspace-main').evaluate(el => [...el.childNodes].filter(n => n.nodeType === Node.TEXT_NODE).every(n => n.textContent.trim() !== '>'))).toBe(true);
    await expect(page.locator('.workspace-top-site')).toHaveAttribute('aria-label', 'Marketplace');
    await expect(page.locator('.workspace-top-site svg')).toBeVisible();

    const controls = ['.workspace-sidebar-toggle', '.workspace-top-site', '.workspace-theme-switch', '.workspace-language-trigger'];
    for (const selector of controls) {
      const color = await page.locator('header.workspace-top ' + selector).evaluate(el => getComputedStyle(el).backgroundColor);
      expect(color).toBe('rgba(0, 0, 0, 0)');
    }
    await expect(page.locator('.workspace-profile-trigger')).toBeVisible();
    await expect(page.locator('.workspace-language-flag').first()).toBeVisible();

    await page.locator('.workspace-language-trigger').click();
    const options = page.locator('.workspace-language-list a');
    await expect(options).toHaveCount(13);
    await expect(options.locator('.workspace-language-flag')).toHaveCount(13);
    await expect(page.locator('.workspace-language-list a[aria-current="true"]')).toHaveCount(1);
    await page.keyboard.press('Escape');
    expect(await page.evaluate(() => document.documentElement.scrollWidth))
      .toBeLessThanOrEqual(width + 3);
  }
});
