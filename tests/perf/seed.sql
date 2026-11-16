-- Fills the live test site (tests/live/setup.sh + install.js + flow.js) with a large volume of tickets to measure speed.
-- Usage: mariadb -h127.0.0.1 -P3399 -uroot gibbon < tests/perf/seed.sql        (about a minute)
-- 200 000 tickets (5% open or in progress, 95% closed, spread over ~3 years), 1 000 000 history lines,
-- ~330 000 follower rows, ~330 000 "seen" rows and ~330 000 notifications. Requesters are the demo parents;
-- two thirds of the tickets are assigned to teacher 119 (member of team Administration after flow.js).

SET @module := (SELECT gibbonModuleID FROM gibbonModule WHERE name='ServiceDesk');

DROP TABLE IF EXISTS perfParent;
CREATE TABLE perfParent (n INT PRIMARY KEY AUTO_INCREMENT, gibbonPersonID INT UNSIGNED);
INSERT INTO perfParent (gibbonPersonID) SELECT DISTINCT gibbonPersonID FROM gibbonFamilyAdult ORDER BY gibbonPersonID;
SET @parents := (SELECT COUNT(*) FROM perfParent);

DROP TABLE IF EXISTS perfStage;
CREATE TABLE perfStage (typeID INT, name VARCHAR(40), stageID INT UNSIGNED, PRIMARY KEY (typeID, name));
INSERT INTO perfStage SELECT gibbonServiceDeskTicketTypeID, name, MIN(gibbonServiceDeskStageID) FROM gibbonServiceDeskStage WHERE gibbonServiceDeskTicketTypeID IS NOT NULL GROUP BY gibbonServiceDeskTicketTypeID, name;

SET @first := (SELECT COALESCE(MAX(gibbonServiceDeskTicketID), 0) FROM gibbonServiceDeskTicket);

INSERT INTO gibbonServiceDeskTicket
    (gibbonSchoolYearID, gibbonServiceDeskTicketTypeID, gibbonServiceDeskStageID, gibbonPersonIDRequester, gibbonPersonIDAssignee,
     subject, fields, searchText, priority, timestampCreated, timestampModified, timestampModifiedPublic, timestampClosed)
SELECT 25,
       1 + (seq % 5),
       (SELECT stageID FROM perfStage WHERE typeID = 1 + (seq % 5) AND name = CASE WHEN seq % 20 = 0 THEN 'New' WHEN seq % 20 = 1 THEN 'In Progress' WHEN seq % 20 = 2 THEN 'Rejected' ELSE 'Done' END),
       (SELECT gibbonPersonID FROM perfParent WHERE n = 1 + (seq % @parents)),
       CASE WHEN seq % 3 = 0 THEN NULL ELSE 119 END,
       CONCAT('Perf request ', seq, ' ', ELT(1 + seq % 6, 'uniform', 'bus', 'canteen', 'report card', 'locker', 'school trip')),
       CONCAT('[{"name":"details","label":"Details","type":"Long Text","value":"Question number ', seq, ' about the ', ELT(1 + seq % 6, 'uniform', 'bus', 'canteen', 'report card', 'locker', 'school trip'), ' for my child."}]'),
       CONCAT('Question number ', seq, ' about the ', ELT(1 + seq % 6, 'uniform', 'bus', 'canteen', 'report card', 'locker', 'school trip'), ' for my child.'),
       ELT(1 + seq % 10, 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Normal', 'Low', 'High', 'High', 'Urgent'),
       NOW() - INTERVAL (seq % 1000) DAY - INTERVAL (seq % 80000) SECOND - INTERVAL 3 DAY,
       NOW() - INTERVAL (seq % 1000) DAY - INTERVAL (seq % 80000) SECOND - INTERVAL 1 DAY,
       NOW() - INTERVAL (seq % 1000) DAY - INTERVAL (seq % 80000) SECOND - INTERVAL 1 DAY,
       CASE WHEN seq % 20 IN (0, 1) THEN NULL ELSE NOW() - INTERVAL (seq % 1000) DAY - INTERVAL (seq % 80000) SECOND - INTERVAL 1 DAY END
FROM seq_1_to_200000;

-- five history lines per ticket
INSERT INTO gibbonServiceDeskTicketComment (gibbonServiceDeskTicketID, gibbonPersonID, type, comment, timestamp)
SELECT t.gibbonServiceDeskTicketID,
       CASE s.seq WHEN 2 THEN t.gibbonPersonIDRequester WHEN 5 THEN NULL ELSE 119 END,
       ELT(s.seq, 'Log', 'Comment', 'Internal Note', 'Comment', 'Log'),
       ELT(s.seq, 'Assigned to Nathan Schultz.', CONCAT('Could you tell me more about request ', t.gibbonServiceDeskTicketID, '?'), 'Checked with the office, nothing unusual.', 'Here is the answer you asked for. Kind regards.', 'Moved to Done.'),
       t.timestampCreated + INTERVAL s.seq HOUR
FROM gibbonServiceDeskTicket t JOIN seq_1_to_5 s
WHERE t.gibbonServiceDeskTicketID > @first;

-- followers and "seen" rows: the requester, and the assignee
INSERT INTO gibbonServiceDeskTicketSubscriber (gibbonServiceDeskTicketID, gibbonPersonID, notifyInApp, notifyEmail)
SELECT gibbonServiceDeskTicketID, gibbonPersonIDRequester, 'Y', 'N' FROM gibbonServiceDeskTicket WHERE gibbonServiceDeskTicketID > @first;
INSERT IGNORE INTO gibbonServiceDeskTicketSubscriber (gibbonServiceDeskTicketID, gibbonPersonID, notifyInApp, notifyEmail)
SELECT gibbonServiceDeskTicketID, gibbonPersonIDAssignee, 'Y', 'N' FROM gibbonServiceDeskTicket WHERE gibbonServiceDeskTicketID > @first AND gibbonPersonIDAssignee IS NOT NULL;
INSERT INTO gibbonServiceDeskTicketSeen (gibbonServiceDeskTicketID, gibbonPersonID, timestampSeen)
SELECT gibbonServiceDeskTicketID, gibbonPersonID, NOW() FROM gibbonServiceDeskTicketSubscriber WHERE gibbonServiceDeskTicketID > @first;

-- notifications about the tickets, already read
INSERT INTO gibbonNotification (gibbonPersonID, gibbonModuleID, text, actionLink, status, count, timestamp)
SELECT s.gibbonPersonID, @module, CONCAT('#', t.gibbonServiceDeskTicketID+0, ' ', t.subject, ': New comment: Here is the answer you asked for.'),
       CONCAT('/index.php?q=/modules/ServiceDesk/tickets_view.php&gibbonServiceDeskTicketID=', t.gibbonServiceDeskTicketID), 'Archived', 1, t.timestampModified
FROM gibbonServiceDeskTicketSubscriber s JOIN gibbonServiceDeskTicket t ON t.gibbonServiceDeskTicketID = s.gibbonServiceDeskTicketID
WHERE t.gibbonServiceDeskTicketID > @first;

DROP TABLE perfParent;
DROP TABLE perfStage;

SELECT (SELECT COUNT(*) FROM gibbonServiceDeskTicket) AS tickets,
       (SELECT COUNT(*) FROM gibbonServiceDeskTicket t JOIN gibbonServiceDeskStage s USING (gibbonServiceDeskStageID) WHERE s.category <> 'Closed') AS openTickets,
       (SELECT COUNT(*) FROM gibbonServiceDeskTicketComment) AS historyLines,
       (SELECT COUNT(*) FROM gibbonServiceDeskTicketSubscriber) AS followers,
       (SELECT COUNT(*) FROM gibbonServiceDeskTicketSeen) AS seenRows,
       (SELECT COUNT(*) FROM gibbonNotification) AS notifications;
