// Notification rules of 1.10.00: staff are told about nothing by default; only about tickets assigned to them, tickets
// they follow, and the ticket types they (or an administrator) chose. Run after flow.js (teacher 119 is in team
// Administration, which handles ticket type 5; type 4 has no team). Sends mail to the SMTP sink (tests/live/mail-on.sh).
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium, BASE, login, check, sql } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const MAIL = '/tmp/servicedesk-mail.txt';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const told = async (page, subject) => {
  await page.goto(BASE + '/index.php?q=notifications.php');
  return page.$$eval('table tbody tr', (rows, s) => rows.map(r => r.innerText.replace(/\s+/g, ' ')).filter(t => t.includes(s)), subject);
};
const expect = (label, got, want) => console.log((got === want ? 'ok   ' : 'FAIL ') + label + ' (expect ' + want + '): ' + got);

(async () => {
  execFileSync('sh', [path.join(__dirname, 'mail-on.sh')], { stdio: 'ignore' });
  const browser = await chromium.launch();   // log in after mail-on.sh: Gibbon reads the mail settings at login
  const parent = await login(browser, '463');
  const member = await login(browser, '119');
  const admin = await login(browser, '192');
  const other = await login(browser, '120');

  // Start clean: no type settings, no default assignee (restored at the end).
  const defaults = sql('select gibbonServiceDeskTeamID, gibbonPersonIDDefaultAssignee from gibbonServiceDeskTeam where gibbonPersonIDDefaultAssignee is not null');
  sql('delete from gibbonServiceDeskTypeNotification');
  sql('update gibbonServiceDeskTeam set gibbonPersonIDDefaultAssignee=null');

  const create = async (subject, typeID = 5) => {
    await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=' + typeID);
    await parent.fill('form#request textarea', 'Rules test: ' + subject);
    await parent.fill('form#request input[name=subject]', subject);
    await submit(parent, 'form#request'); await check(parent, 'request sent: ' + subject);
    return parent.url().replace(/&return=.*/, '');
  };
  const reply = async (page, url, text, type) => {
    await page.goto(url);
    await page.fill('form#comment textarea', text);
    if (type) await page.selectOption('form#comment select[name=type]', type);
    await submit(page, 'form#comment');
  };
  const idOf = url => url.match(/gibbonServiceDeskTicketID=(\d+)/)[1];
  const stamp = String(Date.now() % 100000);

  // ---- 1. Default: nobody on the staff side is told, not even after commenting
  const a = 'Rules A ' + stamp;
  const urlA = await create(a);
  expect('admin not told about a new ticket by default', (await told(admin, a)).length, 0);
  expect('team member not told about a new ticket by default', (await told(member, a)).length, 0);
  await reply(admin, urlA, 'The admin answers.');
  await reply(member, urlA, 'The member answers too.');
  await reply(parent, urlA, 'The parent replies.');
  expect('requester told about the team comments (expect 2: admin + member)', (await told(parent, a)).length >= 2, true);
  expect('admin not told about updates after commenting', (await told(admin, a)).length, 0);
  expect('member not told about updates after commenting', (await told(member, a)).length, 0);
  expect('commenting does not make staff followers', sql(`select count(*) from gibbonServiceDeskTicketSubscriber where gibbonServiceDeskTicketID=${idOf(urlA)}`)[0][0], '1');

  // ---- 2. Assigned: the assignee is told
  await member.goto(urlA);
  await member.selectOption('form#manage select[name=gibbonPersonIDAssignee]', '0000000119');
  await submit(member, 'form#manage'); await check(member, 'assigned to the member');
  await reply(parent, urlA, 'Still waiting for an answer.');
  expect('assignee told about the requester\'s comment', (await told(member, a)).length >= 1, true);
  expect('admin still not told', (await told(admin, a)).length, 0);

  // ---- 3. The member chooses: new tickets in the notification centre, updates by email (Notification Settings)
  await member.goto(BASE + '/index.php?q=/modules/ServiceDesk/notifications.php');
  await check(member, 'notification settings of a team member');
  const sections = await member.$$eval('input[name^="typeNotify_"]', els => [...new Set(els.map(e => e.name))]);
  expect('member sees only the types of their teams (type 5 yes, type 4 no)', sections.includes('typeNotify_5[]') && !sections.includes('typeNotify_4[]'), true);
  await member.check('input[name="typeNotify_5[]"][value=newInApp]');
  await member.check('input[name="typeNotify_5[]"][value=updateEmail]');
  await submit(member, 'form#notificationTypes');
  expect('settings stored', sql('select newInApp, newEmail, updateInApp, updateEmail from gibbonServiceDeskTypeNotification where gibbonPersonID=119 and gibbonServiceDeskTicketTypeID=5')[0].join(','), 'Y,N,N,Y');

  const b = 'Rules B ' + stamp;
  fs.writeFileSync(MAIL, '');
  const urlB = await create(b);
  expect('member told about the new ticket (centre)', (await told(member, b)).length, 1);
  expect('admin still not told', (await told(admin, b)).length, 0);
  expect('no mail for a new ticket (only updates by email)', fs.readFileSync(MAIL, 'utf8').includes(b), false);
  await reply(parent, urlB, 'An update on B.');
  expect('update: no second notification in the centre', (await told(member, b)).length, 1);
  await new Promise(r => setTimeout(r, 1500));
  expect('update: email sent to the member', fs.readFileSync(MAIL, 'utf8').includes(b), true);

  // ---- 4. Internal notes reach the staff who chose updates, never the requester
  const before = (await told(parent, b)).length;
  await reply(admin, urlB, 'Internal: be careful with this one.', 'Internal Note');
  expect('requester not told about the internal note', (await told(parent, b)).length, before);

  // ---- 5. An administrator assigns the admin to the type (Manage Ticket Types > Notified Staff)
  await admin.goto(M + 'types_manage.php');
  await check(admin, 'ticket types list');
  await admin.goto(M + 'types_manage_edit.php&gibbonServiceDeskTicketTypeID=5');
  await check(admin, 'Notified Staff form');
  const offered = await admin.$$eval('select[name="add[]"] option', os => os.map(o => o.value));
  expect('the person outside the team is not offered', offered.includes('0000000120'), false);
  await admin.selectOption('select[name="add[]"]', ['0000000192']);
  await submit(admin, 'form#typeNotified');
  expect('admin now listed with their default channels', sql('select newInApp, newEmail, updateInApp, updateEmail from gibbonServiceDeskTypeNotification where gibbonPersonID=192 and gibbonServiceDeskTicketTypeID=5')[0].join(','), 'Y,Y,Y,Y');
  const c = 'Rules C ' + stamp;
  const urlC = await create(c);
  expect('admin told about the new ticket', (await told(admin, c)).length, 1);

  // ---- 6. The per-ticket choice overrides the type setting
  sql(`insert into gibbonServiceDeskTicketSubscriber (gibbonServiceDeskTicketID, gibbonPersonID, notifyInApp, notifyEmail) values (${idOf(urlC)}, 192, 'N', 'N') on duplicate key update notifyInApp='N', notifyEmail='N'`);
  await reply(parent, urlC, 'Update on C.');
  expect('admin who muted the ticket is not told about its update', (await told(admin, c)).length, 1);

  // ---- 7. Forged posts
  await member.goto(BASE + '/index.php?q=/modules/ServiceDesk/notifications.php');
  await member.evaluate(() => {
    const form = document.querySelector('form#notificationTypes');
    const input = document.createElement('input'); input.type = 'hidden'; input.name = 'typeNotify_4[]'; input.value = 'newInApp'; form.appendChild(input);
  });
  await submit(member, 'form#notificationTypes');
  expect('a type the member does not handle is refused', sql('select count(*) from gibbonServiceDeskTypeNotification where gibbonPersonID=119 and gibbonServiceDeskTicketTypeID=4')[0][0], '0');
  await admin.goto(M + 'types_manage_edit.php&gibbonServiceDeskTicketTypeID=5');
  await admin.evaluate(() => {
    const select = document.querySelector('select[name="add[]"]');
    const option = document.createElement('option'); option.value = '0000000120'; option.selected = true; select.appendChild(option);
  });
  await submit(admin, 'form#typeNotified');
  expect('a person outside the teams cannot be added by a forged post', sql('select count(*) from gibbonServiceDeskTypeNotification where gibbonPersonID=120')[0][0], '0');
  await other.goto(BASE + '/index.php?q=/modules/ServiceDesk/notifications.php');
  expect('a user who is not staff has no ticket type section', (await other.$('form#notificationTypes')) === null, true);

  // restore
  sql('delete from gibbonServiceDeskTypeNotification');
  for (const [team, person] of defaults) sql(`update gibbonServiceDeskTeam set gibbonPersonIDDefaultAssignee=${person} where gibbonServiceDeskTeamID=${team}`);
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 6).join('\n')); process.exit(1); });
