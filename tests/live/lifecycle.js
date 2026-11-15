// Move a ticket to another type, delete a ticket (data protection), and the retention script.
// Run after flow.js (teacher 119 is in team Administration, not in team Management; Management has no members).
const { chromium, BASE, login, check, sql } = require('./lib');
const { execFileSync } = require('child_process');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const ret = page => (page.url().match(/return=(\w+)/) || [])[1];
const stamp = Date.now() % 100000;
(async () => {
  const browser = await chromium.launch();
  const parent = await login(browser, '463'), member = await login(browser, '119'), admin = await login(browser, '192');
  const file = async subject => {
    await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=5');
    await parent.fill('form#request textarea', 'Details for ' + subject); await parent.fill('form#request input[name=subject]', subject);
    await submit(parent, 'form#request'); return parent.url().match(/TicketID=0*(\d+)/)[1];
  };

  // ---- MOVE
  const id = await file('Move me ' + stamp);
  await member.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
  await member.selectOption('form#manage select[name=gibbonPersonIDAssignee]', '0000000119'); await submit(member, 'form#manage');
  console.log('types offered to move to (expect 4, not the current one):', await member.$$eval('form#move select option:not([value=""])', o => o.length));
  await member.screenshot({ path: `${__dirname}/shots/move_form.png` });
  await member.selectOption('form#move select[name=gibbonServiceDeskTicketTypeID]', { label: 'Complaint' });
  await submit(member, 'form#move'); console.log('moved:', ret(member), '| the member is not in the new type\'s team, so they land on the list (expect true):', member.url().includes('tickets.php&return') || member.url().includes('tickets.php'));
  const row = sql(`select t.name, ticket.gibbonPersonIDAssignee is null, s.name from gibbonServiceDeskTicket ticket join gibbonServiceDeskTicketType t using (gibbonServiceDeskTicketTypeID) join gibbonServiceDeskStage s on s.gibbonServiceDeskStageID=ticket.gibbonServiceDeskStageID and s.gibbonServiceDeskTicketTypeID=ticket.gibbonServiceDeskTicketTypeID where ticket.gibbonServiceDeskTicketID=${id}`)[0];
  console.log('ticket now:', row.join(' | '), '(expect Complaint | 1 | New)');
  await admin.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
  let text = await admin.innerText('.serviceDesk-ticket');
  console.log('history: public line (expect true):', text.includes('Moved from General Request to Complaint.'), '| internal line about the assignee (expect true):', text.includes('unassigned'));
  await parent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id); text = await parent.innerText('.serviceDesk-ticket');
  console.log('requester sees the move, not the assignee line (expect true):', text.includes('Moved from General Request to Complaint.') && !text.includes('unassigned') && /TICKET TYPE\s*Complaint/i.test(text));
  await admin.goto(BASE + '/index.php?q=notifications.php');
  console.log('administrator told (the new type\'s team is empty) (expect true):', (await admin.innerText('body')).includes('Move me ' + stamp));

  // ---- DELETE (data protection)
  await member.goto(M + 'tickets_delete.php&gibbonServiceDeskTicketID=' + id);
  console.log('team member without the permission: refused (expect true):', (await member.content()).includes('do not have access'));
  await parent.goto(M + 'tickets_delete.php&gibbonServiceDeskTicketID=' + id);
  console.log('requester: refused (expect true):', (await parent.content()).includes('do not have access'));
  await parent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
  console.log('requester has no delete link (expect true):', !(await parent.$('a[href*="tickets_delete.php"]')));
  await admin.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
  await admin.fill('form#comment textarea', 'Please remove my data.'); await submit(admin, 'form#comment');
  console.log('before: comments / subscribers / notifications:', sql(`select (select count(*) from gibbonServiceDeskTicketComment where gibbonServiceDeskTicketID=${id}), (select count(*) from gibbonServiceDeskTicketSubscriber where gibbonServiceDeskTicketID=${id}), (select count(*) from gibbonNotification where text like '#${id} %')`)[0].join(' / '));
  await admin.click('a[href*="tickets_delete.php"]'); await admin.waitForLoadState(); await check(admin, 'delete page');
  await admin.screenshot({ path: `${__dirname}/shots/delete_confirm.png` });
  console.log('confirmation page, ticket still there (expect 1):', sql(`select count(*) from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${id}`)[0][0]);
  await submit(admin, 'form'); console.log('deleted:', ret(admin));
  console.log('after: tickets / comments / subscribers / notifications (expect 0 / 0 / 0 / 0):', sql(`select (select count(*) from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${id}), (select count(*) from gibbonServiceDeskTicketComment where gibbonServiceDeskTicketID=${id}), (select count(*) from gibbonServiceDeskTicketSubscriber where gibbonServiceDeskTicketID=${id}), (select count(*) from gibbonNotification where text like '#${id} %')`)[0].join(' / '));
  const log = sql(`select serialisedArray from gibbonLog where title='Service Desk' order by gibbonLogID desc limit 1`)[0][0];
  console.log('log line records the deletion, not the content (expect true):', log.includes('Ticket deleted') && log.includes(`"${id}"`) === false && !log.includes('Move me') && !log.includes('remove my data'), '|', log.slice(0, 120));
  await parent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
  console.log('the ticket is gone (expect true):', (await parent.content()).includes('cannot be found'));

  // ---- RETENTION
  await admin.goto(M + 'types_manage_edit.php&gibbonServiceDeskTicketTypeID=5'); await check(admin, 'type edit');
  await admin.fill('form#ticketType input[name=retentionDays]', '1'); await submit(admin, 'form#ticketType');
  console.log('retention saved:', ret(admin), '->', sql(`select retentionDays from gibbonServiceDeskTicketType where gibbonServiceDeskTicketTypeID=5`)[0][0], 'day(s)');
  await admin.goto(M + 'types_manage_edit.php&gibbonServiceDeskTicketTypeID=5'); await admin.screenshot({ path: `${__dirname}/shots/retention_field.png` });
  console.log('saving again keeps it (expect 1):', await admin.inputValue('form#ticketType input[name=retentionDays]'));

  const open = await file('Retention open ' + stamp), old = await file('Retention old ' + stamp), recent = await file('Retention recent ' + stamp);
  const doneStage = sql(`select gibbonServiceDeskStageID from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=5 and name='Done'`)[0][0];
  sql(`update gibbonServiceDeskTicket set gibbonServiceDeskStageID=${doneStage}, timestampClosed=DATE_SUB(NOW(), INTERVAL 3 DAY) where gibbonServiceDeskTicketID=${old}`);
  sql(`update gibbonServiceDeskTicket set gibbonServiceDeskStageID=${doneStage}, timestampClosed=DATE_SUB(NOW(), INTERVAL 12 HOUR) where gibbonServiceDeskTicketID=${recent}`);
  const noRetention = sql(`select count(*) from gibbonServiceDeskTicket where gibbonServiceDeskTicketTypeID<>5`)[0][0];
  const core = sql(`select value from gibbonSetting where name='absolutePath'`)[0][0];
  const script = core + '/modules/ServiceDesk/cli_deleteExpiredTickets.php';
  const run = args => execFileSync('php', [script, ...args], { cwd: core }).toString().trim();
  console.log('dry run:', run(['--dry-run']).replace(/\n/g, ' | '), '| nothing deleted yet (expect 3):', sql(`select count(*) from gibbonServiceDeskTicket where gibbonServiceDeskTicketID in (${open},${old},${recent})`)[0][0]);
  console.log('real run:', run([]));
  const left = sql(`select gibbonServiceDeskTicketID from gibbonServiceDeskTicket where gibbonServiceDeskTicketID in (${open},${old},${recent}) order by 1`).map(r => parseInt(r[0], 10));
  console.log('only the old closed ticket is gone (expect true):', JSON.stringify(left) === JSON.stringify([parseInt(open, 10), parseInt(recent, 10)]), '| other ticket types untouched (expect true):', sql(`select count(*) from gibbonServiceDeskTicket where gibbonServiceDeskTicketTypeID<>5`)[0][0] === noRetention);
  console.log('second run has nothing to do:', run([]));
  const web = await (await fetch(BASE + '/modules/ServiceDesk/cli_deleteExpiredTickets.php')).text();
  console.log('the script refuses a web request (expect true):', web.includes('only be run from the command line') && !web.includes('Fatal'));
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
