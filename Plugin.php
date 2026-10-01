<?php

namespace Kanboard\Plugin\TagAlong;

use Kanboard\Core\Plugin\Base;
use Kanboard\Notification\MailNotification;

class Plugin extends Base
{
    /** Core's transports, each replaced by TagAlong's copy that adds thread headers. */
    private static $transports = array(
        'smtp'     => array('\Kanboard\Core\Mail\Transport\Smtp', '\Kanboard\Plugin\TagAlong\Transport\Smtp'),
        'sendmail' => array('\Kanboard\Core\Mail\Transport\Sendmail', '\Kanboard\Plugin\TagAlong\Transport\Sendmail'),
        'mail'     => array('\Kanboard\Core\Mail\Transport\Mail', '\Kanboard\Plugin\TagAlong\Transport\Mail'),
    );

    public function initialize()
    {
        $this->template->hook->attach('template:config:email', 'TagAlong:config/email');

        // Lets NotifyMe (or any plugin that calls this hook) get the token on
        // its own emails. With no caller the listener never runs. The Mailmagik
        // check happens when the hook fires, so plugin load order does not matter.
        $this->hook->on('notifyme:email:subject', function (&$data) {
            if (! is_array($data) || ! isset($data['subject'], $data['task_id']) || ! Token::mailmagikInstalled()) {
                return;
            }

            $format = Subject::format($this->configModel, Subject::NOTIFYME_KEY);

            if ($format !== '') {
                $action = isset($data['action']) && is_string($data['action']) ? t($data['action']) : '';
                $formatted = Subject::render($format, array(
                    'project'    => isset($data['project_name']) ? $data['project_name'] : '',
                    'task_id'    => (int) $data['task_id'],
                    'task_title' => isset($data['task_title']) ? $data['task_title'] : '',
                    'title'      => $action,
                    'event'      => Subject::label(isset($data['event_name']) ? $data['event_name'] : '', $action),
                ));

                if ($formatted !== '') {
                    $data['subject'] = $formatted;
                }
            }

            $data['subject'] = Token::append($data['subject'], $data['task_id']);
        });

        // Same reason for Reply-To: replies must reach Mailmagik's mailbox.
        $this->hook->on('notifyme:email:reply_to', function (&$replyTo) {
            if (Token::mailmagikInstalled()) {
                $replyTo = Token::replyTo($this->configModel);
            }
        });
    }

    // Registered in onStartup() and not initialize(): Mailmagik registers the
    // email type and its mail helper in its initialize(), and plugin load order
    // is unsorted, so this runs after every plugin has had its initialize() call.
    public function onStartup()
    {
        // Without Mailmagik the subclasses have no parent, so registering them
        // would fatal. Skip and leave core's handlers alone.
        if (! Token::mailmagikInstalled()) {
            return;
        }

        $this->userNotificationTypeModel->setType(
            MailNotification::TYPE,
            t('Email'),
            '\Kanboard\Plugin\TagAlong\Notification\MailNotification'
        );

        try {
            $this->helper->register('mailHelper', '\Kanboard\Plugin\TagAlong\Helper\MailHelper');
        } catch (\Exception $e) {
            $this->logger->error('TagAlong: mail helper already in use, filters off: '.$e->getMessage());
        }

        $this->registerTransports();
    }

    /**
     * Swap in the thread-header transports. Never call getTransport() here to
     * check what is registered: it resolves the Pimple service, and a resolved
     * service is frozen, so the setTransport() after it throws
     * FrozenServiceException.
     */
    private function registerTransports()
    {
        foreach (self::$transports as $name => $classes) {
            try {
                $this->emailClient->setTransport($name, $classes[1]);
            } catch (\Throwable $e) {
                $this->logger->error('TagAlong: transport '.$name.' not replaced, no thread headers: '.$e->getMessage());
            }
        }
    }

    public function getPluginName()
    {
        return 'TagAlong';
    }

    public function getPluginDescription()
    {
        return t('Adds a Mailmagik reply token, thread headers, and Reply-To to task emails so replies become comments, and filters autoreplies, signatures, and quoted text from incoming mail');
    }

    public function getPluginAuthor()
    {
        return 'Christefano Reyes';
    }

    public function getPluginVersion()
    {
        return '1.0.0';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/christefano/TagAlong';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.20';
    }
}
