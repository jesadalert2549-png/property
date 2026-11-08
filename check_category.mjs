export default async function run(page) {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('http://localhost/kasatest/category.php?page=' + encodeURIComponent('ขายคอนโด'));
  // Dismiss cookie banner
  try {
    const btn = page.locator('button:has-text("ยอมรับ"), button:has-text("ใช้เฉพาะคุกกี้ที่จำเป็น")').first();
    if (await btn.isVisible()) {
      await btn.click();
      await page.waitForTimeout(300);
    }
  } catch (e) { }
  await page.evaluate(() => {
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(500);
  await page.screenshot({ path: 'c:/xampp/htdocs/kasatest/category-mobile-top.png' });
  return { ok: true };
}
