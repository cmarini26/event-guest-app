<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RsvpConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Guest $guest,
        public readonly Event $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rsvpUrl = config('app.url') . '/rsvp/' . $this->guest->rsvp_token;

        return match ($this->guest->rsvp_status) {
            'attending' => $this->attendingMail($rsvpUrl),
            'waitlisted' => $this->waitlistedMail($rsvpUrl),
            default => $this->declinedMail(),
        };
    }

    private function attendingMail(string $rsvpUrl): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("You're confirmed for {$this->event->name}!")
            ->greeting("Hi {$this->guest->first_name}!")
            ->line("You're all set — we've got you on the list for **{$this->event->name}**.");

        if ($this->event->starts_at) {
            $tzLabel = $this->event->timezone ? " ({$this->event->timezone})" : '';
            $mail->line('**Date:** ' . $this->event->starts_at->format('l, F j, Y \a\t g:i A') . $tzLabel);
        }
        if ($this->event->venue_name) {
            $mail->line('**Venue:** ' . $this->event->venue_name);
        }

        return $mail
            ->action('View or update your RSVP', $rsvpUrl)
            ->line('See you there!');
    }

    private function waitlistedMail(string $rsvpUrl): MailMessage
    {
        return (new MailMessage)
            ->subject("You're on the waitlist for {$this->event->name}")
            ->greeting("Hi {$this->guest->first_name}!")
            ->line("The event is currently at capacity, but you've been added to the waitlist for **{$this->event->name}**.")
            ->line("We'll let you know if a spot opens up.")
            ->action('View your RSVP', $rsvpUrl);
    }

    private function declinedMail(): MailMessage
    {
        return (new MailMessage)
            ->subject("RSVP received — {$this->event->name}")
            ->greeting("Hi {$this->guest->first_name}!")
            ->line("We've received your response for **{$this->event->name}** — sorry you can't make it!")
            ->line('Thanks for letting us know.');
    }
}
