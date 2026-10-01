<?php

namespace Kanboard\Plugin\TagAlong\Notification;

use Kanboard\Plugin\Mailmagik\Notification\MailNotification as MailmagikMailNotification;
use Kanboard\Plugin\TagAlong\Subject;
use Kanboard\Plugin\TagAlong\Token;

class MailNotification extends MailmagikMailNotification
{
    public function getMailSubject($eventName, $eventData)
    {
        $subject = parent::getMailSubject($eventName, $eventData);

        if (! is_array($eventData) || ! isset($eventData['task']['id'])) {
            return $subject;
        }

        $format = Subject::format($this->configModel, Subject::KANBOARD_KEY);

        if ($format !== '') {
            $task = $eventData['task'];
            $title = $this->notificationModel->getTitleWithoutAuthor($eventName, $eventData);
            $formatted = Subject::render($format, array(
                'project'    => isset($eventData['project_name']) ? $eventData['project_name'] : (isset($task['project_name']) ? $task['project_name'] : ''),
                'task_id'    => (int) $task['id'],
                'task_title' => isset($task['title']) ? $task['title'] : '',
                'title'      => $title,
                'event'      => Subject::label($eventName, $title),
            ));

            if ($formatted !== '') {
                $subject = $formatted;
            }
        }

        // Always last, so the token ends the subject whatever the format says.
        return Token::append($subject, $eventData['task']['id']);
    }

    /**
     * Core sends with no author email, so Reply-To becomes the logged-in user
     * and a reply never reaches the reply-by-email mailbox. Pass the sender
     * address instead. With none set, core's behavior is unchanged.
     */
    public function notifyUser(array $user, $eventName, array $eventData)
    {
        $replyTo = Token::replyTo($this->configModel);

        if ($replyTo === '' || empty($user['email'])) {
            return parent::notifyUser($user, $eventName, $eventData);
        }

        $this->emailClient->send(
            $user['email'],
            $user['name'] ?: $user['username'],
            $this->getMailSubject($eventName, $eventData),
            $this->getMailContent($eventName, $eventData),
            null,
            $replyTo
        );
    }
}
