const { chromium, BASE, login, check } = require('./lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const shot = (page, name) => page.screenshot({ path: `${__dirname}/shots/admin2_${name}.png`, fullPage: true });
const submit = (page, form) => Promise.all([page.waitForNavigation(), page.click(`${form} button[type=submit]`)]);
const ret = page => (page.url().match(/return=(\w+)/) || [])[1];
(async () => {
  const browser = await chromium.launch();
  const admin = await login(browser, '192');
  const stamp = Date.now() % 100000;

  // New ticket type -> gets a copy of the default workflow
  await admin.goto(M + 'types_manage_edit.php');
  await admin.fill('form#ticketType input[name=name]', 'IT Support ' + stamp);
  await admin.fill('form#ticketType input[name=description]', 'Computers & accounts');
  await admin.check('form#ticketType input[name="teams[]"][value="00000001"]');
  await submit(admin, 'form#ticketType'); console.log('type saved:', ret(admin), admin.url()); await check(admin, 'type saved');
  const typeID = admin.url().match(/gibbonServiceDeskTicketTypeID=(\d+)/)[1];

  // Add a field (name derived from label) and a Select field
  await admin.goto(M + 'types_field_edit.php&gibbonServiceDeskTicketTypeID=' + typeID);
  await admin.fill('input[name=label]', 'Device Name');
  await submit(admin, 'form#field'); console.log('field saved:', ret(admin)); await check(admin, 'field saved');
  await admin.goto(M + 'types_field_edit.php&gibbonServiceDeskTicketTypeID=' + typeID);
  await admin.fill('input[name=label]', 'Urgency'); await admin.selectOption('select[name=type]', 'Select');
  await admin.fill('textarea[name=options]', 'Low, Medium , High');
  await submit(admin, 'form#field'); console.log('select field saved:', ret(admin)); await shot(admin, 'type_edit');
  // duplicate name must fail
  await admin.goto(M + 'types_field_edit.php&gibbonServiceDeskTicketTypeID=' + typeID);
  await admin.fill('input[name=label]', 'Urgency');
  await submit(admin, 'form#field'); console.log('duplicate field (expect error7):', ret(admin));

  // Workflow of the new type: copied stages, add one with automation
  await admin.goto(M + 'workflow_manage.php&gibbonServiceDeskTicketTypeID=' + typeID); await check(admin, 'workflow of new type');
  console.log('copied stages:', (await admin.$$eval('table tbody tr td:nth-child(2)', c => c.map(e => e.innerText.trim()))).join(', '));
  await admin.click('a[href*="workflow_stage_edit.php"]:has-text("Add")'); await admin.waitForLoadState();
  await admin.fill('input[name=name]', 'Waiting for Parts'); await admin.fill('input[name=sequenceNumber]', '15');
  await submit(admin, 'form#stage'); console.log('stage saved:', ret(admin)); await check(admin, 'stage saved'); await shot(admin, 'workflow');

  // Delete a stage through the modal page
  const del = await admin.$('a[href*="workflow_stage_delete.php"]');
  const delURL = (await del.getAttribute('href')).replace(/^.*index\.php/, BASE + '/index.php').replace('fullscreen.php', 'index.php');
  await admin.goto(BASE + '/index.php?q=/modules/ServiceDesk/workflow_stage_delete.php&' + delURL.split('workflow_stage_delete.php&')[1].replace(/&width.*$/, ''));
  await check(admin, 'stage delete page'); await submit(admin, 'form'); console.log('stage deleted:', ret(admin));

  // Staff request form of the Complaint type (student select for staff)
  await admin.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=4'); await check(admin, 'staff complaint form'); await shot(admin, 'staff_form');
  await admin.fill('textarea', 'The heating in room 12 is broken.'); await admin.fill('input[name=subject]', 'Heating');
  await submit(admin, 'form#request'); console.log('staff ticket:', ret(admin)); await check(admin, 'staff ticket');

  // Filters: custom criteria, save shared with team 1, then delete
  await admin.goto(M + 'tickets.php&status[]=Closed&type[]=1&search=Absence'); await check(admin, 'custom filter');
  console.log('rows for closed absence:', await admin.$$eval('table tbody tr', r => r.length)); await shot(admin, 'tickets_custom');
  await admin.fill('form#filterSave input[name=name]', 'Closed absences ' + stamp);
  await admin.selectOption('form#filterSave select[name=share]', '1');
  await submit(admin, 'form#filterSave'); console.log('filter saved:', ret(admin), admin.url()); await check(admin, 'saved filter view'); await shot(admin, 'tickets_saved');
  console.log('rows via saved filter:', await admin.$$eval('table tbody tr', r => r.length));
  await submit(admin, 'form#filterDelete'); console.log('filter deleted:', ret(admin));

  // Type with tickets cannot be deleted (expect error4)
  await admin.goto(M + 'types_manage_delete.php&gibbonServiceDeskTicketTypeID=1'); await submit(admin, 'form'); console.log('delete type with tickets (expect error4):', ret(admin)); await check(admin, 'error4 shown');

  // Automation failure: absence on a weekend only
  const parent = await login(browser, '463');
  await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=1');
  await parent.check('form#request input[type=checkbox]');
  await parent.selectOption('select[name=field2]', 'Family');
  await parent.fill('input[name=field3]', '2026-11-21'); await parent.fill('input[name=field4]', '2026-11-22');
  await submit(parent, 'form#request'); const url = parent.url().replace(/&return=.*/, ''); console.log('weekend ticket:', url);
  await admin.goto(url);
  await admin.selectOption('form#manage select[name=gibbonServiceDeskStageID]', { label: 'Approved *' });
  await submit(admin, 'form#manage'); console.log('weekend approve (expect warning1):', ret(admin));
  const text = await admin.innerText('.serviceDesk-ticket');
  console.log('stays New:', /STAGE\s*New/i.test(text), '| failure logged:', text.includes('failed'));
  await shot(admin, 'automation_failed');
  await parent.goto(url); console.log('parent sees failure log:', (await parent.innerText('.serviceDesk-ticket')).includes('failed'));
  // required validation server side: missing children
  await parent.goto(M + 'request_new.php&gibbonServiceDeskTicketTypeID=3');
  await parent.fill('input[type=date]', '2026-12-01'); await parent.fill('textarea', 'Moving');
  await submit(parent, 'form#request'); console.log('missing students (expect error1):', ret(parent));
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 8).join('\n')); process.exit(1); });
