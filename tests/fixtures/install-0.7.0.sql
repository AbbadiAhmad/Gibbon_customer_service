/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsAutomationRule` (
  `csAutomationRuleID` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketTypeID` int(6) unsigned DEFAULT NULL COMMENT 'NULL = applies to every ticket type',
  `name` varchar(100) NOT NULL,
  `triggerKey` varchar(50) NOT NULL COMMENT 'See AutomationEngine::triggers()',
  `conditions` text DEFAULT NULL COMMENT 'JSON list of {field, op, value} - same format as board filters',
  `actions` text NOT NULL COMMENT 'JSON list of {type, params} - see Automation/Actions',
  `sequenceNumber` int(4) NOT NULL DEFAULT 0,
  `active` enum('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (`csAutomationRuleID`),
  KEY `triggerKey` (`triggerKey`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `gibbonCsAutomationRule` (`csAutomationRuleID`, `csTicketTypeID`, `name`, `triggerKey`, `conditions`, `actions`, `sequenceNumber`, `active`) VALUES (1,2,'Registration: add the steps checklist','ticket_created',NULL,'[{\"type\":\"add_checklist\",\"params\":{\"title\":\"Registration steps\",\"items\":\"Check the application and the documents\\nPlan the interview\\nTake the decision\\nSend the decision letter\\nCreate the student record\"}}]',1,'Y'),
(2,3,'Leaving: add the leaving checklist','ticket_created',NULL,'[{\"type\":\"add_checklist\",\"params\":{\"title\":\"Leaving checklist\",\"items\":\"Confirm the last day at school\\nPrepare the transfer documents\\nCollect books and materials\\nSettle open fees\\nSend the confirmation\"}}]',1,'Y');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsBoard` (
  `csBoardID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `csSpaceID` int(6) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `filter` text DEFAULT NULL COMMENT 'JSON list of conditions: [{field, op, value}]. Empty = all tickets',
  `active` enum('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (`csBoardID`),
  KEY `csSpaceID` (`csSpaceID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `gibbonCsBoard` (`csBoardID`, `csSpaceID`, `name`, `description`, `filter`, `active`) VALUES (1,1,'All requests','Every request with every status',NULL,'Y'),
(2,1,'Absences','Absence reports of the families','[{\"field\":\"type\",\"op\":\"equals\",\"value\":\"Absence Report\"}]','Y'),
(3,1,'Registrations','The registration process from application to decision','[{\"field\":\"type\",\"op\":\"equals\",\"value\":\"School Registration\"}]','Y'),
(4,1,'Leaving','Children leaving the school','[{\"field\":\"type\",\"op\":\"equals\",\"value\":\"School Leaving\"}]','Y'),
(5,1,'Questions','General questions of the families','[{\"field\":\"type\",\"op\":\"equals\",\"value\":\"General Question\"}]','Y'),
(6,1,'Staff Services','Staff requests and material orders (everything routed to the Staff Services team)','[{\"field\":\"team\",\"op\":\"equals\",\"value\":\"Staff Services\"}]','Y');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsBoardColumn` (
  `csBoardColumnID` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `csBoardID` int(6) unsigned NOT NULL,
  `name` varchar(60) NOT NULL COMMENT 'Also the ticket status a ticket gets when moved into this column',
  `sequenceNumber` int(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`csBoardColumnID`),
  UNIQUE KEY `board_name` (`csBoardID`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `gibbonCsBoardColumn` (`csBoardColumnID`, `csBoardID`, `name`, `sequenceNumber`) VALUES (1,1,'New',1),
(2,1,'In Progress',2),
(3,1,'Waiting',3),
(4,1,'Documents Requested',4),
(5,1,'Interview Planned',5),
(6,1,'Awaiting Approval',6),
(7,1,'Ordered',7),
(8,1,'Resolved',8),
(9,1,'Declined',9),
(10,1,'Closed',10),
(11,2,'New',1),
(12,2,'In Progress',2),
(13,2,'Resolved',3),
(14,2,'Closed',4),
(15,3,'New',1),
(16,3,'Documents Requested',2),
(17,3,'Interview Planned',3),
(18,3,'Waiting',4),
(19,3,'Resolved',5),
(20,3,'Declined',6),
(21,3,'Closed',7),
(22,4,'New',1),
(23,4,'In Progress',2),
(24,4,'Waiting',3),
(25,4,'Resolved',4),
(26,4,'Closed',5),
(27,5,'New',1),
(28,5,'In Progress',2),
(29,5,'Waiting',3),
(30,5,'Resolved',4),
(31,5,'Closed',5),
(32,6,'New',1),
(33,6,'In Progress',2),
(34,6,'Awaiting Approval',3),
(35,6,'Ordered',4),
(36,6,'Resolved',5),
(37,6,'Declined',6),
(38,6,'Closed',7);
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsChecklist` (
  `csChecklistID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketID` int(10) unsigned NOT NULL,
  `title` varchar(100) NOT NULL,
  `sequenceNumber` int(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`csChecklistID`),
  KEY `csTicketID` (`csTicketID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsChecklistItem` (
  `csChecklistItemID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csChecklistID` int(10) unsigned NOT NULL,
  `text` varchar(255) NOT NULL,
  `done` enum('N','Y') NOT NULL DEFAULT 'N',
  `sequenceNumber` int(4) NOT NULL DEFAULT 0,
  `gibbonPersonIDDone` int(10) unsigned zerofill DEFAULT NULL,
  `timestampDone` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`csChecklistItemID`),
  KEY `csChecklistID` (`csChecklistID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsField` (
  `csFieldID` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketTypeID` int(6) unsigned NOT NULL,
  `fieldKey` varchar(50) NOT NULL COMMENT 'Machine name (a-z, 0-9, _). Same key on different types = same field for board filters',
  `label` varchar(100) NOT NULL,
  `fieldType` enum('varchar','text','number','date','select','checkboxes','radio','yesno') NOT NULL DEFAULT 'varchar',
  `options` text DEFAULT NULL COMMENT 'Max length for varchar, comma separated list for select/checkboxes/radio',
  `required` enum('N','Y') NOT NULL DEFAULT 'N',
  `showOnForm` enum('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'N = only the agents fill it in, the portal form does not show it',
  `description` varchar(255) DEFAULT NULL,
  `sequenceNumber` int(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`csFieldID`),
  UNIQUE KEY `type_key` (`csTicketTypeID`,`fieldKey`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `gibbonCsField` (`csFieldID`, `csTicketTypeID`, `fieldKey`, `label`, `fieldType`, `options`, `required`, `showOnForm`, `description`, `sequenceNumber`) VALUES (1,1,'child_name','Child\'s name','varchar','100','Y','Y',NULL,10),
(2,1,'class_name','Class','varchar','50','N','Y',NULL,20),
(3,1,'absent_from','Absent from','date','','Y','Y',NULL,30),
(4,1,'absent_until','Absent until','date','','N','Y','Leave empty if you don\'t know yet.',40),
(5,1,'reason','Reason','select','Illness,Medical appointment,Family reasons,Other','Y','Y',NULL,50),
(6,1,'contact_phone','Phone number where we can reach you','varchar','30','N','Y',NULL,60),
(7,1,'attendance_recorded','Entered in the attendance register','yesno','','N','N','For the office only.',70),
(8,2,'child_first_name','Child\'s first name','varchar','100','Y','Y',NULL,10),
(9,2,'child_last_name','Child\'s last name','varchar','100','Y','Y',NULL,20),
(10,2,'birth_date','Date of birth','date','','Y','Y',NULL,30),
(11,2,'year_group','Year / grade applying for','varchar','50','Y','Y',NULL,40),
(12,2,'previous_school','Previous school','varchar','150','N','Y',NULL,50),
(13,2,'siblings_at_school','Does your child have siblings at our school?','yesno','','N','Y',NULL,60),
(14,2,'parent_phone','Phone number','varchar','30','Y','Y',NULL,70),
(15,2,'documents_complete','All documents received','yesno','','N','N','For the office only.',80),
(16,2,'interview_date','Interview date','date','','N','N','For the office only.',90),
(17,3,'child_name','Child\'s name','varchar','100','Y','Y',NULL,10),
(18,3,'class_name','Class','varchar','50','N','Y',NULL,20),
(19,3,'reason','Reason for leaving','select','Moving away,Changing school,Other','Y','Y',NULL,30),
(20,3,'new_school','New school (if known)','varchar','150','N','Y',NULL,40),
(21,3,'documents_needed','Documents you need from us','checkboxes','Report card,Certificate of attendance,Transfer form','N','Y',NULL,50),
(22,3,'fees_settled','Open fees settled','yesno','','N','N','For the office only.',60),
(23,3,'library_returned','Books and materials returned','yesno','','N','N','For the office only.',70),
(24,4,'topic','Topic','select','Fees and payment,Schedule and calendar,Health and safety,Other','Y','Y',NULL,10),
(25,4,'child_name','Child\'s name (if it concerns a child)','varchar','100','N','Y',NULL,20),
(26,5,'request_kind','Kind of request','select','Leave of absence,Certificate or letter,Schedule change,IT or access,Other','Y','Y',NULL,10),
(27,6,'item','Item','varchar','150','Y','Y',NULL,10),
(28,6,'quantity','Quantity','number','','Y','Y',NULL,20),
(29,6,'room','Room / department','varchar','100','N','Y',NULL,30),
(30,6,'budget_code','Budget code','varchar','50','N','Y',NULL,40),
(31,6,'supplier','Supplier','varchar','150','N','N','For the office only.',50),
(32,6,'order_reference','Order reference','varchar','100','N','N','For the office only.',60);
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsRole` (
  `csRoleID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`csRoleID`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `gibbonCsRole` (`csRoleID`, `name`, `description`) VALUES (1,'Space Admin','Full control of a space: members, boards and all tickets'),
(2,'Agent','Triages and works on tickets (e.g. the front office)'),
(3,'Viewer','Can see the boards of the space and comment');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsRolePermission` (
  `csRolePermissionID` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `csRoleID` int(6) unsigned NOT NULL,
  `permission` varchar(50) NOT NULL,
  PRIMARY KEY (`csRolePermissionID`),
  UNIQUE KEY `role_permission` (`csRoleID`,`permission`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `gibbonCsRolePermission` (`csRolePermissionID`, `csRoleID`, `permission`) VALUES (5,1,'assign_ticket'),
(2,1,'comment'),
(6,1,'delete_ticket'),
(3,1,'edit_ticket'),
(7,1,'manage_boards'),
(8,1,'manage_members'),
(4,1,'transition_ticket'),
(1,1,'view_board'),
(13,2,'assign_ticket'),
(10,2,'comment'),
(11,2,'edit_ticket'),
(12,2,'transition_ticket'),
(9,2,'view_board'),
(15,3,'comment'),
(14,3,'view_board');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsSavedFilter` (
  `csSavedFilterID` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  `name` varchar(100) NOT NULL,
  `params` text NOT NULL COMMENT 'JSON of the ticket search form values',
  PRIMARY KEY (`csSavedFilterID`),
  KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsSpace` (
  `csSpaceID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `active` enum('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (`csSpaceID`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `gibbonCsSpace` (`csSpaceID`, `name`, `description`, `active`) VALUES (1,'School Office','Front office, admissions and staff services','Y');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsSpaceMember` (
  `csSpaceMemberID` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `csSpaceID` int(6) unsigned NOT NULL,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  `csRoleID` int(6) unsigned NOT NULL,
  PRIMARY KEY (`csSpaceMemberID`),
  UNIQUE KEY `space_person_role` (`csSpaceID`,`gibbonPersonID`,`csRoleID`),
  KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsStatus` (
  `csStatusID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL COMMENT 'Ticket status, also the name of the board column that shows it',
  `category` enum('To Do','In Progress','Done') NOT NULL DEFAULT 'In Progress' COMMENT 'Every status belongs to one of the three base categories',
  `sequenceNumber` int(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`csStatusID`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `gibbonCsStatus` (`csStatusID`, `name`, `category`, `sequenceNumber`) VALUES (1,'New','To Do',1),
(2,'In Progress','In Progress',2),
(3,'Waiting','In Progress',3),
(4,'Documents Requested','In Progress',4),
(5,'Interview Planned','In Progress',5),
(6,'Awaiting Approval','In Progress',6),
(7,'Ordered','In Progress',7),
(8,'Resolved','Done',8),
(9,'Declined','Done',9),
(10,'Closed','Done',10);
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsTag` (
  `csTagID` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`csTagID`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsTeam` (
  `csTeamID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `active` enum('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (`csTeamID`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `gibbonCsTeam` (`csTeamID`, `name`, `description`, `active`) VALUES (1,'Front Office','First line (L1): triages every incoming request, answers questions and records absences','Y'),
(2,'Admissions','Handles registrations and leaving requests','Y'),
(3,'Staff Services','Handles requests of employees and material orders','Y');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsTeamMember` (
  `csTeamMemberID` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `csTeamID` int(6) unsigned NOT NULL,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  PRIMARY KEY (`csTeamMemberID`),
  UNIQUE KEY `team_person` (`csTeamID`,`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsTicket` (
  `csTicketID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketTypeID` int(6) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(60) NOT NULL DEFAULT 'New',
  `priority` enum('Low','Medium','High','Urgent') NOT NULL DEFAULT 'Medium',
  `gibbonPersonIDReporter` int(10) unsigned zerofill NOT NULL,
  `gibbonPersonIDAssignee` int(10) unsigned zerofill DEFAULT NULL,
  `csTeamID` int(6) unsigned DEFAULT NULL,
  `customData` longtext DEFAULT NULL COMMENT 'JSON object of custom field values keyed by fieldKey',
  `resolution` text DEFAULT NULL COMMENT 'Resolution note given when the ticket was moved to Resolved / Closed',
  `timestampResolved` timestamp NULL DEFAULT NULL,
  `csTicketIDParent` int(10) unsigned DEFAULT NULL COMMENT 'Set on subtasks: the ticket this one is a part of',
  `dateStart` date DEFAULT NULL,
  `dateDue` date DEFAULT NULL,
  `dueReminderSent` datetime DEFAULT NULL COMMENT 'When the due soon reminder was sent (reset when the due date changes)',
  `overdueNoticeSent` datetime DEFAULT NULL COMMENT 'When the overdue notice was sent (reset when the due date changes)',
  `timestampCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `timestampUpdated` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`csTicketID`),
  KEY `csTicketTypeID` (`csTicketTypeID`),
  KEY `status` (`status`),
  KEY `reporter` (`gibbonPersonIDReporter`),
  KEY `assignee` (`gibbonPersonIDAssignee`),
  KEY `csTeamID` (`csTeamID`),
  KEY `csTicketIDParent` (`csTicketIDParent`),
  KEY `dateDue` (`dateDue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsTicketActivity` (
  `csTicketActivityID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketID` int(10) unsigned NOT NULL,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  `kind` enum('Comment','Event') NOT NULL DEFAULT 'Comment',
  `visibility` enum('Public','Internal') NOT NULL DEFAULT 'Public' COMMENT 'Internal = only visible to the people working on the ticket',
  `body` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`csTicketActivityID`),
  KEY `csTicketID` (`csTicketID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsTicketAttachment` (
  `csTicketAttachmentID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketID` int(10) unsigned NOT NULL,
  `csTicketActivityID` int(10) unsigned DEFAULT NULL COMMENT 'The comment it was attached to; NULL = attached to the request itself',
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  `fileName` varchar(255) NOT NULL,
  `filePath` varchar(255) NOT NULL COMMENT 'Relative to the Gibbon root, below uploads/',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`csTicketAttachmentID`),
  KEY `csTicketID` (`csTicketID`),
  KEY `csTicketActivityID` (`csTicketActivityID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsTicketLink` (
  `csTicketLinkID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketIDFrom` int(10) unsigned NOT NULL COMMENT 'FROM blocks / duplicates / relates to TO',
  `csTicketIDTo` int(10) unsigned NOT NULL,
  `linkType` enum('blocks','duplicates','relates') NOT NULL,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`csTicketLinkID`),
  UNIQUE KEY `link` (`csTicketIDFrom`,`csTicketIDTo`,`linkType`),
  KEY `csTicketIDTo` (`csTicketIDTo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsTicketTag` (
  `csTicketTagID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketID` int(10) unsigned NOT NULL,
  `csTagID` int(8) unsigned NOT NULL,
  PRIMARY KEY (`csTicketTagID`),
  UNIQUE KEY `ticket_tag` (`csTicketID`,`csTagID`),
  KEY `csTagID` (`csTagID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsTicketType` (
  `csTicketTypeID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `csTeamID` int(6) unsigned DEFAULT NULL COMMENT 'Default team new tickets of this type are routed to',
  `workflow` text DEFAULT NULL COMMENT 'JSON: {transitions: {from: [to, ...]}, commentRequired: [status, ...]}',
  `groupName` varchar(60) DEFAULT NULL COMMENT 'Heading the type is listed under on the customer portal',
  `icon` varchar(16) DEFAULT NULL COMMENT 'Emoji shown on the portal card',
  `audience` varchar(100) DEFAULT NULL COMMENT 'Comma separated Gibbon role categories allowed to raise it (Staff, Student, Parent, Other). Empty = everybody',
  `formConfig` text DEFAULT NULL COMMENT 'JSON: settings of the basic request form fields (show, required, label, help, order)',
  `active` enum('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (`csTicketTypeID`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `gibbonCsTicketType` (`csTicketTypeID`, `name`, `description`, `csTeamID`, `workflow`, `groupName`, `icon`, `audience`, `formConfig`, `active`) VALUES (1,'Absence Report','Tell us that your child will be absent or late.',1,'{\"transitions\":{\"New\":[\"In Progress\",\"Resolved\"],\"In Progress\":[\"Resolved\"],\"Resolved\":[\"In Progress\",\"Closed\"]}}','Families','✚','Parent,Staff','{\"title\":{\"label\":\"Summary\",\"help\":\"For example: Anna - ill on 3 November\",\"order\":1},\"description\":{\"label\":\"Additional information\",\"order\":80},\"priority\":{\"show\":false},\"dateStart\":{\"show\":false},\"dateDue\":{\"show\":false},\"attachments\":{\"label\":\"Doctor\'s note\",\"help\":\"Optional.\"}}','Y'),
(2,'School Registration','Register a child at our school.',2,'{\"transitions\":{\"New\":[\"Documents Requested\",\"Interview Planned\",\"Waiting\",\"Declined\"],\"Documents Requested\":[\"Interview Planned\",\"Waiting\",\"Declined\"],\"Waiting\":[\"Documents Requested\",\"Interview Planned\",\"Declined\"],\"Interview Planned\":[\"Waiting\",\"Resolved\",\"Declined\"],\"Resolved\":[\"Closed\"],\"Declined\":[\"Closed\"]},\"commentRequired\":[\"Waiting\"]}','Families','✎','Parent,Staff','{\"title\":{\"label\":\"Summary\",\"help\":\"For example: Registration of Anna Meier\"},\"description\":{\"label\":\"Anything we should know?\",\"order\":100},\"priority\":{\"show\":false},\"dateDue\":{\"show\":false},\"dateStart\":{\"label\":\"Desired start date\",\"help\":\"When should your child start with us?\",\"required\":true,\"order\":95},\"attachments\":{\"label\":\"Documents\",\"help\":\"Birth certificate, last report card, vaccination record...\"}}','Y'),
(3,'School Leaving','Tell us that your child is leaving the school.',2,'{\"transitions\":{\"New\":[\"In Progress\",\"Waiting\"],\"In Progress\":[\"Waiting\",\"Resolved\"],\"Waiting\":[\"In Progress\",\"Resolved\"],\"Resolved\":[\"In Progress\",\"Closed\"]}}','Families','➜','Parent,Staff','{\"title\":{\"label\":\"Summary\",\"help\":\"For example: Leaving of Anna Meier\"},\"description\":{\"label\":\"Anything we should know?\",\"order\":100},\"priority\":{\"show\":false},\"dateStart\":{\"show\":false},\"dateDue\":{\"label\":\"Last day at school\",\"help\":\"The last day your child attends.\",\"required\":true,\"order\":95}}','Y'),
(4,'General Question','Ask us anything else.',1,'{\"transitions\":{\"New\":[\"In Progress\",\"Resolved\"],\"In Progress\":[\"Waiting\",\"Resolved\"],\"Waiting\":[\"In Progress\",\"Resolved\"],\"Resolved\":[\"In Progress\",\"Closed\"]}}','Families','❓',NULL,'{\"title\":{\"label\":\"Subject\"},\"description\":{\"label\":\"Your question\",\"required\":true,\"order\":30},\"priority\":{\"show\":false},\"dateStart\":{\"show\":false},\"dateDue\":{\"show\":false}}','Y'),
(5,'Staff Request','Leave, certificates, schedule changes and other requests of employees.',3,'{\"transitions\":{\"New\":[\"In Progress\",\"Awaiting Approval\",\"Declined\"],\"In Progress\":[\"Awaiting Approval\",\"Resolved\",\"Declined\"],\"Awaiting Approval\":[\"In Progress\",\"Resolved\",\"Declined\"],\"Resolved\":[\"Closed\"],\"Declined\":[\"Closed\"]}}','Staff','✉','Staff','{\"description\":{\"label\":\"Details\",\"required\":true,\"order\":80},\"dateStart\":{\"label\":\"From\",\"order\":20},\"dateDue\":{\"label\":\"Needed by\",\"order\":30}}','Y'),
(6,'Material Request','Order teaching materials and supplies.',3,'{\"transitions\":{\"New\":[\"Awaiting Approval\",\"Declined\"],\"Awaiting Approval\":[\"Ordered\",\"Declined\"],\"Ordered\":[\"Resolved\"],\"Resolved\":[\"Closed\"],\"Declined\":[\"Closed\"]}}','Staff','⚒','Staff','{\"description\":{\"label\":\"Why do you need it?\",\"order\":80},\"dateStart\":{\"show\":false},\"dateDue\":{\"label\":\"Needed by\",\"required\":true,\"order\":70}}','Y');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gibbonCsTicketWatcher` (
  `csTicketWatcherID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketID` int(10) unsigned NOT NULL,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  PRIMARY KEY (`csTicketWatcherID`),
  UNIQUE KEY `ticket_person` (`csTicketID`,`gibbonPersonID`),
  KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
