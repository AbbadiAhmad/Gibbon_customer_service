<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

namespace Gibbon\Module\ServiceDesk\Service;

use Gibbon\Services\Format;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Domain\System\LogGateway;
use Gibbon\Module\ServiceDesk\Domain\StageGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Domain\CommentGateway;
use Gibbon\Module\ServiceDesk\Domain\SubscriberGateway;
use Gibbon\Module\ServiceDesk\Automation\AutomationContext;
use Gibbon\Module\ServiceDesk\Automation\AutomationRegistry;

/**
 * Everything that changes a ticket goes through here, so the history, the notifications and the workflow automation
 * always happen the same way. Callers (process scripts) check permissions first.
 */
class TicketService
{
    /**
     * A stage with automation can move the ticket to a next stage, which can have automation too: stop runaway chains.
     */
    public const MAX_AUTOMATION_CHAIN = 5;

    public const PRIORITIES = ['Low', 'Normal', 'High', 'Urgent'];

    protected $db;
    protected $ticketGateway;
    protected $stageGateway;
    protected $commentGateway;
    protected $subscriberGateway;
    protected $notifier;
    protected $automations;
    protected $teamGateway;
    protected $ticketTypeGateway;

    public function __construct(Connection $db, TicketGateway $ticketGateway, StageGateway $stageGateway, CommentGateway $commentGateway, SubscriberGateway $subscriberGateway, Notifier $notifier, AutomationRegistry $automations, TeamGateway $teamGateway, TicketTypeGateway $ticketTypeGateway)
    {
        $this->db = $db;
        $this->ticketGateway = $ticketGateway;
        $this->stageGateway = $stageGateway;
        $this->commentGateway = $commentGateway;
        $this->subscriberGateway = $subscriberGateway;
        $this->notifier = $notifier;
        $this->automations = $automations;
        $this->teamGateway = $teamGateway;
        $this->ticketTypeGateway = $ticketTypeGateway;
    }

    /**
     * Creates a ticket in the first stage of its type's workflow. The requester's notification policy is copied to
     * the ticket and the teams of the type are told.
     *
     * @param mixed $actorID The person sending the request when it is not the requester (a request on behalf of
     *                       someone): the history then says who created it, and the requester is told.
     * @return int|false The new ticket id.
     */
    public function create(array $type, $gibbonPersonIDRequester, $gibbonSchoolYearID, string $subject, array $answers, $actorID = null)
    {
        $actorID = !empty($actorID) ? $actorID : $gibbonPersonIDRequester;

        $stage = $this->stageGateway->getFirstStage($type['gibbonServiceDeskTicketTypeID']);
        if (empty($stage)) return false;

        // A team can name a default assignee: the ticket then starts assigned to that person.
        $assignee = $this->teamGateway->getDefaultAssigneeByTicketType($type['gibbonServiceDeskTicketTypeID']);

        $now = date('Y-m-d H:i:s');
        $gibbonServiceDeskTicketID = $this->ticketGateway->insert([
            'gibbonSchoolYearID'            => $gibbonSchoolYearID,
            'gibbonServiceDeskTicketTypeID' => $type['gibbonServiceDeskTicketTypeID'],
            'gibbonServiceDeskStageID'      => $stage['gibbonServiceDeskStageID'],
            'gibbonPersonIDRequester'       => $gibbonPersonIDRequester,
            'gibbonPersonIDAssignee'        => $assignee['gibbonPersonID'] ?? null,
            'subject'                       => mb_substr($subject, 0, 120),
            'fields'                        => json_encode($answers, JSON_UNESCAPED_UNICODE),
            'searchText'                    => RequestFields::searchText($answers),
            'timestampCreated'              => $now,
            'timestampModified'             => $now,
            'timestampClosed'               => $stage['category'] == 'Closed' ? $now : null,
        ]);
        if (empty($gibbonServiceDeskTicketID)) return false;

        $this->subscriberGateway->subscribe($gibbonServiceDeskTicketID, $gibbonPersonIDRequester);
        if (!empty($assignee)) {
            $this->subscriberGateway->subscribe($gibbonServiceDeskTicketID, $assignee['gibbonPersonID']);
        }

        $ticket = $this->ticketGateway->getTicketByID($gibbonServiceDeskTicketID);
        $requesterName = Format::name('', $ticket['requesterPreferredName'], $ticket['requesterSurname'], 'Student', false);
        $text = __m('New {type} request from {name}.', ['type' => $ticket['typeName'], 'name' => $requesterName]);

        // Sent by someone else: say so on the ticket. The requester is told too (only the actor is left out).
        if (intval($actorID) !== intval($gibbonPersonIDRequester)) {
            $actor = $this->db->select("SELECT preferredName, surname FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID", ['gibbonPersonID' => $actorID])->fetch();
            $onBehalf = __m('Created by {creator} on behalf of {name}.', [
                'creator' => Format::name('', $actor['preferredName'] ?? '', $actor['surname'] ?? '', 'Student', false),
                'name'    => $requesterName,
            ]);
            $this->writeHistory($ticket, $actorID, $onBehalf, 'Log');
            $text .= ' '.$onBehalf;
        }

        if (!empty($assignee)) {
            $this->writeHistory($ticket, null, __m('Assigned to {name}.', ['name' => Format::name('', $assignee['preferredName'], $assignee['surname'], 'Student', false)]), 'Log');
        }

        $this->notifier->notify($ticket, $text, $actorID, false, true);

        // The first stage may have automation as well.
        if (!empty($stage['automation'])) {
            $this->changeStage($ticket, $stage, null, true);
        }

        return $gibbonServiceDeskTicketID;
    }

    /**
     * Adds a comment (type "Comment") or an internal note (type "Internal Note") and tells the followers.
     */
    public function addComment(array $ticket, $gibbonPersonID, string $comment, string $type = 'Comment')
    {
        $internal = $type == 'Internal Note';
        $this->writeHistory($ticket, $gibbonPersonID, $comment, $internal ? 'Internal Note' : 'Comment');
        $this->subscriberGateway->subscribe($ticket['gibbonServiceDeskTicketID'], $gibbonPersonID);

        $this->notifier->notify(
            $ticket,
            ($internal ? __m('New internal note') : __m('New comment')).': '.mb_substr($comment, 0, 200),
            $gibbonPersonID,
            $internal,
            empty($ticket['gibbonPersonIDAssignee'])
        );
    }

    /**
     * Assigns the ticket (or removes the assignee when empty). The new assignee starts following the ticket.
     */
    public function assign(array $ticket, $gibbonPersonIDAssignee, $actorID)
    {
        $gibbonPersonIDAssignee = !empty($gibbonPersonIDAssignee) ? $gibbonPersonIDAssignee : null;
        if (intval($ticket['gibbonPersonIDAssignee']) === intval($gibbonPersonIDAssignee)) return;

        $this->ticketGateway->update($ticket['gibbonServiceDeskTicketID'], ['gibbonPersonIDAssignee' => $gibbonPersonIDAssignee, 'timestampModified' => date('Y-m-d H:i:s')]);
        $ticket['gibbonPersonIDAssignee'] = $gibbonPersonIDAssignee;

        if (empty($gibbonPersonIDAssignee)) {
            $text = __m('The ticket is no longer assigned.');
        } else {
            $name = $this->db->select("SELECT preferredName, surname FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID", ['gibbonPersonID' => $gibbonPersonIDAssignee])->fetch();
            $text = __m('Assigned to {name}.', ['name' => Format::name('', $name['preferredName'] ?? '', $name['surname'] ?? '', 'Student', false)]);
            $this->subscriberGateway->subscribe($ticket['gibbonServiceDeskTicketID'], $gibbonPersonIDAssignee);
        }

        $this->writeHistory($ticket, $actorID, $text, 'Log');
        $this->notifier->notify($ticket, $text, $actorID);
    }

    public function setPriority(array $ticket, string $priority, $actorID)
    {
        if (!in_array($priority, self::PRIORITIES, true) || $priority == $ticket['priority']) return;

        $this->ticketGateway->update($ticket['gibbonServiceDeskTicketID'], ['priority' => $priority, 'timestampModified' => date('Y-m-d H:i:s')]);
        $this->writeHistory($ticket, $actorID, __m('Priority set to {priority}.', ['priority' => __m($priority)]), 'Internal Log');
    }

    /**
     * Moves a ticket into a stage and runs the stage's automation, if it has one (see AutomationScript for the
     * contract). Returns false when the automation failed: the ticket then stays in the stage it was in.
     *
     * @param array $stage  A stage of the ticket's own type.
     * @param bool  $rerun  Run the automation even though the ticket is already in the stage (used on creation).
     */
    public function changeStage(array $ticket, array $stage, $actorID, bool $rerun = false, int $depth = 0, ?string $text = null, bool $withTeam = false): bool
    {
        $gibbonServiceDeskTicketID = $ticket['gibbonServiceDeskTicketID'];
        $alreadyThere = intval($ticket['gibbonServiceDeskStageID']) === intval($stage['gibbonServiceDeskStageID']);
        if ($alreadyThere && !$rerun) return true;

        // The stage's automation scripts, in the order they are listed. Scripts that no longer exist are skipped.
        $scripts = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($stage['automation'] ?? '')))) as $key) {
            if ($script = $this->automations->get($key)) {
                $scripts[] = $script;
            }
        }
        $results = [];
        $running = null;
        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $this->ticketGateway->update($gibbonServiceDeskTicketID, [
                'gibbonServiceDeskStageID' => $stage['gibbonServiceDeskStageID'],
                'timestampModified'        => $now,
                'timestampClosed'          => $stage['category'] == 'Closed' ? ($ticket['timestampClosed'] ?? null) ?: $now : null,
            ]);

            // All scripts share the transaction: if one fails, what the others did is undone as well.
            foreach ($scripts as $running) {
                $result = $running->run(new AutomationContext($ticket, $stage, $actorID, $this->db));
                if (!$result->isSuccess()) {
                    throw new \RuntimeException($result->getMessage());
                }
                $results[] = $result;
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->writeHistory($ticket, null, __m('Automation "{automation}" failed, the ticket was not moved to {stage}: {reason}', [
                'automation' => $running !== null ? $running->getName() : $stage['automation'],
                'stage'      => __m($stage['name']),
                'reason'     => $e->getMessage(),
            ]), 'Internal Log');

            return false;
        }

        if (!$alreadyThere) {
            $text = $text ?? __m('Moved to {stage}.', ['stage' => __m($stage['name'])]);
            $this->writeHistory($ticket, $actorID, $text, 'Log');
            $this->notifier->notify($ticket, $text, $actorID, false, $withTeam);
        }

        $ticket = $this->ticketGateway->getTicketByID($gibbonServiceDeskTicketID);

        foreach ($results as $result) {
            if ($result->getMessage() !== '') {
                $this->writeHistory($ticket, null, $result->getMessage(), $result->isInternal() ? 'Internal Log' : 'Log');
            }
        }

        // After a successful automation, move on to the configured next stage.
        if (!empty($scripts) && !empty($stage['nextStage']) && $depth < self::MAX_AUTOMATION_CHAIN) {
            $next = $this->stageGateway->getStageByName($ticket['gibbonServiceDeskTicketTypeID'], $stage['nextStage']);
            if (!empty($next) && intval($next['gibbonServiceDeskStageID']) !== intval($stage['gibbonServiceDeskStageID'])) {
                $this->changeStage($ticket, $next, null, false, $depth + 1);
            }
        }

        return true;
    }

    protected function writeHistory(array $ticket, $gibbonPersonID, string $text, string $type)
    {
        $now = date('Y-m-d H:i:s');
        $this->commentGateway->insert([
            'gibbonServiceDeskTicketID' => $ticket['gibbonServiceDeskTicketID'],
            'gibbonPersonID'            => $gibbonPersonID ?: null,
            'type'                      => $type,
            'comment'                   => $text,
            'timestamp'                 => $now,
        ]);
        $this->ticketGateway->update($ticket['gibbonServiceDeskTicketID'], ['timestampModified' => $now]);
    }

    /**
     * Changes the text of a comment and marks it as edited. Nobody is notified.
     */
    public function updateComment(array $ticket, array $comment, string $text, $actorID)
    {
        if ($text === $comment['comment']) return;

        $this->commentGateway->update($comment['gibbonServiceDeskTicketCommentID'], ['comment' => $text, 'timestampEdited' => date('Y-m-d H:i:s')]);

        // Editing someone else's comment leaves a trace for the team.
        if (intval($comment['gibbonPersonID']) !== intval($actorID)) {
            $this->writeHistory($ticket, $actorID, __m('Edited a comment of {date}.', ['date' => Format::dateTimeReadable($comment['timestamp'])]), 'Internal Log');
        }
    }

    /**
     * Switches a comment between "Comment" (the requester sees it) and "Internal Note" (team only). When a note
     * becomes visible, the followers are told as for a new comment.
     */
    public function setCommentVisibility(array $ticket, array $comment, string $type, $actorID)
    {
        if (!in_array($type, ['Comment', 'Internal Note'], true) || $type == $comment['type']) return;

        $this->commentGateway->update($comment['gibbonServiceDeskTicketCommentID'], ['type' => $type]);

        $this->writeHistory($ticket, $actorID, $type == 'Comment'
            ? __m('Made a comment of {date} visible to the requester.', ['date' => Format::dateTimeReadable($comment['timestamp'])])
            : __m('Made a comment of {date} internal.', ['date' => Format::dateTimeReadable($comment['timestamp'])]), 'Internal Log');

        if ($type == 'Comment') {
            $this->notifier->notify($ticket, __m('New comment').': '.mb_substr($comment['comment'], 0, 200), $actorID, false, empty($ticket['gibbonPersonIDAssignee']));
        }
    }

    /**
     * Deletes a comment. The team's history keeps a line saying that a comment was deleted, not what it said.
     */
    public function deleteComment(array $ticket, array $comment, $actorID): bool
    {
        if (!$this->commentGateway->delete($comment['gibbonServiceDeskTicketCommentID'])) return false;

        $this->writeHistory($ticket, $actorID, __m('Deleted a comment of {date}.', ['date' => Format::dateTimeReadable($comment['timestamp'])]), 'Internal Log');

        return true;
    }

    /**
     * The requester (or another adult of the family) withdraws the request: the ticket moves to the workflow's
     * "Withdrawn" stage and the team is told. An optional note is added as a comment.
     */
    public function withdraw(array $ticket, string $note, $actorID): bool
    {
        $stage = $this->stageGateway->getWithdrawnStage($ticket['gibbonServiceDeskTicketTypeID']);
        if (empty($stage)) return false;

        if ($note !== '') {
            $this->writeHistory($ticket, $actorID, $note, 'Comment');
        }

        return $this->changeStage($ticket, $stage, $actorID, false, 0, __m('{name} withdrew the request.', ['name' => $this->personName($actorID)]), true);
    }

    /**
     * The requester reopens a closed request: the ticket goes back to the first stage of its workflow.
     */
    public function reopen(array $ticket, string $note, $actorID): bool
    {
        $stage = $this->stageGateway->getFirstStage($ticket['gibbonServiceDeskTicketTypeID']);
        if (empty($stage)) return false;

        if ($note !== '') {
            $this->writeHistory($ticket, $actorID, $note, 'Comment');
        }

        return $this->changeStage($ticket, $stage, $actorID, false, 0, __m('{name} reopened the request.', ['name' => $this->personName($actorID)]), true);
    }

    /**
     * Moves a ticket to another ticket type (it was filed under the wrong service). The ticket keeps the stage of the
     * same name when the new workflow has one, otherwise it starts in the first stage; the stage's automation is not
     * run. The assignee stays only if they belong to a team of the new type. The requester sees the move; the teams
     * of the new type are told.
     */
    public function changeType(array $ticket, array $type, $actorID): bool
    {
        if (empty($type) || $type['active'] != 'Y' || intval($type['gibbonServiceDeskTicketTypeID']) === intval($ticket['gibbonServiceDeskTicketTypeID'])) {
            return false;
        }

        $stage = $this->stageGateway->getStageByName($type['gibbonServiceDeskTicketTypeID'], $ticket['stageName']) ?: $this->stageGateway->getFirstStage($type['gibbonServiceDeskTicketTypeID']);
        if (empty($stage)) return false;

        $members = array_map('intval', array_column($this->teamGateway->selectMembersByTicketType($type['gibbonServiceDeskTicketTypeID'])->fetchAll(), 'gibbonPersonID'));
        $keepAssignee = !empty($ticket['gibbonPersonIDAssignee']) && in_array(intval($ticket['gibbonPersonIDAssignee']), $members, true);

        $now = date('Y-m-d H:i:s');
        $this->ticketGateway->update($ticket['gibbonServiceDeskTicketID'], [
            'gibbonServiceDeskTicketTypeID' => $type['gibbonServiceDeskTicketTypeID'],
            'gibbonServiceDeskStageID'      => $stage['gibbonServiceDeskStageID'],
            'gibbonPersonIDAssignee'        => $keepAssignee ? $ticket['gibbonPersonIDAssignee'] : null,
            'timestampModified'             => $now,
            'timestampClosed'               => $stage['category'] == 'Closed' ? ($ticket['timestampClosed'] ?: $now) : null,
        ]);

        $moved = $this->ticketGateway->getTicketByID($ticket['gibbonServiceDeskTicketID']);
        $text = __m('Moved from {from} to {to}.', ['from' => __m($ticket['typeName']), 'to' => __m($type['name'])]);
        $this->writeHistory($moved, $actorID, $text, 'Log');

        if (!empty($ticket['gibbonPersonIDAssignee']) && !$keepAssignee) {
            $this->writeHistory($moved, $actorID, __m('The assignee is not in a team of the new ticket type: the ticket is unassigned.'), 'Internal Log');
        }

        $this->notifier->notify($moved, $text, $actorID, false, true);

        return true;
    }

    /**
     * Permanently deletes a ticket with its comments, notification settings and the notifications written about it.
     * What the workflow changed elsewhere in Gibbon (attendance, enrolment) is not undone. A line in Gibbon's log
     * records that, when and by whom - not what the ticket said.
     *
     * @param mixed $actorID The person who deleted it; empty for the automatic retention script.
     */
    public function deleteTicket(array $ticket, $actorID = null): bool
    {
        $data = ['gibbonServiceDeskTicketID' => $ticket['gibbonServiceDeskTicketID']];

        $this->db->beginTransaction();
        $this->db->delete("DELETE FROM gibbonServiceDeskTicketComment WHERE gibbonServiceDeskTicketID=:gibbonServiceDeskTicketID", $data);
        $this->db->delete("DELETE FROM gibbonServiceDeskTicketSubscriber WHERE gibbonServiceDeskTicketID=:gibbonServiceDeskTicketID", $data);
        $this->db->delete("DELETE gibbonNotification FROM gibbonNotification JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonNotification.gibbonModuleID) WHERE gibbonModule.name='ServiceDesk' AND gibbonNotification.text LIKE :prefix", ['prefix' => '#'.intval($ticket['gibbonServiceDeskTicketID']).' %']);
        $deleted = $this->ticketGateway->delete($ticket['gibbonServiceDeskTicketID']);

        if (!$deleted) {
            $this->db->rollBack();
            return false;
        }
        $this->db->commit();

        try {
            global $container;
            $gibbonSchoolYearID = $ticket['gibbonSchoolYearID'] ?: $this->db->selectOne("SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current'");
            $container->get(LogGateway::class)->addLog($gibbonSchoolYearID, 'ServiceDesk', $actorID ?: null, 'Service Desk', [
                'event'  => empty($actorID) ? 'Ticket deleted after its retention time' : 'Ticket deleted',
                'ticket' => intval($ticket['gibbonServiceDeskTicketID']),
                'type'   => $ticket['typeName'] ?? '',
            ], empty($actorID) ? '127.0.0.1' : null);
        } catch (\Throwable $e) {
            error_log('ServiceDesk: could not log a deleted ticket: '.$e->getMessage());
        }

        return true;
    }

    protected function personName($gibbonPersonID): string
    {
        $person = $this->db->select("SELECT preferredName, surname FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID", ['gibbonPersonID' => $gibbonPersonID])->fetch();

        return Format::name('', $person['preferredName'] ?? '', $person['surname'] ?? '', 'Student', false);
    }
}
