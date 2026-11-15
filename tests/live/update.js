// Updates the module through System Admin > Manage Modules when the code is newer than the installed version.
// This runs the CHANGEDB.php steps the way a school's administrator would.
const { chromium, BASE, login, check } = require('./lib');
(async () => {
  const browser = await chromium.launch();
  const admin = await login(browser, '192');
  await admin.goto(BASE + '/index.php?q=/modules/System Admin/module_manage.php');
  const update = await admin.$('tr:has-text("ServiceDesk") a[href*="module_manage_update.php"]');
  if (!update) { console.log('no update link found'); await browser.close(); return; }
  await admin.goto(await update.evaluate(a => a.href)); await check(admin, 'update page');
  const button = await admin.$('form button[type=submit]');
  if (!button) { console.log('nothing to update'); await browser.close(); return; }
  await Promise.all([admin.waitForNavigation(), button.click()]);
  console.log('update result:', (admin.url().match(/return=(\w+)/) || [])[1]);
  await check(admin, 'after update');
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 6).join('\n')); process.exit(1); });
