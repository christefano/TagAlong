<?php

namespace Kanboard\Plugin\TagAlong\Transport;

use Kanboard\Plugin\TagAlong\Token;

/**
 * Core's sendEmail() (1.2.54, Transport/Mail.php) plus In-Reply-To and References for any
 * subject that ends with a reply token. Every email about one task then points at the same
 * message id and groups into one thread. The id comes from the subject and not from shared
 * state, so it survives Kanboard's queue worker.
 */
trait ThreadHeaders
{
    public function sendEmail($recipientEmail, $recipientName, $subject, $html, $authorName, $authorEmail = '')
    {
        try {
            $message = \Swift_Message::newInstance()
                ->setSubject($subject)
                ->setFrom($this->helper->mail->getMailSenderAddress(), $authorName)
                ->setTo(array($recipientEmail => $recipientName));

            if (! empty(MAIL_BCC)) {
                $message->setBcc(MAIL_BCC);
            }

            $headers = $message->getHeaders();

            // See https://tools.ietf.org/html/rfc3834#section-5
            $headers->addTextHeader('Auto-Submitted', 'auto-generated');

            $taskId = Token::taskIdFromSubject($subject);

            if ($taskId > 0) {
                $threadId = Token::threadId($taskId, $this->helper->mail->getMailSenderAddress());
                $headers->addIdHeader('In-Reply-To', $threadId);
                $headers->addIdHeader('References', $threadId);
            }

            if (! empty($authorEmail)) {
                $message->setReplyTo($authorEmail);
            }

            $message->setBody($html, 'text/html');

            \Swift_Mailer::newInstance($this->getTransport())->send($message);
        } catch (\Swift_TransportException $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
