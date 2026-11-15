// Create tickets on behalf of others (action "New Request_onBehalf"). Run after flow.js.
//  0. if the installed module is older than the code, update it through System Admin > Manage Modules (tests CHANGEDB),
//  1. the administrator sees every service, chooses a parent, gets that parent's children in the form, sends it:
//     the parent is the requester, the history says who created it, the parent is notified,
//  2. a teacher (no permission) sees only the services of their role and cannot use the on-behalf parameter.
const { chromium, BASE, login, check } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
(async () => {
  const browser = await chromium.launch();
  const admin = await login(browser, '192');

  // 0. upgrade if offered
  await admin.goto(BASE + '/index.php?q=/modules/System Admin/module_manage.php');
  const update = await admin.$('a[href*="module_manage_update.php"][href*="ServiceDesk"], tr:has-text("ServiceDesk") a[href*="module_manage_update.php"]');
  if (update) {
    await admin.goto(await update.evaluate(a => a.href)); await check(admin, 'update page');
    console.log('update page says:', (await admin.innerText('#content-wrap, body')).replace(/\s+/g, ' ').match(/(up to date|Version[^.]*\.[^.]*\.[^ ]*|update)[^.]{0,80}/i)?.[0]);
    const button = await admin.$('form button[type=submit]');
    if (button) { await submit(admin, 'form'); console.log('update result:', (admin.url().match(/return=(\w+)/) || [])[1]); await check(admin, 'after update'); }
  }
  const relog = await login(browser, '192');   // the menu and permissions are read at login

  // 1. administrator: all services, on behalf of parent 463
  await relog.goto(M + 'request_new.php'); await check(relog, 'catalog with permission');
  console.log('services shown to the administrator:', (await relog.$$eval('.serviceDesk-cardTitle', c => c.map(e => e.innerText))).join(', '));
  await relog.goto(M + 'request_new.php&gibbonPersonIDOnBehalf=463'); await check(relog, 'catalog on behalf');
  await relog.screenshot({ path: `${__dirname}/shots/onbehalf_catalog.png` });
  await relog.click('.serviceDesk-card:has-text("Absence Report")'); await relog.waitForLoadState(); await check(relog, 'form on behalf');
  console.log('children offered:', (await relog.$$eval('form#request input[type=checkbox]', c => c.map(e => e.value))).join(', '));
  await relog.check('form#request input[type=checkbox] >> nth=0');
  await relog.selectOption('form#request select', 'Other');
  await relog.fill('form#request input[type=date] >> nth=0', '2026-12-01');
  await relog.screenshot({ path: `${__dirname}/shots/onbehalf_form.png` });
  await submit(relog, 'form#request'); console.log('sent:', relog.url().replace(BASE, '')); await check(relog, 'ticket created on behalf');
  const text = await relog.innerText('.serviceDesk-ticket');
  console.log('requester is the parent (expect true):', /REQUESTER\s*Jada Schultz/i.test(text), '| history line (expect true):', /Created by .* on behalf of Jada Schultz/.test(text));
  await relog.screenshot({ path: `${__dirname}/shots/onbehalf_ticket.png` });

  const parent = await login(browser, '463');
  await parent.goto(BASE + '/index.php?q=notifications.php');
  console.log('parent notified (expect true):', (await parent.innerText('body')).includes('on behalf of Jada Schultz'));
  await parent.goto(M + 'tickets.php');
  console.log('parent sees the ticket in their list (expect true):', (await parent.innerText('body')).includes('Absence Report'));
  await parent.goto(M + 'request_new.php'); console.log('parent gets no on-behalf picker (expect true):', !(await parent.$('[name=gibbonPersonIDOnBehalf]')));

  // 2. teacher without the permission
  const teacher = await login(browser, '119');
  await teacher.goto(M + 'request_new.php');
  console.log('services shown to a teacher:', (await teacher.$$eval('.serviceDesk-cardTitle', c => c.map(e => e.innerText))).join(', '));
  await teacher.goto(M + 'request_new.php&gibbonPersonIDOnBehalf=463&gibbonServiceDeskTicketTypeID=1');
  console.log('teacher refused the parent-only form on behalf (expect true):', (await teacher.content()).includes('cannot be found'));
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 6).join('\n')); process.exit(1); });
