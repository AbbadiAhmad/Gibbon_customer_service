const { chromium, BASE, login, check, sql } = require('./lib');
// A stage of the default type's workflow (ids differ between installs)
const stageID = parseInt(sql('select min(gibbonServiceDeskStageID) from gibbonServiceDeskStage where gibbonServiceDeskTicketTypeID=1')[0][0], 10);
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
(async () => {
  const browser = await chromium.launch();
  const page = await login(browser, '192');
  for (const p of ['tickets.php', 'request_new.php', 'request_new.php&gibbonServiceDeskTicketTypeID=1', 'request_new.php&gibbonServiceDeskTicketTypeID=2', 'request_new.php&gibbonServiceDeskTicketTypeID=4', 'notifications.php',
    'types_manage.php', 'types_manage_edit.php', 'types_manage_edit.php&gibbonServiceDeskTicketTypeID=1', 'types_manage_edit.php&gibbonServiceDeskTicketTypeID=5', 'types_field_edit.php&gibbonServiceDeskTicketTypeID=1', 'types_field_edit.php&gibbonServiceDeskTicketTypeID=1&gibbonServiceDeskFieldID=2',
    'workflow_manage.php', 'workflow_manage.php&gibbonServiceDeskTicketTypeID=1', 'workflow_stage_edit.php', 'workflow_stage_edit.php&gibbonServiceDeskTicketTypeID=1&gibbonServiceDeskStageID=' + stageID,
    'teams_manage.php', 'teams_manage_edit.php', 'teams_manage_edit.php&gibbonServiceDeskTeamID=1',
    'tickets.php&status[]=Open&assignee=me', 'tickets.php&gibbonServiceDeskFilterID=2']) {
    await page.goto(M + p);
    await check(page, p);
    await page.screenshot({ path: `${__dirname}/shots/admin_${p.replace(/[^a-zA-Z0-9]+/g, '_')}.png`, fullPage: true });
  }
  await browser.close();
})();
