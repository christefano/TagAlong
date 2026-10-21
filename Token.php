<?php

namespace Kanboard\Plugin\TagAlong;

class Token
{
    const FORMAT = ' [CommentOnTask#%d]';
    const MARKER = 'CommentOnTask#';

    /** Mailmagik's notification class, the one thing the token is useless without. */
    const MAILMAGIK_CLASS = '\Kanboard\Plugin\Mailmagik\Notification\MailNotification';

    public static function mailmagikInstalled()
    {
        return class_exists(self::MAILMAGIK_CLASS);
    }

    /** Kanboard's sender address (Settings, Email settings), or an empty string when none is set. */
    public static function replyTo($configModel)
    {
        $address = $configModel->get('mail_sender_address', '');

        return is_string($address) ? $address : '';
    }

    /**
     * Subject with the reply token appended, unchanged when there is no task or it already ends
     * with this task's token. A task title is user text and can hold another task's token, which
     * would send replies to that task, so any other marker in the subject is broken up first.
     */
    public static function append($subject, $taskId)
    {
        $taskId = (int) $taskId;
        $token = sprintf(self::FORMAT, $taskId);

        if ($taskId <= 0 || substr($subject, -strlen($token)) === $token) {
            return $subject;
        }

        return str_replace(self::MARKER, 'CommentOnTask #', $subject).$token;
    }

    /**
     * The Reply-above line as the first thing in the body of a task email, on a line of its own,
     * so a reply that quotes the email keeps it above the quoted text. Unchanged when no line is set.
     */
    public static function withReplyMarker($html, $marker)
    {
        $marker = is_string($marker) ? trim($marker) : '';

        if ($marker === '') {
            return $html;
        }

        $line = '<p style="color:#888;font-size:12px;margin:0 0 16px">'.htmlspecialchars($marker, ENT_QUOTES, 'UTF-8').'</p>';

        // After the opening body tag when the email has one, so the line is not left outside the page.
        if (preg_match('/<body\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            $at = $match[0][1] + strlen($match[0][0]);

            return substr($html, 0, $at).$line.substr($html, $at);
        }

        return $line.$html;
    }

    /** Task id from the token at the end of a subject, or 0 when the subject has none. */
    public static function taskIdFromSubject($subject)
    {
        return preg_match('/\[CommentOnTask#(\d+)\]$/', (string) $subject, $match) ? (int) $match[1] : 0;
    }

    /**
     * Message id every email about one task refers to, so mail clients group them into one
     * thread. No email is ever sent with this id: clients that thread by References show the
     * emails under a placeholder parent.
     */
    public static function threadId($taskId, $senderAddress)
    {
        $domain = 'kanboard.invalid';

        if (is_string($senderAddress) && preg_match('/@([A-Za-z0-9.-]+)>?$/', trim($senderAddress), $match)) {
            $domain = strtolower($match[1]);
        }

        return 'task-'.(int) $taskId.'.tagalong@'.$domain;
    }
}
