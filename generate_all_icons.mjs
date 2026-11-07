import fs from 'fs';
export default async function run(page) {
  const buf = fs.readFileSync('images/kasa_logo.png');
  const base64 = buf.toString('base64');
  const dataUrl = `data:image/png;base64,${base64}`;

  const generated = await page.evaluate(async (src) => {
    return new Promise((resolve) => {
      const img = new Image();
      img.onload = () => {
        function renderToSize(targetSize) {
          const c = document.createElement('canvas');
          c.width = targetSize;
          c.height = targetSize;
          const ctx = c.getContext('2d');

          const maxDim = Math.max(img.width, img.height);
          const padding = Math.round(targetSize * 0.08);
          const availSize = targetSize - padding * 2;
          const scale = availSize / maxDim;
          const drawW = Math.round(img.width * scale);
          const drawH = Math.round(img.height * scale);
          const drawX = Math.round((targetSize - drawW) / 2);
          const drawY = Math.round((targetSize - drawH) / 2);

          ctx.drawImage(img, drawX, drawY, drawW, drawH);
          return c.toDataURL('image/png').replace(/^data:image\/png;base64,/, '');
        }

        resolve({
          s16: renderToSize(16),
          s32: renderToSize(32),
          s48: renderToSize(48),
          s96: renderToSize(96),
          s128: renderToSize(128),
          s180: renderToSize(180),
          s192: renderToSize(192),
          s512: renderToSize(512),
        });
      };
      img.src = src;
    });
  }, dataUrl);

  // Write all icon sizes
  fs.writeFileSync('images/favicon-16x16.png', Buffer.from(generated.s16, 'base64'));
  fs.writeFileSync('images/favicon-32x32.png', Buffer.from(generated.s32, 'base64'));
  fs.writeFileSync('images/favicon-48x48.png', Buffer.from(generated.s48, 'base64'));
  fs.writeFileSync('images/favicon-96x96.png', Buffer.from(generated.s96, 'base64'));
  fs.writeFileSync('images/apple-touch-icon.png', Buffer.from(generated.s180, 'base64'));
  fs.writeFileSync('apple-touch-icon.png', Buffer.from(generated.s180, 'base64'));
  fs.writeFileSync('images/android-chrome-192x192.png', Buffer.from(generated.s192, 'base64'));
  fs.writeFileSync('images/android-chrome-512x512.png', Buffer.from(generated.s512, 'base64'));
  fs.writeFileSync('favicon.png', Buffer.from(generated.s128, 'base64'));
  fs.writeFileSync('images/favicon.png', Buffer.from(generated.s128, 'base64'));

  // favicon.ico (modern browsers accept PNG format inside or as favicon.ico)
  fs.writeFileSync('favicon.ico', Buffer.from(generated.s32, 'base64'));
  fs.writeFileSync('images/favicon.ico', Buffer.from(generated.s32, 'base64'));

  // SVG favicons
  const svgContent = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192" width="100%" height="100%">
  <image href="data:image/png;base64,${generated.s192}" width="192" height="192" />
</svg>`;
  fs.writeFileSync('favicon.svg', svgContent);
  fs.writeFileSync('images/favicon.svg', svgContent);

  return { success: true };
}
