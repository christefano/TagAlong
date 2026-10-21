<?php

namespace Kanboard\Plugin\TagAlong\Filter;

/**
 * Trim an emailed comment after a marker line, as Redmine does with "--Reply above this line--".
 * TagAlong puts the marker at the top of its outgoing task emails, so whatever the reply's mail
 * client quotes sits at or below it, and the cut never has to recognize a client's quote format.
 *
 * The marker comes from the "Reply-above line" setting, and the "Trim incoming comments after
 * these lines" list adds more: plain text matches a whole line, /pattern/ is a regex. The FIRST
 * matching line and everything after it is removed, anywhere in the email.
 *
 * A mail client or the HTML to Markdown conversion can change a marker line, so a line is compared
 * after leading ">" quote marks, Markdown formatting, en and em dashes, and repeated spaces are removed.
 */
class TrimFilter
{
    /** Shown as the placeholder of the Reply-above line setting. */
    const MARKER_EXAMPLE = '--Reply above this line--';

    /**
     * @param string $marker The Reply-above line, empty for none
     * @param string $list   Extra lines, one per line
     * @return array [rules, invalid], as LineListFilter::parse()
     */
    public static function parse(string $marker, string $list): array
    {
        $entries = trim($marker) === '' ? '' : self::collapse(self::normalize($marker));
        // A marker that looks like /pattern/ is taken literally: it is a line of text, not a rule.
        $rules = array();

        if ($entries !== '') {
            $rules[] = array(false, mb_strtolower($entries));
        }

        list($listRules, $invalid) = LineListFilter::parse($list);

        return array(array_merge($rules, $listRules), $invalid);
    }

    /**
     * @param string $text
     * @param array  $rules From parse()
     * @return string The text cut at the first matching line, with the quote header the mail
     *                client put just above it, or the text unchanged if nothing matched or
     *                nothing would be left (a reply typed below the marker)
     */
    public static function strip(string $text, array $rules): string
    {
        if (empty($rules)) {
            return $text;
        }

        $lines = preg_split('/\R/', $text);

        foreach ($lines as $i => $line) {
            if (! self::isMarker($line, $rules)) {
                continue;
            }

            $kept = self::dropQuoteHeader(array_slice($lines, 0, $i));
            $result = rtrim(implode("\n", $kept));

            return $result === '' ? $text : $result;
        }

        return $text;
    }

    private static function isMarker(string $line, array $rules): bool
    {
        // A client that quotes the notification puts ">" in front of the marker.
        $line = trim(preg_replace('/^(?:[ \t]*>)+/', '', $line));

        if ($line === '') {
            return false;
        }

        // Autocorrect turns "--" into one dash, so the marker also matches with its dash runs collapsed.
        $plain = self::normalize($line);

        return LineListFilter::matches($plain, $rules) || LineListFilter::matches(self::collapse($plain), $rules);
    }

    /** Each run of dashes made one dash. */
    private static function collapse(string $line): string
    {
        return preg_replace('/-+/', '-', $line);
    }

    /** Dashes a client or converter may swap for "-", and runs of spaces, made plain. */
    private static function normalize(string $line): string
    {
        $line = str_replace(array("\xE2\x80\x90", "\xE2\x80\x91", "\xE2\x80\x92", "\xE2\x80\x93", "\xE2\x80\x94", "\xE2\x88\x92"), '-', $line);

        // The converter escapes punctuation, "\-\-" for "--".
        $line = preg_replace('/\\\\([\\\\`*_{}\[\]()#+.!|>~-])/', '$1', $line);

        return trim(preg_replace('/[ \t]+/', ' ', str_replace("\xC2\xA0", ' ', $line)));
    }

    /**
     * Remove what a mail client puts above a quote it placed below the reply, so the comment
     * does not end in "On Wed, Oct 21, 2026 at 11:40 AM Alex wrote:" or an Outlook
     * From / Sent block.
     *
     * @param array $lines The lines above the marker
     * @return array
     */
    private static function dropQuoteHeader(array $lines): array
    {
        do {
            $before = count($lines);

            while ($lines && self::isFiller(end($lines))) {
                array_pop($lines);
            }

            $last = count($lines) - 1;

            if ($last >= 0 && preg_match('/wrote:$/', self::bare($lines[$last]))) {
                $oneLine = preg_match('/^On .*\d/', self::bare($lines[$last]));
                // Gmail wraps a long attribution onto a second line.
                $twoLines = $last >= 1 && preg_match('/^On .*\d/', self::bare($lines[$last - 1]));

                if ($oneLine) {
                    array_pop($lines);
                } elseif ($twoLines) {
                    array_pop($lines);
                    array_pop($lines);
                }
            }

            // Outlook: a From: line with Sent: or Date:, then To, Cc and Subject.
            $header = 0;

            for ($i = count($lines) - 1; $i >= 0 && preg_match('/^[*_]*(From|Sent|Date|To|Cc|Subject):/i', self::bare($lines[$i])); $i--) {
                $header++;
            }

            if ($header > 0 && preg_match('/^[*_]*From:/i', self::bare($lines[count($lines) - $header]))) {
                $lines = array_slice($lines, 0, count($lines) - $header);
            }
        } while (count($lines) < $before);

        return $lines;
    }

    /** Blank lines, lone ">" marks, rules of underscores or dashes, and an "Original Message" line. */
    private static function isFiller(string $line): bool
    {
        $line = self::bare($line);

        return $line === ''
            || preg_match('/^_{5,}$/', $line)
            || preg_match('/^-{2,}\s*Original Message\s*-{2,}$/i', $line);
    }

    /** A line without ">" quote marks, Markdown emphasis at its ends, and outer spaces. */
    private static function bare(string $line): string
    {
        $line = trim(preg_replace('/^(?:[ \t]*>)+/', '', $line));

        return trim(self::normalize($line), " \t*_");
    }
}
