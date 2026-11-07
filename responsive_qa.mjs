export default async function run(page) {
  const results = [];
  for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }, { width: 320, height: 740 }]) {
    await page.setViewportSize(viewport);
    await page.waitForTimeout(250);
    results.push(await page.evaluate(() => ({
      viewport: innerWidth,
      documentWidth: document.documentElement.scrollWidth,
      bodyWidth: document.body.scrollWidth,
      horizontalOverflow: document.documentElement.scrollWidth > innerWidth,
      title: document.title
    })));
    if (viewport.width === 390) await page.screenshot({ path: 'responsive-mobile-qa.png', fullPage: true });
  }
  return results;
}
