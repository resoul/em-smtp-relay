<?php
/**
 * @var $l10n LocalizationInterface
 */

use Emercury\Smtp\Contracts\LocalizationInterface;
?>
<div class="em-smtp-config-manager">
    <div class="em-smtp-section">
        <h2><?php echo $l10n->esc('Export Configuration'); ?></h2>
        <p class="description">
            <?php echo $l10n->esc('Export your current SMTP configuration to a JSON file. Note: Password will not be included for security reasons.'); ?>
        </p>

        <form method="post" action="">
            <?php wp_nonce_field('em_smtp_relay_export_config'); ?>
            <p class="submit">
                <input
                    type="submit"
                    name="em_smtp_relay_export_config"
                    class="button button-primary"
                    value="<?php echo $l10n->esc('Export Configuration'); ?>"
                />
            </p>
        </form>
    </div>

    <hr>

    <div class="em-smtp-section">
        <h2><?php echo $l10n->esc('Import Configuration'); ?></h2>
        <p class="description">
            <?php echo $l10n->esc('Import SMTP configuration from a previously exported JSON file.'); ?>
        </p>

        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('em_smtp_relay_import_config'); ?>

            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row">
                        <label for="config_file">
                            <?php echo $l10n->esc('Configuration File'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="file"
                            name="config_file"
                            id="config_file"
                            accept=".json,application/json"
                            required
                        />
                        <p class="description">
                            <?php echo $l10n->esc('Select a JSON configuration file (max 1MB)'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="overwrite_password">
                            <?php echo $l10n->esc('Overwrite Password'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="checkbox"
                            name="overwrite_password"
                            id="overwrite_password"
                            value="1"
                        />
                        <p class="description">
                            <?php echo $l10n->esc('If unchecked, the current password will be preserved.'); ?>
                        </p>
                    </td>
                </tr>
                </tbody>
            </table>

            <p class="submit">
                <input
                    type="submit"
                    name="em_smtp_relay_import_config"
                    class="button button-primary"
                    value="<?php echo $l10n->esc('Import Configuration'); ?>"
                />
            </p>
        </form>
    </div>
</div>
