export default async function run(page) {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('http://localhost/kasatest/property.php?id=16');
  await page.waitForTimeout(400);
  try {
    const btn = page.locator('button:has-text("ยอมรับ"), button:has-text("ใช้เฉพาะคุกกี้ที่จำเป็น")').first();
    if (await btn.isVisible()) {
      await btn.click();
      await page.waitForTimeout(200);
    }
  } catch (e) { }
  await page.screenshot({ path: 'property-mobile-swipe.png' });
  return { ok: true, title: await page.title() };
}
