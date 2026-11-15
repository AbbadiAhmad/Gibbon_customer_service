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

use Gibbon\Contracts\Comms\Mailer;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;
use Gibbon\Module\ServiceDesk\Domain\SubscriberGateway;

/**
 * Tells people about ticket updates, each through the channels of their notification policy for that ticket:
 * Gibbon's notification centre and/or email.
 *
 * Gibbon's own NotificationSender is not used because it ties email to one global user preference; here the two
 * channels are chosen per person and per ticket. Nothing in here may break the user's request: failures are logged.
 */
class Notifier
{
    protected $session;
    protected $db;
    protected $mailer;
    protected $notificationGateway;
    protected $subscriberGateway;
    protected $teamGateway;

    public function __construct(Session $session, Connection $db, Mailer $mailer, NotificationGateway $notificationGateway, SubscriberGateway $subscriberGateway, TeamGateway $teamGateway)
    {
        $this->session = $session;
        $this->db = $db;
        $this->mailer = $mailer;
        $this->notificationGateway = $notificationGateway;
        $this->subscriberGateway = $subscriberGateway;
        $this->teamGateway = $teamGateway;
    }

    /**
     * @param array  $ticket   Ticket row.
     * @param string $text     What happened, already translated.
     * @param mixed  $actorID  The person who did it; never notified.
     * @param bool   $internal Only for the team: the requester is not told.
     * @param bool   $withTeam Also tell the members of the type's teams (new tickets, tickets nobody is assigned to).
     */
    public function notify(array $ticket, string $text, $actorID, bool $internal = false, bool $withTeam = false)
    {
        try {
            $gibbonServiceDeskTicketID = $ticket['gibbonServiceDeskTicketID'];
            $recipients = $this->subscriberGateway->selectSubscribersByTicket($gibbonServiceDeskTicketID);

            $team = [];
            foreach ($this->teamGateway->selectMembersByTicketType($ticket['gibbonServiceDeskTicketTypeID'])->fetchAll() as $member) {
                $team[intval($member['gibbonPersonID'])] = true;
            }

            $people = [];
            foreach ($recipients as $gibbonPersonID => $policy) {
                $people[intval($gibbonPersonID)] = $policy;
            }

            if ($withTeam) {
                foreach (array_keys($team) as $gibbonPersonID) {
                    if (!isset($people[$gibbonPersonID])) {
                        $people[$gibbonPersonID] = $this->subscriberGateway->getPreference($gibbonPersonID);
                    }
                }
            }

            if ($internal) {
                $people = array_intersect_key($people, $team + [intval($ticket['gibbonPersonIDAssignee'] ?? 0) => true]);
            }
            unset($people[intval($actorID)], $people[0]);

            $text = '#'.intval($gibbonServiceDeskTicketID).' '.$ticket['subject'].': '.$text;
            $link = '/index.php?q=/modules/ServiceDesk/tickets_view.php&gibbonServiceDeskTicketID='.$gibbonServiceDeskTicketID;

            foreach ($people as $gibbonPersonID => $policy) {
                if ($policy['notifyInApp'] == 'Y') {
                    $this->sendInApp($gibbonPersonID, $text, $link);
                }
                if ($policy['notifyEmail'] == 'Y') {
                    $this->sendEmail($gibbonPersonID, $text, $link);
                }
            }
        } catch (\Throwable $e) {
            error_log('ServiceDesk notification failed: '.$e->getMessage());
        }
    }

    protected function sendInApp($gibbonPersonID, string $text, string $link)
    {
        $notification = ['gibbonPersonID' => $gibbonPersonID, 'text' => $text, 'moduleName' => 'ServiceDesk', 'actionLink' => $link];

        $existing = $this->notificationGateway->selectNotificationByStatus($notification, 'New');
        if ($existing && $existing->rowCount() > 0) {
            $row = $existing->fetch();
            $this->notificationGateway->updateNotificationCount($row['gibbonNotificationID'], $row['count'] + 1);
        } else {
            $this->notificationGateway->insertNotification($notification);
        }
    }

    protected function sendEmail($gibbonPersonID, string $text, string $link)
    {
        $email = $this->db->selectOne("SELECT email FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID AND status='Full' AND NOT email=''", ['gibbonPersonID' => $gibbonPersonID]);
        $from = $this->session->get('organisationEmail') ?: $this->session->get('organisationAdministratorEmail');
        if (empty($email) || empty($from)) return;

        try {
            $mail = $this->mailer;
            $mail->clearAllRecipients();
            $mail->SetFrom($from, $this->session->get('organisationName'));
            $mail->AddAddress($email);
            $mail->Subject = '['.$this->session->get('organisationNameShort').'] '.mb_substr($text, 0, 120);
            $mail->renderBody('mail/notification.twig.html', [
                'title'  => __m('Service Desk'),
                'body'   => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
                'button' => ['url' => ltrim($link, '/'), 'text' => __('View Details')],
            ]);
            $mail->Send();
        } catch (\Throwable $e) {
            error_log('ServiceDesk email failed: '.$e->getMessage());
        }
    }
}
