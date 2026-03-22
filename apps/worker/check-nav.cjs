const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'], ignoreHTTPSErrors: true });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 }, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();

  await page.goto('https://xpmgateway.ddev.site/admin/sign/in', { waitUntil: 'networkidle' });
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin123');
  await page.click('button[type="submit"]');
  await page.waitForURL(/admin/, { timeout: 10000 });

  // Check all nav links
  const navLinks = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('.nav-link')).map(function(a) {
      return { text: a.textContent.trim(), href: a.href };
    });
  });
  console.log('Nav links:', JSON.stringify(navLinks, null, 2));

  // Try clicking Joby nav item
  for (const link of navLinks) {
    if (link.text.includes('Joby')) {
      console.log('Navigating to Joby link:', link.href);
      await page.goto(link.href, { waitUntil: 'networkidle' });
      console.log('Actual URL:', page.url());
      const bodyText = await page.evaluate(function() { return document.body.innerText.substring(0, 500); });
      console.log('Body text:', bodyText);
      break;
    }
  }

  // Try direct routes
  const routes = [
    '/admin/jobs',
    '/admin/job',
    '/admin/Jobs',
  ];
  for (const route of routes) {
    await page.goto('https://xpmgateway.ddev.site' + route, { waitUntil: 'networkidle' });
    console.log('Route:', route, '-> URL:', page.url(), '-> status check');
    const hasError = await page.evaluate(function() {
      return document.body.innerText.includes('Cannot load presenter') || document.body.innerText.includes('404');
    });
    console.log('  Has error:', hasError);
  }

  await browser.close();
})().catch(console.error);
