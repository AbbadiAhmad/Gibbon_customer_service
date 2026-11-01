---
name: customerservice-automation-actions
description: How to add an automation action or trigger to the administration module (rule = trigger + conditions + actions). Actions are single class files discovered automatically. Use when asked for a new automatic behaviour, rule action, or trigger.
---

# Automation actions and triggers

Rule = **trigger -> conditions -> actions** (`gibbonCsAutomationRule`). Conditions use the board-filter format (`BoardFilter`), so any filter field is available.
Engine: `src/Automation/AutomationEngine.php`; one action = one class in `src/Automation/Actions/`.

## New action = one file, nothing else
```php
namespace Gibbon\Module\administration\Automation\Actions;

use Gibbon\Module\administration\Automation\AutomationContext;
use Gibbon\Module\administration\Automation\BaseAction;

class MyAction extends BaseAction                 // key is derived from the class name: my_action - it is STORED in rules, never rename a shipped class
{
    public function label(): string { return __('Do my thing'); }

    public function params($container): array     // drives the rule editor form AND validation of required values
    {
        return [
            'value' => ['label' => __('Value'), 'type' => 'text', 'required' => true, 'description' => __('Help text')],
            // types: text | textarea | number | select (+ 'options' => [value => label]) | person | team
        ];
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        // read $params defensively (strings, may be empty); change things ONLY through $ctx:
        $ctx->update(['priority' => 'High'], __('priority set to High'));   // column changes + internal activity line
        // $ctx->changeStatus($s)  $ctx->assign($personID, $name)  $ctx->comment($body, $internal)
        // $ctx->notify([$personIDs], $text)  $ctx->log($line)  $ctx->fire($trigger, $data)  $ctx->ticket()
    }
}
```
Rules:
* Put it in the Actions folder - `ActionRegistry` discovers it (glob). No registration, no form code; `ActionForm` builds the inputs from `params()`.
* Be idempotent when possible (skip if the ticket already has the value) - rules can fire repeatedly (`MAX_RUNS` = 3 per rule+ticket per request, `MAX_DEPTH` = 3 chained triggers).
* Never throw for bad input: return silently or log; the engine also catches exceptions.
* Use `$ctx` for every change so activity, notifications and follow-up triggers happen; direct gateway writes skip them.
* Automation bypasses the per-type workflow rules by design.
* `describe()` of `BaseAction` summarises params for the rule list; override it for a nicer text. Removing an action class later only makes existing rules skip it ("unavailable").
* Test stand-alone: autoload `src/`, stub `__()`, call `ActionRegistry::all()` (discovery) and `ActionForm::collect($post, $fakeContainer)`.

## New trigger
1. Add `'my_trigger' => __('Label shown in the rule editor')` to `AutomationEngine::triggers()`.
2. Call `(new AutomationEngine($container))->fire('my_trigger', $csTicketID, $data)` where the event happens - after the change is saved and after notifications; reload the ticket afterwards if the process script keeps using it (`$tickets->getTicket()`).
3. Time-based triggers are fired by a cron script (`cli_reminders.php` shows the pattern: mark the ticket first so a failure cannot repeat it daily).
4. Document the trigger in the README automation list.
