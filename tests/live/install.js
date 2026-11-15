const { chromium, BASE, login, check } = require('./lib');
(async () => {
  const browser = await chromium.launch();
  const page = await login(browser, '192');
  console.log('after login:', page.url());
  await page.goto(BASE + '/index.php?q=/modules/System Admin/module_manage.php');
  await check(page, 'module manage');
  const link = await page.$('a[href*="module_manage_installProcess.php?name=ServiceDesk"]');
  console.log('install link:', !!link);
  if (link) { await Promise.all([page.waitForNavigation(), link.click()]); console.log(page.url()); await check(page, 'after install'); }
  await page.screenshot({ path: __dirname + '/shots/install.png', fullPage: true });
  await browser.close();
})();
