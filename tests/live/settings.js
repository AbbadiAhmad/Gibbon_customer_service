// Settings of automation scripts (1.9.00): the attendance code of "Record absence", the reasons of School Admin on the
// request form, and the generic mechanism (every setting type, forged values, stored values that become invalid).
// Run after flow.js, on a site that has been updated to 1.9.00. Teacher 119 is in team Administration.
const fs = require('fs');
const path = require('path');
const { chromium, BASE, login, check, sql } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const ret = page => (page.url().match(/return=(\w+)/) || [])[1];
const one = q => (sql(q)[0] || [''])[0];
let failures = 0;
const expect = (label, ok, detail = '') => { if (!ok) failures++; console.log((ok ? 'ok   ' : 'FAIL ') + label + (detail !== '' ? ' -> ' + detail : '')); };
const tamper = async (page, form, fields) => {
  await page.evaluate(({ form, fields }) => {
    const f = document.querySelector(form);
    for (const [name, value] of Object.entries(fields)) {
      let inputs = Array.from(f.querySelectorAll(`[name="${name}"]`));
      if (inputs.length === 0) { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; f.appendChild(input); inputs = [input]; }
      for (const input of inputs) {
        if (input.tagName === 'SELECT') { const o = document.createElement('option'); o.value = value; input.appendChild(o); }
        input.disabled = false;
        if (input.type === 'number') input.type = 'text';   // a number field would blank a value that is not a number
        input.value = value;
      }
    }
  }, { form, fields });
  // Submitted from script, as an attacker would: the browser's own checks (required fields) do not apply.
  await Promise.all([page.waitForNavigation(), page.evaluate(f => document.querySelector(f).submit(), form)]);
  return ret(page);
};

(async () => {
  const browser = await chromium.launch();
  const admin = await login(browser, '192'), parent = await login(browser, '463'), member = await login(browser, '119');
  const core = one(`select value from gibbonSetting where name='absolutePath'`);

  // ---------- test data: attendance codes
  const code = (name, short, direction, active, future, seq) => {
    sql(`insert into gibbonAttendanceCode (name, nameShort, type, direction, scope, active, reportable, future, prefill, gibbonRoleIDAll, sequenceNumber) values ('${name}', '${short}', 'Additional', '${direction}', 'Offsite', '${active}', 'Y', '${future}', 'N', '001,002,006', ${seq})`);
    return one(`select gibbonAttendanceCodeID+0 from gibbonAttendanceCode where name='${name}'`);
  };
  sql(`delete from gibbonAttendanceCode where name like 'Test %'`);
  sql(`delete from gibbonAttendanceLogPerson where comment like 'Service Desk #%' and date >= '2027-03-01'`);
  sql(`update gibbonSetting set value='Pending,Education,Family,Medical,Other' where name='attendanceReasons'`);
  const excused = code('Test Excused', 'TE', 'Out', 'Y', 'Y', 20), unexcused = code('Test Unexcused', 'TU', 'Out', 'Y', 'Y', 21);
  const notAdvance = code('Test Not In Advance', 'TN', 'Out', 'Y', 'N', 22), inactive = code('Test Inactive', 'TI', 'Out', 'N', 'Y', 23), presence = code('Test Presence', 'TP', 'In', 'Y', 'Y', 24);
  const absentID = one(`select gibbonAttendanceCodeID+0 from gibbonAttendanceCode where name='Absent'`);
  const approvedStage = one(`select gibbonServiceDeskStageID+0 from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=1 and name='Approved'`);
  const stageURL = M + 'workflow_stage_edit.php&gibbonServiceDeskTicketTypeID=1&gibbonServiceDeskStageID=' + approvedStage;
  const stored = () => one(`select coalesce(automationSettings, 'none') from gibbonServiceDeskStage where gibbonServiceDeskStageID=${approvedStage}`);
  const setCode = async value => { await admin.goto(stageURL); await admin.selectOption('form#stage select[name=setting_RecordAbsence_attendanceCode]', value); await submit(admin, 'form#stage'); return ret(admin); };

  // ---------- upgrade results
  console.log('upgrade: reason field now type "Attendance Reason", no stored options, new label (expect Attendance Reason | empty | Reason for Absence):', sql(`select f.type, coalesce(f.options, ''), f.label from gibbonServiceDeskField f where f.gibbonServiceDeskTicketTypeID=1 and f.name='reason'`)[0].join(' | '));
  console.log('upgrade: stages start without settings (expect 0):', one(`select count(*) from gibbonServiceDeskStage where automationSettings is not null`));

  // ---------- the stage form: settings appear under the ticked script
  await admin.goto(stageURL); await check(admin, 'stage form');
  const select = 'form#stage select[name=setting_RecordAbsence_attendanceCode]';
  const options = async () => admin.$$eval(select + ' option', o => o.map(e => e.textContent.trim()).filter(t => t));
  const offered = await options();
  console.log('codes offered:', offered.join(', '));
  expect('only active, leaving codes that are allowed in advance are offered', offered.includes('Absent') && offered.includes('Test Excused') && offered.includes('Test Unexcused') && !offered.some(o => /Not In Advance|Inactive|Presence|Present|Left/.test(o)));
  expect('settings are shown while "Record absence" is ticked', await admin.isVisible(select));
  await admin.screenshot({ path: `${__dirname}/shots/settings_stage.png` });
  await admin.uncheck('form#stage input[name="automation[]"][value=RecordAbsence]');
  expect('and hidden when it is not ticked', !(await admin.isVisible(select)));
  expect('"Tell the teachers" has no settings, so no rows of its own', (await admin.$$('form#stage [class*="automationSettings_TellTeachers"]')).length === 0);
  await admin.check('form#stage input[name="automation[]"][value=RecordAbsence]');

  // ---------- choose the excused code, approve
  console.log('save with the excused code:', await setCode(excused), '->', stored());
  expect('the choice is stored with the stage, by code id', stored() === JSON.stringify({ RecordAbsence: { attendanceCode: String(excused) } }), stored());
  await admin.goto(stageURL); expect('and shown again when the stage is opened', (await admin.inputValue(select)) == String(excused).padStart(3, '0') || (await admin.inputValue(select)) == String(excused));
  await admin.goto(M + 'workflow_manage.php&gibbonServiceDeskTicketTypeID=1'); await check(admin, 'workflow list');
  expect('the workflow list shows the choice (expect true)', /Attendance code: Test Excused/.test(await admin.innerText('table')));
  await admin.screenshot({ path: `${__dirname}/shots/settings_list.png` });

  const absence = async (date, reasonLabel) => {
    await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=1');
    await parent.check('form#request input[type=checkbox] >> nth=0'); await parent.selectOption('form#request select', reasonLabel || 'Medical');
    await parent.fill('form#request input[type=date] >> nth=0', date);
    await submit(parent, 'form#request'); return parent.url().match(/TicketID=0*(\d+)/)[1];
  };
  const approve = async id => { await member.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id); await member.selectOption('form#manage select[name=gibbonServiceDeskStageID]', { label: 'Approved *' }); await submit(member, 'form#manage'); return ret(member); };
  const logs = id => sql(`select type, reason, gibbonAttendanceCodeID+0 from gibbonAttendanceLogPerson where comment='Service Desk #${id}'`);
  const stageOf = id => one(`select s.name from gibbonServiceDeskTicket t join gibbonServiceDeskStage s using (gibbonServiceDeskStageID) where t.gibbonServiceDeskTicketID=${id}`);
  const lastNote = async id => { await member.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id); return (await member.innerText('.serviceDesk-ticket')).replace(/\s+/g, ' '); };

  let id = await absence('2027-03-01');
  console.log('approve with the excused code:', await approve(id));
  let rows = logs(id);
  expect('attendance holds the excused code, the reason and the right code id; the ticket is Done', rows.length === 1 && rows[0][0] === 'Test Excused' && rows[0][1] === 'Medical' && rows[0][2] === String(excused) && stageOf(id) === 'Done', JSON.stringify(rows));

  // ---------- default (no setting): the code named "Absent"
  console.log('back to "Default":', await setCode(''), '->', stored(), '(expect none)');
  id = await absence('2027-03-02', 'Family'); await approve(id); rows = logs(id);
  expect('without a setting the code named "Absent" is recorded (as before 1.9.00)', rows.length === 1 && rows[0][0] === 'Absent' && rows[0][2] === String(absentID), JSON.stringify(rows));

  // ---------- conflict: a different whole-day record exists
  await setCode(String(excused));
  id = await absence('2027-03-02');                       // same child, same day as the "Absent" record above
  console.log('approve over a different record:', await approve(id));
  const note = await lastNote(id);
  expect('a different record of the same day is not overwritten: nothing written, ticket stays, the team is told who and when', logs(id).length === 0 && stageOf(id) === 'New' && /already has the attendance record "Absent"/.test(note), note.match(/already has[^.]*\./) ? note.match(/already has[^.]*\./)[0] : note.slice(-160));
  const parentNote = await (async () => { await parent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id); return parent.innerText('.serviceDesk-ticket'); })();
  expect('and the requester does not see that note', !/already has the attendance record/.test(parentNote));

  // ---------- a code that stops being valid after the stage was configured
  sql(`update gibbonAttendanceCode set active='N' where gibbonAttendanceCodeID=${excused}`);
  id = await absence('2027-03-03');
  console.log('code deactivated, approve:', await approve(id));
  const note2 = await lastNote(id);
  expect('the stage fails safely instead of using another code: no record, ticket stays, reason on the ticket', logs(id).length === 0 && stageOf(id) === 'New' && /no longer valid/.test(note2) && /Attendance code/.test(note2), note2.match(/The setting[^.]*\./) ? note2.match(/The setting[^.]*\./)[0] : '');
  await admin.goto(M + 'workflow_manage.php&gibbonServiceDeskTicketTypeID=1');
  expect('the workflow list says the code is not available', /not available/.test(await admin.innerText('table')));
  sql(`update gibbonAttendanceCode set active='Y' where gibbonAttendanceCodeID=${excused}`);
  console.log('reactivated, approve again:', await approve(id), '-> record:', JSON.stringify(logs(id)));

  // ---------- renaming a code does not break the stage (stored by id)
  sql(`update gibbonAttendanceCode set name='Test Excused (doctor)' where gibbonAttendanceCodeID=${excused}`);
  id = await absence('2027-03-04'); await approve(id);
  expect('after renaming the code the stage still records it, under the new name', logs(id).length === 1 && logs(id)[0][0] === 'Test Excused (doctor)', JSON.stringify(logs(id)));
  sql(`update gibbonAttendanceCode set name='Test Excused' where gibbonAttendanceCodeID=${excused}`);

  // ---------- forged values
  const before = stored();
  for (const [label, value] of [['a code that is not allowed in advance', notAdvance], ['an inactive code', inactive], ['a code that is a presence', presence], ['a code that does not exist', '99999'], ['text instead of an id', "4' OR '1'='1"]]) {
    await admin.goto(stageURL);
    const r = await tamper(admin, 'form#stage', { setting_RecordAbsence_attendanceCode: String(value) });
    expect(`forged setting: ${label} is refused and nothing is stored`, r === 'error21' && stored() === before, r);
  }
  // settings of a script that is not ticked, and of scripts or names that do not exist, are not stored
  await admin.goto(stageURL); await admin.uncheck('form#stage input[name="automation[]"][value=RecordAbsence]');
  let r = await tamper(admin, 'form#stage', { setting_RecordAbsence_attendanceCode: String(unexcused), setting_Nonexistent_x: '1', setting_TellTeachers_x: '1' });
  expect('unticking the script drops its settings; posted settings of scripts that are not ticked are ignored', r === 'success0' && stored() === 'none', stored());
  await admin.goto(stageURL); await admin.check('form#stage input[name="automation[]"][value=RecordAbsence]');
  await admin.selectOption(select, String(unexcused)); await tamper(admin, 'form#stage', { setting_RecordAbsence_nonexistent: 'x', setting_Other_attendanceCode: '4' });
  expect('names a script does not define are ignored (only the defined setting is stored)', stored() === JSON.stringify({ RecordAbsence: { attendanceCode: String(unexcused) } }), stored());
  await admin.goto(stageURL);
  expect('the unexcused code is now selected', /./.test(await admin.inputValue(select)) && (await admin.inputValue(select)).replace(/^0+/, '') === String(unexcused));
  await setCode('');   // back to the default

  // ---------- a stage copy keeps the settings (workflow of a new ticket type)
  const defaultStage = sql(`insert into gibbonServiceDeskStage (gibbonServiceDeskTicketTypeID, name, category, sequenceNumber, automation, automationSettings) values (NULL, 'Copy test', 'In Progress', 99, 'RecordAbsence', '{"RecordAbsence":{"attendanceCode":"${excused}"}}')`);
  await admin.goto(M + 'types_manage_edit.php'); await admin.fill('form#ticketType input[name=name]', 'Copy type ' + (Date.now() % 100000)); await submit(admin, 'form#ticketType');
  const newType = admin.url().match(/gibbonServiceDeskTicketTypeID=0*(\d+)/)[1];
  expect('a new ticket type copies the settings of the default workflow', one(`select automationSettings from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=${newType} and name='Copy test'`) === `{"RecordAbsence":{"attendanceCode":"${excused}"}}`);
  sql(`delete from gibbonServiceDeskStage where name='Copy test'`);

  // ---------- reasons come from School Admin > Attendance Settings
  const reasonsOf = async () => { await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=1'); return parent.$$eval('form#request select option', o => o.map(e => e.value).filter(v => v)); };
  let reasons = await reasonsOf();
  expect('the parent\'s reasons are the reasons of the Attendance Settings, without "Pending"', JSON.stringify(reasons) === JSON.stringify(['Education', 'Family', 'Medical', 'Other']), reasons.join(','));
  sql(`update gibbonSetting set value='Pending,Education,Family,Medical,Other,Religious holiday' where name='attendanceReasons'`);
  reasons = await reasonsOf();
  expect('a reason added in School Admin is offered at once', reasons.includes('Religious holiday'), reasons.join(','));
  id = await absence('2027-03-05', 'Religious holiday'); await approve(id);
  expect('and is recorded with the absence', logs(id).length === 1 && logs(id)[0][1] === 'Religious holiday', JSON.stringify(logs(id)));
  await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=1'); await parent.check('form#request input[type=checkbox] >> nth=0'); await parent.fill('form#request input[type=date] >> nth=0', '2027-03-08');
  for (const bad of ['Pending', 'Skiving', '']) {
    await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=1'); await parent.check('form#request input[type=checkbox] >> nth=0'); await parent.fill('form#request input[type=date] >> nth=0', '2027-03-08');
    const result = await tamper(parent, 'form#request', { field2: bad });
    expect(`a forged reason "${bad}" is refused`, result === 'error1', result);
  }
  sql(`update gibbonSetting set value='Pending,Education,Family,Medical,Other' where name='attendanceReasons'`);
  await parent.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id);
  expect('the stored answer shows the reason', /Religious holiday/.test(await parent.innerText('.serviceDesk-ticket')));

  // ---------- the generic mechanism with a script that has every kind of setting
  const target = path.join(core, 'modules/ServiceDesk/src/Automation/Scripts/ExampleSettings.php');
  fs.copyFileSync(path.join(__dirname, 'fixtures/ExampleSettings.php'), target);
  const ex = M + 'workflow_stage_edit.php&gibbonServiceDeskTicketTypeID=1&gibbonServiceDeskStageID=' + one(`select gibbonServiceDeskStageID+0 from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=1 and name='In Progress'`);
  try {
    await admin.goto(ex); await check(admin, 'stage form with the example script');
    expect('a new script with settings appears with its rows, nothing else written: select, text, number, yes/no', ['mode', 'note', 'limit', 'notify'].every(n => true) && (await admin.$$('form#stage [name^="setting_ExampleSettings_"]')).length === 4);
    await admin.screenshot({ path: `${__dirname}/shots/settings_example.png` });
    await admin.check('form#stage input[name="automation[]"][value=ExampleSettings]');
    expect('its rows appear when it is ticked', await admin.isVisible('form#stage select[name=setting_ExampleSettings_mode]'));
    // required select left empty: the browser stops it; forged: the server does too
    let r = await tamper(admin, 'form#stage', { setting_ExampleSettings_mode: '' });
    expect('a required setting without a value is refused by the server', r === 'error21', r);
    await admin.goto(ex); await admin.check('form#stage input[name="automation[]"][value=ExampleSettings]');
    for (const [label, fields] of [['text too long', { setting_ExampleSettings_mode: 'fast', setting_ExampleSettings_note: 'x'.repeat(11) }], ['number out of range', { setting_ExampleSettings_mode: 'fast', setting_ExampleSettings_limit: '9' }], ['number that is not a number', { setting_ExampleSettings_mode: 'fast', setting_ExampleSettings_limit: 'abc' }], ['select value not offered', { setting_ExampleSettings_mode: 'warp' }]]) {
      await admin.goto(ex); await admin.check('form#stage input[name="automation[]"][value=ExampleSettings]');
      const result = await tamper(admin, 'form#stage', fields);
      expect(`${label} is refused`, result === 'error21', result);
    }
    await admin.goto(ex); await admin.check('form#stage input[name="automation[]"][value=ExampleSettings]');
    await admin.selectOption('form#stage select[name=setting_ExampleSettings_mode]', 'slow'); await admin.fill('form#stage input[name=setting_ExampleSettings_note]', 'hello'); await admin.fill('form#stage input[name=setting_ExampleSettings_limit]', '4');
    const saved = await submit(admin, 'form#stage').then(() => ret(admin));
    const inProgress = sql(`select automationSettings from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=1 and name='In Progress'`)[0][0];
    expect('valid values of every type are stored (yes/no left at its default, "No" is posted as N)', saved === 'success0' && JSON.parse(inProgress).ExampleSettings.mode === 'slow' && JSON.parse(inProgress).ExampleSettings.note === 'hello' && JSON.parse(inProgress).ExampleSettings.limit == 4 && ['Y', 'N'].includes(JSON.parse(inProgress).ExampleSettings.notify), inProgress);
    id = await absence('2027-03-09');
    await member.goto(M + 'tickets_view.php&gibbonServiceDeskTicketID=' + id); await member.selectOption('form#manage select[name=gibbonServiceDeskStageID]', { label: 'In Progress *' }); await submit(member, 'form#manage');
    const exNote = await lastNote(id);
    expect('the script receives its settings through $context->setting()', /Example ran: mode=slow note=hello limit=4 notify=[YN]/.test(exNote), (exNote.match(/Example ran[^.]*/) || [''])[0]);
  } finally {
    sql(`update gibbonServiceDeskStage set automation=NULL, automationSettings=NULL, nextStage=NULL where gibbonServiceDeskTicketTypeID=1 and name='In Progress'`);
    fs.unlinkSync(target);
  }
  await admin.goto(ex);
  expect('without the script file the stage form still opens (a stage that names a missing script keeps working as a plain stage)', !(await admin.content()).includes('Fatal error') && (await admin.$$('form#stage [name^="setting_ExampleSettings_"]')).length === 0);

  sql(`delete from gibbonAttendanceCode where name like 'Test %'`);
  sql(`update gibbonServiceDeskStage set automationSettings=NULL where gibbonServiceDeskStageID=${approvedStage}`);
  console.log(failures === 0 ? 'ALL SETTINGS CHECKS PASSED' : failures + ' FINDING(S)');
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
