<div class="em-smtp-widget">
    <div class="em-smtp-widget-header">
        <span class="em-smtp-status <?php echo $isConfigured ? 'active' : 'inactive'; ?>">
            <?php echo $isConfigured
                ? esc_html__('Active', 'em-smtp-relay')
                : esc_html__('Not Configured', 'em-smtp-relay'); ?>
        </span>
    </div>

    <?php if ($isConfigured): ?>
        <!-- Statistics for Today -->
        <div class="em-smtp-stats">
            <div class="em-smtp-stat-box success">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['today']['sent']); ?></span>
                <span class="em-smtp-stat-label"><?php esc_html_e('Sent Today', 'em-smtp-relay'); ?></span>
            </div>
            <div class="em-smtp-stat-box error">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['today']['failed']); ?></span>
                <span class="em-smtp-stat-label"><?php esc_html_e('Failed Today', 'em-smtp-relay'); ?></span>
            </div>
            <div class="em-smtp-stat-box">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['today']['success_rate']); ?>%</span>
                <span class="em-smtp-stat-label"><?php esc_html_e('Success Rate', 'em-smtp-relay'); ?></span>
            </div>
        </div>

        <!-- Weekly Stats -->
        <div class="em-smtp-stats">
            <div class="em-smtp-stat-box">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['week']['total']); ?></span>
                <span class="em-smtp-stat-label"><?php esc_html_e('This Week', 'em-smtp-relay'); ?></span>
            </div>
            <div class="em-smtp-stat-box">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['month']['total']); ?></span>
                <span class="em-smtp-stat-label"><?php esc_html_e('This Month', 'em-smtp-relay'); ?></span>
            </div>
            <div class="em-smtp-stat-box">
                <span class="em-smtp-stat-value"><?php echo esc_html($summary['month']['success_rate']); ?>%</span>
                <span class="em-smtp-stat-label"><?php esc_html_e('Monthly Rate', 'em-smtp-relay'); ?></span>
            </div>
        </div>

        <!-- Recent Logs -->
        <?php if (!empty($recentLogs)): ?>
        <?php var_dump($recentLogs); ?>
            <div class="em-smtp-recent-logs">
                <h4><?php esc_html_e('Recent Emails', 'em-smtp-relay'); ?></h4>
                <?php foreach ($recentLogs as $log): ?>
                    <div class="em-smtp-log-item">
                        <span class="em-smtp-log-status <?php echo esc_attr($log['status']); ?>"></span>
                        <span class="em-smtp-log-subject">
                            <?php
                            $subject = isset($log['subject']) ? $log['subject'] : '';
                            echo esc_html(wp_trim_words($subject, 6, '...'));
                            ?>
                        </span>
                        <br>
                        <span class="em-smtp-log-time">
                            <?php
                            echo esc_html(
                                sprintf(
                                    __('To: %s • %s', 'em-smtp-relay'),
                                    wp_trim_words($log['recipient'], 2, '...'),
                                    human_time_diff(strtotime($log['created_at']), current_time('timestamp'))
                                ) . ' ' . __('ago', 'em-smtp-relay')
                            );
                            ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="em-smtp-no-data">
                <p><?php esc_html_e('No emails sent yet', 'em-smtp-relay'); ?></p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="em-smtp-no-data">
            <p><?php esc_html_e('Please configure SMTP settings first', 'em-smtp-relay'); ?></p>
        </div>
    <?php endif; ?>

    <div class="em-smtp-widget-footer">
        <a href="<?php echo esc_url(admin_url('options-general.php?page=em-smtp-relay-settings')); ?>" class="button button-small">
            <?php esc_html_e('View Settings', 'em-smtp-relay'); ?>
        </a>
        <?php if ($isConfigured): ?>
            <a href="<?php echo esc_url(admin_url('options-general.php?page=em-smtp-relay-settings&tab=test-email')); ?>" class="button button-small">
                <?php esc_html_e('Send Test Email', 'em-smtp-relay'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>