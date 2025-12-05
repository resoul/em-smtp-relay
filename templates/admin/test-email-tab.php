<form method="post" action="">
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
                <label for="debug"><?php _e('Debug', 'em-smtp-relay');?></label>
            </th>
            <td>
                <input name="debug" type="checkbox" id="debug" value="1">
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