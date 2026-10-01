<?php

namespace Kanboard\Plugin\TagAlong\Filter;

/**
 * Cut an email's signature using an admin-edited list of signature start lines, such as
 * "Sent from my iPhone". The LAST matching line, and every line after it, is removed, but only
 * when that line is in the tail of the email (within the last TAIL non-empty lines). The same
 * text earlier in the message, or in the middle of it, is left alone. One entry per line: a literal (compared to the
 * whole trimmed line, ignoring case) or a /pattern/flags regex (tested against the trimmed line).
 * Bodies from HTML mail arrive as Markdown, so a line is also tested with its formatting removed
 * ("Christefano Reyes | **Large Robot**" matches "Christefano Reyes | Large Robot").
 */
class LineListFilter
{
    /** A match only counts when at most this many non-empty lines (itself included) are left. */
    const TAIL = 10;

    /** Used until the setting is saved. Saving an empty list turns the filter off. */
    const DEFAULTS = "Sent from my iPhone\nSent from my iPad\n/^Sent from my (Android|Galaxy|Samsung|BlackBerry|Windows Phone)\\b.*/i\n/^Get Outlook for .*/\n/^Sent from Mail for Windows.*/i\n/^Sent from Yahoo Mail.*/i";

    /**
     * @param string $list One entry per line
     * @return array [rules, invalid]: rules are [isRegex, text] pairs, invalid lists the
     *               regex entries that did not compile (they are skipped, never fatal)
     */
    public static function parse(string $list): array
    {
        $rules = array();
        $invalid = array();

        foreach (preg_split('/\R/', $list) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if (preg_match('#^/.+/[a-z]*$#', $entry)) {
                if (@preg_match($entry, '') === false) {
                    $invalid[] = $entry;
                    continue;
                }

                $rules[] = array(true, $entry);
            } else {
                $rules[] = array(false, mb_strtolower(self::plain($entry)));
            }
        }

        return array($rules, $invalid);
    }

    /**
     * @param string $text
     * @param array  $rules From parse()
     * @return string The text cut at the last matching line in the tail, or the text
     *                unchanged if nothing matched there or nothing would be left
     */
    public static function strip(string $text, array $rules): string
    {
        if (empty($rules)) {
            return $text;
        }

        $lines = preg_split('/\R/', $text);
        $nonEmpty = array();

        foreach ($lines as $i => $line) {
            if (trim($line) !== '') {
                $nonEmpty[] = $i;
            }
        }

        $from = $nonEmpty[max(0, count($nonEmpty) - self::TAIL)] ?? count($lines);

        for ($i = count($lines) - 1; $i >= $from; $i--) {
            if (self::matches(trim($lines[$i]), $rules)) {
                $result = rtrim(implode("\n", array_slice($lines, 0, $i)));

                return $result === '' ? $text : $result;
            }
        }

        return $text;
    }

    /**
     * A line without Markdown formatting: links reduced to their text, emphasis markers,
     * backticks and backslash escapes removed, non-breaking and repeated spaces collapsed.
     */
    private static function plain(string $line): string
    {
        $line = str_replace("\xC2\xA0", ' ', $line);
        $line = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $line);
        $line = preg_replace('/\\\\([\\\\`*_{}\[\]()#+.!|>~-])/', '$1', $line);
        $line = preg_replace('/(\*\*|__|~~|\*|`)/', '', $line);
        $line = preg_replace('/(?<![\w])_|_(?![\w])/', '', $line);

        return trim(preg_replace('/\s+/', ' ', $line));
    }

    private static function matches(string $line, array $rules): bool
    {
        if ($line === '') {
            return false;
        }

        $variants = array_unique(array($line, self::plain($line)));

        foreach ($rules as list($isRegex, $value)) {
            foreach ($variants as $variant) {
                if ($isRegex ? preg_match($value, $variant) === 1 : mb_strtolower($variant) === $value) {
                    return true;
                }
            }
        }

        return false;
    }
}
