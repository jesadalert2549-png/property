export default async function run(page) {
  await page.setViewportSize({ width: 390, height: 844 });

  // 1. Property Detail #3
  await page.goto('http://localhost/kasatest/property.php?id=3');
  await page.waitForTimeout(300);
  try {
    const btn = page.locator('button:has-text("ยอมรับ"), button:has-text("ใช้เฉพาะคุกกี้ที่จำเป็น")').first();
    if (await btn.isVisible()) {
      await btn.click();
      await page.waitForTimeout(100);
    }
  } catch (e) { }
  await page.screenshot({ path: 'qa-mobile-property-3.png' });

  // 2. Projects list
  await page.goto('http://localhost/kasatest/project.php');
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'qa-mobile-projects.png' });

  // 3. Login
  await page.goto('http://localhost/kasatest/login.php');
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'qa-mobile-login.png' });

  // 4. Submit (with auth)
  await page.goto('http://localhost/kasatest/qa_login_bridge.php?role=admin&to=' + encodeURIComponent('submit.php'));
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'qa-mobile-submit.php.png' });

  // 5. Dashboard (with auth)
  await page.goto('http://localhost/kasatest/qa_login_bridge.php?role=admin&to=' + encodeURIComponent('dashboard.php'));
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'qa-mobile-dashboard.png' });

  return { ok: true };
}
