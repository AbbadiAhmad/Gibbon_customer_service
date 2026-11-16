-- Experiments behind docs/AUDIT.md: the same "all tickets, newest news first" list written in different ways.
-- Usage: mariadb -h127.0.0.1 -P3399 -uroot gibbon -vvv < tests/perf/variants.sql | grep -E "^-- |rows in set|row in set"
SET @me := 192;

-- A: as before 1.8.00: every column of the ticket (with the JSON answers and the search text), total counted by SQL_CALC_FOUND_ROWS
SELECT SQL_CALC_FOUND_ROWS t.*, ty.name AS typeName, s.name AS stageName, s.category AS stageCategory, r.surname AS requesterSurname, a.surname AS assigneeSurname,
  (CASE WHEN seen.timestampSeen IS NULL THEN (s.category<>'Closed') WHEN t.timestampModified > seen.timestampSeen THEN 1 ELSE 0 END) AS unread
FROM gibbonServiceDeskTicket t
JOIN gibbonServiceDeskTicketType ty ON ty.gibbonServiceDeskTicketTypeID=t.gibbonServiceDeskTicketTypeID
JOIN gibbonServiceDeskStage s ON s.gibbonServiceDeskStageID=t.gibbonServiceDeskStageID
LEFT JOIN gibbonPerson r ON r.gibbonPersonID=t.gibbonPersonIDRequester
LEFT JOIN gibbonPerson a ON a.gibbonPersonID=t.gibbonPersonIDAssignee
LEFT JOIN gibbonServiceDeskTicketSeen seen ON seen.gibbonServiceDeskTicketID=t.gibbonServiceDeskTicketID AND seen.gibbonPersonID=@me
ORDER BY unread DESC, t.timestampModified DESC LIMIT 25;

-- B: only the columns a list needs (no JSON answers, no search text)
SELECT SQL_CALC_FOUND_ROWS t.gibbonServiceDeskTicketID, t.subject, t.priority, t.timestampModified, t.gibbonPersonIDAssignee, ty.name AS typeName, s.name AS stageName, s.category AS stageCategory, r.surname AS requesterSurname, a.surname AS assigneeSurname,
  (CASE WHEN seen.timestampSeen IS NULL THEN (s.category<>'Closed') WHEN t.timestampModified > seen.timestampSeen THEN 1 ELSE 0 END) AS unread
FROM gibbonServiceDeskTicket t
JOIN gibbonServiceDeskTicketType ty ON ty.gibbonServiceDeskTicketTypeID=t.gibbonServiceDeskTicketTypeID
JOIN gibbonServiceDeskStage s ON s.gibbonServiceDeskStageID=t.gibbonServiceDeskStageID
LEFT JOIN gibbonPerson r ON r.gibbonPersonID=t.gibbonPersonIDRequester
LEFT JOIN gibbonPerson a ON a.gibbonPersonID=t.gibbonPersonIDAssignee
LEFT JOIN gibbonServiceDeskTicketSeen seen ON seen.gibbonServiceDeskTicketID=t.gibbonServiceDeskTicketID AND seen.gibbonPersonID=@me
ORDER BY unread DESC, t.timestampModified DESC LIMIT 25;

-- C: two steps. Step 1 finds the 25 ticket numbers of the page from the narrow tables only ...
SELECT t.gibbonServiceDeskTicketID,
  (CASE WHEN seen.timestampSeen IS NULL THEN (s.category<>'Closed') WHEN t.timestampModified > seen.timestampSeen THEN 1 ELSE 0 END) AS unread
FROM gibbonServiceDeskTicket t
JOIN gibbonServiceDeskStage s ON s.gibbonServiceDeskStageID=t.gibbonServiceDeskStageID
LEFT JOIN gibbonServiceDeskTicketSeen seen ON seen.gibbonServiceDeskTicketID=t.gibbonServiceDeskTicketID AND seen.gibbonPersonID=@me
ORDER BY unread DESC, t.timestampModified DESC LIMIT 25;
-- C: ... and the total is a plain count
SELECT COUNT(*) FROM gibbonServiceDeskTicket t;

-- D: open tickets only, status written as FIND_IN_SET (before)
SELECT SQL_CALC_FOUND_ROWS t.gibbonServiceDeskTicketID, t.subject, s.name,
  (CASE WHEN seen.timestampSeen IS NULL THEN (s.category<>'Closed') WHEN t.timestampModified > seen.timestampSeen THEN 1 ELSE 0 END) AS unread
FROM gibbonServiceDeskTicket t
JOIN gibbonServiceDeskStage s ON s.gibbonServiceDeskStageID=t.gibbonServiceDeskStageID
LEFT JOIN gibbonServiceDeskTicketSeen seen ON seen.gibbonServiceDeskTicketID=t.gibbonServiceDeskTicketID AND seen.gibbonPersonID=@me
WHERE FIND_IN_SET(s.category, 'Open,In Progress')
ORDER BY unread DESC, t.timestampModified DESC LIMIT 2;

-- E: open tickets only, status as a column of the ticket with an index (after)
-- (needs: ALTER TABLE gibbonServiceDeskTicket ADD status ..., ADD KEY (status, timestampModified))
