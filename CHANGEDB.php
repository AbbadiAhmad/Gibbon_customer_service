<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!
// Never edit an old entry: add a new version for every schema or action change and mirror it in manifest.php.

$sql = [];
$count = 0;

// v1.0.00 - first release
$sql[$count][0] = '1.0.00';
$sql[$count][1] = '';

// v1.1.00 - action "New Request_onBehalf" (create tickets on behalf of others), given to Administrator and Support Staff;
//           optional default assignee per team
++$count;
$sql[$count][0] = '1.1.00';
$sql[$count][1] = "
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='ServiceDesk'), 'New Request_onBehalf', 1, 'Requests', 'Create tickets on behalf of others: see every service and send a request for another person, who becomes the requester.', 'request_new.php,request_newProcess.php', 'request_new.php', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'Y', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`gibbonRoleID`, `gibbonActionID`) SELECT gibbonRole.gibbonRoleID, gibbonAction.gibbonActionID FROM gibbonRole JOIN gibbonAction ON (gibbonAction.name='New Request_onBehalf') JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID AND gibbonModule.name='ServiceDesk') WHERE gibbonRole.name IN ('Administrator', 'Support Staff');end
ALTER TABLE `gibbonServiceDeskTeam` ADD `gibbonPersonIDDefaultAssignee` int(10) unsigned zerofill NULL AFTER `active`;
";

// v1.2.00 - comment permissions (Edit Comments_my/_all, Change Comment Visibility_my/_all) and the "edited" time of a comment
++$count;
$sql[$count][0] = '1.2.00';
$sql[$count][1] = "
ALTER TABLE `gibbonServiceDeskTicketComment` ADD `timestampEdited` datetime NULL AFTER `timestamp`;end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='ServiceDesk'), 'Edit Comments_my', 0, 'Requests', 'Change the text of own comments on tickets, and delete own comments.', 'tickets_comment_edit.php,tickets_comment_editProcess.php,tickets_comment_delete.php,tickets_comment_deleteProcess.php', 'tickets_comment_edit.php', 'Y', 'N', 'N', 'Y', 'N', 'N', 'Y', 'Y', 'Y', 'Y', 'Y');end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='ServiceDesk'), 'Edit Comments_all', 1, 'Requests', 'Change the text of any comment on tickets, and delete any comment.', 'tickets_comment_edit.php,tickets_comment_editProcess.php,tickets_comment_delete.php,tickets_comment_deleteProcess.php', 'tickets_comment_edit.php', 'Y', 'N', 'Y', 'N', 'N', 'N', 'N', 'Y', 'Y', 'Y', 'Y');end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='ServiceDesk'), 'Change Comment Visibility_my', 0, 'Requests', 'Switch own comments between visible to the requester and internal. Only on tickets the person works on.', 'tickets_comment_edit.php,tickets_comment_editProcess.php', 'tickets_comment_edit.php', 'Y', 'N', 'N', 'Y', 'N', 'N', 'Y', 'Y', 'Y', 'Y', 'Y');end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='ServiceDesk'), 'Change Comment Visibility_all', 1, 'Requests', 'Switch any comment between visible to the requester and internal. Only on tickets the person works on.', 'tickets_comment_edit.php,tickets_comment_editProcess.php', 'tickets_comment_edit.php', 'Y', 'N', 'Y', 'N', 'N', 'N', 'N', 'Y', 'Y', 'Y', 'Y');end
INSERT INTO `gibbonPermission` (`gibbonRoleID`, `gibbonActionID`) SELECT gibbonRole.gibbonRoleID, gibbonAction.gibbonActionID FROM gibbonRole JOIN gibbonAction ON (gibbonAction.name IN ('Edit Comments_all', 'Change Comment Visibility_all')) JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID AND gibbonModule.name='ServiceDesk') WHERE gibbonRole.name IN ('Administrator');end
INSERT INTO `gibbonPermission` (`gibbonRoleID`, `gibbonActionID`) SELECT gibbonRole.gibbonRoleID, gibbonAction.gibbonActionID FROM gibbonRole JOIN gibbonAction ON (gibbonAction.name IN ('Edit Comments_my', 'Change Comment Visibility_my')) JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID AND gibbonModule.name='ServiceDesk') WHERE gibbonRole.name IN ('Teacher', 'Support Staff');
";

// v1.3.00 - board view: colour per ticket type, last used view per person, board process script in the Tickets action
++$count;
$sql[$count][0] = '1.3.00';
$sql[$count][1] = "
ALTER TABLE `gibbonServiceDeskTicketType` ADD `color` varchar(7) NOT NULL DEFAULT '' AFTER `roleCategories`;end
UPDATE `gibbonServiceDeskTicketType` SET color='#f59e0b' WHERE name='Absence Report';end
UPDATE `gibbonServiceDeskTicketType` SET color='#3b82f6' WHERE name='Course Unenrolment';end
UPDATE `gibbonServiceDeskTicketType` SET color='#8b5cf6' WHERE name='School De-registration';end
UPDATE `gibbonServiceDeskTicketType` SET color='#ef4444' WHERE name='Complaint';end
UPDATE `gibbonServiceDeskTicketType` SET color='#10b981' WHERE name='General Request';end
CREATE TABLE `gibbonServiceDeskViewPreference` (`gibbonPersonID` int(10) unsigned zerofill NOT NULL, `ticketView` enum('List','Board') NOT NULL DEFAULT 'List', PRIMARY KEY (`gibbonPersonID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
UPDATE `gibbonAction` SET URLList='tickets.php,tickets_filterProcess.php,tickets_boardProcess.php,tickets_view.php,tickets_viewProcess.php' WHERE name='Tickets' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='ServiceDesk');
";

// v1.4.00 - audience of shared filters: existing ones are for agents (administrators, team members); requesters get Open / Closed
++$count;
$sql[$count][0] = '1.4.00';
$sql[$count][1] = "
ALTER TABLE `gibbonServiceDeskFilter` ADD `audience` enum('Agents','Requesters') NOT NULL DEFAULT 'Agents' AFTER `gibbonServiceDeskTeamID`;end
INSERT INTO `gibbonServiceDeskFilter` (`gibbonPersonID`, `gibbonServiceDeskTeamID`, `audience`, `name`, `criteria`, `sequenceNumber`) VALUES (NULL, NULL, 'Requesters', 'Open', '{\\\"status\\\":[\\\"Open\\\",\\\"In Progress\\\"]}', 1), (NULL, NULL, 'Requesters', 'Closed', '{\\\"status\\\":[\\\"Closed\\\"]}', 2);
";

// v1.4.01 - confirmation page for deleting a saved filter
++$count;
$sql[$count][0] = '1.4.01';
$sql[$count][1] = "
UPDATE `gibbonAction` SET URLList='tickets.php,tickets_filterProcess.php,tickets_filter_delete.php,tickets_boardProcess.php,tickets_view.php,tickets_viewProcess.php' WHERE name='Tickets' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='ServiceDesk');
";

// v1.5.00 - family sharing, withdrawn stage, teacher notices, retention, search text, dashboard hooks, action "Delete Tickets"
++$count;
$sql[$count][0] = '1.5.00';
$serviceDeskHooks = [];
foreach (['Parental Dashboard' => 'hook_dashboard_requests.php', 'Staff Dashboard' => 'hook_dashboard_staff.php'] as $serviceDeskHookType => $serviceDeskHookFile) {
    $serviceDeskHooks[] = "INSERT INTO `gibbonHook` (`name`, `type`, `options`, `gibbonModuleID`) VALUES ('Service Desk', '".$serviceDeskHookType."', '".serialize(['sourceModuleName' => 'ServiceDesk', 'sourceModuleAction' => 'Tickets', 'sourceModuleInclude' => $serviceDeskHookFile])."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='ServiceDesk'))";
}
$sql[$count][1] = "
ALTER TABLE `gibbonServiceDeskTicketType` ADD `shareWithFamily` enum('Y','N') NOT NULL DEFAULT 'N' AFTER `roleCategories`, ADD `retentionDays` int(5) unsigned NULL AFTER `color`;end
ALTER TABLE `gibbonServiceDeskStage` ADD `notifyTeachers` enum('Y','N') NOT NULL DEFAULT 'N' AFTER `nextStage`, ADD `withdrawn` enum('Y','N') NOT NULL DEFAULT 'N' AFTER `notifyTeachers`;end
ALTER TABLE `gibbonServiceDeskTicket` ADD `searchText` text NULL AFTER `fields`;end
UPDATE `gibbonServiceDeskTicketType` SET shareWithFamily='Y' WHERE name IN ('Absence Report', 'Course Unenrolment', 'School De-registration');end
UPDATE `gibbonServiceDeskStage` SET withdrawn='Y' WHERE name='Withdrawn';end
INSERT INTO `gibbonServiceDeskStage` (`gibbonServiceDeskTicketTypeID`, `name`, `category`, `sequenceNumber`, `withdrawn`) SELECT t.gibbonServiceDeskTicketTypeID, 'Withdrawn', 'Closed', 50, 'Y' FROM `gibbonServiceDeskTicketType` t WHERE NOT EXISTS (SELECT 1 FROM `gibbonServiceDeskStage` s WHERE s.gibbonServiceDeskTicketTypeID=t.gibbonServiceDeskTicketTypeID AND s.withdrawn='Y');end
INSERT INTO `gibbonServiceDeskStage` (`gibbonServiceDeskTicketTypeID`, `name`, `category`, `sequenceNumber`, `withdrawn`) SELECT NULL, 'Withdrawn', 'Closed', 50, 'Y' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `gibbonServiceDeskStage` s WHERE s.gibbonServiceDeskTicketTypeID IS NULL AND s.withdrawn='Y');end
INSERT INTO `gibbonServiceDeskStage` (`gibbonServiceDeskTicketTypeID`, `name`, `category`, `sequenceNumber`, `notifyTeachers`) SELECT t.gibbonServiceDeskTicketTypeID, 'Approved', 'In Progress', 25, 'Y' FROM `gibbonServiceDeskTicketType` t WHERE t.name='Course Unenrolment' AND NOT EXISTS (SELECT 1 FROM `gibbonServiceDeskStage` s WHERE s.gibbonServiceDeskTicketTypeID=t.gibbonServiceDeskTicketTypeID AND s.name='Approved');end
UPDATE `gibbonServiceDeskStage` SET notifyTeachers='Y' WHERE name='Approved' AND gibbonServiceDeskTicketTypeID IN (SELECT gibbonServiceDeskTicketTypeID FROM `gibbonServiceDeskTicketType` WHERE name IN ('Absence Report', 'Course Unenrolment'));end
UPDATE `gibbonAction` SET URLList='tickets.php,tickets_filterProcess.php,tickets_filter_delete.php,tickets_boardProcess.php,tickets_view.php,tickets_viewProcess.php,tickets_requester.php,tickets_requesterProcess.php' WHERE name='Tickets' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='ServiceDesk');end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='ServiceDesk'), 'Delete Tickets', 0, 'Requests', 'Permanently delete a ticket, for example for a data-protection request. Tickets can also be deleted automatically after the retention time of their ticket type.', 'tickets_delete.php,tickets_deleteProcess.php', 'tickets_delete.php', 'Y', 'N', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`gibbonRoleID`, `gibbonActionID`) SELECT gibbonRole.gibbonRoleID, gibbonAction.gibbonActionID FROM gibbonRole JOIN gibbonAction ON (gibbonAction.name='Delete Tickets') JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID AND gibbonModule.name='ServiceDesk') WHERE gibbonRole.name='Administrator';end
".implode(";end\n", $serviceDeskHooks).";
";

// v1.6.00 - several automation scripts per stage; "tell the teachers" becomes the script TellTeachers; ticket type
//           switches for withdraw / reopen by requesters
++$count;
$sql[$count][0] = '1.6.00';
$sql[$count][1] = "
ALTER TABLE `gibbonServiceDeskStage` MODIFY `automation` varchar(255) NULL;end
UPDATE `gibbonServiceDeskStage` SET automation=CONCAT_WS(',', NULLIF(automation, ''), 'TellTeachers') WHERE notifyTeachers='Y';end
ALTER TABLE `gibbonServiceDeskStage` DROP `notifyTeachers`;end
ALTER TABLE `gibbonServiceDeskTicketType` ADD `allowWithdraw` enum('Y','N') NOT NULL DEFAULT 'Y' AFTER `shareWithFamily`, ADD `allowReopen` enum('Y','N') NOT NULL DEFAULT 'Y' AFTER `allowWithdraw`;
";
