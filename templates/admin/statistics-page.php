<div class="wrap em-smtp-statistics">
    <h1><?php _e('Email Statistics', 'em-smtp-relay'); ?></h1>

    <!-- Key Metrics -->
    <div class="em-smtp-metrics-grid">
        <div class="em-smtp-metric-card">
            <div class="metric-icon">📧</div>
            <div class="metric-content">
                <h3><?php _e('Today', 'em-smtp-relay'); ?></h3>
                <div class="metric-value"><?php echo esc_html($metrics['today']['sent']); ?></div>
                <div class="metric-label"><?php _e('Emails Sent', 'em-smtp-relay'); ?></div>
            </div>
        </div>

        <div class="em-smtp-metric-card">
            <div class="metric-icon">📊</div>
            <div class="metric-content">
                <h3><?php _e('Success Rate', 'em-smtp-relay'); ?></h3>
                <div class="metric-value"><?php echo esc_html($metrics['today']['success_rate']); ?>%</div>
                <div class="metric-label"><?php _e('Today', 'em-smtp-relay'); ?></div>
            </div>
        </div>

        <div class="em-smtp-metric-card">
            <div class="metric-icon">📅</div>
            <div class="metric-content">
                <h3><?php _e('This Week', 'em-smtp-relay'); ?></h3>
                <div class="metric-value"><?php echo esc_html($metrics['week']['total']); ?></div>
                <div class="metric-label"><?php _e('Total Emails', 'em-smtp-relay'); ?></div>
            </div>
        </div>

        <div class="em-smtp-metric-card">
            <div class="metric-icon">📈</div>
            <div class="metric-content">
                <h3><?php _e('This Month', 'em-smtp-relay'); ?></h3>
                <div class="metric-value"><?php echo esc_html($metrics['month']['total']); ?></div>
                <div class="metric-label"><?php _e('Total Emails', 'em-smtp-relay'); ?></div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="em-smtp-charts">
        <div class="em-smtp-chart-container">
            <h2><?php _e('Last 30 Days', 'em-smtp-relay'); ?></h2>
            <canvas id="dailyChart"></canvas>
        </div>

        <div class="em-smtp-chart-container">
            <h2><?php _e('Last 7 Days (Hourly)', 'em-smtp-relay'); ?></h2>
            <canvas id="hourlyChart"></canvas>
        </div>
    </div>

    <!-- Detailed Stats -->
    <div class="em-smtp-detailed-stats">
        <h2><?php _e('Detailed Statistics', 'em-smtp-relay'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th><?php _e('Period', 'em-smtp-relay'); ?></th>
                <th><?php _e('Sent', 'em-smtp-relay'); ?></th>
                <th><?php _e('Failed', 'em-smtp-relay'); ?></th>
                <th><?php _e('Total', 'em-smtp-relay'); ?></th>
                <th><?php _e('Success Rate', 'em-smtp-relay'); ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><?php _e('Today', 'em-smtp-relay'); ?></td>
                <td><?php echo esc_html($metrics['today']['sent']); ?></td>
                <td><?php echo esc_html($metrics['today']['failed']); ?></td>
                <td><?php echo esc_html($metrics['today']['total']); ?></td>
                <td><?php echo esc_html($metrics['today']['success_rate']); ?>%</td>
            </tr>
            <tr>
                <td><?php _e('This Week', 'em-smtp-relay'); ?></td>
                <td><?php echo esc_html($metrics['week']['sent']); ?></td>
                <td><?php echo esc_html($metrics['week']['failed']); ?></td>
                <td><?php echo esc_html($metrics['week']['total']); ?></td>
                <td><?php echo esc_html($metrics['week']['success_rate']); ?>%</td>
            </tr>
            <tr>
                <td><?php _e('This Month', 'em-smtp-relay'); ?></td>
                <td><?php echo esc_html($metrics['month']['sent']); ?></td>
                <td><?php echo esc_html($metrics['month']['failed']); ?></td>
                <td><?php echo esc_html($metrics['month']['total']); ?></td>
                <td><?php echo esc_html($metrics['month']['success_rate']); ?>%</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>