<?php

namespace Kanboard\Plugin\TagAlong\Filter;

/**
 * Recognize automatic replies (out-of-office notices, autoresponders, bulk
 * mail) from their raw headers, following RFC 3834 and the headers that
 * Exchange and list servers add. From the Mailmagik branch skip-auto-replies.
 */
class AutoReplyFilter
{
    /**
     * Return every value of one header field in a raw header block.
     * Folded lines are joined first.
     *
     * @param string $headersRaw
     * @param string $name Header name, case-insensitive
     * @return string[]
     */
    public static function getHeaderValues(string $headersRaw, string $name): array
    {
        $unfolded = preg_replace('/\r?\n[ \t]+/', ' ', $headersRaw);
        $values = array();

        foreach (preg_split('/\r?\n/', $unfolded) as $line) {
            if (stripos($line, $name . ':') === 0) {
                $values[] = trim(substr($line, strlen($name) + 1));
            }
        }

        return $values;
    }

    /**
     * Check whether the headers mark the mail as an automatic reply.
     *
     * - Auto-Submitted is present and not "no" (RFC 3834)
     * - X-Auto-Response-Suppress contains OOF or All (Exchange)
     * - Precedence is bulk, auto_reply, or junk
     *
     * @param string $headersRaw
     * @return bool
     */
    public static function isAutoReply(string $headersRaw): bool
    {
        foreach (self::getHeaderValues($headersRaw, 'Auto-Submitted') as $value) {
            // Parameters may follow the keyword, as in "auto-replied; owner-email=..."
            $keyword = strtolower(trim(explode(';', $value)[0]));
            if ($keyword !== '' && $keyword !== 'no') {
                return true;
            }
        }

        foreach (self::getHeaderValues($headersRaw, 'X-Auto-Response-Suppress') as $value) {
            $tokens = preg_split('/[\s,]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY);
            if (in_array('oof', $tokens) || in_array('all', $tokens)) {
                return true;
            }
        }

        foreach (self::getHeaderValues($headersRaw, 'Precedence') as $value) {
            if (in_array(strtolower($value), array('bulk', 'auto_reply', 'junk'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether the sender is one of the given addresses.
     *
     * @param string $from Sender address
     * @param string[] $own Addresses of the Mailmagik mailbox
     * @return bool
     */
    public static function isOwnAddress(string $from, array $own): bool
    {
        $from = strtolower(trim($from));
        if ($from === '') {
            return false;
        }

        foreach ($own as $address) {
            if (is_string($address) && $from === strtolower(trim($address))) {
                return true;
            }
        }

        return false;
    }
}
