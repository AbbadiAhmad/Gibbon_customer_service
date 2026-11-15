// Search: ticket number, subject, details, children, classes, dates, requester, assignee, public comments - and not
// internal notes. Run after flow.js. Ticket #1 of flow.js was created before the search text existed (it has none).
const { chromium, BASE, login, check, sql } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const stamp = 'zq' + (Date.now() % 100000);
(async () => {
  const browser = await chromium.launch();
  const parent = await login(browser, '463'), member = await login(browser, '119'), admin = await login(browser, '192'), other = await login(browser, '427');
  // a request with its own words in the subject and the details, an assignee, a public comment and an internal note
  await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=5');
  await parent.fill('form#request textarea', 'Lost the blazer ' + stamp + 'blazer in the gym');
  await parent.fill('form#request input[name=subject]', 'Zebra uniform ' + stamp);
  await submit(parent, 'form#request');
  const id = parent.url().match(/TicketID=0*(\d+)/)[1];
  await member.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
  await member.selectOption('form#manage select[name=gibbonPersonIDAssignee]', '0000000119'); await submit(member, 'form#manage');
  await member.fill('form#comment textarea', 'Found a hoodie ' + stamp + 'hoodie in lost property.'); await submit(member, 'form#comment');
  await member.fill('form#comment textarea', 'Secret ' + stamp + 'secret between colleagues.'); await member.selectOption('form#comment select[name=type]', 'Internal Note'); await submit(member, 'form#comment');
  console.log('search text stored for the new ticket (expect true):', sql(`select searchText is not null and searchText like '%blazer%' from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${id}`)[0][0] == '1');

  const find = async (page, query) => {
    await page.goto(M + 'tickets.php&view=list&search=' + encodeURIComponent(query));
    const html = await page.content();
    const problems = (html.match(/SQLSTATE[^<]*|Fatal error[^<]*/g) || []);
    const links = await page.$$eval('a[href*="tickets_view.php"]', a => a.map(e => (e.href.match(/TicketID=0*(\d+)/) || [])[1]));
    return { ids: [...new Set(links)], problems };
  };
  const expect = async (label, page, query, ids, notIds = []) => {
    const r = await find(page, query);
    const ok = ids.every(i => r.ids.includes(String(i))) && notIds.every(i => !r.ids.includes(String(i))) && r.problems.length === 0;
    console.log((ok ? 'ok   ' : 'FAIL ') + label + ' "' + query + '" ->', r.ids.join(',') || '-', r.problems.join(' '));
  };

  await expect('subject', admin, 'Zebra ' + stamp, [id]);
  await expect('details', admin, stamp + 'blazer', [id]);
  await expect('requester name', admin, 'Jada', [id]);
  await expect('requester, surname first', admin, 'Schultz Jada', [id]);
  await expect('assignee name', admin, 'Nathan ' + stamp + 'blazer', [id]);
  await expect('public comment', admin, stamp + 'hoodie', [id]);
  await expect('ticket number', admin, '#' + id, [id]);
  await expect('several words, all must match', admin, 'Zebra gym ' + stamp, [id]);
  await expect('one word that is not there', admin, 'Zebra giraffe', [], [id]);
  await expect('children (absence of #1 was for Amber)', admin, 'Amber Blackwell', [1]);
  await expect('answers of a ticket made before the search text existed ("Medical")', admin, 'Medical Amber', [1]);
  await expect('a date as typed in Gibbon', admin, '16/11/2026', [1]);
  await expect('the requester finds their own ticket', parent, stamp + 'blazer', [id]);
  await expect('the requester finds a public comment', parent, stamp + 'hoodie', [id]);
  await expect('internal notes are not searched (administrator)', admin, stamp + 'secret', [], [id]);
  await expect('internal notes are not searched (requester)', parent, stamp + 'secret', [], [id]);
  await expect('another family finds nothing of it', other, stamp + 'blazer', [], [id]);
  await expect('LIKE wildcards are plain characters', admin, '%', [], [id]);
  await expect('quotes do not break it', admin, `O'Brien "x" ; --`, []);
  // the search can be saved in a filter like any other criterion
  await admin.goto(M + 'tickets.php&view=list&search=' + stamp + 'blazer');
  await admin.fill('form#filterSave input[name=name]', 'Blazers ' + stamp); await submit(admin, 'form#filterSave');
  console.log('saved as a filter and applied (expect true):', (await admin.content()).includes('Zebra uniform'), '| search shown in the filter form (expect true):', (await admin.inputValue('input#search')) === stamp + 'blazer');
  await admin.screenshot({ path: `${__dirname}/shots/search_admin.png` });
  await admin.click('.serviceDesk-filterDelete a'); await admin.waitForLoadState(); await submit(admin, 'form');
  // and on the board
  await admin.goto(M + 'tickets.php&view=board&search=' + stamp + 'blazer');
  console.log('the board uses the search too (expect 1):', await admin.$$eval('.serviceDesk-ticketCard', c => c.length));
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
