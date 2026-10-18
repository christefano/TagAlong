# Changelog

## Unreleased

- The "Subject of NotifyMe notifications" setting shows up only when NotifyMe is installed.
- README and INSTALL.md now explicitly say Mailmagik is required. Without Mailmagik, the TagAlong settings show a "These settings take effect once Mailmagik is installed." message.
- NotifyMe by default now uses the subject format `{project}: {task_title} (#{task_id}) {event}` until the "Subject of NotifyMe notifications" setting is saved, e.g. `Test: test 5 (#130) Moved to another column [CommentOnTask#130]`. An already saved setting will be respected.
- Added screenshots.

## v1.1.1

## v1.1.0

- Added email subject formats for Kanboard's and NotifyMe's emails in *Settings -> Email settings -> TagAlong*, with `{project}`, `{task_id}`, `{task_title}`, `{event}`, and `{title}`. An empty format keeps the default subject, and the reply token always ends the subject.
- Added `In-Reply-To` and `References` headers, so mail clients group every email about a task into one thread.
- Added incoming email filters, all off by default: ignore automatic replies and email from the Mailmagik mailbox, remove quoted text, and remove signatures.
- Added a list of signature lines to remove in *Settings -> Email settings -> TagAlong*. The last matching line and everything after it is removed when it's in the last 10 non-empty lines. One entry per line, as plain text or a `/pattern/` regex. It starts with common lines like "Sent from my iPhone" and stays on until the list is saved empty.
- Reply-To now points to Kanboard's sender address on task notifications and NotifyMe emails, so replies reach Mailmagik's mailbox.
- NotifyMe emails get the reply token through its `notifyme:email:subject` hook.
- A `CommentOnTask#` already in a task title becomes `CommentOnTask #`, so a title can't send replies to a different task.
- Fixed a fatal error on every notification when Mailmagik isn't installed.
- Fixed a fatal error when the `emailClient` service was already in use. The plugin now loads and skips only the thread headers.
- Fixed quote removal gluing a quote's `>` onto the text typed just before it.
- Fixed "Remove quoted text" deleting everything after the first quote line. Inline and bottom-posted replies are now kept whole.

## v1.0.0

- Initial release
