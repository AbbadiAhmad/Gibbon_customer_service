// Comment permissions. Run after flow.js: ticket #1 then has an internal note and a public comment by teacher 119
// (team member; holds Edit Comments_my and Change Comment Visibility_my) and a comment by parent 463 (no permission).
// Administrator 192 holds both "_all" actions.
const { chromium, BASE, login, check } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const TICKET = M + 'tickets_view.php&gibbonServiceDeskTicketID=1';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const ret = page => (page.url().match(/return=(\w+)/) || [])[1];
// The entries of the history: text, and the addresses of the Edit / Delete links when they are offered.
const entries = page => page.$$eval('.serviceDesk-entry', els => els.map(e => ({
  text: e.querySelector('.serviceDesk-entryText').innerText.trim(),
  meta: e.querySelector('.serviceDesk-entryMeta') ? e.querySelector('.serviceDesk-entryMeta').innerText : '',
  edit: (e.querySelector('a[href*="tickets_comment_edit.php"]') || {}).href,
  del: (e.querySelector('a[href*="tickets_comment_delete.php"]') || {}).href,
})));
const find = (list, part) => list.find(e => e.text.includes(part)) || {};
(async () => {
  const browser = await chromium.launch();
  const teacher = await login(browser, '119');
  const parent = await login(browser, '463');
  const admin = await login(browser, '192');

  // TEACHER: tools on own comments only
  await teacher.goto(TICKET); await check(teacher, 'teacher ticket');
  let list = await entries(teacher);
  const own = find(list, 'Thank you, we hope'), note = find(list, 'Checking with the class teacher'), parents = find(list, 'Thanks!'), log = find(list, 'Moved to Done');
  console.log('teacher: tools on own comment (expect true):', !!own.edit && !!own.del, '| on own internal note (expect true):', !!note.edit,
    '| none on the parent\'s comment (expect true):', !parents.edit && !parents.del, '| none on log lines (expect true):', !log.edit && !log.del);
  await teacher.screenshot({ path: `${__dirname}/shots/comments_teacher.png` });

  // edit own text
  await teacher.goto(own.edit); await check(teacher, 'edit page');
  await teacher.screenshot({ path: `${__dirname}/shots/comments_edit.png` });
  await teacher.fill('form#commentEdit textarea', 'Thank you, we wish them a quick recovery.');
  await submit(teacher, 'form#commentEdit'); console.log('edit own:', ret(teacher));
  list = await entries(teacher);
  console.log('text changed and marked edited (expect true):', find(list, 'quick recovery').meta.includes('edited'));

  // make own internal note visible
  await parent.goto(TICKET);
  console.log('parent does not see the note before (expect true):', !find(await entries(parent), 'Checking with the class teacher').text);
  await teacher.goto(note.edit);
  await teacher.selectOption('form#commentEdit select[name=type]', 'Comment');
  await submit(teacher, 'form#commentEdit'); console.log('visibility change:', ret(teacher));
  await parent.goto(TICKET);
  list = await entries(parent);
  console.log('parent sees it now (expect true):', !!find(list, 'Checking with the class teacher').text,
    '| parent sees no tools and no internal log (expect true):', list.every(e => !e.edit && !e.del) && !find(list, 'visible to the requester').text);
  await parent.goto(BASE + '/index.php?q=notifications.php');
  console.log('parent notified of the now visible comment (expect true):', (await parent.innerText('body')).includes('Checking with the class teacher'));

  // teacher cannot reach the parent's comment by address; parent has no access to the pages at all
  await admin.goto(TICKET);
  list = await entries(admin);
  const parentsAsAdmin = find(list, 'Thanks!');
  console.log('administrator: tools on every comment (expect true):', list.filter(e => e.meta && !/Service Desk/.test(e.meta) && !/Moved to|Assigned to|Priority|visible to|Edited|Deleted/.test(e.text)).every(e => e.edit && e.del));
  await teacher.goto(parentsAsAdmin.edit);
  console.log('teacher refused on the parent\'s comment (expect true):', (await teacher.content()).includes('cannot be found'));
  await parent.goto(parentsAsAdmin.edit);
  console.log('parent refused on the edit page (expect true):', (await parent.content()).includes('do not have access'));

  // ADMINISTRATOR: edit someone else's comment, make it internal, delete another
  await admin.goto(parentsAsAdmin.edit); await check(admin, 'admin edit page');
  await admin.fill('form#commentEdit textarea', 'Thanks a lot!');
  await submit(admin, 'form#commentEdit'); console.log('admin edits the parent\'s comment:', ret(admin));
  list = await entries(admin);
  console.log('changed + trace in internal history (expect true):', !!find(list, 'Thanks a lot!').text && !!find(list, 'Edited a comment').text);
  await admin.goto(find(list, 'quick recovery').del); await check(admin, 'admin delete page');
  await submit(admin, 'form'); console.log('admin deletes the teacher\'s comment:', ret(admin));
  list = await entries(admin);
  console.log('gone + trace (expect true):', !find(list, 'quick recovery').text && !!find(list, 'Deleted a comment').text);
  await admin.screenshot({ path: `${__dirname}/shots/comments_admin.png` });
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 6).join('\n')); process.exit(1); });
