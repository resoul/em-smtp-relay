<?php
/**
 * @var $data AdvancedSettingsDTO
 * @var $l10n LocalizationInterface
 */

use Emercury\Smtp\Contracts\LocalizationInterface;
use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;

?>
<form method="post" action="">
    <?php wp_nonce_field('em_smtp_relay_advanced_settings'); ?>
    <table class="form-table">
        <tbody>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_reply_to_email">
                    <?php echo $l10n->t('Reply To Email Address');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_reply_to_email" type="text" id="em_smtp_relay_reply_to_email" value="<?php echo esc_attr($data->replyToEmail); ?>" class="regular-text code">
                <p class="description">
                    <?php echo $l10n->t('The email address to be used as the \'Reply To\' address if none is provided to the mail function.');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_reply_to_name">
                    <?php echo $l10n->t('Reply To Name');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_reply_to_name" type="text" id="em_smtp_relay_reply_to_name" value="<?php echo esc_attr($data->replyToName); ?>" class="regular-text code">
                <p class="description">
                    <?php echo $l10n->t('The name to be used as the \'Reply To\' name if none is provided to the mail function.');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_force_reply_to"><?php echo $l10n->t('Force Reply To Address');?></label>
            </th>
            <td>
                <input name="em_smtp_relay_force_reply_to" type="checkbox" id="em_smtp_relay_force_reply_to" <?php checked($data->forceReplyTo, 1); ?> value="1">
                <p class="description">
                    <?php echo $l10n->t('The "Reply To" address specified in the settings will apply to all outgoing email messages.'); ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_cc_email">
                    <?php echo $l10n->t('Cc Email Address');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_cc_email" type="text" id="em_smtp_relay_cc_email" value="<?php echo esc_attr($data->ccEmail); ?>" class="regular-text code">
                <p class="description">
                    <?php echo $l10n->t('The email address to be used as the \'Cc\' address if none is provided to the mail function.');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_cc_name">
                    <?php echo $l10n->t('Cc Name');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_cc_name" type="text" id="em_smtp_relay_cc_name" value="<?php echo esc_attr($data->ccName); ?>" class="regular-text code">
                <p class="description">
                    <?php echo $l10n->t('The name to be used as the \'Cc\' name if none is provided to the mail function.');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_force_cc"><?php echo $l10n->t('Force Cc Address');?></label>
            </th>
            <td>
                <input name="em_smtp_relay_force_cc" type="checkbox" id="em_smtp_relay_force_cc" <?php checked($data->forceCc, 1); ?> value="1">
                <p class="description">
                    <?php echo $l10n->t('The "Cc" address specified in the settings will apply to all outgoing email messages.'); ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_bcc_email">
                    <?php echo $l10n->t('Bcc Email Address');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_bcc_email" type="text" id="em_smtp_relay_bcc_email" value="<?php echo esc_attr($data->bccEmail); ?>" class="regular-text code">
                <p class="description">
                    <?php echo $l10n->t('The email address to be used as the \'Bcc\' address if none is provided to the mail function.');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_bcc_name">
                    <?php echo $l10n->t('Bcc Name');?>
                </label>
            </th>
            <td>
                <input name="em_smtp_relay_bcc_name" type="text" id="em_smtp_relay_bcc_name" value="<?php echo esc_attr($data->bccName); ?>" class="regular-text code">
                <p class="description">
                    <?php echo $l10n->t('The name to be used as the \'Bcc\' name if none is provided to the mail function.');?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label for="em_smtp_relay_force_bcc"><?php echo $l10n->t('Force Bcc Address');?></label>
            </th>
            <td>
                <input name="em_smtp_relay_force_bcc" type="checkbox" id="em_smtp_relay_force_bcc" <?php checked($data->forceBcc, 1); ?> value="1">
                <p class="description">
                    <?php echo $l10n->t('The "Bcc" address specified in the settings will apply to all outgoing email messages.'); ?>
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
            value="<?php echo $l10n->t('Update')?>"
        />
    </p>
</form>