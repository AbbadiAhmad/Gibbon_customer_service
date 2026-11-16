// Audience of shared filters. Run after flow.js (teacher 119 is in team Administration; teacher 120 is in no team).
//  - requesters (parent 463, and staff 120 who is in no team) see: All, Open, Closed
//  - agents (team member 119) see the agent filters and their team's filter, none of the requesters' filters
//  - the administrator sees everything, the requesters' filters marked
//  - a filter saved by the administrator for requesters reaches the parent and not the team member
const { chromium, BASE, login, check } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const names = async page => { await page.goto(M + 'tickets.php&view=list'); return (await page.$$eval('.serviceDesk-filters > a.serviceDesk-filter', a => a.map(e => e.innerText.replace(/\s+/g, ' ').trim()))).join(', '); };
(async () => {
  const browser = await chromium.launch();
  const parent = await login(browser, '463'), outsider = await login(browser, '120'), member = await login(browser, '119'), admin = await login(browser, '192');

  let list = await names(parent); await check(parent, 'parent tickets');
  console.log('parent:', list, '| (expect true):', list === 'All, Open, Closed');
  await parent.screenshot({ path: `${__dirname}/shots/audience_parent.png` });
  list = await names(outsider);
  console.log('staff in no team:', list, '| (expect true):', list === 'All, Open, Closed', '| no board switch (expect true):', !(await outsider.$('.serviceDesk-viewSwitch')));
  list = await names(member); await check(member, 'member tickets');
  console.log('team member:', list, '| (expect true):', list === 'All, My Requests, Open Tickets, Assigned to Me, Unassigned, Absence Intake');
  list = await names(admin); await check(admin, 'admin tickets');
  console.log('administrator:', list, '| (expect true):', list.includes('Unassigned') && list.includes('Open (requesters)') && list.includes('Closed (requesters)'));
  await admin.screenshot({ path: `${__dirname}/shots/audience_admin.png` });

  // the requesters' filters work: ticket #1 of the flow test is closed
  await parent.click('.serviceDesk-filters a:text-is("Closed")'); await parent.waitForLoadState(); await check(parent, 'parent closed filter');
  const closed = await parent.$$eval('a[href*="tickets_view.php"]', a => a.length);
  await parent.goto(M + 'tickets.php'); await parent.click('.serviceDesk-filters a:text-is("Open")'); await parent.waitForLoadState();
  console.log('parent: closed tickets listed (expect > 0):', closed, '| open filter loads (expect true):', parent.url().includes('gibbonServiceDeskFilterID'));

  // administrator shares a new filter with requesters
  const name = 'High priority ' + (Date.now() % 100000);
  await admin.goto(M + 'tickets.php&view=list&status[]=Open&status[]=In Progress&type[]=5');
  await admin.fill('form#filterSave input[name=name]', name);
  await admin.selectOption('form#filterSave select[name=share]', 'requesters');
  await submit(admin, 'form#filterSave'); console.log('saved for requesters:', (admin.url().match(/return=(\w+)/) || [])[1]);
  console.log('parent gets it (expect true):', (await names(parent)).includes(name), '| team member does not (expect true):', !(await names(member)).includes(name));
  await admin.click('.serviceDesk-filterDelete a'); await admin.waitForLoadState();
  await submit(admin, 'form');
  console.log('administrator deleted it; gone for the parent (expect true):', !(await names(parent)).includes(name));
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 6).join('\n')); process.exit(1); });
