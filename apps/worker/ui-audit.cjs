/**
 * Comprehensive UI Audit Script for xPmGateway Admin
 * Saves screenshots to /home/pedros89/projects/xPmGateway/adapter/storage/ui-review/
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'https://xpmgateway.ddev.site/admin';
const SCREENSHOT_DIR = '/home/pedros89/projects/xPmGateway/adapter/storage/ui-review';
const LOGIN = { user: 'admin', pass: 'admin123' };

const DESKTOP = { width: 1280, height: 800 };
const MOBILE = { width: 375, height: 812 };
const TABLET = { width: 768, height: 1024 };

const consoleErrors = [];
const findings = [];

async function shot(page, name) {
  const filepath = path.join(SCREENSHOT_DIR, `${name}.png`);
  await page.screenshot({ path: filepath, fullPage: true });
  console.log(`  [screenshot] ${name}.png`);
  return filepath;
}

async function login(page) {
  await page.goto(`${BASE_URL}/sign/in`, { waitUntil: 'networkidle' });
  await page.fill('input[name="username"]', LOGIN.user);
  await page.fill('input[name="password"]', LOGIN.pass);
  await page.click('button[type="submit"], input[type="submit"]');
  await page.waitForURL(/admin/, { timeout: 10000 });
  console.log('  [login] OK');
}

async function setViewport(page, vp) {
  await page.setViewportSize(vp);
}

async function checkContrast(page, selector, label) {
  try {
    const result = await page.evaluate((sel) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const style = window.getComputedStyle(el);
      return {
        color: style.color,
        background: style.backgroundColor,
        fontSize: style.fontSize,
      };
    }, selector);
    return result;
  } catch (e) {
    return null;
  }
}

async function getPageInfo(page) {
  return await page.evaluate(() => {
    return {
      title: document.title,
      h1: document.querySelector('h1, h2.page-title')?.textContent?.trim(),
      hasFlash: !!document.querySelector('.alert'),
      flashMessages: Array.from(document.querySelectorAll('.alert')).map(a => a.textContent.trim()),
      linkCount: document.querySelectorAll('a').length,
      formCount: document.querySelectorAll('form').length,
      tableCount: document.querySelectorAll('table').length,
    };
  });
}

async function checkEmptyStates(page) {
  return await page.evaluate(() => {
    const tbodies = document.querySelectorAll('tbody');
    const results = [];
    tbodies.forEach(tbody => {
      const rows = tbody.querySelectorAll('tr');
      results.push({ rowCount: rows.length });
    });
    return results;
  });
}

async function checkOverflow(page) {
  return await page.evaluate(() => {
    const issues = [];
    const elements = document.querySelectorAll('*');
    const bodyWidth = document.body.scrollWidth;
    const windowWidth = window.innerWidth;
    if (bodyWidth > windowWidth + 5) {
      issues.push({ type: 'horizontal-scroll', bodyWidth, windowWidth });
    }
    return issues;
  });
}

async function checkFocusIndicators(page) {
  return await page.evaluate(() => {
    const links = document.querySelectorAll('a, button, input, select, textarea');
    return { count: links.length };
  });
}

async function main() {
  console.log('Starting UI audit...');
  const browser = await chromium.launch({
    args: ['--ignore-certificate-errors', '--no-sandbox'],
    ignoreHTTPSErrors: true,
  });

  // =========================================================
  // SECTION 1: LOGIN PAGE
  // =========================================================
  console.log('\n[1] Login page');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push({ page: 'login', msg: msg.text() });
      }
    });

    await page.goto(`${BASE_URL}/sign/in`, { waitUntil: 'networkidle' });
    await shot(page, 'r01-login-desktop');

    // Check form structure
    const formInfo = await page.evaluate(() => {
      const inputs = Array.from(document.querySelectorAll('input')).map(i => ({
        type: i.type, name: i.name, id: i.id, hasLabel: !!document.querySelector(`label[for="${i.id}"]`)
      }));
      return { inputs };
    });
    console.log('  Login form inputs:', JSON.stringify(formInfo.inputs));

    // Test invalid login
    await page.fill('input[name="username"]', 'wrong');
    await page.fill('input[name="password"]', 'wrong');
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForTimeout(1500);
    await shot(page, 'r01b-login-error-desktop');

    // Mobile
    await page.setViewportSize(MOBILE);
    await page.goto(`${BASE_URL}/sign/in`, { waitUntil: 'networkidle' });
    await shot(page, 'r01c-login-mobile');

    await ctx.close();
  }

  // =========================================================
  // SECTION 2: DASHBOARD
  // =========================================================
  console.log('\n[2] Dashboard');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push({ page: 'dashboard', msg: msg.text() });
      }
    });

    await login(page);
    await page.waitForTimeout(2000);
    await shot(page, 'r02-dashboard-desktop');

    const info = await getPageInfo(page);
    console.log('  Dashboard info:', JSON.stringify(info));

    const overflow = await checkOverflow(page);
    if (overflow.length > 0) findings.push({ priority: 'P1', page: 'dashboard', issue: 'Horizontal overflow detected', data: overflow });

    // Check worker bar
    const workerBar = await page.$('#worker-status-bar');
    if (workerBar) {
      const barClass = await workerBar.getAttribute('class');
      console.log('  Worker bar class:', barClass);
    }

    // Expand worker bar
    await page.click('#ws-toggle');
    await page.waitForTimeout(500);
    await shot(page, 'r02b-dashboard-worker-expanded-desktop');

    // Mobile
    await page.setViewportSize(MOBILE);
    await page.waitForTimeout(300);
    await shot(page, 'r02c-dashboard-mobile');

    // Check sidebar on mobile
    const sidebarToggle = await page.$('.navbar-toggler');
    if (sidebarToggle) {
      await sidebarToggle.click();
      await page.waitForTimeout(400);
      await shot(page, 'r02d-sidebar-mobile');
    }

    // Tablet
    await page.setViewportSize(TABLET);
    await page.waitForTimeout(300);
    await shot(page, 'r02e-dashboard-tablet');

    await ctx.close();
  }

  // =========================================================
  // SECTION 3: JOB LIST
  // =========================================================
  console.log('\n[3] Job list');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push({ page: 'job-list', msg: msg.text() });
      }
    });

    await login(page);
    await page.goto(`${BASE_URL}/jobs`, { waitUntil: 'networkidle' });
    await shot(page, 'r03-job-list-desktop');

    const emptyState = await checkEmptyStates(page);
    console.log('  Job list rows:', JSON.stringify(emptyState));

    const overflow = await checkOverflow(page);
    if (overflow.length > 0) findings.push({ priority: 'P1', page: 'job-list', issue: 'Horizontal overflow', data: overflow });

    // Test filter form
    const filterRow = await page.$('.card-body.border-bottom');
    if (filterRow) {
      await shot(page, 'r03b-job-list-filters');
    }

    // Mobile
    await page.setViewportSize(MOBILE);
    await page.waitForTimeout(300);
    await shot(page, 'r03c-job-list-mobile');

    const mobileOverflow = await checkOverflow(page);
    if (mobileOverflow.length > 0) findings.push({ priority: 'P1', page: 'job-list-mobile', issue: 'Horizontal overflow on mobile', data: mobileOverflow });

    await ctx.close();
  }

  // =========================================================
  // SECTION 4: JOB DETAIL
  // =========================================================
  console.log('\n[4] Job detail');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push({ page: 'job-detail', msg: msg.text() });
      }
    });

    await login(page);
    await page.goto(`${BASE_URL}/jobs`, { waitUntil: 'networkidle' });

    // Try to click on the first job link
    const firstJobLink = await page.$('tbody td a');
    if (firstJobLink) {
      const href = await firstJobLink.getAttribute('href');
      console.log('  First job href:', href);
      await firstJobLink.click();
      await page.waitForLoadState('networkidle');
      await shot(page, 'r04-job-detail-desktop');

      // Mobile
      await page.setViewportSize(MOBILE);
      await page.waitForTimeout(300);
      await shot(page, 'r04b-job-detail-mobile');
    } else {
      console.log('  No jobs found, navigating to job list');
      await shot(page, 'r04-job-detail-empty');
    }

    await ctx.close();
  }

  // =========================================================
  // SECTION 5: JOB CREATE
  // =========================================================
  console.log('\n[5] Job create form');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push({ page: 'job-create', msg: msg.text() });
      }
    });

    await login(page);
    await page.goto(`${BASE_URL}/jobs/create`, { waitUntil: 'networkidle' });
    await shot(page, 'r05-job-create-desktop');

    // Check form accessibility
    const formInfo = await page.evaluate(() => {
      const labels = Array.from(document.querySelectorAll('label'));
      const inputs = Array.from(document.querySelectorAll('select, textarea, input:not([type="submit"])'));
      return {
        labelCount: labels.length,
        inputCount: inputs.length,
        labels: labels.map(l => ({ for: l.getAttribute('for'), text: l.textContent.trim() })),
      };
    });
    console.log('  Job create form info:', JSON.stringify(formInfo));

    // Try selecting a tool to load schema
    const toolSelect = await page.$('select[name="tool_id"]');
    if (toolSelect) {
      const options = await page.evaluate(() => {
        const sel = document.querySelector('select[name="tool_id"]');
        return Array.from(sel.options).map(o => ({ value: o.value, text: o.text }));
      });
      console.log('  Tool options:', JSON.stringify(options));

      if (options.length > 1) {
        await page.selectOption('select[name="tool_id"]', options[1].value);
        await page.waitForTimeout(1000);
        await shot(page, 'r05b-job-create-with-schema');
      }
    }

    // Mobile
    await page.setViewportSize(MOBILE);
    await page.waitForTimeout(300);
    await shot(page, 'r05c-job-create-mobile');

    await ctx.close();
  }

  // =========================================================
  // SECTION 6: CLIENT LIST
  // =========================================================
  console.log('\n[6] Client list');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push({ page: 'clients', msg: msg.text() });
      }
    });

    await login(page);
    await page.goto(`${BASE_URL}/clients`, { waitUntil: 'networkidle' });
    await shot(page, 'r06-client-list-desktop');

    // Click into a client detail
    const firstClientLink = await page.$('tbody td a');
    if (firstClientLink) {
      await firstClientLink.click();
      await page.waitForLoadState('networkidle');
      await shot(page, 'r06b-client-detail-desktop');
    }

    // Client create form
    await page.goto(`${BASE_URL}/clients/create`, { waitUntil: 'networkidle' });
    await shot(page, 'r06c-client-create-desktop');

    // Mobile
    await page.setViewportSize(MOBILE);
    await page.goto(`${BASE_URL}/clients`, { waitUntil: 'networkidle' });
    await shot(page, 'r06d-client-list-mobile');

    await ctx.close();
  }

  // =========================================================
  // SECTION 7: USER MANAGEMENT
  // =========================================================
  console.log('\n[7] User management');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push({ page: 'users', msg: msg.text() });
      }
    });

    await login(page);
    await page.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle' });
    await shot(page, 'r07-user-list-desktop');

    await page.goto(`${BASE_URL}/users/create`, { waitUntil: 'networkidle' });
    await shot(page, 'r07b-user-create-desktop');

    await ctx.close();
  }

  // =========================================================
  // SECTION 8: SERVICE ACCOUNTS
  // =========================================================
  console.log('\n[8] Service accounts');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push({ page: 'service-accounts', msg: msg.text() });
      }
    });

    await login(page);
    await page.goto(`${BASE_URL}/service-accounts`, { waitUntil: 'networkidle' });
    await shot(page, 'r08-service-accounts-desktop');

    await page.goto(`${BASE_URL}/service-accounts/create`, { waitUntil: 'networkidle' });
    await shot(page, 'r08b-service-accounts-create-desktop');

    await ctx.close();
  }

  // =========================================================
  // SECTION 9: TOOLS
  // =========================================================
  console.log('\n[9] Tools');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push({ page: 'tools', msg: msg.text() });
      }
    });

    await login(page);
    await page.goto(`${BASE_URL}/tools`, { waitUntil: 'networkidle' });
    await shot(page, 'r09-tools-desktop');

    // Mobile
    await page.setViewportSize(MOBILE);
    await page.waitForTimeout(300);
    await shot(page, 'r09b-tools-mobile');

    await ctx.close();
  }

  // =========================================================
  // SECTION 10: TOKENS (via client)
  // =========================================================
  console.log('\n[10] Tokens');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push({ page: 'tokens', msg: msg.text() });
      }
    });

    await login(page);
    await page.goto(`${BASE_URL}/clients`, { waitUntil: 'networkidle' });

    // Try to navigate to tokens via Actions dropdown
    const actionBtn = await page.$('button.dropdown-toggle');
    if (actionBtn) {
      await actionBtn.click();
      await page.waitForTimeout(300);
      const tokenLink = await page.$('.dropdown-menu a[href*="token"]');
      if (tokenLink) {
        await tokenLink.click();
        await page.waitForLoadState('networkidle');
        await shot(page, 'r10-tokens-desktop');
      } else {
        console.log('  No token link found in dropdown');
        await shot(page, 'r10-tokens-no-link');
      }
    } else {
      console.log('  No action button found');
    }

    await ctx.close();
  }

  // =========================================================
  // SECTION 11: AUDIT LOG
  // =========================================================
  console.log('\n[11] Audit log');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push({ page: 'audit-log', msg: msg.text() });
      }
    });

    await login(page);
    await page.goto(`${BASE_URL}/audit-log`, { waitUntil: 'networkidle' });
    await shot(page, 'r11-audit-log-desktop');

    // Test date filter
    const dateFrom = await page.$('input[name="date_from"]');
    if (dateFrom) {
      await dateFrom.fill('2024-01-01');
      const dateTo = await page.$('input[name="date_to"]');
      if (dateTo) await dateTo.fill('2026-12-31');
      await page.click('button[type="submit"]');
      await page.waitForLoadState('networkidle');
      await shot(page, 'r11b-audit-log-filtered');
    }

    // Mobile
    await page.setViewportSize(MOBILE);
    await page.goto(`${BASE_URL}/audit-log`, { waitUntil: 'networkidle' });
    await shot(page, 'r11c-audit-log-mobile');

    const mobileOverflow = await checkOverflow(page);
    if (mobileOverflow.length > 0) {
      findings.push({ priority: 'P1', page: 'audit-log-mobile', issue: 'Horizontal overflow on mobile', data: mobileOverflow });
    }

    await ctx.close();
  }

  // =========================================================
  // SECTION 12: ACCESSIBILITY CHECKS
  // =========================================================
  console.log('\n[12] Accessibility checks');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();

    await login(page);

    // Check keyboard navigation on dashboard
    await page.goto(`${BASE_URL}`, { waitUntil: 'networkidle' });
    await page.keyboard.press('Tab');
    await page.waitForTimeout(200);
    await shot(page, 'r12a-keyboard-focus-first');

    await page.keyboard.press('Tab');
    await page.waitForTimeout(200);
    await shot(page, 'r12b-keyboard-focus-second');

    await page.keyboard.press('Tab');
    await page.waitForTimeout(200);
    await shot(page, 'r12c-keyboard-focus-third');

    // Check ARIA and semantic structure
    const a11yReport = await page.evaluate(() => {
      const report = {
        hasMain: !!document.querySelector('main'),
        hasNav: !!document.querySelector('nav, [role="navigation"]'),
        hasH1: !!document.querySelector('h1'),
        h2Count: document.querySelectorAll('h2').length,
        imagesWithoutAlt: Array.from(document.querySelectorAll('img:not([alt])')).length,
        buttonsWithoutText: Array.from(document.querySelectorAll('button')).filter(b => {
          return !b.textContent.trim() && !b.getAttribute('aria-label') && !b.getAttribute('title');
        }).length,
        inputsWithoutLabel: Array.from(document.querySelectorAll('input, select, textarea')).filter(i => {
          const id = i.id;
          const ariaLabel = i.getAttribute('aria-label');
          const ariaLabelledby = i.getAttribute('aria-labelledby');
          const hasLabel = id && !!document.querySelector(`label[for="${id}"]`);
          return !hasLabel && !ariaLabel && !ariaLabelledby;
        }).length,
        skipLink: !!document.querySelector('a[href="#main"], a[href="#content"]'),
        landmarkRoles: {
          aside: !!document.querySelector('aside'),
          header: !!document.querySelector('header, [role="banner"]'),
          footer: !!document.querySelector('footer, [role="contentinfo"]'),
        },
      };
      return report;
    });
    console.log('  A11y report:', JSON.stringify(a11yReport, null, 2));

    // Save a11y report
    fs.writeFileSync(
      path.join(SCREENSHOT_DIR, 'r-a11y-report.json'),
      JSON.stringify(a11yReport, null, 2)
    );

    // Check contrast on key elements
    const navLinkContrast = await checkContrast(page, '.nav-link', 'nav-link');
    console.log('  Nav link contrast:', JSON.stringify(navLinkContrast));

    await ctx.close();
  }

  // =========================================================
  // SECTION 13: SPECIFIC FOCUS AREAS
  // =========================================================
  console.log('\n[13] Focus areas - badges and status indicators');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();

    await login(page);
    await page.goto(`${BASE_URL}/jobs`, { waitUntil: 'networkidle' });

    // Zoom into filter bar
    await shot(page, 'r13a-job-filter-bar');

    // Check badge styles
    const badgeInfo = await page.evaluate(() => {
      const badges = Array.from(document.querySelectorAll('.badge'));
      return badges.map(b => ({
        text: b.textContent.trim(),
        classes: b.className,
        bgColor: window.getComputedStyle(b).backgroundColor,
        color: window.getComputedStyle(b).color,
      }));
    });
    console.log('  Badges found:', JSON.stringify(badgeInfo));

    await ctx.close();
  }

  // =========================================================
  // SECTION 14: CLIENT DETAIL & TOKENS
  // =========================================================
  console.log('\n[14] Client detail deep dive');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();

    await login(page);
    await page.goto(`${BASE_URL}/clients`, { waitUntil: 'networkidle' });

    const clientLink = await page.$('tbody td a');
    if (clientLink) {
      await clientLink.click();
      await page.waitForLoadState('networkidle');
      await shot(page, 'r14a-client-detail-desktop');

      const currentUrl = page.url();
      console.log('  Client detail URL:', currentUrl);
    }

    await ctx.close();
  }

  // =========================================================
  // SECTION 15: FORM VALIDATION STATES
  // =========================================================
  console.log('\n[15] Form validation states');
  {
    const ctx = await browser.newContext({ viewport: DESKTOP, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();

    await login(page);
    await page.goto(`${BASE_URL}/jobs/create`, { waitUntil: 'networkidle' });

    // Submit empty form to trigger validation
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForTimeout(500);
    await shot(page, 'r15a-job-create-validation-errors');

    await ctx.close();
  }

  // =========================================================
  // SECTION 16: WIDE DESKTOP
  // =========================================================
  console.log('\n[16] Wide desktop (1920px)');
  {
    const ctx = await browser.newContext({ viewport: { width: 1920, height: 1080 }, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();

    await login(page);
    await page.waitForTimeout(1000);
    await shot(page, 'r16a-dashboard-wide');

    await page.goto(`${BASE_URL}/jobs`, { waitUntil: 'networkidle' });
    await shot(page, 'r16b-job-list-wide');

    await ctx.close();
  }

  // =========================================================
  // FINALIZE
  // =========================================================
  await browser.close();

  // Save findings
  const report = {
    timestamp: new Date().toISOString(),
    consoleErrors,
    findings,
  };

  fs.writeFileSync(
    path.join(SCREENSHOT_DIR, 'r-console-errors.json'),
    JSON.stringify(report, null, 2)
  );

  console.log('\n[DONE]');
  console.log(`Console errors: ${consoleErrors.length}`);
  console.log(`Auto-findings: ${findings.length}`);
  console.log(`Screenshots saved to: ${SCREENSHOT_DIR}`);
}

main().catch(e => {
  console.error('Fatal error:', e);
  process.exit(1);
});
