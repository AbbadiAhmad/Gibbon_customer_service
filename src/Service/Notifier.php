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
use Gibbon\Domain\System\LogGateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;
use Gibbon\Module\ServiceDesk\Domain\SubscriberGateway;
use Gibbon\Module\ServiceDesk\Domain\GuardianGateway;

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
    protected $notificationGateway;
    protected $subscriberGateway;
    protected $teamGateway;
    protected $guardianGateway;

    public function __construct(Session $session, Connection $db, NotificationGateway $notificationGateway, SubscriberGateway $subscriberGateway, TeamGateway $teamGateway, GuardianGateway $guardianGateway)
    {
        $this->session = $session;
        $this->db = $db;
        $this->notificationGateway = $notificationGateway;
        $this->subscriberGateway = $subscriberGateway;
        $this->teamGateway = $teamGateway;
        $this->guardianGateway = $guardianGateway;
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
                // A ticket type whose teams have no members yet would reach nobody: tell the administrators instead.
                $audience = $team;
                if (empty($audience)) {
                    foreach ($this->teamGateway->selectAdministrators()->fetchAll() as $admin) {
                        $audience[intval($admin['gibbonPersonID'])] = true;
                    }
                    $team = $audience;
                }

                foreach (array_keys($audience) as $gibbonPersonID) {
                    if (!isset($people[$gibbonPersonID])) {
                        $people[$gibbonPersonID] = $this->subscriberGateway->getPreference($gibbonPersonID);
                    }
                }
            }

            // The other adults of the requester's family follow public updates of tickets shared with the family, with
            // their own notification settings, unless they chose something else for this ticket (a row in $recipients).
            if (!$internal && ($ticket['shareWithFamily'] ?? 'N') == 'Y') {
                foreach ($this->guardianGateway->selectCoAdultIDs($ticket['gibbonPersonIDRequester']) as $gibbonPersonID) {
                    if (!isset($people[intval($gibbonPersonID)])) {
                        $people[intval($gibbonPersonID)] = $this->subscriberGateway->getPreference($gibbonPersonID);
                    }
                }
            }

            if ($internal) {
                $people = array_intersect_key($people, $team + [intval($ticket['gibbonPersonIDAssignee'] ?? 0) => true]);
            }
            unset($people[intval($actorID)], $people[0]);

            $text = '#'.intval($gibbonServiceDeskTicketID).' '.$ticket['subject'].': '.$text;
            $link = '/index.php?q=/modules/ServiceDesk/tickets_view.php&gibbonServiceDeskTicketID='.$gibbonServiceDeskTicketID;

            // Notification centre first, for everyone: a slow or broken mail server must never hold it back.
            foreach ($people as $gibbonPersonID => $policy) {
                if ($policy['notifyInApp'] == 'Y') {
                    $this->sendInApp($gibbonPersonID, $text, $link);
                }
            }
            foreach ($people as $gibbonPersonID => $policy) {
                if ($policy['notifyEmail'] == 'Y') {
                    $this->sendEmail($gibbonPersonID, $text, $link);
                }
            }
        } catch (\Throwable $e) {
            $this->logFailure('Notification failed', $e);
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

    /**
     * Tells people through the notification centre only - no email, whatever their settings. Used for notices the
     * recipient did not ask for (teachers who are told about an approved absence). The text must start with
     * "#<ticket number> " so that the notice is removed together with the ticket.
     */
    public function notifyCentre(array $gibbonPersonIDs, string $text, string $link = '/index.php?q=notifications.php')
    {
        try {
            foreach (array_unique(array_map('intval', $gibbonPersonIDs)) as $gibbonPersonID) {
                if ($gibbonPersonID > 0) {
                    $this->sendInApp($gibbonPersonID, $text, $link);
                }
            }
        } catch (\Throwable $e) {
            $this->logFailure('Notification failed', $e);
        }
    }

    /**
     * Tries both channels for one person, whatever their policy, and says what happened. Used by "Send me a test
     * notification" so that a school can check its own installation (module name, mail settings) in one click.
     *
     * @return array ['inApp' => [ok, message], 'email' => [ok, message]]
     */
    public function sendTest($gibbonPersonID): array
    {
        $text = __m('Service Desk test notification ({time}).', ['time' => date('H:i:s')]);
        $link = '/index.php?q=/modules/ServiceDesk/notifications.php';
        $result = [];

        try {
            $this->sendInApp($gibbonPersonID, $text, $link);
            $found = $this->db->selectOne(
                "SELECT COUNT(*) FROM gibbonNotification WHERE gibbonPersonID=:gibbonPersonID AND text=:text AND status='New' AND gibbonModuleID IS NOT NULL",
                ['gibbonPersonID' => $gibbonPersonID, 'text' => $text]
            );
            $result['inApp'] = $found > 0
                ? [true, __m('A notification was added to your notification centre (the bell at the top of the page).')]
                : [false, __m('The notification could not be stored. Is the module installed under the name ServiceDesk?')];
        } catch (\Throwable $e) {
            $result['inApp'] = [false, $e->getMessage()];
        }

        $result['email'] = $this->sendEmail($gibbonPersonID, $text, $link);

        return $result;
    }

    /**
     * @return array [sent, message] - the message explains a failure in words an administrator can act on.
     */
    protected function sendEmail($gibbonPersonID, string $text, string $link): array
    {
        $email = $this->db->selectOne("SELECT email FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID AND status='Full' AND NOT email=''", ['gibbonPersonID' => $gibbonPersonID]);
        $from = $this->session->get('organisationEmail') ?: $this->session->get('organisationAdministratorEmail');
        if (empty($email)) {
            return [false, __m('No email was sent: your user account has no email address.')];
        }
        if (empty($from)) {
            return [false, __m('No email was sent: the school has no sender address. Set the organisation email in System Admin > System Settings.')];
        }

        try {
            // The mailer is created only when an email is really sent, so mail settings cannot affect anything else.
            global $container;
            $mail = $container->get(Mailer::class);
            $mail->SetFrom($from, $this->session->get('organisationName'));
            $mail->AddAddress($email);
            $mail->Subject = '['.$this->session->get('organisationNameShort').'] '.mb_substr($text, 0, 120);
            $mail->renderBody('mail/notification.twig.html', [
                'title'  => __m('Service Desk'),
                'body'   => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
                'button' => ['url' => ltrim($link, '/'), 'text' => __('View Details')],
            ]);
            if ($mail->Send()) {
                return [true, __m('An email was sent to {email}.', ['email' => $email])];
            }

            // The mailer reports failures in ErrorInfo instead of throwing.
            $this->logFailure('Email failed', new \RuntimeException((string) $mail->ErrorInfo));
            return [false, __m('The email to {email} could not be sent: {error}', ['email' => $email, 'error' => (string) $mail->ErrorInfo])];
        } catch (\Throwable $e) {
            $this->logFailure('Email failed', $e);
            return [false, __m('The email to {email} could not be sent: {error}', ['email' => $email, 'error' => $e->getMessage()])];
        }
    }

    /**
     * Failures never break the user's request, but they must be findable: System Admin > System Check / View Logs
     * (gibbonLog, title "Service Desk"), and the PHP error log.
     */
    protected function logFailure(string $what, \Throwable $e)
    {
        error_log('ServiceDesk: '.$what.': '.$e->getMessage());

        try {
            global $container;
            $container->get(LogGateway::class)->addLog(
                $this->session->get('gibbonSchoolYearID'),
                'ServiceDesk',
                $this->session->get('gibbonPersonID'),
                'Service Desk',
                ['problem' => $what, 'message' => $e->getMessage()]
            );
        } catch (\Throwable $ignored) {
        }
    }
}
