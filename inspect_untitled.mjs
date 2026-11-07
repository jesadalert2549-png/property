import fs from 'fs';
export default async function run(page) {
  const buf = fs.readFileSync('images/untitled_design.png');
  const base64 = buf.toString('base64');
  const dataUrl = `data:image/png;base64,${base64}`;

  const info = await page.evaluate(async (src) => {
    return new Promise((resolve) => {
      const img = new Image();
      img.onload = () => {
        const c = document.createElement('canvas');
        c.width = img.width;
        c.height = img.height;
        const ctx = c.getContext('2d');
        ctx.drawImage(img, 0, 0);
        const data = ctx.getImageData(0, 0, img.width, img.height).data;

        // Find transparent bounding box
        let minX = img.width, minY = img.height, maxX = 0, maxY = 0;
        for (let y = 0; y < img.height; y++) {
          for (let x = 0; x < img.width; x++) {
            const idx = (y * img.width + x) * 4;
            const a = data[idx + 3];
            if (a > 10) {
              if (x < minX) minX = x;
              if (x > maxX) maxX = x;
              if (y < minY) minY = y;
              if (y > maxY) maxY = y;
            }
          }
        }

        resolve({
          w: img.width,
          h: img.height,
          bounds: { minX, minY, maxX, maxY, contentW: maxX - minX + 1, contentH: maxY - minY + 1 }
        });
      };
      img.src = src;
    });
  }, dataUrl);

  return info;
}
