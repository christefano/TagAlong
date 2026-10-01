# TagAlong

*TagAlong* is a Kanboard plugin that supercharges email handling. It groups every email for a task into one thread, points Reply-To to the reply-by-email address, and allows Kanboard admins to customize the email subject format.

It can also filter autoreplies (like out-of-office messages), email signatures, and quoted text out of incoming emails.

If [Mailmagik](https://github.com/creecros/Mailmagik) is installed, *TagAlong* appends "`[CommentOnTask#12345]`" to the subject of outgoing task and comment notification emails (e.g. `Test #12345: New comment [CommentOnTask#12345]`), so an email reply from a project member becomes a comment on a task.


## Quick start

1. Copy the `TagAlong` folder into Kanboard's `plugins/` directory.
2. Customize the email subject formats and filters in *Settings -> Email settings -> TagAlong* (options include (Remove signatures from emailed comments, Remove quoted text from emailed comments, and Ignore automatic replies and email from the Mailmagik mailbox).
2. TagAlong works without Mailmagik, but if Mailmagik *is* installed:
    - Configure Mailmagik at *Settings -> Email settings -> Task- and Comment-Creation* and select "Parse from the SUBJECT field"*
    - On each project that should accept replies, go to *Configure this project -> Automatic actions* and add Mailmagik's "Automatically Convert Emails to Comments" action with "Trigger Mailmagik's mail fetching".
    - In Kanboard's *Settings -> Email settings* set the sender email to the mailbox that Mailmagik uses, and replies will turn into task comments (see Reply-To handling).

*Mailmagik's "TO" field mode is unsupported. Don't use Mailmagik's "TO" field mode. Friends don't let friends use Mailmagik's "TO" field mode.

[INSTALL.md](INSTALL.md) covers the requirements, removal, and compatibility.


## Email subject formats

*TagAlong* adds two new settings in *Settings -> Email settings*: one configures Kanboard's task notifications, and the other configures [NotifyMe](https://github.com/christefano/NotifyMe) emails. An empty field keeps the default email subject.

| Placeholder | Value |
|---|---|
| `{project}` | Project name |
| `{task_id}` | Task number |
| `{task_title}` | Task title |
| `{event}` | Short label such as "New comment" or the full title for an event with no label |
| `{title}` | Kanboard's full title, such as "New comment on task #12345" |

Kanboard's default is `[{project}] {title}` and NotifyMe's is `[{project}] {task_title} (#{task_id})`. The reply token is always appended the subject no matter what the format says. 

Each emailed comment starts with the subject line, for example `Re: Test #12345: New comment`, since Mailmagik makes the subject the comment's first line.


## Email threads

Every email with an email subject ending in a reply token gets `In-Reply-To` and `References` pointing at one ID per task (`task-ID.tagalong@<sender domain>`), so email clients that thread by those headers group "New comment", "Task updated", and the rest under the task. Gmail also needs matching email subjects, so it only groups emails with the same event label.

TagAlong copies Kanboard's three email transports (smtp, sendmail, and mail) and adds support for the headers. It does this in `onStartup()`, so it also replaces a transport another plugin set in its `initialize()`. A transport that was already in use when TagAlong starts can't be replaced, and that failure is logged and leaves the transport as it was.


## Incoming email filters

Everything below removes text from incoming email before Mailmagik saves it, and the removed text isn't kept anywhere in Kanboard. The original email stays in the mailbox (either marked as read or deleted if Mailmagik is set to delete processed email).

There are three checkboxes in *Settings -> Email settings*, and all off by default:

1. Ignore automatic replies and email from the Mailmagik mailbox. Mail with `Auto-Submitted` (anything but `no`), `X-Auto-Response-Suppress: OOF` or `All`, or `Precedence: bulk`, `auto_reply`, or `junk` is marked as read and never becomes a task or comment. So is email sent from Mailmagik's own address, its login, or Kanboard's sender address.
2. Remove quoted text from emailed comments. The cut starts at an "On ... wrote:" line that has a digit, an "Original Message" line, an Outlook `From:`/`Sent:` header block, or a `>` line, and runs to the end. An "On ... wrote:" or `>` line only counts when everything after it is quoted, so an inline or bottom-posted reply is kept whole. Text typed directly above a quoted block is kept apart from the quote's `>`.
3. Remove signatures from emailed comments, cut at the first standard `-- ` line.

TagAlong can also filter out email signatures (like the scourge that is "Sent from my iPhone") in emailed comments and in the bodies of tasks created from email:

- The last matching line and everything after it is removed, but only when the match is found in the last 10 non-empty lines of the email.
- List only the first line of an email signature. Formatting is ignored when matching: bold, italics, and link markup are removed from a line, too.
- Plain text matches a whole line exactly (ignoring case), and `/pattern/` is a regular expression tested against each line.
- TagAlong includes a few common lines from phone email clients and Outlook.
- The filters run in order: quoted text, then `--` email signatures, then the list.
- An invalid pattern is skipped and logged.
- Inline images aren't embedded. Mailmagik's task attribute parsing reads that text in place of the plaintext part.


## Reply-To handling

Replies go to the email's Reply-To address, but Kanboard sets that to the user who triggered the email. This isn't what we want since replies need to go to Mailmagik's mailbox. TagAlong sets `Reply-To` to Kanboard's sender address on the notifications it tags. With no sender address set, Kanboard's behavior is unchanged.


## Works with NotifyMe

[NotifyMe](https://github.com/christefano/NotifyMe) calls the `notifyme:email:subject` hook with `subject`, `task_id`, `project_name`, `task_title`, `event_name`, and `action`. TagAlong applies the NotifyMe subject format and appends the token. NotifyMe's `notifyme:email:reply_to` hook gets the sender address.


## What it overrides

TagAlong doesn't change any Kanboard core files or other plugin's files and replaces these at runtime:

| Part | Owner | Replaced with | Without Mailmagik |
|---|---|---|---|
| Email notification type (`MailNotification`) | Mailmagik, which replaced Kanboard's | Subclass adding the subject format, token, and Reply-To (`getMailSubject()`, `notifyUser()`) | Not registered |
| `mailHelper` helper | Mailmagik | Subclass adding the incoming email filters (`getUnseenMails()`, `getItemId()`) | Not registered |
| smtp, sendmail, and mail transports on the `emailClient` service | Kanboard, or NotKanboard when it owns `emailClient` | Copies of core's `sendEmail()` adding thread headers | Not registered |
| Settings, Email settings form | Kanboard | Adds a TagAlong fieldset through the `template:config:email` hook | Still shown |

The transport copies duplicate core 1.2.54's `sendEmail()`. Recheck them after a Kanboard update.


## Limitations

- If Mailmagik is installed, Mailmagik really needs to be in "SUBJECT" mode. In "TO" mode, email threads and email tokens aren't very useful because replies to the Kanboard sender address just stay unread in the Mailmagik mailbox. The filters will work in either mode, but "TO" mode also risks adding countless new email addresses to the address books in Kanboard users' email clients (one address book entry per project task ID per person).
- An email reply that has its token deleted from the subject doesn't become a comment. That's not really a limitation of TagAlong, though, and we're left wondering why someone would chose to rewrite the email subject.
- TagAlong adds the reply token to the end of the subject. If the subject already contains `CommentOnTask#ID` (because, for example, it's in a task title), TagAlong changes it to `CommentOnTask #` first. This prevents a title from sending email replies to a different task.
- Anything after the last matching filter line that's in the last 10 lines of an email gets filtered and removed (e.g. a P.S. under "Sent from my iPhone").
- With the autoreply filter on, email from Kanboard's sender address, Mailmagik's address, or Mailmagik's login is discarded. If one of these is a person's own address, their replies are dropped, too, so use a dedicated mailbox.
- The quoted text feature looks for text in emails that starts with "On " and ends with "wrote:" with at least one digit in in between, so any date format works. Other languages (like German's "Am ... schrieb") aren't recognized at the moment, however. Contributions are welcome: fork [TagAlong](https://github.com/christefano/TagAlong) on GitHub and create a pull request.
- TagAlong supports Mailmagik 1.6.1, so check for an update to TagAlong if Mailmagik also gets an update.
- Mailmagik 1.6.1 has a one-character bug that allows any Kanboard user to add a comment to any task in any project, so please chime in on ([Mailmagik issue 55](https://github.com/creecros/Mailmagik/issues/55)) to help get it fixed.



## Other plugins

TagAlong works with several other plugins by the same author:

- [NotKanboard](https://github.com/christefano/NotKanboard) helps whitelabel Kanboard and replaces "Kanboard" in page titles, outgoing emails, and a few other places with your own product name.
- [NotifyMe](https://github.com/christefano/NotifyMe) emails you about your own actions in a project, adds a vacation mode, notifies users of failed login attempts, and gives admins control of how many notifications are shown in the Notifications menu. Its footer uses NotKanboard's name when both are installed.


## Compatibility

- Kanboard >= 1.2.20
- Mailmagik 1.6.1
- No database changes


## License

GNU General Public License v2.
