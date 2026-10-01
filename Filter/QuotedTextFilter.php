<?php

namespace Kanboard\Plugin\TagAlong\Filter;

/**
 * Remove the quoted earlier message from a reply.
 * From the Mailmagik branch strip-quoted-text.
 */
class QuotedTextFilter
{
    /**
     * Patterns that start the quoted part, all anchored at a line start.
     * Markdown emphasis ("**From:**") is allowed, since HTML mail is
     * converted to Markdown before it reaches this filter.
     */
    private const PATTERNS = array(
        // "On <date>, <name> wrote:", which Gmail may wrap onto a second line
        'attribution' => '/^[ \t]*On [^\r\n]*(?:\r?\n(?![ \t]*On )[^\r\n]*)?wrote:[ \t]*\r?$/m',
        // Outlook plain text
        'original' => '/^[ \t]*-{2,}[ \t]*Original Message[ \t]*-{2,}[ \t]*\r?$/mi',
        // Outlook header block: From: followed by Sent: or Date:
        'outlook' => '/^[ \t]*[*_]*From:[*_]*[ \t]+[^\r\n]+\r?\n[ \t]*[*_]*(?:Sent|Date):/mi',
        // The first line quoted with ">"
        'quote' => '/^[ \t]*>/m',
    );

    /**
     * Cut the text at the first line that starts the quoted part.
     *
     * An "On ... wrote:" line counts only if it has a digit, as dates do,
     * so a sentence that happens to start with "On" and a later line that
     * ends in "wrote:" are not mistaken for an attribution.
     *
     * An "On ... wrote:" line or a ">" line counts only when every line after
     * it is quoted or blank. An inline or bottom-posted reply has the sender's
     * own text after the quote, and cutting there would delete it.
     *
     * @param string $text
     * @return string The text without the quoted part, or the text unchanged
     *                if none is found or nothing would be left
     */
    public static function strip(string $text): string
    {
        $cut = null;

        foreach (self::PATTERNS as $name => $pattern) {
            if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as $match) {
                if ($name === 'attribution' && !preg_match('/\d/', $match[0])) {
                    continue;
                }
                if ($name === 'attribution' && !self::onlyQuotedAfter($text, $match[1] + strlen($match[0]))) {
                    continue;
                }
                if ($name === 'quote' && !self::onlyQuotedAfter($text, $match[1])) {
                    continue;
                }
                if ($cut === null || $match[1] < $cut) {
                    $cut = $match[1];
                }
                break;
            }
        }

        if ($cut === null) {
            return $text;
        }

        $kept = rtrim(substr($text, 0, $cut));
        // Outlook puts a line of underscores above its header block
        $kept = rtrim(preg_replace('/(?:\r?\n[ \t]*_{5,}[ \t]*)+$/', '', $kept));

        return trim($kept) === '' ? $text : $kept;
    }

    /**
     * Put a blank line between a plain line and the ">" line that follows it.
     *
     * HTML mail converts to "text  \n> quote" with no blank line, and Kanboard's
     * Markdown parser then renders the quote on the same line as the text before it.
     */
    public static function separateQuotes(string $text): string
    {
        $out = array();
        $previous = '';

        foreach (preg_split('/(\r?\n)/', $text) as $line) {
            $isQuote = ltrim($line) !== '' && ltrim($line)[0] === '>';
            $prevBlank = trim($previous) === '';
            $prevQuote = !$prevBlank && ltrim($previous)[0] === '>';

            if ($isQuote && !$prevBlank && !$prevQuote) {
                $out[] = '';
            }

            $out[] = $line;
            $previous = $line;
        }

        return implode("\n", $out);
    }

    /** True when every line from $offset to the end starts with ">" or is blank. */
    private static function onlyQuotedAfter(string $text, int $offset): bool
    {
        foreach (preg_split('/\R/', substr($text, $offset)) as $line) {
            $line = trim($line);

            if ($line !== '' && $line[0] !== '>') {
                return false;
            }
        }

        return true;
    }
}
