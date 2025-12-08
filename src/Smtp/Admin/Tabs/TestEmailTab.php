<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\App\Localization;
use Emercury\Smtp\App\RequestHandler;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;
use Emercury\Smtp\Contracts\RateLimiterInterface;

class TestEmailTab
{
    private NonceManagerInterface $nonceManager;
    private ConfigInterface $config;
    private RateLimiterInterface $rateLimiter;
    private AdminNotifier $notifier;

    private Localization $localization;
    private RequestHandler $request;

    public function __construct(
        NonceManagerInterface $nonceManager,
        ConfigInterface $config,
        RateLimiterInterface $rateLimiter,
        Localization $localization,
        AdminNotifier $notifier,
        RequestHandler $request
    ) {
        $this->nonceManager = $nonceManager;
        $this->config = $config;
        $this->rateLimiter = $rateLimiter;
        $this->notifier = $notifier;
        $this->localization = $localization;
        $this->request = $request;
        $this->init();
    }

    public function init(): void
    {
        add_action('admin_init', function () {
            if (isset($_POST['em_smtp_relay_send_test_email'])) {
                $this->handleTestEmail();
            }
        });
    }

    public function render(): void
    {
        $uploadedFiles = $this->getUploadedTestFiles();

        include EM_SMTP_PATH . 'templates/admin/test-email-tab.php';
    }

    private function handleTestEmail(): void
    {
        if (!$this->nonceManager->verifyWithCapability('em_smtp_relay_test_email')) {
            wp_die(
                $this->localization->escHtml('Security check failed. Please try again.'),
                $this->localization->escHtml('Security Error'),
                ['response' => 403]
            );
        }

        $userId = get_current_user_id();

        if (!$this->rateLimiter->checkLimit('test_email_' . $userId)) {
            $this->notifier->addError(
                $this->localization->t('Too many test emails sent. Please wait before trying again.')
            );
            return;
        }

        $to = $this->request->getEmail('em_smtp_relay_to_email');
        $subject = $this->request->getString('em_smtp_relay_email_subject', 'Emercury SMTP Test Email');
        $message = $this->request->getHtml(
            'em_smtp_relay_email_body',
            'If you receive this email, Emercury SMTP is working correctly.'
        );

        if (!$this->validateRecipient($to)) {
            $this->notifier->addError(
                $this->localization->t('Please enter a valid recipient email address.')
            );
            return;
        }

        if (!$this->validateSmtpConfiguration()) {
            $this->notifier->addError(
                $this->localization->t('Please setup SMTP settings first.')
            );
            return;
        }

        $attachments = $this->handleAttachments();

        $this->sendTestEmail($to, $subject, $message, $attachments);
    }

    private function validateRecipient(string $to): bool
    {
        return !empty($to) && is_email($to);
    }

    private function validateSmtpConfiguration(): bool
    {
        $data = $this->config->getGeneralSettings();

        return !empty($data->smtpUsername)
            && !empty($data->smtpPassword)
            && !empty($data->fromEmail);
    }

    /**
     * @param array<int, string>|string[] $attachments
     */
    private function sendTestEmail(string $to, string $subject, string $message, array $attachments = []): void
    {
        $headers = [];

        if (!empty($_POST['debug'])) {
            $headers[] = 'EM-SMTP-Debug: True';
        }

        if (wp_mail($to, $subject, $message, $headers, $attachments)) {
            $attachmentInfo = '';

            if (!empty($attachments)) {
                $attachmentInfo = sprintf(
                    $this->localization->t(' with %d attachment(s)'),
                    count($attachments)
                );
            }

            $this->notifier->addSuccess(
                $this->localization->t('Test email has been sent successfully!') . $attachmentInfo
            );
        } else {
            $this->notifier->addError(
                $this->localization->t('Failed to send test email. Please check your settings.')
            );
        }
    }

    private function handleAttachments(): array
    {
        $attachments = [];

        if (isset($_FILES['test_attachments']) && !empty($_FILES['test_attachments']['name'][0])) {
            $files = $_FILES['test_attachments'];
            $uploadDir = $this->getTestAttachmentsDir();

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = sanitize_file_name($files['name'][$i]);
                    $tmpName = $files['tmp_name'][$i];
                    $fileType = $files['type'][$i];
                    $fileSize = $files['size'][$i];

                    if (!$this->validateAttachment($fileType, $fileSize)) {
                        $this->notifier->addError(
                            sprintf(
                                $this->localization->t('File %s is invalid or too large (max 5MB).'),
                                $filename
                            )
                        );
                        continue;
                    }

                    $filepath = $uploadDir . '/' . $filename;

                    if (move_uploaded_file($tmpName, $filepath)) {
                        $attachments[] = $filepath;
                    }
                }
            }
        }

        if (isset($_POST['existing_attachments']) && is_array($_POST['existing_attachments'])) {
            $uploadDir = $this->getTestAttachmentsDir();

            foreach ($_POST['existing_attachments'] as $filename) {
                $filename = sanitize_file_name($filename);
                $filepath = $uploadDir . '/' . $filename;

                if (file_exists($filepath)) {
                    $attachments[] = $filepath;
                }
            }
        }

        return $attachments;
    }

    private function validateAttachment(string $fileType, int $fileSize): bool
    {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if (!in_array($fileType, $allowedMimeTypes, true)) {
            return false;
        }

        if ($fileSize > 5242880) {
            return false;
        }

        return true;
    }

    private function getTestAttachmentsDir(): string
    {
        $uploadDir = wp_upload_dir();
        $testAttachmentsDir = $uploadDir['basedir'] . '/em-smtp-test-attachments';

        if (!file_exists($testAttachmentsDir)) {
            wp_mkdir_p($testAttachmentsDir);

            $htaccess = $testAttachmentsDir . '/.htaccess';

            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'deny from all');
            }
        }

        return $testAttachmentsDir;
    }

    private function getUploadedTestFiles(): array
    {
        $uploadDir = $this->getTestAttachmentsDir();
        $files = [];

        if (is_dir($uploadDir)) {
            $items = scandir($uploadDir);

            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item === '.htaccess') {
                    continue;
                }

                $filepath = $uploadDir . '/' . $item;

                if (is_file($filepath)) {
                    $files[] = [
                        'name' => $item,
                        'size' => filesize($filepath),
                        'date' => date('Y-m-d H:i:s', filemtime($filepath)),
                    ];
                }
            }
        }

        return $files;
    }

    public function deleteTestAttachment(): void
    {
        check_ajax_referer('em_smtp_delete_attachment', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => $this->localization->t('Permission denied')]);
        }

        $filename = sanitize_file_name($_POST['filename'] ?? '');

        if (empty($filename)) {
            wp_send_json_error(['message' => $this->localization->t('Invalid filename')]);
        }

        $uploadDir = $this->getTestAttachmentsDir();
        $filepath = $uploadDir . '/' . $filename;

        if (file_exists($filepath) && unlink($filepath)) {
            wp_send_json_success(['message' => $this->localization->t('File deleted successfully')]);
        } else {
            wp_send_json_error(['message' => $this->localization->t('Failed to delete file')]);
        }
    }
}
