// Notification centre. Run after flow.js.
//  1. the parent (463) and the teacher (119) see their Service Desk notifications on Gibbon's notification page,
//     the bell counts them, and "Action & Archive" opens the ticket,
//  2. a request for a ticket type whose teams have no members (Complaint -> team Management) reaches the administrator.
const { chromium, BASE, login, check } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
(async () => {
  const browser = await chromium.launch();
  for (const user of ['463', '119']) {
    const page = await login(browser, user);
    await page.goto(BASE + '/index.php?q=notifications.php');
    await check(page, user + ' notifications page');
    const rows = await page.$$eval('table tbody tr', trs => trs.map(tr => tr.innerText.replace(/\s+/g, ' ').trim()));
    console.log(user, 'service desk rows (expect > 0):', rows.filter(r => r.includes('ServiceDesk')).length);
    await page.screenshot({ path: `${__dirname}/shots/notifications_${user}.png` });
    const bell = await page.evaluate(async base => (await fetch(base + '/index_notification_ajax.php')).text(), BASE);
    console.log(user, 'bell:', bell.slice(0, 60));
    // The link is an htmx link: follow its address directly.
    const href = await page.$eval('a[href*="notificationsActionProcess.php"]', a => a.href);
    await page.goto(href);
    console.log(user, 'link opens the ticket (expect true):', page.url().includes('tickets_view.php'));
    await check(page, user + ' ticket behind the notification');
  }

  // Empty team: nobody is told by default any more (staff opt in; see notifyrules.js).
  const parent = await login(browser, '463');
  await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=4');
  await parent.fill('form#request textarea', 'The bus was late three times this week.');
  await parent.fill('form#request input[name=subject]', 'Late bus ' + (Date.now() % 100000));
  await Promise.all([parent.waitForNavigation(), parent.click('form#request button[type=submit]')]);
  await check(parent, 'complaint sent');
  const admin = await login(browser, '192');
  await admin.goto(BASE + '/index.php?q=notifications.php');
  const text = await admin.innerText('body');
  console.log('administrator NOT told about a ticket of a team without members (expect true):', !text.includes('Late bus'));
  await admin.screenshot({ path: `${__dirname}/shots/notifications_admin.png` });
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 6).join('\n')); process.exit(1); });
