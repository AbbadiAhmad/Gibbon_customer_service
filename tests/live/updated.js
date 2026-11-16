// The "Updated" mark (a ticket changed since you last opened it) and sorting in the list.
// Run after flow.js (teacher 119 is in team Administration).
const { chromium, BASE, login, check, sql } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const stamp = Date.now() % 100000;
// The rows of the list: ticket number, whether it carries the mark, whether it is bold.
const rows = async page => {
  await page.goto(M + 'tickets.php&view=list');
  return page.$$eval('table tbody tr', trs => trs.map(tr => {
    const link = tr.querySelector('a[href*="tickets_view.php"]');
    return link ? { id: (link.href.match(/TicketID=0*(\d+)/) || [])[1], subject: link.innerText.trim(), updated: !!tr.querySelector('.serviceDesk-new'), bold: parseInt(getComputedStyle(link).fontWeight, 10) >= 600 } : null;
  }).filter(Boolean));
};
const row = (list, id) => list.find(r => r.id === String(id)) || {};
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
(async () => {
  const browser = await chromium.launch();
  const parent = await login(browser, '463'), member = await login(browser, '119');
  const file = async subject => {
    await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=5');
    await parent.fill('form#request textarea', 'Details.'); await parent.fill('form#request input[name=subject]', subject);
    await submit(parent, 'form#request'); return parent.url().match(/TicketID=0*(\d+)/)[1];
  };
  const comment = async (page, id, text, internal) => {
    await page.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
    await page.fill('form#comment textarea', text);
    if (internal) await page.selectOption('form#comment select[name=type]', 'Internal Note');
    await submit(page, 'form#comment');
  };

  const older = await file('Older ' + stamp); await wait(1100);
  const id = await file('Flag me ' + stamp); await wait(1100);

  // the requester has just seen their own new ticket; for the team it is new
  console.log('requester: own new ticket is not marked (expect false false):', row(await rows(parent), id).updated, row(await rows(parent), id).bold);
  let list = await rows(member);
  console.log('team member: the new ticket is marked and bold (expect true true):', row(list, id).updated, row(list, id).bold);
  await member.screenshot({ path: `${__dirname}/shots/updated_list.png` });
  console.log('  and listed before tickets without news (expect true):', list.findIndex(r => r.updated === false) === -1 || list.findIndex(r => r.id === String(id)) < list.findIndex(r => r.updated === false));
  await member.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
  console.log('team member opened it: mark gone (expect false):', row(await rows(member), id).updated);

  // the PARENT comments -> marked for the team member, not for the parent
  await wait(1100); await comment(parent, id, 'Any news?');
  console.log('parent commented: marked for the team member (expect true), not for the parent (expect false):', row(await rows(member), id).updated, row(await rows(parent), id).updated);
  await member.goto(M + 'tickets.php&view=board&type[]=5');
  console.log('  on the board too (expect true):', await member.$$eval('.serviceDesk-ticketCard', (cards, id) => cards.some(c => c.href.endsWith('TicketID=' + id) && !!c.querySelector('.serviceDesk-new')), id));
  await member.screenshot({ path: `${__dirname}/shots/updated_board.png` });
  await member.goto(BASE + '/index.php');
  console.log('  and in the sidebar box (expect true):', await member.$$eval('.serviceDesk-box li', (items, s) => items.some(li => li.innerText.includes(s) && !!li.querySelector('.serviceDesk-new')), 'Flag me ' + stamp));

  // the team member answers -> marked for the parent; an internal note does not alert the parent
  await wait(1100); await comment(member, id, 'We are on it.');
  console.log('team member answered: marked for the parent (expect true), not for the team member (expect false):', row(await rows(parent), id).updated, row(await rows(member), id).updated);
  await parent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
  await wait(1100); await comment(member, id, 'Between us: call the bursar.', true);
  console.log('internal note: parent not alerted (expect false):', row(await rows(parent), id).updated);
  const other = await login(browser, '192');
  console.log('  but the administrator, who has not opened it, is (expect true):', row(await rows(other), id).updated);

  // moving a card on the board is the person's own change
  await member.goto(M + 'tickets.php&view=board&type[]=5');
  await Promise.all([member.waitForNavigation(), member.locator(`.serviceDesk-ticketCard[href$="TicketID=${id}"]`).dragTo(member.locator('.serviceDesk-column:has(.serviceDesk-columnHead span:text-is("In Progress"))'))]);
  console.log('moved on the board by the team member: not marked for them (expect false), marked for the parent (expect true):', row(await rows(member), id).updated, row(await rows(parent), id).updated);

  // closed tickets nobody has opened are not marked
  const doneStage = sql(`select gibbonServiceDeskStageID from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=5 and name='Done'`)[0][0];
  sql(`update gibbonServiceDeskTicket set gibbonServiceDeskStageID=${doneStage}, timestampClosed=NOW() where gibbonServiceDeskTicketID=${older}`);
  sql(`delete from gibbonServiceDeskTicketSeen where gibbonServiceDeskTicketID=${older} and gibbonPersonID=119`);
  console.log('a closed ticket never opened is not marked (expect false):', row(await rows(member), older).updated);

  // SORTING: the "Last Update" column shows date and time; clicking a heading sorts
  await member.goto(M + 'tickets.php&view=list'); await check(member, 'list');
  console.log('last update shows the date and time (expect true):', /\d{1,2} \w{3} \d{4}, \d{2}:\d{2}/.test(await member.innerText('table tbody tr')));
  const order = () => member.$$eval('table tbody tr a[href*="tickets_view.php"]', a => [...new Set(a.map(e => (e.href.match(/TicketID=0*(\d+)/) || [])[1]))].map(Number));
  const sorted = (list, dir) => list.every((v, i) => i === 0 || (dir === 'asc' ? list[i - 1] <= v : list[i - 1] >= v));
  const heading = async label => { await member.click(`table thead th:has-text("${label}"), table thead a:has-text("${label}")`); await member.waitForTimeout(1200); };
  await heading('#'); const first = await order();
  await heading('#'); const second = await order();
  console.log('click "#": sorted by ticket number, and the other way on the second click (expect true):', (sorted(first, 'asc') && sorted(second, 'desc')) || (sorted(first, 'desc') && sorted(second, 'asc')), '|', first.join(','), '->', second.join(','));
  await heading('Subject'); const subjects = await member.$$eval('table tbody tr td:nth-child(2) a', a => a.map(e => e.innerText.trim().toLowerCase()));
  console.log('click "Subject": sorted by subject (expect true):', subjects.every((v, i) => i === 0 || subjects[i - 1].localeCompare(v) <= 0) || subjects.every((v, i) => i === 0 || subjects[i - 1].localeCompare(v) >= 0));
  await check(member, 'list after sorting');
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
