const { chromium } = require('playwright');
const BASE = process.env.GIBBON_URL || 'http://127.0.0.1:8088';
async function login(browser, username, password = 'Test1234!') {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 2200 } });
  const page = await ctx.newPage();
  page.on('pageerror', e => console.log('PAGEERROR', e.message));
  await page.goto(BASE + '/index.php');
  await page.fill('input[name=username]', username);
  await page.fill('input[name=password]', password);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit], input[type=submit]')]);
  const ok = await page.$('button:has-text("OK")'); if (ok) await ok.click().catch(() => {});
  return page;
}
// Report PHP errors/warnings and Gibbon error boxes visible on the page
async function check(page, label) {
  const html = await page.content();
  const problems = [];
  for (const re of [/SQLSTATE[^<]*/g, /Warning<\/\w+>:[^<]*/g, /Fatal error[^<]*/g, /Warning<\/b>:[^<]*/g, /Notice<\/b>:[^<]*/g, /Deprecated<\/b>:[^<]*/g, /Uncaught[^<]*/g, /Cannot create[^<]*/g, /Undefined Method[^<]*/g, /You do not have access[^<]*/g, /An error has occurred[^<]*/g, /cannot be found[^<]*/g]) {
    const m = html.match(re); if (m) problems.push(...m.slice(0, 3));
  }
  console.log((problems.length ? 'FAIL ' : 'ok   ') + label + (problems.length ? ' -> ' + problems.join(' | ') : ''));
  return problems.length === 0;
}
module.exports = { chromium, BASE, login, check };
