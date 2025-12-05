<form method="post" action="">
    <?php wp_nonce_field('em_smtp_relay_settings'); ?>
    <table class="form-table">
        <tbody>
        <tr valign="top">
            <th scope="row">
                <label for="smtp_username">
                    <?php _e('SMTP Username', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="smtp_username" type="text" id="smtp_username" value="<?php echo esc_attr($data['em_smtp_username']); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('SMTP Username.', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="smtp_password">
                    <?php _e('SMTP Password (Token)', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="smtp_password" type="password" id="smtp_password" value="" class="regular-text code">
                <p class="description">
                    <?php _e('Your SMTP Password (The saved password is hidden for security reasons. Leave this field empty if you don\'t want to change the saved password while updating other settings).', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="type_of_encryption">
                    <?php _e('Encryption', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <select name="encryption" id="encryption">
                    <option value="tls" <?php echo selected( $data['em_smtp_encryption'], 'tls', false );?>><?php _e('STARTTLS', 'em-smtp-relay');?></option>
                    <option value="ssl" <?php echo selected( $data['em_smtp_encryption'], 'ssl', false );?>><?php _e('SSL', 'em-smtp-relay');?></option>
                </select>
                <p class="description">
                    <?php _e('The encryption method to be used for sending emails (recommended: STARTTLS).', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="from_email">
                    <?php _e('From Email Address', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="from_email" type="text" id="from_email" value="<?php echo esc_attr($data['em_smtp_from_email']); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('The email address to be used as the \'From\' address if none is provided to the mail function. The domain of From Address should be validated in Emercury SMTP panel, Sending Domains section.', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="from_name">
                    <?php _e('From Name', 'em-smtp-relay');?>
                </label>
            </th>
            <td>
                <input name="from_name" type="text" id="from_name" value="<?php echo esc_attr($data['em_smtp_from_name']); ?>" class="regular-text code">
                <p class="description">
                    <?php _e('The name to be used as the \'From\' name if none is provided to the mail function.', 'em-smtp-relay');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="force_from_address"><?php _e('Force From Address', 'em-smtp-relay');?></label>
            </th>
            <td>
                <input name="force_from_address" type="checkbox" id="force_from_address" <?php checked($data['em_smtp_force_from_address'], 1); ?> value="1">
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