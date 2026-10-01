# Installation

## Requirements

- Kanboard 1.2.20 or later.
- If [Mailmagik](https://github.com/creecros/Mailmagik) is installed:
    - Set Mailmagik to "Parse from the SUBJECT field" (*not* "TO" mode).
      - The "Automatically Convert Emails to Comments" action (`ConvertEmailToComment`) on each project that should accept email replies.
      - Kanboard's sender address set to the mailbox Mailmagik reads. See the README's Reply-To handling section.


## Install

1. Copy the `TagAlong` folder into Kanboard's `plugins/` directory.
2. Optionally enter in the email subject formats and turn on the filters in *Settings -> Email settings -> TagAlong*. The email signature list includes a few common phone and Outlook email signatures.
3. Send a test notification and check that the subject ends in `[CommentOnTask#ID]` and the headers include `In-Reply-To: <task-ID.kanboard@sender-domain>`.
4. Reply to it without changing the subject, run Mailmagik's fetch, and check that the reply became a comment on the task.


## Remove

Delete `plugins/TagAlong`. Mailmagik's own notification handler and mail helper and Kanboard's own transports resume. The saved `tagalong_*` settings are still in the settings table in case TagAlong is installed again.


## Mailmagik fetch

To run Mailmagik's fetch:

1. Manually from the Kanboard directory: `./cli mailmagik:fetchmail`. For example: `cd /home/kanboard/public_html && ./cli mailmagik:fetchmail`
2. Cron every minute: `* * * * * cd /path/to/kanboard && ./cli mailmagik:fetchmail`
3. Webcron (Mailmagik 1.4.0 and later): request `https://<server>/fetchmail?token=<webhook_token>`

## Compatibility

- TagAlong doesn't make any database changes. Settings are stored in Kanboard's settings table (`tagalong_subject_format`, `tagalong_notifyme_subject_format`, `tagalong_line_filters`, `tagalong_skip_auto_replies`, `tagalong_strip_quoted_text`, `tagalong_strip_signatures`).
- No Kanboard core or third-party plugin files are modified. At runtime TagAlong copies Mailmagik's email notification type and `mailHelper` with subclasses, and Kanboard's smtp, sendmail, and mail transports. A plugin that replaces any of these too conflicts, and the one that registers last gets priority.
- Works with [NotifyMe](https://github.com/christefano/NotifyMe) through the `notifyme:email:subject` and `notifyme:email:reply_to` hooks. Neither needs the other.
- Works with [NotKanboard](https://github.com/christefano/NotKanboard), which replaces the email client and keeps Kanboard's transports. TagAlong swaps the transports after that, in `onStartup()`.
