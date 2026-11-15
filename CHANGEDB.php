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
