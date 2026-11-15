const { chromium, BASE, login, check } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const shot = (page, name) => page.screenshot({ path: `${__dirname}/shots/flow_${name}.png`, fullPage: true });
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
(async () => {
  const browser = await chromium.launch();
  // 1. admin adds teacher 119 to team 1
  let admin = await login(browser, '192');
  await admin.goto(M + 'teams_manage_edit.php&gibbonServiceDeskTeamID=1');
  if (await admin.$('form#memberAdd option[value="0000000119"]')) {
    await admin.selectOption('form#memberAdd select[name="people[]"]', '0000000119');
    await submit(admin, 'form#memberAdd'); console.log(admin.url()); await check(admin, 'member added');
  }
  await shot(admin, 'team');

  // 2. parent submits absence
  let parent = await login(browser, '463');
  await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=1');
  for (const cb of await parent.$$('form#request input[type=checkbox]')) await cb.check();
  await parent.selectOption('select[name=field2]', 'Medical');
  await parent.fill('input[name=field3]', '2026-11-16');
  await parent.fill('input[name=field4]', '2026-11-17');
  await parent.fill('textarea[name=field5]', 'Flu.\nDoctor says two days.');
  await submit(parent, 'form#request'); console.log(parent.url()); await check(parent, 'parent ticket view'); await shot(parent, 'parent_ticket');
  const ticketURL = parent.url().replace(/&return=.*/, '');
  await parent.goto(M + 'tickets.php'); await check(parent, 'parent list'); await shot(parent, 'parent_list');

  // 3. teacher (agent)
  let agent = await login(browser, '119');
  await agent.goto(M + 'tickets.php'); await check(agent, 'agent list'); await shot(agent, 'agent_list');
  await agent.goto(M + 'tickets.php&gibbonServiceDeskFilterID=5'); await check(agent, 'agent absence intake filter');
  console.log('rows in intake filter:', await agent.$$eval('table tbody tr', r => r.length));
  await agent.goto(ticketURL); await check(agent, 'agent view');
  await agent.fill('form#comment textarea[name=comment]', 'Checking with the class teacher.');
  await agent.selectOption('form#comment select[name=type]', 'Internal Note');
  await submit(agent, 'form#comment'); await check(agent, 'internal note');
  await agent.fill('form#comment textarea[name=comment]', 'Thank you, we hope they get well soon.');
  await submit(agent, 'form#comment'); await check(agent, 'public comment');
  await agent.selectOption('form#manage select[name=gibbonServiceDeskStageID]', { label: 'Approved *' });
  await agent.selectOption('form#manage select[name=gibbonPersonIDAssignee]', '0000000119');
  await agent.selectOption('form#manage select[name=priority]', 'High');
  await submit(agent, 'form#manage'); console.log(agent.url()); await check(agent, 'approved'); await shot(agent, 'agent_ticket');

  // 4. parent sees result, no internal
  await parent.goto(ticketURL); await check(parent, 'parent view after');
  const text = await parent.innerText('.serviceDesk-ticket');
  console.log('parent sees internal note:', text.includes('Checking with the class teacher'), '| sees stage Done:', text.includes('Done'), '| sees automation msg:', text.includes('recorded in Attendance'));
  await shot(parent, 'parent_ticket_after');
  await parent.fill('form#comment textarea[name=comment]', 'Thanks!'); await submit(parent, 'form#comment'); await check(parent, 'parent comment');
  await parent.click('form#notify label[id^=toggle-label][for=notifyEmail]'); await submit(parent, 'form#notify'); await check(parent, 'parent notify');
  await parent.goto(M + 'notifications.php'); await parent.click('form#notifications label[id^=toggle-label][for=notifyEmail]'); await submit(parent, 'form#notifications'); await check(parent, 'parent prefs');

  // 5. other parent cannot see
  let other = await login(browser, '427');
  await other.goto(ticketURL);
  console.log('other parent blocked:', (await other.content()).includes('cannot be found'));
  await other.goto(M + 'tickets.php'); console.log('other parent ticket links (expect 0):', await other.$$eval('a[href*="tickets_view.php"]', a => a.length));
  await other.goto(M + 'types_manage.php'); console.log('other parent admin page blocked:', (await other.content()).includes('do not have access'));
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 6).join('\n')); process.exit(1); });
