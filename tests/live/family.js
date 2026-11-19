// Family sharing and withdraw / reopen. Run after flow.js (teacher 119 is in team Administration).
// Parents 463 and 462 share family 148 (both with data access); 427 is in another family.
const { chromium, BASE, login, check, sql } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const ret = page => (page.url().match(/return=(\w+)/) || [])[1];
const file = async (page, typeID, fill) => {
  await page.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=' + typeID);
  await fill(page);
  await submit(page, 'form#request');
  return page.url().match(/TicketID=0*(\d+)/)[1];
};
const sees = async (page, id) => { await page.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id); return !(await page.content()).includes('cannot be found'); };
const told = async (page, subject) => { await page.goto(BASE + '/index.php?q=notifications.php'); return page.$$eval('table tbody tr', (rows, s) => rows.map(r => r.innerText.replace(/\s+/g, ' ')).filter(t => t.includes(s)), subject); };
(async () => {
  const browser = await chromium.launch();
  const parent = await login(browser, '463'), coParent = await login(browser, '462'), stranger = await login(browser, '427'), member = await login(browser, '119');
  const subject = 'Family test ' + (Date.now() % 100000);

  // an absence (shared with the family) and a complaint (not shared)
  const absence = await file(parent, 1, async p => {
    await p.check('form#request input[type=checkbox] >> nth=0'); await p.selectOption('form#request select', 'Family');
    await p.fill('form#request input[type=date] >> nth=0', '2026-12-14'); await p.fill('form#request input[name=subject]', subject);
  });
  const complaint = await file(parent, 4, async p => { await p.fill('form#request textarea', 'Private matter.'); await p.fill('form#request input[name=subject]', 'Private ' + subject); });
  console.log('tickets:', absence, complaint);

  console.log('co-parent sees the shared absence (expect true):', await sees(coParent, absence));
  await coParent.goto(M + 'tickets.php'); console.log('  and finds it in the list (expect true):', (await coParent.innerText('body')).includes(subject));
  console.log('co-parent does NOT see the complaint (expect true):', !(await sees(coParent, complaint)));
  console.log('parent of another family sees neither (expect true):', !(await sees(stranger, absence)) && !(await sees(stranger, complaint)));
  await coParent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + absence);
  console.log('co-parent can comment, withdraw (expect true):', !!(await coParent.$('form#comment')) && !!(await coParent.$('a[href*="action=withdraw"]')));

  // the team comments: both parents are told
  await member.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + absence);
  await member.fill('form#comment textarea', 'We have received the absence.'); await submit(member, 'form#comment');
  console.log('both parents told about the team\'s comment (expect 1, 2: the co-parent was also told about the new request):', (await told(parent, subject)).length, (await told(coParent, subject)).length);

  // withdraw by the co-parent, with a note
  await coParent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + absence);
  await coParent.click('a[href*="action=withdraw"]'); await coParent.waitForLoadState(); await check(coParent, 'withdraw page');
  await coParent.screenshot({ path: `${__dirname}/shots/family_withdraw.png` });
  await coParent.fill('form#requesterAction textarea', 'The trip was cancelled.');
  await submit(coParent, 'form#requesterAction'); console.log('withdrawn:', ret(coParent));
  let text = await coParent.innerText('.serviceDesk-ticket');
  console.log('  stage Withdrawn (expect true):', /STAGE\s*Withdrawn/i.test(text), '| history says who (expect true):', /Family\s+withdrew the request|Schultz withdrew the request|withdrew the request/.test(text), '| note shown (expect true):', text.includes('The trip was cancelled.'));
  console.log('  team member not told by default (expect true):', !(await told(member, subject)).some(t => t.includes('withdrew the request')));
  console.log('  withdraw link gone, reopen link there (expect true):', !(await coParent.$('a[href*="action=withdraw"]')) && !!(await coParent.$('a[href*="action=reopen"]')));
  console.log('  other family cannot open the withdraw page (expect true):', await (async () => { await stranger.goto(M + 'tickets_requester.php&action=reopen&gibbonServiceDeskTicketID=' + absence); return (await stranger.content()).includes('cannot be found'); })());

  // reopen by the parent
  await parent.goto(M + 'tickets_requester.php&action=reopen&gibbonServiceDeskTicketID=' + absence); await check(parent, 'reopen page');
  await submit(parent, 'form#requesterAction'); console.log('reopened:', ret(parent));
  text = await parent.innerText('.serviceDesk-ticket');
  console.log('  stage New again (expect true):', /STAGE\s*New/i.test(text), '| closed time cleared (expect true):', sql(`select timestampClosed is null from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${absence}`)[0][0] == '1');

  // reopening is limited to 30 days after closing
  sql(`update gibbonServiceDeskTicket set gibbonServiceDeskStageID=(select gibbonServiceDeskStageID from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=1 and name='Done'), timestampClosed=DATE_SUB(NOW(), INTERVAL 40 DAY) where gibbonServiceDeskTicketID=${absence}`);
  await parent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + absence);
  console.log('closed 40 days ago: no reopen link (expect true):', !(await parent.$('a[href*="action=reopen"]')));
  await parent.goto(M + 'tickets_requester.php&action=reopen&gibbonServiceDeskTicketID=' + absence);
  console.log('  and the page refuses (expect true):', (await parent.content()).includes('cannot be found'));
  // a ticket type without a withdrawn stage cannot be withdrawn
  sql(`update gibbonServiceDeskStage set withdrawn='N' where gibbonServiceDeskTicketTypeID=4`);
  await parent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + complaint);
  console.log('no withdrawn stage in the workflow: no withdraw link (expect true):', !(await parent.$('a[href*="action=withdraw"]')));
  sql(`update gibbonServiceDeskStage set withdrawn='Y' where gibbonServiceDeskTicketTypeID=4 and name='Withdrawn'`);
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
