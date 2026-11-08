export default async function run(page) {
  // Desktop
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('http://localhost/kasatest/search.php');
  await page.waitForTimeout(300);
  try {
    const btn = page.locator('button:has-text("ยอมรับ"), button:has-text("ใช้เฉพาะคุกกี้ที่จำเป็น")').first();
    if (await btn.isVisible()) {
      await btn.click();
      await page.waitForTimeout(150);
    }
  } catch (e) { }
  await page.screenshot({ path: 'qa-search-desktop.png' });

  // Mobile
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('http://localhost/kasatest/search.php');
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'qa-search-mobile.png' });

  return { ok: true };
}
