<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Service;

use Gibbon\Comms\NotificationSender;
use Gibbon\Module\CustomerService\Domain\BoardGateway;
use Gibbon\Module\CustomerService\Domain\SpaceGateway;
use Gibbon\Module\CustomerService\Domain\TeamGateway;
use Gibbon\Module\CustomerService\Domain\TicketGateway;

/**
 * Sends Gibbon notifications (in-app, plus e-mail for users who enabled notification e-mails)
 * about ticket events. The person who caused the event is never notified, and a failure to
 * notify never breaks the request that triggered it.
 */
class Notifier
{
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    /** New request: the assignee, else the team, else everybody who can work on the boards that show it. */
    public function ticketCreated(array $ticket, $actorID): void
    {
        $this->send(
            $this->staffRecipients($ticket),
            $actorID, $ticket,
            sprintf(__('New request: %1$s'), $ticket['title'])
        );
    }

    public function statusChanged(array $ticket, string $newStatus, $actorID): void
    {
        $text = sprintf(__('Status changed to %1$s'), $newStatus);
        $this->send([$ticket['gibbonPersonIDReporter'], $ticket['gibbonPersonIDAssignee']], $actorID, $ticket, $text);
    }

    public function assigned(array $ticket, $newAssigneeID, $actorID): void
    {
        if (!empty($newAssigneeID)) {
            $this->send([$newAssigneeID], $actorID, $ticket, sprintf(__('Assigned to you: %1$s'), $ticket['title']));
        }
    }

    /** A public comment: staff comment notifies the requester (+assignee), a requester comment notifies the staff side. */
    public function commented(array $ticket, $actorID, string $body): void
    {
        $excerpt = mb_strlen($body) > 140 ? mb_substr($body, 0, 140).'...' : $body;
        $text = sprintf(__('New comment: %1$s'), $excerpt);

        if ((int)$actorID === (int)$ticket['gibbonPersonIDReporter']) {
            $this->send($this->staffRecipients($ticket), $actorID, $ticket, $text);
        } else {
            $this->send([$ticket['gibbonPersonIDReporter'], $ticket['gibbonPersonIDAssignee']], $actorID, $ticket, $text);
        }
    }

    public function reopened(array $ticket, $actorID): void
    {
        $this->send($this->staffRecipients($ticket), $actorID, $ticket, sprintf(__('Reopened by the requester: %1$s'), $ticket['title']));
    }

    /** @return int[] */
    public function staffRecipients(array $ticket): array
    {
        if (!empty($ticket['gibbonPersonIDAssignee'])) {
            return [(int)$ticket['gibbonPersonIDAssignee']];
        }
        if (!empty($ticket['csTeamID'])) {
            $team = array_keys($this->container->get(TeamGateway::class)->selectMembers($ticket['csTeamID']));
            if (!empty($team)) {
                return $team;
            }
        }

        $tickets = $this->container->get(TicketGateway::class);
        $spaceIDs = [];
        foreach ($this->container->get(BoardGateway::class)->selectAllActive() as $board) {
            if (!empty($tickets->selectByFilter($board['filter'], $ticket['csTicketID'], 1))) {
                $spaceIDs[$board['csSpaceID']] = $board['csSpaceID'];
            }
        }

        return $this->container->get(SpaceGateway::class)->selectPeopleWithPermission(array_values($spaceIDs), Permissions::TRANSITION);
    }

    protected function send(array $recipients, $actorID, array $ticket, string $text): void
    {
        try {
            $recipients = array_unique(array_filter(array_map('intval', $recipients)));
            $recipients = array_diff($recipients, [(int)$actorID]);
            if (empty($recipients)) {
                return;
            }

            $sender = $this->container->get(NotificationSender::class);
            $link = '/index.php?q=/modules/CustomerService/cs_ticket_view.php&csTicketID='.(int)$ticket['csTicketID'];
            $message = csTicketRef($ticket['csTicketID']).' '.$ticket['title'].' - '.$text;

            foreach ($recipients as $person) {
                $sender->addNotification($person, $message, __('Customer Service'), $link);
            }
            $sender->sendNotifications();
        } catch (\Throwable $e) {
            error_log('CustomerService notification failed: '.$e->getMessage());
        }
    }
}
