<?php /**@var $data \Emercury\Smtp\Config\DTO\AdvancedSettingsDTO */ ?>
<form method="post" action="">
    <?php wp_nonce_field('em_smtp_relay_advanced_settings'); ?>
    <table class="form-table">
        <tbody>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_reply_to_email">
                    <?php _e('Reply To Email Address', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_reply_to_email" type="text" id="em_smtp_reply_to_email" value="<?php echo esc_attr($data->replyToEmail); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('The email address to be used as the \'Reply To\' address if none is provided to the mail function.', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_reply_to_name">
                    <?php _e('Reply To Name', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_reply_to_name" type="text" id="em_smtp_reply_to_name" value="<?php echo esc_attr($data->replyToName); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('The name to be used as the \'Reply To\' name if none is provided to the mail function.', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_force_reply_to"><?php _e('Force Reply To Address', 'em-smtp-relay');?></label>
            </th>
            <td>
                <input name="em_smtp_force_reply_to" type="checkbox" id="em_smtp_force_reply_to" <?php checked($data->forceReplyTo, 1); ?> value="1">
                <p class="description">
                    <?php _e('The "Reply To" address specified in the settings will apply to all outgoing email messages.', 'em-smtp-relay'); ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_cc_email">
                    <?php _e('Cc Email Address', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_cc_email" type="text" id="em_smtp_cc_email" value="<?php echo esc_attr($data->ccEmail); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('The email address to be used as the \'Cc\' address if none is provided to the mail function.', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_cc_name">
                    <?php _e('Cc Name', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_cc_name" type="text" id="em_smtp_cc_name" value="<?php echo esc_attr($data->ccName); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('The name to be used as the \'Cc\' name if none is provided to the mail function.', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_force_cc"><?php _e('Force Cc Address', 'em-smtp-relay');?></label>
            </th>
            <td>
                <input name="em_smtp_force_cc" type="checkbox" id="em_smtp_force_cc" <?php checked($data->forceCc, 1); ?> value="1">
                <p class="description">
                    <?php _e('The "Cc" address specified in the settings will apply to all outgoing email messages.', 'em-smtp-relay'); ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_bcc_email">
                    <?php _e('Bcc Email Address', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_bcc_email" type="text" id="em_smtp_bcc_email" value="<?php echo esc_attr($data->bccEmail); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('The email address to be used as the \'Bcc\' address if none is provided to the mail function.', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_bcc_name">
                    <?php _e('Bcc Name', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_bcc_name" type="text" id="em_smtp_bcc_name" value="<?php echo esc_attr($data->bccName); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('The name to be used as the \'Bcc\' name if none is provided to the mail function.', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_force_bcc"><?php _e('Force Bcc Address', 'em-smtp-relay');?></label>
            </th>
            <td>
                <input name="em_smtp_force_bcc" type="checkbox" id="em_smtp_force_bcc" <?php checked($data->forceBcc, 1); ?> value="1">
                <p class="description">
                    <?php _e('The "Bcc" address specified in the settings will apply to all outgoing email messages.', 'em-smtp-relay'); ?>
                </p>
            </td>
        </tr>
        </tbody>
    </table>
    <p class="submit">
        <input
            type="submit"
            name="em_smtp_relay_update_advanced_settings"
            id="em_smtp_relay_update_advanced_settings"
            class="button button-primary"
            value="<?php _e('Update', 'em-smtp-relay')?>"
        />
    </p>
</form>