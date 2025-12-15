<?php
/**
 * @var $summary array
 * @var $recentLogs array
 * @var $settings SmtpSettingsDTO
 * @var $isConfigured boolean
 * @var $l10n LocalizationInterface
 */

use Emercury\Smtp\Contracts\LocalizationInterface;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;

?>
<div class="em-smtp-widget">
    <div class="em-smtp-widget-header">
        <span class="em-smtp-status <?php echo $isConfigured ? 'active' : 'inactive'; ?>">
            <?php echo $isConfigured
                ? $l10n->esc('Active')
                : $l10n->esc('Not Configured'); ?>
        </span>
    </div>

    <?php if ($isConfigured): ?>
        <div class="em-smtp-stats">
            <div class="em-smtp-stat-box success">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['today']['sent']); ?></span>
                <span class="em-smtp-stat-label"><?php echo $l10n->esc('Sent Today'); ?></span>
            </div>
            <div class="em-smtp-stat-box error">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['today']['failed']); ?></span>
                <span class="em-smtp-stat-label"><?php echo $l10n->esc('Failed Today'); ?></span>
            </div>
            <div class="em-smtp-stat-box">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['today']['success_rate']); ?>%</span>
                <span class="em-smtp-stat-label"><?php echo $l10n->esc('Success Rate'); ?></span>
            </div>
        </div>

        <!-- Weekly Stats -->
        <div class="em-smtp-stats">
            <div class="em-smtp-stat-box">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['week']['total']); ?></span>
                <span class="em-smtp-stat-label"><?php echo $l10n->esc('This Week'); ?></span>
            </div>
            <div class="em-smtp-stat-box">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['month']['total']); ?></span>
                <span class="em-smtp-stat-label"><?php echo $l10n->esc('This Month'); ?></span>
            </div>
            <div class="em-smtp-stat-box">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['month']['success_rate']); ?>%</span>
                <span class="em-smtp-stat-label"><?php echo $l10n->esc('Monthly Rate'); ?></span>
            </div>
        </div>

        <?php if (!empty($recentLogs)): ?>
            <div class="em-smtp-recent-logs">
                <h4><?php echo $l10n->esc('Recent Emails'); ?></h4>
                <?php foreach ($recentLogs as $log): ?>
                    <div class="em-smtp-log-item">
                        <span class="em-smtp-log-status <?php echo esc_attr($log['status']); ?>"></span>
                        <span class="em-smtp-log-subject">
                            <?php
                            $subject = isset($log['subject']) ? $log['subject'] : '';
                            echo $l10n->esc(wp_trim_words($subject, 6, '...'));
                            ?>
                        </span>
                        <br>
                        <span class="em-smtp-log-time">
                            <?php
                            echo esc_html(
                                sprintf(
                                    $l10n->t('To: %s • %s'),
                                    wp_trim_words($log['recipient'], 2, '...'),
                                    human_time_diff(strtotime($log['created_at']), current_time('timestamp'))
                                ) . ' dashboard-widget.php' . $l10n->t('ago')
                            );
                            ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="em-smtp-no-data">
                <p><?php echo $l10n->esc('No emails sent yet'); ?></p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="em-smtp-no-data">
            <p><?php echo $l10n->esc('Please configure SMTP settings first'); ?></p>
        </div>
    <?php endif; ?>

    <div class="em-smtp-widget-footer">
        <a href="<?php echo esc_url(admin_url('options-general.php?page=em-smtp-relay-settings')); ?>" class="button button-small">
            <?php echo $l10n->esc('View Settings'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('options-general.php?page=em-smtp-statistics')); ?>" class="button button-small">
            <?php echo $l10n->esc('View Stats'); ?>
        </a>
        <?php if ($isConfigured): ?>
            <a href="<?php echo esc_url(admin_url('options-general.php?page=em-smtp-relay-settings&tab=test-email')); ?>" class="button button-small">
                <?php echo $l10n->esc('Send Test Email'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>