// Home page: the sidebar box "My open requests" / "Open tickets for me", and the hidden dashboard tab.
// Run after flow.js (119 is a team member; 120 a teacher in no team; 463 a parent with two children; 427 a parent
// without requests).
const { chromium, BASE, login, check, sql } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const stamp = Date.now() % 100000;
(async () => {
  const browser = await chromium.launch();
  const home = async (user, name) => {
    const page = await login(browser, user);
    await page.setViewportSize({ width: 1280, height: 1500 });
    await page.goto(BASE + '/index.php'); await check(page, user + ' home page');
    await page.waitForTimeout(500);
    if (name) await page.screenshot({ path: `${__dirname}/shots/sidebar_${name}.png` });
    const info = await page.evaluate(() => {
      const box = document.querySelector('.serviceDesk-box');
      const wall = Array.from(document.querySelectorAll('h2, h3, h5')).find(h => /message wall/i.test(h.innerText));
      const visibleTabs = Array.from(document.querySelectorAll('[role=tab]')).filter(b => /service desk/i.test(b.innerText) && b.offsetParent !== null).length;
      return {
        boxes: document.querySelectorAll('.serviceDesk-box').length,
        text: box ? box.innerText.replace(/\s+/g, ' ').trim() : '',
        items: box ? box.querySelectorAll('li').length : 0,
        inSidebar: box ? !!box.closest('#sidebar, [id*=sidebar], aside, .sidebar') || box.getBoundingClientRect().left < 420 : false,
        belowWall: box && wall ? box.getBoundingClientRect().top > wall.getBoundingClientRect().top : null,
        visibleTabs,
        fallbackVisible: Array.from(document.querySelectorAll('.serviceDesk-hideTab')).filter(e => e.offsetParent !== null).length,
      };
    });
    return { page, ...info };
  };

  // a parent with two children: three open requests -> the two newest are listed, once
  // show Gibbon's Message Wall box in the sidebar, to see that ours comes below it
  sql(`update gibbonSetting set value='Y' where scope='Messenger' and name='enableHomeScreenWidget'`);
  const parent = await login(browser, '463');
  for (const subject of ['Sidebar A ' + stamp, 'Sidebar B ' + stamp, 'Sidebar C ' + stamp]) {
    await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=5');
    await parent.fill('form#request textarea', 'Details.'); await parent.fill('form#request input[name=subject]', subject);
    await submit(parent, 'form#request');
  }
  const open = sql(`select count(*) from gibbonServiceDeskTicket t join gibbonServiceDeskStage s on s.gibbonServiceDeskStageID=t.gibbonServiceDeskStageID where t.gibbonPersonIDRequester=463 and s.category in ('Open','In Progress')`)[0][0];
  // tickets with unseen updates from other tests would rightly come first: start from "everything seen"
  sql(`insert into gibbonServiceDeskTicketSeen (gibbonServiceDeskTicketID, gibbonPersonID, timestampSeen) select gibbonServiceDeskTicketID, 463, date_add(now(), interval 1 minute) from gibbonServiceDeskTicket on duplicate key update timestampSeen=values(timestampSeen)`);
  let r = await home('463', 'parent');
  console.log('parent: one box although there are two children (expect 1):', r.boxes, '| in the sidebar (expect true):', r.inSidebar, '| below the Message Wall (expect true or null if the wall is off):', r.belowWall);
  console.log('  text:', r.text.slice(0, 230));
  console.log('  two newest listed with the total ' + open + ' (expect true):', r.items === 2 && r.text.includes('Sidebar C ' + stamp) && !r.text.includes('Sidebar A ' + stamp) && new RegExp('My open requests ' + open + ' ', 'i').test(r.text));
  console.log('  no "Service Desk" tab left in the children blocks (expect 0, 0):', r.visibleTabs, r.fallbackVisible);
  console.log('  the other tabs still work (expect true):', await (async () => { const t = await r.page.$$('[role=tab]'); if (t.length < 2) return 'no tabs'; await t[1].click(); await r.page.waitForTimeout(300); return (await r.page.$$eval('[role=tabpanel]', p => p.filter(e => e.offsetParent !== null).length)) >= 1; })());
  await r.page.click('.serviceDesk-box li a'); await r.page.waitForLoadState(); await check(r.page, 'ticket opened from the box');
  console.log('  the box links to the ticket (expect true):', r.page.url().includes('tickets_view.php') || (await r.page.content()).includes('serviceDesk-ticket'));

  r = await home('427', 'no_requests');
  console.log('parent without open requests: no box (expect 0):', r.boxes, '| no tab either (expect 0):', r.visibleTabs);

  const waiting = sql(`select count(*) from gibbonServiceDeskTicket t join gibbonServiceDeskStage s on s.gibbonServiceDeskStageID=t.gibbonServiceDeskStageID where s.category in ('Open','In Progress') and t.gibbonServiceDeskTicketTypeID in (select gibbonServiceDeskTicketTypeID from gibbonServiceDeskTicketTypeTeam where gibbonServiceDeskTeamID=1) and (t.gibbonPersonIDAssignee is null or t.gibbonPersonIDAssignee=119)`)[0][0];
  r = await home('119', 'team_member');
  console.log('team member: box "Open tickets for me" with ' + waiting + ' (expect true):', new RegExp('Open tickets for me ' + waiting + ' ', 'i').test(r.text), '| boxes:', r.boxes, '| tab hidden (expect 0):', r.visibleTabs, '|', r.text.slice(0, 120));

  r = await home('120', 'teacher');
  console.log('teacher in no team, no requests: no box (expect 0):', r.boxes, '| tab hidden (expect 0):', r.visibleTabs);

  // a student: files a request, then has the box
  const student = sql(`select p.username from gibbonPerson p join gibbonStudentEnrolment e on e.gibbonPersonID=p.gibbonPersonID and e.gibbonSchoolYearID=25 where p.status='Full' and p.canLogin='Y' and p.gibbonRoleIDPrimary=3 limit 1`)[0][0];
  const s = await login(browser, student);
  await s.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=5'); await check(s, 'student request form');
  await s.fill('form#request textarea', 'I lost my locker key.'); await s.fill('form#request input[name=subject]', 'Student ' + stamp);
  await submit(s, 'form#request');
  r = await home(student, 'student');
  console.log('student ' + student + ': box with their request (expect true):', r.boxes === 1 && r.text.includes('Student ' + stamp), '| tab hidden (expect 0):', r.visibleTabs);
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
