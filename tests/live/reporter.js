// What the reporter (requester) is told. Run after flow.js (teacher 119 is in team Administration).
// A parent files a request; a team member then acts on it. The parent must get a notification for every public
// update (comment, assignment, stage change) and none for internal notes or for their own actions.
// Also: "Send me a test notification" reports on both channels.
const { chromium, BASE, login, check } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
// The texts on Gibbon's own notification page that mention this ticket.
const told = async (page, subject) => {
  await page.goto(BASE + '/index.php?q=notifications.php');
  return page.$$eval('table tbody tr', (rows, s) => rows.map(r => r.innerText.replace(/\s+/g, ' ')).filter(t => t.includes(s)), subject);
};
(async () => {
  const browser = await chromium.launch();
  const parent = await login(browser, '463');
  const member = await login(browser, '119');
  const subject = 'Reporter test ' + (Date.now() % 100000);

  await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=5');
  await parent.fill('form#request textarea', 'Please confirm the term dates.');
  await parent.fill('form#request input[name=subject]', subject);
  await submit(parent, 'form#request'); await check(parent, 'request sent');
  const url = parent.url().replace(/&return=.*/, '');
  console.log('reporter told about their own new request (expect 0):', (await told(parent, subject)).length);
  console.log('team member told about the new request (expect 1):', (await told(member, subject)).length);

  // team member: internal note, public comment, assign to self, move to In Progress
  await member.goto(url);
  await member.fill('form#comment textarea', 'Internal: check with the head first.');
  await member.selectOption('form#comment select[name=type]', 'Internal Note');
  await submit(member, 'form#comment');
  await member.fill('form#comment textarea', 'Term starts on 5 January.');
  await submit(member, 'form#comment');
  await member.selectOption('form#manage select[name=gibbonPersonIDAssignee]', '0000000119');
  await member.selectOption('form#manage select[name=gibbonServiceDeskStageID]', { label: 'In Progress' });
  await submit(member, 'form#manage'); await check(member, 'ticket updated');

  let list = await told(parent, subject);
  const has = part => list.some(t => t.includes(part));
  console.log('reporter notifications:', list.length);
  console.log('  public comment (expect true):', has('New comment: Term starts on 5 January'));
  console.log('  assignment (expect true):', has('Assigned to'));
  console.log('  stage change (expect true):', has('Moved to In Progress'));
  console.log('  internal note NOT sent (expect true):', !has('Internal') && !has('check with the head'));
  await parent.screenshot({ path: `${__dirname}/shots/reporter_notifications.png` });
  const bell = await parent.evaluate(async base => (await fetch(base + '/index_notification_ajax.php')).text(), BASE);
  console.log('  bell count:', bell.slice(0, 40));

  // the reporter answers: not told about their own comment, the team member is
  await parent.goto(url);
  await parent.fill('form#comment textarea', 'Thank you.');
  await submit(parent, 'form#comment');
  const before = list.length;
  list = await told(parent, subject);
  console.log('reporter not told about their own comment (expect true):', list.length === before);
  console.log('team member told about the reporter\'s comment (expect true):', (await told(member, subject)).some(t => t.includes('New comment: Thank you')));

  // self test on the settings page
  await parent.goto(M + 'notifications.php'); await check(parent, 'notification settings');
  await submit(parent, 'form#notificationTest');
  const alerts = await parent.$$eval('.success, .error', els => els.map(e => e.innerText.replace(/\s+/g, ' ').trim()).filter(t => t.length > 10));
  console.log('test result shown:', alerts.join(' || ').slice(0, 300));
  await parent.screenshot({ path: `${__dirname}/shots/reporter_test.png` });
  console.log('test notification in the centre (expect true):', (await told(parent, 'Service Desk test notification')).length > 0);
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 6).join('\n')); process.exit(1); });
