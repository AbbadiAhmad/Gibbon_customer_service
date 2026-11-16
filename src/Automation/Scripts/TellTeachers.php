<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

namespace Gibbon\Module\ServiceDesk\Automation\Scripts;

use Gibbon\Module\ServiceDesk\Service\Notifier;
use Gibbon\Module\ServiceDesk\Service\RequestFields;
use Gibbon\Module\ServiceDesk\Service\TeacherFinder;
use Gibbon\Module\ServiceDesk\Automation\AutomationScript;
use Gibbon\Module\ServiceDesk\Automation\AutomationContext;
use Gibbon\Module\ServiceDesk\Automation\AutomationResult;

/**
 * Tells the teachers of the children on the ticket - form tutors and class teachers - that the request is approved.
 * Notification centre only, never email. The notice says what was approved for whom and when, never why: reasons such
 * as "Medical" stay on the ticket. Combine it with another script on the same stage (e.g. Record absence): they run in
 * one transaction, so no teacher is told when the other script fails.
 */
class TellTeachers implements AutomationScript
{
    public function getName(): string
    {
        return __m('Tell the teachers');
    }

    public function getDescription(): string
    {
        return __m('Notifies the form tutors and class teachers of the children (notification centre only, never email; without the reason). Reads the fields of type Students (all class teachers) and Classes (the teachers of those classes).');
    }

    public function run(AutomationContext $context): AutomationResult
    {
        $ticket = $context->ticket();
        $answers = RequestFields::decode($ticket['fields'] ?? '');

        $teachers = $context->container()->get(TeacherFinder::class)->findForAnswers($answers, $ticket['gibbonSchoolYearID']);
        $teachers = array_diff($teachers, [intval($context->actorID())]);

        if (empty($teachers)) {
            return AutomationResult::success(__m('No teachers were found to tell.'), true);
        }

        // The text starts with "#<ticket number> " so that the notices are removed together with the ticket.
        $context->container()->get(Notifier::class)->notifyCentre($teachers, '#'.$context->ticketID().' '.__m('{type} approved: {details}.', [
            'type'    => __m($ticket['typeName'] ?? ''),
            'details' => RequestFields::summary($answers),
        ]));

        return AutomationResult::success(__m('{count} teacher(s) were told (notification centre only).', ['count' => count($teachers)]), true);
    }
}
