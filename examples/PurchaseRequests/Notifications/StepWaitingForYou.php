<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Notifications;

use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\PurchaseRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "A purchase request is waiting for you" — sent when a step is reached, and again as a reminder. */
class StepWaitingForYou extends Notification
{
    public function __construct(public PurchaseRequest $request, public string $stepName, public bool $reminder = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(($this->reminder ? 'Reminder: ' : '').'"'.$this->request->title.'" is waiting for you')
            ->line($this->stepName.': a purchase of HKD '.number_format($this->request->amount).' for '.$this->request->department.'.');
    }
}
