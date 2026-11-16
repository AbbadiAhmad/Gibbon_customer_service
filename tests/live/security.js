// Security probes: what a logged-in person can do by editing forms and addresses, and what a visitor without a login
// can reach. Run after flow.js (teacher 119 is in team Administration; 120 a teacher in no team; 463 and 427 parents of
// different families; 192 administrator). Every line says what is expected; "FAIL" marks a finding.
const { chromium, BASE, login, sql } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const P = BASE + '/modules/ServiceDesk/';
const stamp = Date.now() % 100000;
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const ret = page => (page.url().match(/return=(\w+)/) || [])[1];
let failures = 0;
const expect = (label, ok, detail = '') => { if (!ok) failures++; console.log((ok ? 'ok   ' : 'FAIL ') + label + (detail !== '' ? ' -> ' + detail : '')); };
// Changes hidden fields (or adds them) in a form that the page legitimately shows, then submits it: what an attacker
// with a browser's developer tools can do. The form keeps its valid CSRF token.
const tamper = async (page, form, fields, action) => {
  await page.evaluate(({ form, fields, action }) => {
    const f = document.querySelector(form);
    if (action) f.action = action;
    for (const [name, value] of Object.entries(fields)) {
      let inputs = Array.from(f.querySelectorAll(`[name="${name}"]`));
      if (inputs.length === 0) { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; f.appendChild(input); inputs = [input]; }
      for (const input of inputs) {
        if (input.tagName === 'SELECT') { const o = document.createElement('option'); o.value = value; input.appendChild(o); }
        input.value = value;
      }
    }
  }, { form, fields, action });
  await submit(page, form);
  return ret(page);
};
const one = q => (sql(q)[0] || [''])[0];

(async () => {
  const browser = await chromium.launch();
  const parent = await login(browser, '463'), other = await login(browser, '427'), member = await login(browser, '119'), admin = await login(browser, '192'), teacher = await login(browser, '120');
  for (const page of [parent, other, member, admin, teacher]) page.on('dialog', d => { failures++; console.log('FAIL a script ran: dialog "' + d.message() + '"'); d.dismiss(); });

  // earlier runs may have used up the hourly limit of requests: make those requests two hours old
  sql(`update gibbonServiceDeskTicket set timestampCreated=date_sub(timestampCreated, interval 2 hour) where gibbonPersonIDRequester in (427, 463) and timestampCreated > date_sub(now(), interval 1 hour)`);

  // ---------- a ticket of parent 463 and one of parent 427
  const file = async (page, subject, details) => {
    await page.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=5');
    await page.fill('form#request textarea', details); await page.fill('form#request input[name=subject]', subject);
    await submit(page, 'form#request'); return page.url().match(/TicketID=0*(\d+)/)[1];
  };
  const xss = `<script>window.__xss=1</script><img src=x onerror="window.__xss=2">"'><svg onload=window.__xss=3>`;
  const mine = await file(parent, 'Probe ' + stamp + ' ' + xss, 'Details ' + xss);
  const theirs = await file(other, 'Other family ' + stamp, 'Private.');
  console.log('tickets:', mine, '(parent 463),', theirs, '(parent 427)');

  // ---------- 1. script injection (stored and reflected)
  expect('stored subject has no tags', !/<[a-z!\/]/i.test(one(`select subject from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`)), one(`select subject from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`).slice(0, 80));
  await parent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + mine);
  await parent.fill('form#comment textarea', 'Comment ' + xss); await submit(parent, 'form#comment');
  const ran = async page => page.evaluate(() => window.__xss);
  for (const [who, page, urls] of [
    ['administrator', admin, [M + 'tickets.php&view=list&search=Probe ' + stamp, M + 'tickets_view.php&gibbonServiceDeskTicketID=' + mine, M + 'tickets.php&view=board&search=Probe ' + stamp, BASE + '/index.php', BASE + '/index.php?q=notifications.php']],
    ['team member', member, [M + 'tickets.php&view=list', BASE + '/index.php', BASE + '/index.php?q=notifications.php']],
    ['requester', parent, [M + 'tickets.php&view=list', M + 'tickets_view.php&gibbonServiceDeskTicketID=' + mine, BASE + '/index.php']],
  ]) {
    let bad = [];
    for (const url of urls) { await page.goto(url); await page.waitForTimeout(200); if (await ran(page)) bad.push(url.replace(BASE, '')); if (/<script>window\.__xss|onerror="window\.__xss|<svg onload/.test(await page.content())) bad.push('raw markup on ' + url.replace(BASE, '')); }
    expect(`no script runs for the ${who} (list, ticket, board, home box, notifications)`, bad.length === 0, bad.join(' ; '));
  }
  for (const [label, url] of [
    ['search parameter', M + 'tickets.php&view=list&search=' + encodeURIComponent('"><script>window.__xss=5</script>')],
    ['stage parameter', M + 'tickets.php&view=list&stage=' + encodeURIComponent('"><img src=x onerror=window.__xss=6>')],
    ['ticket number parameter', M + 'tickets_view.php&gibbonServiceDeskTicketID=' + encodeURIComponent(mine + '"><script>window.__xss=7</script>')],
    ['view parameter', M + 'tickets.php&view=' + encodeURIComponent('"><script>window.__xss=8</script>')],
    ['type parameter of the request page', M + 'request_new.php&gibbonServiceDeskTicketTypeID=' + encodeURIComponent('5"><script>window.__xss=9</script>')],
  ]) {
    await parent.goto(url); await parent.waitForTimeout(150);
    expect(`reflected ${label} is not executed`, !(await ran(parent)) && !/<script>window\.__xss|onerror=window\.__xss/.test(await parent.content()));
  }
  await parent.goto(M + 'tickets.php&view=list&status[]=Open');
  await parent.fill('form#filterSave input[name=name]', 'F' + stamp + ' "><img src=x onerror=window.__xss=4>'); await submit(parent, 'form#filterSave');
  await parent.waitForTimeout(150);
  expect('a filter name with markup is not executed', !(await ran(parent)) && !/onerror=window\.__xss=4/.test(await parent.content()), one(`select name from gibbonServiceDeskFilter where gibbonPersonID=463 order by gibbonServiceDeskFilterID desc limit 1`));
  const myFilter = one(`select gibbonServiceDeskFilterID+0 from gibbonServiceDeskFilter where gibbonPersonID=463 order by gibbonServiceDeskFilterID desc limit 1`);

  // ---------- 2. forms edited by the user
  const viewMine = M + 'tickets_view.php&gibbonServiceDeskTicketID=' + mine;
  await parent.goto(viewMine); await parent.fill('form#comment textarea', 'Tries to be internal ' + stamp);
  await tamper(parent, 'form#comment', { type: 'Internal Note' });
  expect('requester cannot write an internal note (stored as a comment)', one(`select type from gibbonServiceDeskTicketComment where comment like 'Tries to be internal ${stamp}%'`) === 'Comment');
  await parent.goto(viewMine); await parent.fill('form#comment textarea', 'Into the other family ' + stamp);
  let r = await tamper(parent, 'form#comment', { gibbonServiceDeskTicketID: theirs });
  expect('requester cannot comment on another family\'s ticket', r === 'error0' && one(`select count(*) from gibbonServiceDeskTicketComment where comment like 'Into the other family ${stamp}%'`) === '0', r);
  const stageBefore = one(`select gibbonServiceDeskStageID from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`);
  const doneStage = one(`select gibbonServiceDeskStageID+0 from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=5 and name='Done'`);
  await parent.goto(viewMine); await parent.fill('form#comment textarea', 'x');
  r = await tamper(parent, 'form#comment', { action: 'manage', gibbonServiceDeskStageID: doneStage, priority: 'Urgent', gibbonPersonIDAssignee: '463' });
  expect('requester cannot change stage, priority or assignee', r === 'error0' && one(`select concat(gibbonServiceDeskStageID, priority, coalesce(gibbonPersonIDAssignee, 'none')) from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`) === stageBefore + 'Normalnone', r);
  await parent.goto(viewMine); await parent.fill('form#comment textarea', 'x');
  r = await tamper(parent, 'form#comment', { action: 'move', gibbonServiceDeskTicketTypeID: '4' });
  expect('requester cannot move the ticket to another type', r === 'error0' && one(`select gibbonServiceDeskTicketTypeID+0 from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`) === '5', r);
  await parent.goto(viewMine); await parent.fill('form#comment textarea', 'x');
  r = await tamper(parent, 'form#comment', { gibbonServiceDeskStageID: doneStage, criteria: '' }, P + 'tickets_boardProcess.php');
  expect('requester cannot move a card through the board script', r === 'error0' && one(`select gibbonServiceDeskStageID from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`) === stageBefore, r);
  const commentID = one(`select gibbonServiceDeskTicketCommentID+0 from gibbonServiceDeskTicketComment where gibbonServiceDeskTicketID=${mine} and type='Comment' order by 1 limit 1`);
  await parent.goto(viewMine); await parent.fill('form#comment textarea', 'Rewritten ' + stamp);
  r = await tamper(parent, 'form#comment', { gibbonServiceDeskTicketCommentID: commentID }, P + 'tickets_comment_editProcess.php');
  expect('requester without the permission cannot edit a comment', r === 'error0' && one(`select count(*) from gibbonServiceDeskTicketComment where comment like 'Rewritten ${stamp}%'`) === '0', r);
  await parent.goto(viewMine); await parent.fill('form#comment textarea', 'x');
  r = await tamper(parent, 'form#comment', {}, P + 'tickets_deleteProcess.php');
  expect('requester cannot delete the ticket', r === 'error0' && one(`select count(*) from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`) === '1', r);
  await other.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + theirs); await other.fill('form#comment textarea', 'x');
  r = await tamper(other, 'form#comment', { gibbonServiceDeskTicketID: mine, action: 'withdraw', note: '' }, P + 'tickets_requesterProcess.php');
  expect('a parent cannot withdraw another family\'s request', r === 'error0' && one(`select gibbonServiceDeskStageID from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`) === stageBefore, r);

  await parent.goto(M + 'tickets_filter_delete.php&gibbonServiceDeskFilterID=' + myFilter);
  r = await tamper(parent, 'form', { gibbonServiceDeskFilterID: '2' });
  expect('a person cannot delete a shared filter', r === 'error0' && one(`select count(*) from gibbonServiceDeskFilter where gibbonServiceDeskFilterID=2`) === '1' && one(`select count(*) from gibbonServiceDeskFilter where gibbonServiceDeskFilterID=${myFilter}`) === '1', r);
  const adminFilter = one(`select gibbonServiceDeskFilterID+0 from gibbonServiceDeskFilter where gibbonPersonID is not null and gibbonPersonID<>463 limit 1`);
  if (adminFilter) {
    await parent.goto(M + 'tickets_filter_delete.php&gibbonServiceDeskFilterID=' + myFilter);
    r = await tamper(parent, 'form', { gibbonServiceDeskFilterID: adminFilter });
    expect('a person cannot delete another person\'s filter', r === 'error0' && one(`select count(*) from gibbonServiceDeskFilter where gibbonServiceDeskFilterID=${adminFilter}`) === '1', r);
  }
  await parent.goto(M + 'tickets.php&view=list&status[]=Closed');
  await parent.fill('form#filterSave input[name=name]', 'Shared by a parent ' + stamp);
  await tamper(parent, 'form#filterSave', { share: 'agents' });
  expect('a person without the administrator role cannot share a filter', one(`select coalesce(gibbonPersonID+0, 'shared') from gibbonServiceDeskFilter where name='Shared by a parent ${stamp}'`) === '463');

  await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=5'); await parent.fill('form#request textarea', 'On behalf ' + stamp);
  r = await tamper(parent, 'form#request', { gibbonPersonIDOnBehalf: '427' });
  expect('without the permission a request cannot be sent on behalf of someone', r === 'error0' && one(`select count(*) from gibbonServiceDeskTicket where searchText like 'On behalf ${stamp}%'`) === '0', r);
  await teacher.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=5'); await teacher.fill('form#request textarea', 'Wrong type ' + stamp);
  r = await tamper(teacher, 'form#request', { gibbonServiceDeskTicketTypeID: '1' });
  expect('a service not offered to the role cannot be requested', r === 'error1' && one(`select count(*) from gibbonServiceDeskTicket where searchText like 'Wrong type ${stamp}%'`) === '0', r);
  const foreignChild = one(`select c.gibbonPersonID+0 from gibbonFamilyChild c join gibbonFamilyAdult a on a.gibbonFamilyID=c.gibbonFamilyID where a.gibbonPersonID=427 limit 1`);
  await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=1');
  await parent.evaluate(child => { const box = document.querySelector('form#request input[type=checkbox]'); box.value = child; box.checked = true; }, foreignChild);
  await parent.selectOption('form#request select', 'Family'); await parent.fill('form#request input[type=date] >> nth=0', '2027-02-01');
  await submit(parent, 'form#request');
  expect('a parent cannot file an absence for a child of another family', ret(parent) === 'error1' && one(`select count(*) from gibbonServiceDeskTicket where gibbonPersonIDRequester=463 and fields like '%${foreignChild}%' and gibbonServiceDeskTicketTypeID=1`) === '0', ret(parent));

  await member.goto(viewMine);
  r = await tamper(member, 'form#manage', { gibbonServiceDeskStageID: one(`select gibbonServiceDeskStageID+0 from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=1 and name='Approved'`) });
  expect('a team member cannot use a stage of another ticket type', r === 'error1', r);
  await member.goto(viewMine);
  r = await tamper(member, 'form#manage', { gibbonPersonIDAssignee: '0000000463' });
  expect('a team member cannot assign someone outside the teams', r === 'error1' && one(`select coalesce(gibbonPersonIDAssignee, 'none') from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`) === 'none', r);

  // ---------- 3. a request without Gibbon's form token
  const before = one(`select count(*) from gibbonServiceDeskTicketComment where gibbonServiceDeskTicketID=${mine}`);
  const status = await parent.evaluate(async ({ url, id }) => {
    const body = new URLSearchParams({ address: '/modules/ServiceDesk/tickets_view.php', gibbonServiceDeskTicketID: id, action: 'comment', comment: 'No token' });
    const response = await fetch(url, { method: 'POST', body, credentials: 'include', redirect: 'manual' }); return response.status + ' ' + response.type;
  }, { url: P + 'tickets_viewProcess.php', id: mine });
  expect('a post without the form token changes nothing', one(`select count(*) from gibbonServiceDeskTicketComment where gibbonServiceDeskTicketID=${mine}`) === before, status);

  // ---------- 4. database injection attempts (as a parent who has few tickets: nothing else may appear)
  for (const query of [`search=${encodeURIComponent("' OR '1'='1")}`, `search=${encodeURIComponent('%\' UNION SELECT 1,2,3 -- ')}`, 'assignee=' + encodeURIComponent('1 OR 1=1'), 'type[]=' + encodeURIComponent('1) OR (1=1'), 'stage=' + encodeURIComponent("' OR ''='"), 'priority=' + encodeURIComponent("High' OR 1=1 -- "), 'requester=' + encodeURIComponent('463 OR 1=1'), 'gibbonServiceDeskFilterID=' + encodeURIComponent('2 OR 1=1')]) {
    await other.goto(M + 'tickets.php&view=list&' + query);
    const html = await other.content();
    const ids = [...new Set(await other.$$eval('a[href*="tickets_view.php"]', a => a.map(e => (e.href.match(/TicketID=0*(\d+)/) || [])[1])))];
    const foreign = ids.filter(id => one(`select count(*) from gibbonServiceDeskTicket t join gibbonServiceDeskTicketType ty using (gibbonServiceDeskTicketTypeID) where t.gibbonServiceDeskTicketID=${id} and (t.gibbonPersonIDRequester=427 or (ty.shareWithFamily='Y' and t.gibbonPersonIDRequester in (select b.gibbonPersonID from gibbonFamilyAdult a join gibbonFamilyAdult b on b.gibbonFamilyID=a.gibbonFamilyID where a.gibbonPersonID=427)))`) !== '1');
    expect(`list with ${decodeURIComponent(query).slice(0, 34)}: no error, nothing outside the family`, !/SQLSTATE|Fatal error/.test(html) && foreign.length === 0, `${ids.length} shown`);
  }
  // sorting and page size posted by the browser (Gibbon's tables post them)
  const posted = await admin.evaluate(async url => {
    const body = new URLSearchParams();
    body.append('sortBy[requester.surname]', 'ASC'); body.append('sortBy[requester.passwordStrong]', 'ASC'); body.append('sortBy[gibbonServiceDeskTicket.fields]', 'DESC'); body.append('pageSize', '100000');
    const response = await fetch(url, { method: 'POST', body, credentials: 'include' }); const html = await response.text();
    return { rows: (html.match(/tickets_view\.php&(amp;)?gibbonServiceDeskTicketID=/g) || []).length, error: /SQLSTATE|Fatal error/.test(html), size: html.length };
  }, M + 'tickets.php&view=list');
  expect('posted sorting by a foreign column and a huge page size are not honoured', !posted.error && posted.rows > 0 && posted.rows <= 1000, `${posted.rows} ticket links, ${Math.round(posted.size / 1024)} kB`);
  await other.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + encodeURIComponent(mine + ' OR 1=1'));
  expect('ticket address with "OR 1=1" shows nothing of another family', (await other.content()).includes('cannot be found'));

  // ---------- 5. pages and files
  for (const [who, page, pages] of [
    ['parent', parent, ['types_manage.php', 'teams_manage.php', 'workflow_manage.php', 'tickets_delete.php&gibbonServiceDeskTicketID=' + mine, 'tickets_comment_edit.php&gibbonServiceDeskTicketID=' + mine + '&gibbonServiceDeskTicketCommentID=' + commentID]],
    ['team member', member, ['types_manage.php', 'teams_manage_edit.php&gibbonServiceDeskTeamID=1', 'workflow_stage_edit.php', 'tickets_delete.php&gibbonServiceDeskTicketID=' + mine]],
  ]) {
    const open = [];
    for (const p of pages) { await page.goto(M + p); if (!/do not have access/.test(await page.content())) open.push(p); }
    expect(`${who}: administration pages refuse`, open.length === 0, open.join(', '));
  }
  expect('another family cannot open the ticket', await (async () => { await other.goto(viewMine); return (await other.content()).includes('cannot be found'); })());
  const visitor = await (await browser.newContext()).newPage();
  const seen = [];
  for (const p of ['tickets.php', 'tickets_view.php&gibbonServiceDeskTicketID=' + mine, 'types_manage.php', 'request_new.php']) {
    await visitor.goto(M + p); if (/Probe |serviceDesk-|Manage Ticket Types/.test(await visitor.innerText('body'))) seen.push(p);
  }
  expect('a visitor without a login sees nothing of the module', seen.length === 0, seen.join(', '));
  const raw = [], pathShown = [];
  for (const f of ['cli_deleteExpiredTickets.php', 'hook_dashboard.php', 'src/Service/TicketService.php', 'src/Domain/TicketGateway.php', 'tickets.php', 'tickets_viewProcess.php', 'manifest.php', 'CHANGEDB.php']) {
    const response = await visitor.goto(P + f); const text = await visitor.content();
    if (/Probe |deleted|SELECT |Other family/.test(text.replace('only be run from the command line', ''))) raw.push(f + ' (' + response.status() + ')');
    if (/Fatal error/.test(text)) pathShown.push(f);
  }
  expect('module files requested directly reveal no data and change nothing', raw.length === 0 && one(`select count(*) from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`) === '1', raw.join(', '));
  console.log('note ' + (pathShown.length ? 'with display_errors on (this test server), a direct request prints a PHP error with the server path for: ' + pathShown.join(', ') + ' - as for Gibbon\'s own files; off in production' : 'no PHP error text shown for direct requests'));

  // ---------- 6. what a requester can learn about internal work
  const shown = async () => { await parent.goto(M + 'tickets.php&view=list&search=Probe ' + stamp); return (await parent.innerText('table tbody tr')).replace(/\s+/g, ' '); };
  const beforeNote = one(`select coalesce(timestampModifiedPublic, '') from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`);
  await parent.goto(viewMine);
  await new Promise(resolve => setTimeout(resolve, 1200));
  await member.goto(viewMine); await member.fill('form#comment textarea', 'Internal secret ' + stamp); await member.selectOption('form#comment select[name=type]', 'Internal Note'); await submit(member, 'form#comment');
  await member.selectOption('form#manage select[name=priority]', 'Urgent'); await submit(member, 'form#manage');
  expect('an internal note and a priority change do not move the requester\'s "last update"', one(`select coalesce(timestampModifiedPublic, '') from gibbonServiceDeskTicket where gibbonServiceDeskTicketID=${mine}`) === beforeNote && !(await shown()).includes('Updated'));
  await parent.goto(viewMine); const page = await parent.content();
  expect('the requester\'s ticket page holds no internal note, no priority, no internal log', !page.includes('Internal secret') && !/Priority/.test(await parent.innerText('.serviceDesk-ticket')) && !page.includes('Priority set to'));
  await parent.goto(M + 'tickets.php&view=list&search=' + encodeURIComponent('secret ' + stamp));
  expect('the requester cannot find the internal note by searching', (await parent.$$('a[href*="tickets_view.php"]')).length === 0);
  await parent.goto(BASE + '/index.php?q=notifications.php');
  expect('the requester got no notification about the internal note', !(await parent.innerText('body')).includes('Internal secret'));

  // ---------- 7. many requests in a short time
  let last = '';
  const recent = parseInt(one(`select count(*) from gibbonServiceDeskTicket where gibbonPersonIDRequester=427 and timestampCreated > date_sub(now(), interval 1 hour)`), 10);
  for (let i = recent; i < 21; i++) {
    await other.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=5'); await other.fill('form#request textarea', 'Flood ' + i);
    await submit(other, 'form#request'); last = ret(other);
  }
  expect('the 21st request within an hour is refused, with a message', last === 'error20' && /many requests/.test(await other.innerText('body')) && one(`select count(*) from gibbonServiceDeskTicket where gibbonPersonIDRequester=427 and timestampCreated > date_sub(now(), interval 1 hour)`) === '20', last);

  console.log(failures === 0 ? 'ALL PROBES PASSED' : failures + ' FINDING(S)');
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
