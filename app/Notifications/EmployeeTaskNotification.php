<?php

namespace App\Notifications;

use App\Models\EmployeeTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EmployeeTaskNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly EmployeeTask $task, private readonly string $message) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return ['task_id'=>$this->task->id, 'project_id'=>$this->task->project_id,
            'title'=>$this->task->title, 'message'=>$this->message, 'url'=>route('employee-tasks.index')];
    }
}
