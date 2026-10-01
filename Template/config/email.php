<fieldset>
    <legend>TagAlong</legend>

    <?= $this->form->label(t('Subject of task notifications'), 'tagalong_subject_format') ?>
    <?= $this->form->text('tagalong_subject_format', $values, $errors, array('placeholder="'.$this->text->e(\Kanboard\Plugin\TagAlong\Subject::KANBOARD_DEFAULT).'"')) ?>

    <?= $this->form->label(t('Subject of NotifyMe notifications'), 'tagalong_notifyme_subject_format') ?>
    <?= $this->form->text('tagalong_notifyme_subject_format', $values, $errors, array('placeholder="'.$this->text->e(\Kanboard\Plugin\TagAlong\Subject::NOTIFYME_DEFAULT).'"')) ?>

    <p class="form-help"><?= t('Placeholders: {project}, {task_id}, {task_title}, {event} (short), {title} (full). The reply token always ends the subject. Empty keeps the default.') ?></p>

    <?php $values += array('tagalong_line_filters' => \Kanboard\Plugin\TagAlong\Filter\LineListFilter::DEFAULTS) ?>
    <?= $this->form->label(t('Lines to remove from incoming mail'), 'tagalong_line_filters') ?>
    <?= $this->form->textarea('tagalong_line_filters', $values, $errors, array('rows="6"')) ?>
    <p class="form-help"><?= t('One per line, for emailed comments and new tasks. List the first line of a signature: the last matching line and everything after it is removed, but only when it is near the end of the email (the last 10 non-empty lines). Plain text matches a whole line exactly, ignoring case. /pattern/ is a regular expression tested against each line. An invalid pattern is skipped and logged. Empty removes nothing.') ?></p>

    <?php foreach (array(
        'tagalong_skip_auto_replies' => t('Ignore automatic replies and mail from the Mailmagik mailbox'),
        'tagalong_strip_quoted_text' => t('Remove quoted text from emailed comments'),
        'tagalong_strip_signatures'  => t('Remove signatures from emailed comments'),
    ) as $name => $label): ?>
        <?= $this->form->hidden($name, array($name => '0')) ?>
        <?= $this->form->checkbox($name, $label, 1, isset($values[$name]) && $values[$name] == 1) ?>
    <?php endforeach ?>
</fieldset>
