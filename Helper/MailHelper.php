<?php

namespace Kanboard\Plugin\TagAlong\Helper;

use Kanboard\Plugin\Mailmagik\Helper\MailHelper as MailmagikMailHelper;
use Kanboard\Plugin\TagAlong\Filter\AutoReplyFilter;
use Kanboard\Plugin\TagAlong\Filter\LineListFilter;
use Kanboard\Plugin\TagAlong\Filter\QuotedTextFilter;
use Kanboard\Plugin\TagAlong\Filter\SignatureFilter;

/**
 * Mailmagik's mail helper with filters on incoming mail, set in Settings, Email settings. The three
 * checkboxes are off until turned on. The signature line list is on with its defaults until it is
 * saved empty. Mailmagik's actions call this helper for every message, so the filters work without
 * editing Mailmagik. They depend on Mailmagik calling getUnseenMails() and getItemId() the way it
 * does today, and on each action fetching a fresh copy of the message (filters are not idempotent).
 */
class MailHelper extends MailmagikMailHelper
{
    const COMMENT_PREFIX = 'CommentOnTask#';
    const TASK_PREFIX = 'Project#';

    /** Unseen mail, minus automatic replies and mail from the mailbox itself, which are marked as seen. */
    public function getUnseenMails(&$mailbox, $prefix)
    {
        $ids = parent::getUnseenMails($mailbox, $prefix);

        if ($this->configModel->get('tagalong_skip_auto_replies', '0') != 1) {
            return $ids;
        }

        $own = array(
            $this->configModel->get('mailmagik_address', ''),
            $this->configModel->get('mailmagik_user', ''),
            $this->configModel->get('mail_sender_address', ''),
        );
        $kept = array();

        foreach ($ids as $id) {
            try {
                $header = $mailbox->getMailHeader($id);
            } catch (\Exception $e) {
                $kept[] = $id;
                continue;
            }

            if (AutoReplyFilter::isAutoReply((string) $header->headersRaw)
                || AutoReplyFilter::isOwnAddress((string) $header->fromAddress, $own)) {
                $mailbox->markMailAsRead($id);
                continue;
            }

            $kept[] = $id;
        }

        return $kept;
    }

    /**
     * Mailmagik's task id lookup. For a reply that becomes a comment, the body loses its
     * signature and quoted text first, since Mailmagik reads the body right after this call.
     */
    public function getItemId(&$email, string $prefix)
    {
        $id = parent::getItemId($email, $prefix);

        if ($id !== null && ($prefix === self::COMMENT_PREFIX || $prefix === self::TASK_PREFIX)) {
            $this->cleanBody($email, $prefix === self::COMMENT_PREFIX);
        }

        return $id;
    }

    /** Parsed once per request: getAll() reads the settings table, and fetchmail calls this per message. */
    private $lineRules = null;

    /** Rules from the line list setting: the saved list (empty means off), or the defaults before it is saved. */
    private function lineRules()
    {
        if ($this->lineRules !== null) {
            return $this->lineRules;
        }

        $options = $this->configModel->getAll();
        $raw = array_key_exists('tagalong_line_filters', $options) ? (string) $options['tagalong_line_filters'] : LineListFilter::DEFAULTS;

        list($rules, $invalid) = LineListFilter::parse($raw);

        foreach ($invalid as $entry) {
            $this->logger->error('TagAlong: skipped invalid line filter pattern '.$entry);
        }

        return $this->lineRules = $rules;
    }

    private function cleanBody($email, $isComment)
    {
        // Quoted text and signatures are cut from comments only. The line list covers comments and new tasks.
        $quotes = $isComment && $this->configModel->get('tagalong_strip_quoted_text', '0') == 1;
        $signatures = $isComment && $this->configModel->get('tagalong_strip_signatures', '0') == 1;
        $lines = $this->lineRules();

        if ((! $quotes && ! $signatures && empty($lines)) || ! $email instanceof \PhpImap\IncomingMail) {
            return;
        }

        $html = (string) $email->textHtml;

        // Same conversion Mailmagik applies to an HTML body, so the result reads the same.
        if ($html !== '' && class_exists('\League\HTMLToMarkdown\HtmlConverter')) {
            $converter = new \League\HTMLToMarkdown\HtmlConverter(array('strip_tags' => true));
            $text = $converter->convert($html);
        } elseif ($html !== '') {
            return;
        } else {
            $text = (string) $email->textPlain;
        }

        $cleaned = $text;

        // Text typed between quoted blocks has no line break before the next block, and the
        // converter then glues the quote's ">" to it ("my answer> Task: ..."). A break first fixes that.
        if ($quotes && $html !== '' && isset($converter)) {
            $cleaned = $converter->convert(preg_replace('/<blockquote/i', '<br><blockquote', $html));
        }

        if ($quotes) {
            $cleaned = QuotedTextFilter::separateQuotes(QuotedTextFilter::strip($cleaned));
        }

        if ($signatures) {
            $cleaned = SignatureFilter::strip($cleaned);
        }

        $cleaned = LineListFilter::strip($cleaned, $lines);

        if ($cleaned === $text) {
            return;
        }

        // The body properties are private with no setter. Emptying the HTML body makes
        // Mailmagik use the cleaned text, which also skips its inline image embedding.
        \Closure::bind(function ($plain) {
            $this->textPlain = $plain;
            $this->textHtml = '';
        }, $email, \PhpImap\IncomingMail::class)($cleaned);
    }
}
