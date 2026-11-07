 import fs from 'fs';
export default async function run(page) {
  const buf = fs.readFileSync('images/Gemini_Generated_Image_a0be5ma0be5ma0be__1_-removebg-preview.png');
  const base64 = buf.toString('base64');
  const dataUrl = `data:image/png;base64,${base64}`;

  const generated = await page.evaluate(async (src) => {
    return new Promise((resolve) => {
      const img = new Image();
      img.onload = () => {
        // Gold building icon coordinates:
        const cropX = 222;
        const cropY = 167;
        const cropW = 100;
        const cropH = 88;

        const c = document.createElement('canvas');
        c.width = cropW;
        c.height = cropH;
        const ctx = c.getContext('2d');
        ctx.drawImage(img, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);

        const imgData = ctx.getImageData(0, 0, cropW, cropH);
        const d = imgData.data;

        for (let i = 0; i < d.length; i += 4) {
          const r = d[i];
          const g = d[i + 1];
          const b = d[i + 2];
          const a = d[i + 3];

          if (a > 0) {
            // Keep ONLY gold/yellow building pixels:
            // Notice: black bottom has r < 60, g < 60, b < 60
            const isGold = (r > 105 && g > 80 && r >= b + 15) && (r + g > 200);
            if (!isGold) {
              d[i + 3] = 0; // eliminate black base curve, dark letters, and speckles
            }
          }
        }
        ctx.putImageData(imgData, 0, 0);

        // Find precise bounding box of remaining gold pixels
        let minX = cropW, minY = cropH, maxX = 0, maxY = 0;
        for (let y = 0; y < cropH; y++) {
          for (let x = 0; x < cropW; x++) {
            const alpha = d[(y * cropW + x) * 4 + 3];
            if (alpha > 20) {
              if (x < minX) minX = x;
              if (x > maxX) maxX = x;
              if (y < minY) minY = y;
              if (y > maxY) maxY = y;
            }
          }
        }

        const cleanW = maxX - minX + 1;
        const cleanH = maxY - minY + 1;

        // Place neatly onto 128x128
        const c128 = document.createElement('canvas');
        c128.width = 128;
        c128.height = 128;
        const ctx128 = c128.getContext('2d');
        const targetX = Math.round((128 - cleanW) / 2);
        const targetY = Math.round((128 - cleanH) / 2);
        ctx128.drawImage(c, minX, minY, cleanW, cleanH, targetX, targetY, cleanW, cleanH);

        resolve({ iconPng: c128.toDataURL('image/png') });
      };
      img.src = src;
    });
  }, dataUrl);

  const iconBase64 = generated.iconPng.replace(/^data:image\/png;base64,/, '');
  fs.writeFileSync('favicon.png', Buffer.from(iconBase64, 'base64'));
  fs.writeFileSync('images/favicon.png', Buffer.from(iconBase64, 'base64'));

  return { success: true };
}
