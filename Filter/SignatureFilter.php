<?php

namespace Kanboard\Plugin\TagAlong\Filter;

/**
 * Remove an email signature marked by the RFC 3676 delimiter, a line that
 * holds only "-- ". Signatures without the delimiter are left alone.
 * From the Mailmagik branch strip-signatures.
 */
class SignatureFilter
{
    /**
     * Cut the text at the first signature delimiter.
     *
     * The delimiter may have extra trailing spaces: the HTML to Markdown
     * conversion turns "-- <br>" into "--" followed by a hard line break.
     * A bare "--" line does not count.
     *
     * @param string $text
     * @return string The text without the signature, or the text unchanged
     *                if no delimiter is found or nothing would be left
     */
    public static function strip(string $text): string
    {
        if (!preg_match('/^-- +\r?$/m', $text, $match, PREG_OFFSET_CAPTURE)) {
            return $text;
        }

        $kept = rtrim(substr($text, 0, $match[0][1]));

        return $kept === '' ? $text : $kept;
    }
}
