 export default async function run(page) {
  await page.goto('http://localhost/kasatest/test_login_bridge.php');
  await page.waitForTimeout(300);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('http://localhost/kasatest/admin/featured.php');
  await page.waitForTimeout(300);

  const wide = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('*'))
      .filter(el => el.getBoundingClientRect().right > window.innerWidth + 2)
      .map(el => ({
        tag: el.tagName,
        id: el.id,
        className: el.className,
        width: Math.round(el.getBoundingClientRect().width),
        right: Math.round(el.getBoundingClientRect().right)
      }))
      .slice(0, 10);
  });

  return wide;
}
