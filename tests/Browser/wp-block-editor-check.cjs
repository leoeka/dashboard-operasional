// Opens pages in a REAL WordPress Block Editor and reports every block the
// editor flags as invalid ("Attempt Block Recovery"), then saves each page,
// reloads it and checks the content is unchanged.
//
// Usage:
//   WP_URL=http://127.0.0.1:8890 WP_USER=claudetest WP_PASS=... \
//     node tests/Browser/wp-block-editor-check.cjs <postId> [<postId> ...]
//
// Prints one JSON line per page. `invalid` lists, for each invalid block, what
// core's save() expects vs what the post body holds — the exact diff to fix in
// ElementorPageBuilderService. Used to verify the V2 build on WordPress 7.1
// (see docs/HANDOFF.md).
const puppeteer = require('puppeteer');

const BASE = process.env.WP_URL || 'http://127.0.0.1:8890';

const inspect = () => {
  const invalid = [];
  const walk = list => list.forEach(block => {
    if (!block.isValid) {
      invalid.push({
        name: block.name,
        expected: wp.blocks.getSaveContent(block.name, block.attributes, block.innerBlocks).slice(0, 600),
        actual: String(block.originalContent || '').slice(0, 600),
      });
    }
    walk(block.innerBlocks);
  });
  const blocks = wp.data.select('core/block-editor').getBlocks();
  walk(blocks);
  return { count: blocks.length, invalid, content: wp.data.select('core/editor').getEditedPostContent() };
};

(async () => {
  const ids = process.argv.slice(2);
  const browser = await puppeteer.launch({ args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });

  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'networkidle2' });
  await page.type('#user_login', process.env.WP_USER || 'admin');
  await page.type('#user_pass', process.env.WP_PASS || '');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('#wp-submit')]);

  const open = async id => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`, { waitUntil: 'networkidle2', timeout: 120000 });
    await page.waitForFunction(() => window.wp && wp.data.select('core/block-editor').getBlocks().length > 0, { timeout: 120000 });
    return page.evaluate(inspect);
  };
  const norm = s => s.replace(/\s+/g, ' ').trim();

  for (const id of ids) {
    const before = await open(id);

    // Save unchanged (a no-op edit marks the post dirty), then reload.
    await page.evaluate(() => {
      const t = wp.data.select('core/editor').getEditedPostAttribute('title');
      wp.data.dispatch('core/editor').editPost({ title: t + ' ' });
      wp.data.dispatch('core/editor').editPost({ title: t });
      return wp.data.dispatch('core/editor').savePost();
    });
    await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost(), { timeout: 60000 });
    const saveError = await page.evaluate(() => {
      const notice = wp.data.select('core/notices').getNotices().find(n => n.status === 'error');
      return notice ? notice.content : null;
    });
    const after = await open(id);

    console.log(JSON.stringify({
      id,
      blocks: before.count,
      invalid: before.invalid,
      saveError,
      invalidAfterSave: after.invalid.length,
      contentStableAfterSave: norm(after.content) === norm(before.content),
    }));
  }

  await browser.close();
})().catch(error => {
  console.error(error.message);
  process.exit(1);
});
