<?php /**@var $data \Emercury\Smtp\Config\DTO\SmtpSettingsDTO */ ?>
<form method="post" action="">
    <?php wp_nonce_field('em_smtp_relay_settings'); ?>
    <table class="form-table">
        <tbody>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_username">
                    <?php _e('SMTP Username', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_username" type="text" id="em_smtp_relay_username" value="<?php echo esc_attr($data->smtpUsername); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('SMTP Username.', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_password">
                    <?php _e('SMTP Password (Token)', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_password" type="password" id="em_smtp_relay_password" value="" class="regular-text code">
                <p class="description">
                    <?php _e('Your SMTP Password (The saved password is hidden for security reasons. Leave this field empty if you don\'t want to change the saved password while updating other settings).', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="em_smtp_relay_encryption">
                    <?php _e('Encryption', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <select name="em_smtp_relay_encryption" id="em_smtp_relay_encryption">
                    <option value="tls" <?php echo selected( $data->smtpEncryption, 'tls', false );?>><?php _e('STARTTLS', 'em-smtp-relay');?></option>
                    <option value="ssl" <?php echo selected( $data->smtpEncryption, 'ssl', false );?>><?php _e('SSL', 'em-smtp-relay');?></option>
                </select>
                <p class="description">
                    <?php _e('The encryption method to be used for sending emails (recommended: STARTTLS).', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="em_smtp_relay_host">
                    <?php _e('Host', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_host" type="text" id="em_smtp_relay_host" value="<?php echo esc_attr($data->smtpHost); ?>" class="regular-text code">
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="em_smtp_relay_port">
                    <?php _e('Port', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_port" type="text" id="em_smtp_relay_port" value="<?php echo esc_attr($data->smtpPort); ?>" class="regular-text code">
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_from_email">
                    <?php _e('From Email Address', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_from_email" type="text" id="em_smtp_relay_from_email" value="<?php echo esc_attr($data->fromEmail); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('The email address to be used as the \'From\' address if none is provided to the mail function. The domain of From Address should be validated in Emercury SMTP panel, Sending Domains section. This field is required if you want to send a test email from the "Test Email" tab', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_from_name">
                    <?php _e('From Name', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_from_name" type="text" id="em_smtp_relay_from_name" value="<?php echo esc_attr($data->fromName); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('The name to be used as the \'From\' name if none is provided to the mail function.', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_force_from_address"><?php _e('Force From Address', 'em-smtp-relay');?></label>
            </th>
            <td>
                <input name="em_smtp_relay_force_from_address" type="checkbox" id="em_smtp_relay_force_from_address" <?php checked($data->forceFromAddress, 1); ?> value="1">
                <p class="description">
                    <?php _e('The "From" address specified in the settings will apply to all outgoing email messages.', 'em-smtp-relay'); ?>
                </p>
            </td>
        </tr>
        </tbody>
    </table>
    <p class="submit">
        <input
            type="submit"
            name="em_smtp_relay_update_settings"
            id="em_smtp_relay_update_settings"
            class="button button-primary"
            value="<?php _e('Update', 'em-smtp-relay')?>"
        />
    </p>
</form>