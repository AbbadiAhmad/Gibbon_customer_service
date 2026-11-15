// Default assignee of a team. Run after flow.js (teacher 119 is then a member of team 1, Administration).
//  1. the administrator chooses 119 as default assignee of team 1; a parent's new request starts assigned to 119,
//  2. the default is cleared again: the next request starts unassigned,
//  3. removing the member who is the default assignee clears the default.
const { chromium, BASE, login, check } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const ret = page => (page.url().match(/return=(\w+)/) || [])[1];
const request = async (parent, subject) => {
  await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=5');
  await parent.fill('form#request textarea', 'Please send a copy of the report card.');
  await parent.fill('form#request input[name=subject]', subject);
  await submit(parent, 'form#request');
  return parent.innerText('.serviceDesk-ticket');
};
(async () => {
  const browser = await chromium.launch();
  const admin = await login(browser, '192');
  const parent = await login(browser, '463');
  const team = M + 'teams_manage_edit.php&gibbonServiceDeskTeamID=1';

  await admin.goto(team); await check(admin, 'team edit');
  await admin.selectOption('form#team select[name=gibbonPersonIDDefaultAssignee]', '0000000119');
  await submit(admin, 'form#team'); console.log('default assignee saved:', ret(admin));
  await admin.goto(M + 'teams_manage.php'); await check(admin, 'team list');
  console.log('team list shows the default assignee (expect true):', (await admin.innerText('table')).includes('Schultz'));
  await admin.screenshot({ path: `${__dirname}/shots/assignee_teams.png` });

  let text = await request(parent, 'Report card A');
  console.log('1. starts assigned to the default (expect true):', /ASSIGNEE\s*Nathan Schultz/i.test(text), '| history line (expect true):', text.includes('Assigned to Nathan Schultz'));
  await parent.screenshot({ path: `${__dirname}/shots/assignee_ticket.png` });
  const teacher = await login(browser, '119');
  await teacher.goto(M + 'tickets.php&gibbonServiceDeskFilterID=3');
  console.log('   in the assignee\'s "Assigned to Me" filter (expect true):', (await teacher.innerText('body')).includes('Report card A'));

  await admin.goto(team);
  await admin.selectOption('form#team select[name=gibbonPersonIDDefaultAssignee]', '');
  await submit(admin, 'form#team');
  text = await request(parent, 'Report card B');
  console.log('2. no default -> unassigned (expect true):', /ASSIGNEE\s*Unassigned/i.test(text));

  await admin.goto(team);
  await admin.selectOption('form#team select[name=gibbonPersonIDDefaultAssignee]', '0000000119');
  await submit(admin, 'form#team');
  await admin.goto(team);
  await submit(admin, 'form[id^=memberRemove]'); console.log('member removed:', ret(admin));
  text = await request(parent, 'Report card C');
  console.log('3. default assignee left the team -> unassigned (expect true):', /ASSIGNEE\s*Unassigned/i.test(text));

  // put the member back for the other tests
  await admin.goto(team);
  await admin.selectOption('form#memberAdd select[name="people[]"]', '0000000119');
  await submit(admin, 'form#memberAdd');
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 6).join('\n')); process.exit(1); });
