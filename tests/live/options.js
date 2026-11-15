// 1.6.00: several automation scripts per stage (with "Tell the teachers" as one of them) and the ticket type switches
// "Requesters Can Withdraw" / "Requesters Can Reopen". Run after flow.js (teacher 119 is in team Administration).
const { chromium, BASE, login, check, sql } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const ret = page => (page.url().match(/return=(\w+)/) || [])[1];
(async () => {
  const browser = await chromium.launch();
  const admin = await login(browser, '192'), parent = await login(browser, '463'), member = await login(browser, '119');
  const stage = name => sql(`select gibbonServiceDeskStageID, coalesce(automation, ''), coalesce(nextStage, '') from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=1 and name='${name}'`)[0];
  const absence = async (from, to) => {
    await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=1');
    await parent.check('form#request input[type=checkbox] >> nth=0'); await parent.selectOption('form#request select', 'Family');
    await parent.fill('form#request input[type=date] >> nth=0', from); if (to) await parent.fill('form#request input[type=date] >> nth=1', to);
    await submit(parent, 'form#request'); return parent.url().match(/TicketID=0*(\d+)/)[1];
  };
  const moveTo = async (id, label) => {
    await member.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
    await member.selectOption('form#manage select[name=gibbonServiceDeskStageID]', { label });
    await submit(member, 'form#manage'); return ret(member);
  };
  const notices = id => sql(`select count(*) from gibbonNotification where text like '#${id} % approved: %'`)[0][0];

  // ---- the stage form: a tick list of scripts
  console.log('absence Approved stage runs (expect RecordAbsence,TellTeachers):', stage('Approved')[1], '| unenrolment Approved (expect TellTeachers):',
    sql(`select automation from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=2 and name='Approved'`)[0][0]);
  const edit = M + 'workflow_stage_edit.php&gibbonServiceDeskTicketTypeID=1&gibbonServiceDeskStageID=' + parseInt(stage('Approved')[0], 10);
  await admin.goto(edit); await check(admin, 'stage form');
  await admin.screenshot({ path: `${__dirname}/shots/options_stage.png` });
  const ticked = () => admin.$$eval('form#stage input[name="automation[]"]:checked', c => c.map(e => e.value).join(','));
  console.log('ticked on the form (expect RecordAbsence,TellTeachers):', await ticked(), '| scripts offered:', await admin.$$eval('form#stage input[name="automation[]"]', c => c.map(e => e.value).join(',')));
  await submit(admin, 'form#stage'); console.log('saved without changes:', ret(admin), '-> still (expect RecordAbsence,TellTeachers | Done):', stage('Approved').slice(1).join(' | '));
  await admin.goto(M + 'workflow_manage.php&gibbonServiceDeskTicketTypeID=1'); await check(admin, 'workflow list');
  console.log('workflow list names both scripts (expect true):', /Record absence in Attendance\s*Tell the teachers/.test(await admin.innerText('table')));

  // ---- both scripts succeed: absence recorded and teachers told
  let id = await absence('2026-12-21', '2026-12-22');
  console.log('approve a weekday absence:', await moveTo(id, 'Approved *'), '| attendance rows (expect 2):', sql(`select count(*) from gibbonAttendanceLogPerson where comment='Service Desk #${id}'`)[0][0], '| teacher notices (expect > 0):', notices(id));
  const history = await member.innerText('.serviceDesk-ticket');
  console.log('history has both results, the ticket ended in Done (expect true):', history.includes('recorded in Attendance') && /teacher\(s\) were told/.test(history) && /STAGE\s*Done/i.test(history));

  // ---- one script fails: nothing takes effect, also not the other script
  id = await absence('2026-12-26', '2026-12-27');   // a weekend: Record absence fails
  console.log('approve a weekend absence (expect warning1):', await moveTo(id, 'Approved *'), '| teacher notices (expect 0):', notices(id), '| attendance rows (expect 0):', sql(`select count(*) from gibbonAttendanceLogPerson where comment='Service Desk #${id}'`)[0][0],
    '| stage (expect New):', sql(`select s.name from gibbonServiceDeskTicket t join gibbonServiceDeskStage s using (gibbonServiceDeskStageID) where t.gibbonServiceDeskTicketID=${id}`)[0][0]);

  // ---- untick "Tell the teachers": only the absence is recorded
  await admin.goto(edit); await admin.uncheck('form#stage input[name="automation[]"][value=TellTeachers]'); await submit(admin, 'form#stage');
  console.log('teachers unticked ->', stage('Approved')[1]);
  id = await absence('2026-12-23');
  console.log('approve:', await moveTo(id, 'Approved *'), '| attendance rows (expect 1):', sql(`select count(*) from gibbonAttendanceLogPerson where comment='Service Desk #${id}'`)[0][0], '| teacher notices (expect 0):', notices(id));
  await admin.goto(edit); await admin.check('form#stage input[name="automation[]"][value=TellTeachers]'); await submit(admin, 'form#stage');
  console.log('ticked again ->', stage('Approved')[1]);
  // no script ticked: the stage is a plain stage and "then move to" is cleared
  await admin.goto(edit); for (const box of await admin.$$('form#stage input[name="automation[]"]:checked')) await box.uncheck(); await submit(admin, 'form#stage');
  console.log('nothing ticked -> automation / next stage (expect empty | empty):', JSON.stringify(stage('Approved').slice(1)));
  sql(`update gibbonServiceDeskStage set automation='RecordAbsence,TellTeachers', nextStage='Done' where gibbonServiceDeskStageID=${parseInt(stage('Approved')[0], 10)}`);

  // ---- ticket type switches
  const typeEdit = M + 'types_manage_edit.php&gibbonServiceDeskTicketTypeID=1';
  const toggle = async name => { await admin.goto(typeEdit); await admin.click(`form#ticketType label[id^=toggle-label][for=${name}]`); await submit(admin, 'form#ticketType'); };
  const flags = () => sql(`select allowWithdraw, allowReopen, roleCategories, shareWithFamily from gibbonServiceDeskTicketType where gibbonServiceDeskTicketTypeID=1`)[0].join(' ');
  console.log('defaults (expect Y Y Parent Y):', flags());
  id = await absence('2026-12-28');
  const links = async () => { await parent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id); return [!!(await parent.$('a[href*="action=withdraw"]')), !!(await parent.$('a[href*="action=reopen"]'))].join(' '); };
  const page = async action => { await parent.goto(M + `tickets_requester.php&action=${action}&gibbonServiceDeskTicketID=` + id); return !(await parent.content()).includes('cannot be found'); };
  console.log('open ticket, withdraw allowed: withdraw link / reopen link (expect true false):', await links());
  await admin.goto(typeEdit); await admin.screenshot({ path: `${__dirname}/shots/options_type.png` });
  await toggle('allowWithdraw'); console.log('withdraw switched off ->', flags());
  console.log('  no withdraw link (expect false false):', await links(), '| the withdraw page refuses (expect false):', await page('withdraw'));
  await moveTo(id, 'Rejected');
  console.log('closed ticket, reopen allowed (expect false true):', await links());
  await toggle('allowReopen'); console.log('reopen switched off ->', flags());
  console.log('  no reopen link (expect false false):', await links(), '| the reopen page refuses (expect false):', await page('reopen'));
  await toggle('allowWithdraw'); await toggle('allowReopen'); console.log('both switched on again (expect Y Y Parent Y):', flags());
  console.log('  reopen link back (expect false true):', await links());
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
