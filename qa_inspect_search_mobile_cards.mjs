export default async function run(page) {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('http://localhost/kasatest/search.php');
  await page.waitForTimeout(300);

  // Dismiss cookie banner
  try {
    const btn = page.locator('button:has-text("ยอมรับ"), button:has-text("ใช้เฉพาะคุกกี้ที่จำเป็น")').first();
    if (await btn.isVisible()) {
      await btn.click();
      await page.waitForTimeout(150);
    }
  } catch (e) { }

  // Scroll to listings section
  await page.evaluate(() => {
    const cards = document.querySelector('.search-results-grid');
    if (cards) cards.scrollIntoView({ block: 'start' });
  });
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'qa-search-mobile-cards.png' });

  return { ok: true };
}
