// Responsive smoke test for the shared mockup site renderer.
//
// Usage: node tests/Browser/responsive-smoke.cjs <dir-with-html-files> [screenshot-dir]
// Prints one JSON line per file × width. Driven by
// tests/Feature/MockupResponsiveSmokeTest.php, which renders the pages and
// asserts on the result.
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const WIDTHS = [390, 768, 1024, 1440];

(async () => {
  const [dir, shots] = process.argv.slice(2);
  const files = fs.readdirSync(dir).filter(f => f.endsWith('.html')).sort();
  const browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  const page = await browser.newPage();

  for (const file of files) {
    for (const width of WIDTHS) {
      await page.setViewport({ width, height: 900 });
      await page.goto('file://' + path.resolve(dir, file).replace(/\\/g, '/'), { waitUntil: 'load' });

      const result = await page.evaluate(() => {
        const de = document.documentElement;
        const vw = window.innerWidth;
        const main = document.querySelector('main');
        const sections = main ? [...main.children].filter(el => el.getBoundingClientRect().height > 0) : [];
        // Anything painted past the right edge, even if a parent clips it.
        const overflowing = [...document.querySelectorAll('body *')]
          .filter(el => el.getBoundingClientRect().right > vw + 1)
          .slice(0, 5)
          .map(el => el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).join('.') : ''));
        // Not blank: the viewport's centre lands on real content, not the bare body.
        const centre = document.elementFromPoint(vw / 2, 300);

        return {
          scrollWidth: de.scrollWidth,
          innerWidth: vw,
          overflowing,
          sections: sections.length,
          textLength: (main ? main.innerText : '').trim().length,
          blank: !centre || centre === document.body || centre === de,
          height: de.scrollHeight,
        };
      });

      if (shots) {
        await page.screenshot({ path: path.join(shots, `${path.basename(file, '.html')}-${width}.png`), fullPage: true });
      }

      console.log(JSON.stringify({ file, width, ...result }));
    }
  }

  await browser.close();
})().catch(error => {
  console.error(error.message);
  process.exit(1);
});
