const { chromium } = require('playwright');
const BASE = process.env.GIBBON_URL || 'http://127.0.0.1:8088';
async function login(browser, username, password = 'Test1234!') {
  // The module allows 20 self-service requests per person per hour; the test scripts together send more. Make the
  // requests already there two hours old (only the creation time, nothing a test looks at).
  try { sql("update gibbonServiceDeskTicket set timestampCreated=date_sub(timestampCreated, interval 2 hour) where timestampCreated > date_sub(now(), interval 1 hour) and gibbonPersonIDRequester in (select gibbonPersonID from gibbonPerson where username='" + String(username).replace(/[^0-9A-Za-z]/g, '') + "')"); } catch (e) { /* module not installed yet */ }
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
// Runs SQL against the test database and returns the rows as arrays of strings (tab separated -> split).
const { execFileSync } = require('child_process');
function sql(query) {
  const out = execFileSync('mariadb', ['-h127.0.0.1', '-P' + (process.env.GIBBON_DB_PORT || '3399'), '-uroot', 'gibbon', '-N', '-e', query]).toString().trim();
  return out === '' ? [] : out.split('\n').map(l => l.split('\t'));
}
module.exports = { chromium, BASE, login, check, sql };
