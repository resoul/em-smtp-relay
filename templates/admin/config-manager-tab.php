<div class="em-smtp-config-manager">
    <div class="em-smtp-section">
        <h2><?php _e('Export Configuration', 'em-smtp-relay'); ?></h2>
        <p class="description">
            <?php _e('Export your current SMTP configuration to a JSON file. Note: Password will not be included for security reasons.', 'em-smtp-relay'); ?>
        </p>

        <form method="post" action="">
            <?php wp_nonce_field('em_smtp_relay_export_config'); ?>
            <p class="submit">
                <input
                    type="submit"
                    name="em_smtp_relay_export_config"
                    class="button button-primary"
                    value="<?php _e('Export Configuration', 'em-smtp-relay'); ?>"
                />
            </p>
        </form>
    </div>

    <hr>

    <div class="em-smtp-section">
        <h2><?php _e('Import Configuration', 'em-smtp-relay'); ?></h2>
        <p class="description">
            <?php _e('Import SMTP configuration from a previously exported JSON file.', 'em-smtp-relay'); ?>
        </p>

        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('em_smtp_relay_import_config'); ?>

            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row">
                        <label for="config_file">
                            <?php _e('Configuration File', 'em-smtp-relay'); ?>
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
                            <?php _e('Select a JSON configuration file (max 1MB)', 'em-smtp-relay'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="overwrite_password">
                            <?php _e('Overwrite Password', 'em-smtp-relay'); ?>
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
                            <?php _e('If unchecked, the current password will be preserved.', 'em-smtp-relay'); ?>
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
                    value="<?php _e('Import Configuration', 'em-smtp-relay'); ?>"
                />
            </p>
        </form>
    </div>
</div>

<style>
    .em-smtp-config-manager {
        max-width: 800px;
        padding-top: 15px;
    }

    .em-smtp-section {
        margin-bottom: 30px;
    }

    .em-smtp-section h2 {
        margin-top: 0;
    }

    .em-smtp-config-manager hr {
        margin: 40px 0;
        border: 0;
        border-top: 1px solid #dcdcde;
    }
</style>