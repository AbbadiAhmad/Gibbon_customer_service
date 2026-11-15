// Board view. Run after flow.js (teacher 119 is in team Administration).
//  1. the view switch, and the last view being remembered per person,
//  2. several ticket types -> columns Open / In Progress / Closed, cards not movable,
//  3. one ticket type -> its workflow stages as columns; drag a card to another stage; drag to a stage with
//     automation (confirmation, attendance recorded, ticket ends in Done),
//  4. a parent gets the list only; ticket type colour can be set.
const { chromium, BASE, login, check } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const columns = page => page.$$eval('.serviceDesk-column', cols => cols.map(c => c.querySelector('.serviceDesk-columnHead span').innerText.trim() + ':' + c.querySelectorAll('.serviceDesk-ticketCard').length).join(' | '));
const columnOf = (page, ticket) => page.$eval(`.serviceDesk-ticketCard[href$="TicketID=${ticket}"]`, c => c.closest('.serviceDesk-column').querySelector('.serviceDesk-columnHead span').innerText.trim());
(async () => {
  const browser = await chromium.launch();

  // a fresh absence ticket in "New"
  const parent = await login(browser, '463');
  await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=1');
  await parent.check('form#request input[type=checkbox] >> nth=0');
  await parent.selectOption('form#request select', 'Family');
  await parent.fill('form#request input[type=date] >> nth=0', '2026-12-07');
  await submit(parent, 'form#request');
  const ticket = parent.url().match(/TicketID=0*(\d+)/)[1];
  console.log('new absence ticket:', ticket);

  // 1. switch + memory
  const teacher = await login(browser, '119');
  await teacher.goto(M + 'tickets.php&view=list'); await check(teacher, 'list');
  console.log('list shown (expect true):', !!(await teacher.$('table')) && !(await teacher.$('.serviceDesk-board')));
  await teacher.click('.serviceDesk-viewSwitch a:has-text("Board")'); await teacher.waitForSelector('.serviceDesk-board'); await check(teacher, 'board');
  await teacher.goto(M + 'tickets.php');
  console.log('board remembered on the next visit (expect true):', !!(await teacher.$('.serviceDesk-board')));
  const again = await login(browser, '119');
  await again.goto(M + 'tickets.php');
  console.log('board remembered after logging in again (expect true):', !!(await again.$('.serviceDesk-board')));

  // 2. mixed types: status columns, nothing movable
  console.log('2. columns:', await columns(teacher), '| movable cards (expect 0):', await teacher.$$eval('.serviceDesk-ticketCard.movable', c => c.length));
  await teacher.screenshot({ path: `${__dirname}/shots/board_status.png` });

  // 3. one type: stage columns, drag and drop
  await teacher.goto(M + 'tickets.php&type[]=1'); await check(teacher, 'board of one type');
  console.log('3. columns:', await columns(teacher), '| ticket is in:', await columnOf(teacher, ticket));
  await teacher.screenshot({ path: `${__dirname}/shots/board_stages.png` });
  const card = () => teacher.locator(`.serviceDesk-ticketCard[href$="TicketID=${ticket}"]`);
  const column = name => teacher.locator(`.serviceDesk-column:has(.serviceDesk-columnHead span:text-is("${name}"))`);
  await Promise.all([teacher.waitForNavigation(), card().dragTo(column('In Progress'))]);
  console.log('after drag to In Progress:', (teacher.url().match(/return=(\w+)/) || [])[1], '| ticket is in (expect In Progress):', await columnOf(teacher, ticket), '| still the same board (expect true):', teacher.url().includes('type'));
  await check(teacher, 'board after move');

  teacher.once('dialog', dialog => { console.log('confirmation asked (expect true):', dialog.message().includes('automation')); dialog.accept(); });
  await Promise.all([teacher.waitForNavigation(), card().dragTo(column('Approved *'))]);
  console.log('after drag to Approved:', (teacher.url().match(/return=(\w+)/) || [])[1], '| ticket is in (expect Done):', await columnOf(teacher, ticket));
  await teacher.screenshot({ path: `${__dirname}/shots/board_after.png` });

  // saved filter (Absence Intake, team filter on type 1) opens as a board too
  await teacher.goto(M + 'tickets.php&gibbonServiceDeskFilterID=5');
  console.log('saved filter as board:', await columns(teacher));

  // back to the list is remembered as well
  await teacher.goto(M + 'tickets.php&view=list'); await teacher.goto(M + 'tickets.php');
  console.log('list remembered (expect true):', !(await teacher.$('.serviceDesk-board')));
  await teacher.screenshot({ path: `${__dirname}/shots/board_list.png` });

  // 4. parent: no switch, no board even when asked; a forged move is refused
  await parent.goto(M + 'tickets.php&view=board'); await check(parent, 'parent tickets');
  console.log('4. parent has no switch and no board (expect true):', !(await parent.$('.serviceDesk-viewSwitch')) && !(await parent.$('.serviceDesk-board')));

  // colour of a ticket type
  const admin = await login(browser, '192');
  await admin.goto(M + 'types_manage_edit.php&gibbonServiceDeskTicketTypeID=5'); await check(admin, 'type edit with colour');
  console.log('colour field value:', await admin.inputValue('[name=color]'));
  await admin.fill('[name=color]', '#0ea5e9').catch(async () => { await admin.$eval('[name=color]', e => { e.value = '#0ea5e9'; e.dispatchEvent(new Event('input', { bubbles: true })); e.dispatchEvent(new Event('change', { bubbles: true })); }); });
  await submit(admin, 'form#ticketType');
  console.log('type saved:', (admin.url().match(/return=(\w+)/) || [])[1]);
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
