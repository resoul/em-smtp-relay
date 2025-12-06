<form method="post" action="" enctype="multipart/form-data">
    <?php wp_nonce_field('em_smtp_relay_test_email'); ?>

    <table class="form-table">
        <tbody>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_email_subject">
                    <?php _e('Subject', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_email_subject" type="text" id="em_smtp_relay_email_subject" value="" class="regular-text">
                <p class="description">
                    <?php _e('Subject of the email', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_to_email">
                    <?php _e('To', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_to_email" type="text" id="em_smtp_relay_to_email" value="" class="regular-text">
                <p class="description">
                    <?php _e('Email address of the recipient', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_email_body">
                    <?php _e('Message', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <textarea name="em_smtp_relay_email_body" id="em_smtp_relay_email_body" class="regular-text" rows="6"></textarea>
                <p class="description">
                    <?php _e('Email body', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">
                <label for="test_attachments">
                    <?php _e('Attachments', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input
                        type="file"
                        name="test_attachments[]"
                        id="test_attachments"
                        multiple
                        accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx"
                />
                <p class="description">
                    <?php _e('Upload files to test attachments (max 5MB per file, multiple files allowed)', 'em-smtp-relay');?>
                </p>

                <?php if (!empty($uploadedFiles)): ?>
                    <div class="em-smtp-uploaded-files" style="margin-top: 15px;">
                        <strong><?php _e('Previously uploaded files:', 'em-smtp-relay'); ?></strong>
                        <div style="margin-top: 10px;">
                            <?php foreach ($uploadedFiles as $file): ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input
                                            type="checkbox"
                                            name="existing_attachments[]"
                                            value="<?php echo esc_attr($file['name']); ?>"
                                    />
                                    <?php echo esc_html($file['name']); ?>
                                    <span style="color: #666; font-size: 12px;">
                                        (<?php echo esc_html(size_format($file['size'])); ?> - <?php echo esc_html($file['date']); ?>)
                                    </span>
                                    <button
                                            type="button"
                                            class="button button-small em-smtp-delete-attachment"
                                            data-filename="<?php echo esc_attr($file['name']); ?>"
                                            style="margin-left: 10px;"
                                    >
                                        <?php _e('Delete', 'em-smtp-relay'); ?>
                                    </button>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">
                <label for="debug"><?php _e('Debug', 'em-smtp-relay');?></label>
            </th>
            <td>
                <input name="debug" type="checkbox" id="debug" value="1">
                <p class="description">
                    <?php _e('Enable debug mode to see detailed SMTP communication', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        </tbody>
    </table>

    <p class="submit">
        <input
                type="submit"
                name="em_smtp_relay_send_test_email"
                id="em_smtp_relay_send_test_email"
                class="button button-primary"
                value="<?php _e('Send Email', 'em-smtp-relay');?>"
        />
    </p>
</form>

<script>
    jQuery(document).ready(function($) {
        $('.em-smtp-delete-attachment').on('click', function(e) {
            e.preventDefault();

            var button = $(this);
            var filename = button.data('filename');

            if (!confirm('<?php _e('Are you sure you want to delete this file?', 'em-smtp-relay'); ?>')) {
                return;
            }

            button.prop('disabled', true).text('<?php _e('Deleting...', 'em-smtp-relay'); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'em_smtp_delete_attachment',
                    filename: filename,
                    nonce: '<?php echo wp_create_nonce('em_smtp_delete_attachment'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        button.closest('label').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message);
                        button.prop('disabled', false).text('<?php _e('Delete', 'em-smtp-relay'); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e('An error occurred', 'em-smtp-relay'); ?>');
                    button.prop('disabled', false).text('<?php _e('Delete', 'em-smtp-relay'); ?>');
                }
            });
        });
    });
</script>

<style>
    .em-smtp-uploaded-files {
        padding: 15px;
        background: #f6f7f7;
        border: 1px solid #dcdcde;
        border-radius: 4px;
    }

    .em-smtp-uploaded-files label {
        padding: 5px 0;
    }

    .em-smtp-delete-attachment {
        color: #d63638;
        border-color: #d63638;
    }

    .em-smtp-delete-attachment:hover {
        background: #d63638;
        color: #fff;
    }
</style>