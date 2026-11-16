// Times the module's pages on the live test site and the database queries behind them.
// Usage: node tests/perf/measure.js [label]      (after tests/perf/seed.sql; prints one line per scenario)
// It switches MariaDB's slow query log to "log everything" for the run and reads the queries each page made.
const fs = require('fs');
const { chromium, BASE, login, sql } = require('../live/lib');
const M = BASE + '/index.php?q=/modules/ServiceDesk/';
const LOG = '/tmp/servicedesk-queries.log';

const scenarios = [
  // user, name, address
  ['192', 'admin: home page (sidebar box)', BASE + '/index.php'],
  ['192', 'admin: list, default', M + 'tickets.php&view=list'],
  ['192', 'admin: list, page 2', M + 'tickets.php&view=list&page=2'],
  ['192', 'admin: list, open tickets', M + 'tickets.php&view=list&status[]=Open&status[]=In Progress'],
  ['192', 'admin: list, closed tickets', M + 'tickets.php&view=list&status[]=Closed'],
  ['192', 'admin: list, one type', M + 'tickets.php&view=list&type[]=1'],
  ['192', 'admin: list, assigned to someone', M + 'tickets.php&view=list&assignee=119'],
  ['192', 'admin: search one word', M + 'tickets.php&view=list&search=canteen'],
  ['192', 'admin: search two words', M + 'tickets.php&view=list&search=locker 199990'],
  ['192', 'admin: search a name', M + 'tickets.php&view=list&search=Schultz'],
  ['192', 'admin: search nothing found', M + 'tickets.php&view=list&search=zzzqqq'],
  ['192', 'admin: board, all types', M + 'tickets.php&view=board'],
  ['192', 'admin: board, one type', M + 'tickets.php&view=board&type[]=1'],
  ['192', 'admin: one ticket', M + 'tickets_view.php&gibbonServiceDeskTicketID=150000'],
  ['119', 'team member: home page (sidebar box)', BASE + '/index.php'],
  ['119', 'team member: list, default', M + 'tickets.php&view=list'],
  ['119', 'team member: list, assigned to me, open', M + 'tickets.php&view=list&assignee=me&status[]=Open&status[]=In Progress'],
  ['119', 'team member: search one word', M + 'tickets.php&view=list&search=canteen'],
  ['119', 'team member: board', M + 'tickets.php&view=board'],
  ['463', 'parent: home page (sidebar box)', BASE + '/index.php'],
  ['463', 'parent: list, default', M + 'tickets.php&view=list'],
  ['463', 'parent: search one word', M + 'tickets.php&view=list&search=canteen'],
  ['463', 'parent: new request page', M + 'request_new.php'],
];

// The entries MariaDB appended to the log since `offset`: [{ seconds, rows, sql }]
function queriesSince(offset) {
  const text = fs.readFileSync(LOG, 'utf8').slice(offset);
  return text.split(/^# User@Host:.*$/m).slice(1).map(entry => {
    const time = parseFloat((entry.match(/Query_time: ([\d.]+)/) || [])[1] || '0');
    const rows = parseInt((entry.match(/Rows_examined: (\d+)/) || [])[1] || '0', 10);
    const statement = entry.split('\n').filter(l => !l.startsWith('#') && !/^(SET timestamp|use )/i.test(l)).join(' ').replace(/\s+/g, ' ').trim();
    return { seconds: time, rows, sql: statement };
  }).filter(q => /gibbonServiceDesk/.test(q.sql));
}

(async () => {
  sql(`SET GLOBAL slow_query_log_file='${LOG}'`); sql(`SET GLOBAL log_output='FILE'`); sql(`SET GLOBAL long_query_time=0`); sql(`SET GLOBAL slow_query_log=1`);
  if (!fs.existsSync(LOG)) fs.writeFileSync(LOG, '');
  const browser = await chromium.launch();
  const pages = {};
  const results = [];
  for (const [user, name, url] of scenarios) {
    if (!pages[user]) pages[user] = await login(browser, user);
    const page = pages[user];
    await page.goto(url);                         // warm-up: caches, autoloader
    const offset = fs.statSync(LOG).size;
    const started = Date.now();
    await page.goto(url);
    const ms = Date.now() - started;
    const html = await page.content();
    const problem = (html.match(/SQLSTATE[^<]*|Fatal error[^<]*/) || [''])[0];
    await new Promise(resolve => setTimeout(resolve, 150));
    const queries = queriesSince(offset).sort((a, b) => b.seconds - a.seconds);
    const dbMs = Math.round(queries.reduce((sum, q) => sum + q.seconds, 0) * 1000);
    const rows = queries.reduce((sum, q) => sum + q.rows, 0);
    results.push({ name, ms, dbMs, count: queries.length, rows, slowest: queries[0] });
    console.log(`${name.padEnd(42)} page ${String(ms).padStart(6)} ms | database ${String(dbMs).padStart(6)} ms in ${String(queries.length).padStart(2)} queries, ${String(rows).padStart(9)} rows read${problem ? ' | PROBLEM ' + problem.slice(0, 80) : ''}`);
    if (queries[0] && queries[0].seconds >= 0.05) console.log(`${''.padEnd(42)} slowest ${Math.round(queries[0].seconds * 1000)} ms: ${queries[0].sql.slice(0, 150)}`);
  }
  sql(`SET GLOBAL slow_query_log=0`); sql(`SET GLOBAL long_query_time=10`);
  if (process.argv[2]) fs.writeFileSync(`/tmp/servicedesk-perf-${process.argv[2]}.json`, JSON.stringify(results, null, 1));
  await browser.close();
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 6).join('\n')); process.exit(1); });
