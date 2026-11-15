// node shot.js <username> <page+params> <name>  -> viewport screenshot (tall viewport, no fullPage stitching)
const { chromium, BASE, login, check } = require('./lib');
(async () => {
  const browser = await chromium.launch();
  const page = await login(browser, process.argv[2]);
  await page.goto(BASE + '/index.php?q=/modules/ServiceDesk/' + process.argv[3]);
  await check(page, process.argv[3]);
  await page.screenshot({ path: `${__dirname}/shots/${process.argv[4]}.png` });
  await browser.close();
})();
