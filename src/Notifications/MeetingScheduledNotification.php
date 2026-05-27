<?php

namespace Afterburner\Meetings\Notifications;

use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\TeamDateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingScheduledNotification extends Notification
{
    use Queueable;

    public function __construct(public Meeting $meeting) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->meeting->team;
        $url = route('teams.meetings.show', [
            'team' => $team->id,
            'meeting' => $this->meeting->id,
        ]);
        $scheduled = TeamDateTime::format($team, $this->meeting->scheduled_at);

        return (new MailMessage)
            ->subject('Meeting scheduled: '.$this->meeting->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('A meeting has been scheduled for '.$team->name.'.')
            ->line('**'.$this->meeting->title.'**')
            ->when($scheduled, fn (MailMessage $mail) => $mail->line('When: '.$scheduled))
            ->when($this->meeting->location, fn (MailMessage $mail) => $mail->line('Where: '.$this->meeting->location))
            ->action('View meeting', $url);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_scheduled',
            'meeting_id' => $this->meeting->id,
            'team_id' => $this->meeting->team_id,
            'title' => $this->meeting->title,
            'message' => 'Meeting "'.$this->meeting->title.'" has been scheduled.',
            'url' => route('teams.meetings.show', [
                'team' => $this->meeting->team_id,
                'meeting' => $this->meeting->id,
            ]),
        ];
    }
}
