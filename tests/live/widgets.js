// Dashboard widgets: "my open requests" on the parents' dashboard, "unassigned tickets" on the staff dashboard.
// Run after flow.js and family.js / teachers.js (some open tickets exist; 119 is a team member; 120 is a teacher in no team).
const { chromium, BASE, login, check, sql } = require('./lib');
(async () => {
  const browser = await chromium.launch();
  const dashboard = async (user, name) => {
    const page = await login(browser, user);
    await page.goto(BASE + '/index.php'); await check(page, user + ' dashboard');
    const tab = await page.$('text=Service Desk');
    if (tab) { await tab.click().catch(() => {}); }
    await page.screenshot({ path: `${__dirname}/shots/widget_${name}.png` });
    return { page, html: await page.content(), widget: (await page.$$eval('.serviceDesk-widget', w => w.map(e => e.innerText.replace(/\s+/g, ' ').trim()))) };
  };

  const open = sql(`select count(*) from gibbonServiceDeskTicket t join gibbonServiceDeskStage s on s.gibbonServiceDeskStageID=t.gibbonServiceDeskStageID where t.gibbonPersonIDRequester=463 and s.category in ('Open','In Progress')`)[0][0];
  const parent = await dashboard('463', 'parent');
  console.log('parent: tab "Service Desk" (expect true):', parent.html.includes('Service Desk'), '| widget:', (parent.widget[0] || '(none)').slice(0, 140));
  console.log('  shows "My open requests" with the count of open tickets', open, '(expect true):', new RegExp('^My open requests ' + open + ' ', 'i').test(parent.widget[0] || ''));
  console.log('  links to the ticket and to a new request (expect true):', (await parent.page.$$('a[href*="tickets_view.php"]')).length > 0 && !!(await parent.page.$('a[href*="request_new.php"]')));

  const coParent = await dashboard('462', 'coparent');
  const familyOpen = sql(`select count(*) from gibbonServiceDeskTicket t join gibbonServiceDeskStage s on s.gibbonServiceDeskStageID=t.gibbonServiceDeskStageID join gibbonServiceDeskTicketType ty on ty.gibbonServiceDeskTicketTypeID=t.gibbonServiceDeskTicketTypeID where s.category in ('Open','In Progress') and (t.gibbonPersonIDRequester=462 or (t.gibbonPersonIDRequester=463 and ty.shareWithFamily='Y'))`)[0][0];
  console.log('co-parent: own + shared requests of the family, ' + familyOpen + ' (expect true):', new RegExp('^My open requests ' + familyOpen + ' ', 'i').test(coParent.widget[0] || ''), '|', (coParent.widget[0] || '').slice(0, 100));

  const stranger = await dashboard('427', 'stranger');
  console.log('parent without requests: empty text (expect true):', /no open requests/i.test(stranger.widget[0] || ''));

  const unassigned = sql(`select count(*) from gibbonServiceDeskTicket t join gibbonServiceDeskStage s on s.gibbonServiceDeskStageID=t.gibbonServiceDeskStageID where t.gibbonPersonIDAssignee is null and s.category in ('Open','In Progress') and t.gibbonServiceDeskTicketTypeID in (select gibbonServiceDeskTicketTypeID from gibbonServiceDeskTicketTypeTeam where gibbonServiceDeskTeamID=1)`)[0][0];
  const staff = await dashboard('119', 'staff');
  console.log('team member: widgets:', staff.widget.map(w => w.slice(0, 50)).join(' || '));
  console.log('  "Unassigned tickets" with the number visible to team Administration', unassigned, '(expect true):', (staff.widget[0] || '').toLowerCase().startsWith('unassigned tickets ' + unassigned + ' ') && (staff.widget[1] || '').toLowerCase().startsWith('assigned to me'));

  const teacher = await dashboard('120', 'teacher');
  console.log('teacher in no team: own requests, no unassigned list (expect true):', /My open requests/i.test(teacher.widget[0] || '') && !/Unassigned tickets/i.test(teacher.widget.join(' ')));

  const admin = await dashboard('192', 'admin');
  console.log('administrator: unassigned of all teams (expect true):', /Unassigned tickets \d+/i.test(admin.widget[0] || ''), '|', (admin.widget[0] || '').slice(0, 80));
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
