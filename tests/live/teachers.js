// Teacher notices: when an absence or unenrolment is approved, the form tutors and class teachers of the children get
// a notification in the notification centre - never an email, never the reason. Run after flow.js.
// Absence of children 925 (form tutor 2363) and 1377 (form tutor 1119), each with their class teachers.
const { chromium, BASE, login, check, sql } = require('./lib');
const { execFileSync } = require('child_process');
const fs = require('fs');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const recipients = id => sql(`select distinct gibbonPersonID from gibbonNotification where text like '#${id} % approved: %' order by 1`).map(r => parseInt(r[0], 10));
const sameSet = (a, b) => JSON.stringify([...a].sort((x, y) => x - y)) === JSON.stringify([...b].sort((x, y) => x - y));
(async () => {
  execFileSync(__dirname + '/mail-on.sh', { stdio: 'ignore' });   // a mail sink: if anything is emailed we can see it
  const browser = await chromium.launch();
  const parent = await login(browser, '463'), member = await login(browser, '119');
  const fileTicket = async (typeID, fill) => {
    await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=' + typeID); await fill();
    await submit(parent, 'form#request'); return parent.url().match(/TicketID=0*(\d+)/)[1];
  };
  const moveTo = async (id, label) => {
    await member.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
    await member.selectOption('form#manage select[name=gibbonServiceDeskStageID]', { label });
    await submit(member, 'form#manage'); return (member.url().match(/return=(\w+)/) || [])[1];
  };
  const mailBefore = fs.readFileSync('/tmp/servicedesk-mail.txt', 'utf8').length;

  // ABSENCE: both children, reason Medical
  const absence = await fileTicket(1, async () => {
    for (const cb of await parent.$$('form#request input[type=checkbox]')) await cb.check();
    await parent.selectOption('form#request select', 'Medical');
    await parent.fill('form#request input[type=date] >> nth=0', '2026-12-15'); await parent.fill('form#request input[type=date] >> nth=1', '2026-12-16');
  });
  console.log('absence ticket', absence, '- approving:', await moveTo(absence, 'Approved *'));
  const tutors = `select gibbonPersonIDTutor p from gibbonFormGroup where gibbonFormGroupID in (select gibbonFormGroupID from gibbonStudentEnrolment where gibbonSchoolYearID=25 and gibbonPersonID in (925,1377))
      union select gibbonPersonIDTutor2 from gibbonFormGroup where gibbonFormGroupID in (select gibbonFormGroupID from gibbonStudentEnrolment where gibbonSchoolYearID=25 and gibbonPersonID in (925,1377))`;
  const classTeachers = `select teacher.gibbonPersonID from gibbonCourseClassPerson student join gibbonCourseClass cc on cc.gibbonCourseClassID=student.gibbonCourseClassID join gibbonCourse c on c.gibbonCourseID=cc.gibbonCourseID and c.gibbonSchoolYearID=25
      join gibbonCourseClassPerson teacher on teacher.gibbonCourseClassID=student.gibbonCourseClassID and teacher.role='Teacher' where student.role='Student' and student.gibbonPersonID in (925,1377)`;
  const expectedAbsence = sql(`select distinct p from (${tutors} union ${classTeachers}) t join gibbonPerson on gibbonPerson.gibbonPersonID=t.p and gibbonPerson.status='Full' where p is not null and p<>119`).map(r => parseInt(r[0], 10));
  const got = recipients(absence);
  console.log('absence: teachers told (expect true):', sameSet(got, expectedAbsence), '- expected', expectedAbsence.length, 'got', got.length);
  const text = sql(`select text from gibbonNotification where text like '#${absence} % approved: %' limit 1`)[0][0];
  console.log('  text:', text);
  console.log('  says who and when, not why (expect true):', text.includes('Amber Blackwell') && text.includes('15/12/2026') && text.includes('16/12/2026') && !text.includes('Medical'));
  console.log('  the person who approved is not told (expect true):', !got.includes(119));
  const view = await member.innerText('.serviceDesk-ticket');
  console.log('  internal history records it (expect true):', /teacher\(s\) were told/.test(view));
  await parent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + absence);
  console.log('  requester does not see that line (expect true):', !/teacher\(s\) were told/.test(await parent.innerText('.serviceDesk-ticket')));

  // UNENROLMENT: one class of the first child only (MAHL13.1, teacher 120)
  const unenrol = await fileTicket(2, async () => { await parent.check('form#request input[type=checkbox][value="925-2389"]'); });
  console.log('unenrolment ticket', unenrol, '- approving:', await moveTo(unenrol, 'Approved *'));
  const got2 = recipients(unenrol);
  console.log('unenrolment: only the form tutor of the child and the teacher of that class (expect true):', sameSet(got2, [2363, 120]), '- got', got2.join(','));
  console.log('  text:', sql(`select text from gibbonNotification where text like '#${unenrol} % approved: %' limit 1`)[0][0]);

  // NO EMAIL to teachers
  const mail = fs.readFileSync('/tmp/servicedesk-mail.txt', 'utf8').slice(mailBefore);
  const toLines = mail.split('\n').filter(l => /^To:/.test(l)).join(' ');
  const teacherEmails = sql(`select email from gibbonPerson where gibbonPersonID in (${[...got, ...got2].join(',')})`).map(r => r[0]).filter(e => e);
  console.log('emails sent meanwhile:', (toLines.match(/To:/g) || []).length, '| any to a notified teacher (expect false):', teacherEmails.some(e => toLines.includes(e)));

  // a teacher sees the notice and its link works
  const teacher = await login(browser, '120');
  await teacher.goto(BASE + '/index.php?q=notifications.php'); await check(teacher, 'teacher notifications');
  console.log('teacher 120 sees it in the notification centre (expect true):', (await teacher.innerText('body')).includes('approved'));
  await teacher.screenshot({ path: `${__dirname}/shots/teachers_notice.png` });
  const href = await teacher.$eval('a[href*="notificationsActionProcess.php"]', a => a.href); await teacher.goto(href); await check(teacher, 'link opens');
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
