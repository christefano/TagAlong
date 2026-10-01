<?php

namespace Kanboard\Plugin\TagAlong;

/**
 * Subject formats from Settings, Email settings. An empty format keeps the subject Kanboard or
 * NotifyMe built, so an install that never saves the settings sees no change.
 */
class Subject
{
    /** Setting for Kanboard's own task notifications. */
    const KANBOARD_KEY = 'tagalong_subject_format';

    /** Setting for NotifyMe's emails. */
    const NOTIFYME_KEY = 'tagalong_notifyme_subject_format';

    /** Kanboard's own subject, written as a format. Shown as the field's placeholder. */
    const KANBOARD_DEFAULT = '[{project}] {title}';

    /** NotifyMe's own subject, written as a format. Shown as the field's placeholder. */
    const NOTIFYME_DEFAULT = '[{project}] {task_title} (#{task_id})';

    /**
     * Short labels for {event}. The English text is the translation key, so a language with no
     * translation shows English. An event missing here falls back to the full title.
     */
    private static $labels = array(
        'task.create'                      => 'New task',
        'task.update'                      => 'Task updated',
        'task.close'                       => 'Task closed',
        'task.open'                        => 'Task reopened',
        'task.move.column'                 => 'Moved to another column',
        'task.move.position'               => 'Position changed',
        'task.move.swimlane'               => 'Moved to another swimlane',
        'task.move.project'                => 'Moved to another project',
        'task.assignee_change'             => 'Assignee changed',
        'task.user.mention'                => 'Mentioned',
        'subtask.create'                   => 'New subtask',
        'subtask.update'                   => 'Subtask updated',
        'subtask.delete'                   => 'Subtask deleted',
        'comment.create'                   => 'New comment',
        'comment.update'                   => 'Comment updated',
        'comment.delete'                   => 'Comment deleted',
        'comment.user.mention'             => 'Mentioned in a comment',
        'task.file.create'                 => 'New attachment',
        'task.file.destroy'                => 'Attachment deleted',
        'task_internal_link.create_update' => 'Internal link added',
        'task_internal_link.delete'        => 'Internal link removed',
    );

    /** Saved format for a setting key, with line breaks removed, or '' when none is saved. */
    public static function format($configModel, $key)
    {
        $format = $configModel->get($key, '');

        return is_string($format) ? trim(str_replace(array("\r", "\n"), ' ', $format)) : '';
    }

    /** Short label for an event, or $fallback when TagAlong has none. */
    public static function label($eventName, $fallback)
    {
        return isset(self::$labels[$eventName]) ? t(self::$labels[$eventName]) : $fallback;
    }

    /**
     * Fill a format's tokens. Values are plain text for a header and not HTML, as in core's
     * subjects, and a value's own braces are never expanded again.
     */
    public static function render($format, array $values)
    {
        $pairs = array();

        foreach (array('project', 'task_id', 'task_title', 'event', 'title') as $name) {
            $value = isset($values[$name]) && is_scalar($values[$name]) ? (string) $values[$name] : '';
            $pairs['{'.$name.'}'] = str_replace(array("\r", "\n"), ' ', $value);
        }

        return trim(strtr($format, $pairs));
    }
}
