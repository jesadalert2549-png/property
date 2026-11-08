export default async function run(page) {
  const issues = [];
  const consoleLogs = [];

  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleLogs.push({ text: msg.text(), location: msg.location() });
    }
  });

  const urlsToTest = [
    // Frontend
    { name: 'Home', url: 'index.php', auth: false },
    { name: 'Category', url: 'category.php?page=' + encodeURIComponent('ขายคอนโด'), auth: false },
    { name: 'Search', url: 'search.php', auth: false },
    { name: 'PropertyDetail', url: 'property.php?id=3', auth: false },
    { name: 'Projects', url: 'project.php', auth: false },
    { name: 'Advertise', url: 'advertise.php', auth: false },
    { name: 'Login', url: 'login.php', auth: false },
    { name: 'Register', url: 'register.php', auth: false },
    { name: 'Submit', url: 'submit.php', auth: 'admin' },
    { name: 'Dashboard', url: 'dashboard.php', auth: 'admin' },
    { name: 'EditListing', url: 'edit-listing.php?id=3', auth: 'admin' },

    // Admin
    { name: 'AdminIndex', url: 'admin/index.php', auth: 'admin' },
    { name: 'AdminAnalytics', url: 'admin/analytics.php', auth: 'admin' },
    { name: 'AdminAgents', url: 'admin/agents.php', auth: 'admin' },
    { name: 'AdminProjects', url: 'admin/projects.php', auth: 'admin' },
    { name: 'AdminSeo', url: 'admin/seo.php', auth: 'admin' },
    { name: 'AdminFeatured', url: 'admin/featured.php', auth: 'admin' },
    { name: 'AdminBanners', url: 'admin/banners.php', auth: 'admin' },
    { name: 'AdminCategoryImages', url: 'admin/category-images.php', auth: 'admin' },
    { name: 'AdminPartners', url: 'admin/partners.php', auth: 'admin' },
    { name: 'AdminVersion', url: 'admin/version.php', auth: 'admin' },
    { name: 'AdminOAuth', url: 'admin/oauth.php', auth: 'admin' },
    { name: 'AdminProfile', url: 'admin/profile.php', auth: 'admin' },
    { name: 'AdminAdd', url: 'admin/add.php', auth: 'admin' },
    { name: 'AdminEdit', url: 'admin/edit.php?id=3', auth: 'admin' },
  ];

  const viewports = [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'mobile', width: 390, height: 844 },
  ];

  for (const vp of viewports) {
    await page.setViewportSize({ width: vp.width, height: vp.height });

    for (const item of urlsToTest) {
      consoleLogs.length = 0;
      let targetUrl = 'http://localhost/kasatest/' + item.url;
      if (item.auth) {
        await page.goto(`http://localhost/kasatest/qa_login_bridge.php?role=${item.auth}&to=${encodeURIComponent(item.url)}`);
      } else {
        await page.goto(targetUrl);
      }
      await page.waitForTimeout(300);

      // Dismiss cookie banner
      try {
        const btn = page.locator('button:has-text("ยอมรับ"), button:has-text("ใช้เฉพาะคุกกี้ที่จำเป็น")').first();
        if (await btn.isVisible()) {
          await btn.click();
          await page.waitForTimeout(150);
        }
      } catch (e) { }

      const check = await page.evaluate((meta) => {
        const winW = window.innerWidth;
        const docW = document.documentElement.scrollWidth;
        const bodyW = document.body.scrollWidth;
        const overflow = docW > winW + 2 || bodyW > winW + 2;

        // Broken images check
        const imgs = Array.from(document.querySelectorAll('img'));
        const brokenImgs = imgs
          .filter(img => img.src && !img.src.startsWith('data:') && img.complete && img.naturalWidth === 0)
          .map(img => img.src.replace('http://localhost/kasatest/', ''));

        // Check for math division symbol ÷ used as location icon
        const bodyText = document.body.innerText;
        const hasDivSymbol = /÷\s*[A-Za-zก-๙]/.test(bodyText);

        // Check for NaN or undefined on page
        const hasBadVars = /\b(NaN|undefined|null)\b/.test(bodyText);

        return {
          overflow,
          winW,
          docW,
          bodyW,
          brokenImgs,
          hasDivSymbol,
          hasBadVars
        };
      }, item);

      if (check.overflow) {
        issues.push({ type: 'overflow', page: item.name, vp: vp.name, diff: check.docW - check.winW });
      }
      if (check.brokenImgs.length > 0) {
        issues.push({ type: 'brokenImages', page: item.name, vp: vp.name, imgs: check.brokenImgs });
      }
      if (check.hasDivSymbol) {
        issues.push({ type: 'divSymbol', page: item.name, vp: vp.name });
      }
      if (consoleLogs.length > 0) {
        issues.push({ type: 'consoleErrors', page: item.name, vp: vp.name, logs: consoleLogs.map(l => l.text) });
      }
    }
  }

  return { totalChecked: urlsToTest.length * viewports.length, issuesCount: issues.length, issues };
}
