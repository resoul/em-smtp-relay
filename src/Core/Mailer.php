<?php

declare(strict_types=1);

namespace Emercury\Smtp\Core;

use Emercury\Smtp\Security\Encryption;
use Emercury\Smtp\Security\Validator;
use Emercury\Smtp\Config\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use WP_Error;

class Mailer
{
    private Encryption $encryption;
    private Validator $validator;
    private Config $config;

    public function __construct(
        Encryption $encryption,
        Validator $validator,
        Config $config
    ) {
        $this->encryption = $encryption;
        $this->validator = $validator;
        $this->config = $config;
    }

    public function sendMail($return, array $atts)
    {
        global $phpmailer;

        $this->initializeMailer($phpmailer);

        $settings = $this->config->getGeneralSettings();
        $advancedSettings = $this->config->getAdvancedSettings();

        if (!$this->validateSettings($settings)) {
            return false;
        }

        try {
            $this->configurePhpMailer($phpmailer, $settings);
            $this->prepareEmail($phpmailer, $atts, $settings, $advancedSettings);

            return $this->send($phpmailer, $atts);
        } catch (\Exception $e) {
            error_log('Emercury SMTP Error: ' . $e->getMessage());
            do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $e->getMessage()));
            return false;
        }
    }

    private function validateSettings(array $settings): bool
    {
        if (empty($settings['em_smtp_username']) ||
            empty($settings['em_smtp_password']) ||
            empty($settings['em_smtp_from_email'])) {

            do_action('wp_mail_failed', new WP_Error(
                'wp_mail_failed',
                'SMTP settings not configured'
            ));

            return false;
        }

        return true;
    }

    private function configurePhpMailer(PHPMailer $phpmailer, array $settings): void
    {
        $phpmailer->isSMTP();
        $phpmailer->SMTPAuth = true;
        $phpmailer->Host = Config::SMTP_HOST;
        $phpmailer->Username = $settings['em_smtp_username'];
        $password = $this->encryption->decrypt($settings['em_smtp_password']);

        if (empty($password)) {
            throw new \RuntimeException('Failed to decrypt SMTP password');
        }

        $phpmailer->Password = $this->encryption->decrypt($settings['em_smtp_password']);
        $phpmailer->SMTPSecure = $settings['em_smtp_encryption'];
        $phpmailer->Port = $this->config->getSmtpPort($settings['em_smtp_encryption']);
    }

    private function prepareEmail(
        PHPMailer $phpmailer,
        array $atts,
        array $settings,
        array $advancedSettings
    ): void {
        [$to, $subject, $message, $headers, $attachments] = $this->parseMailAttributes($atts);

        // Set basic properties
        $phpmailer->Subject = $subject;
        $phpmailer->Body = $message;
        $phpmailer->CharSet = apply_filters('wp_mail_charset', get_bloginfo('charset'));

        // Parse headers
        $parsedHeaders = $this->parseHeaders($headers);

        // Set content type
        $contentType = $parsedHeaders['content_type'] ?? 'text/plain';
        if ($contentType === 'text/html') {
            $phpmailer->isHTML(true);
        }

        // Set From
        $fromEmail = $settings['em_smtp_from_email'];
        $fromName = $settings['em_smtp_from_name'];

        if (empty($settings['em_smtp_force_from_address']) && isset($parsedHeaders['from'])) {
            [$fromName, $fromEmail] = $this->parseEmailHeader([$parsedHeaders['from']]);
        }

        $phpmailer->setFrom(
            apply_filters('wp_mail_from', $fromEmail),
            apply_filters('wp_mail_from_name', $fromName),
            false
        );

        // Add recipients
        $this->addRecipients($phpmailer, $to);

        // Add attachments
        $this->addAttachments($phpmailer, $attachments);

        // Handle Reply-To, CC, BCC
        $this->handleReplyTo($phpmailer, $parsedHeaders, $advancedSettings);
        $this->handleCc($phpmailer, $parsedHeaders, $advancedSettings);
        $this->handleBcc($phpmailer, $parsedHeaders, $advancedSettings);

        // Debug mode
        if (isset($parsedHeaders['em-smtp-debug']) && $parsedHeaders['em-smtp-debug'] === 'True') {
            $phpmailer->SMTPDebug = 2;
        }

        do_action_ref_array('phpmailer_init', [&$phpmailer]);
    }

    private function send(PHPMailer $phpmailer, array $atts): bool
    {
        try {
            $result = $phpmailer->send();

            if ($result) {
                do_action('wp_mail_succeeded', $atts);
            }

            return $result;
        } catch (PHPMailerException $e) {
            do_action('wp_mail_failed', new WP_Error(
                'wp_mail_failed',
                $e->getMessage(),
                ['phpmailer_exception_code' => $e->getCode()]
            ));

            return false;
        }
    }

    private function parseHeaders(array $headers): array
    {
        $parsed = [];

        foreach ($headers as $header) {
            if (strpos($header, ':') === false) {
                continue;
            }

            [$name, $content] = explode(':', trim($header), 2);
            $parsed[strtolower(trim($name))] = trim($content);
        }

        return $parsed;
    }

    private function handleReplyTo(
        PHPMailer $phpmailer,
        array $headers,
        array $advancedSettings
    ): void {
        $replyTo = $advancedSettings['em_smtp_reply_to_email'] ?? '';
        $replyToName = $advancedSettings['em_smtp_reply_to_name'] ?? '';

        if (empty($advancedSettings['em_smtp_force_reply_to']) && isset($headers['reply-to'])) {
            [$replyToName, $replyTo] = $this->parseEmailHeader([explode(',', $headers['reply-to'])]);
        }

        if (!empty($replyTo) && is_email($replyTo)) {
            $phpmailer->addReplyTo($replyTo, $replyToName);
        }
    }

    private function handleCc(
        PHPMailer $phpmailer,
        array $headers,
        array $advancedSettings
    ): void {
        $ccList = [];

        if (!empty($advancedSettings['em_smtp_force_cc'])) {
            $ccList = [$this->createEmailHeader(
                $advancedSettings['em_smtp_cc_email'] ?? '',
                $advancedSettings['em_smtp_cc_name'] ?? ''
            )];
        } elseif (isset($headers['cc'])) {
            $ccList = explode(',', $headers['cc']);
        }

        foreach ($ccList as $cc) {
            [$name, $email] = $this->parseEmailHeader([$cc]);
            if (!empty($email) && is_email($email)) {
                $phpmailer->addCc($email, $name);
            }
        }
    }

    private function handleBcc(
        PHPMailer $phpmailer,
        array $headers,
        array $advancedSettings
    ): void {
        $bccList = [];

        if (!empty($advancedSettings['em_smtp_force_bcc'])) {
            $bccList = [$this->createEmailHeader(
                $advancedSettings['em_smtp_bcc_email'] ?? '',
                $advancedSettings['em_smtp_bcc_name'] ?? ''
            )];
        } elseif (isset($headers['bcc'])) {
            $bccList = explode(',', $headers['bcc']);
        }

        foreach ($bccList as $bcc) {
            [$name, $email] = $this->parseEmailHeader([$bcc]);
            if (!empty($email) && is_email($email)) {
                $phpmailer->addBcc($email, $name);
            }
        }
    }

    private function addRecipients(PHPMailer $phpmailer, $to): void
    {
        if (!is_array($to)) {
            $to = [$to];
        }

        foreach ($to as $recipient) {
            [$name, $email] = $this->parseEmailHeader([$recipient]);
            if (!empty($email) && is_email($email)) {
                $phpmailer->addAddress($email, $name);
            }
        }
    }

    private function addAttachments(PHPMailer $phpmailer, array $attachments): void
    {
        foreach ($attachments as $filename => $attachment) {
            $attachment = sanitize_text_field($attachment);

            if (file_exists($attachment)) {
                $filename = is_string($filename) ? sanitize_file_name($filename) : '';
                $phpmailer->addAttachment($attachment, $filename);
            }
        }
    }

    private function parseMailAttributes(array $atts): array
    {
        $to = $atts['to'] ?? [];
        if (!is_array($to)) {
            $to = explode(',', $to);
        }

        $subject = $atts['subject'] ?? '';
        $message = $atts['message'] ?? '';

        $headers = $atts['headers'] ?? [];
        if (!is_array($headers)) {
            $headers = explode("\n", str_replace("\r\n", "\n", $headers));
        }

        $attachments = $atts['attachments'] ?? [];
        if (!is_array($attachments)) {
            $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
        }

        return [$to, $subject, $message, $headers, $attachments];
    }

    private function createEmailHeader(string $email, string $name): string
    {
        if (empty($email)) {
            return '';
        }

        $email = sanitize_email($email);
        $name = sanitize_text_field($name);

        return empty($name) ? $email : "$name <$email>";
    }

    private function parseEmailHeader(array $content): array
    {
        $name = '';
        $email = '';

        foreach ($content as $item) {
            if (empty($item)) {
                continue;
            }

            if (preg_match('/(.*)<(.+)>/', $item, $matches)) {
                if (count($matches) === 3) {
                    $name = sanitize_text_field(trim($matches[1]));
                    $email = sanitize_email(trim($matches[2]));
                    break;
                }
            } else {
                $email = sanitize_email(trim($item));
                break;
            }
        }

        return [$name, $email];
    }

    private function initializeMailer(&$phpmailer): void
    {
        if (!($phpmailer instanceof PHPMailer)) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

            $phpmailer = new PHPMailer(true);
            $phpmailer::$validator = static function ($email) {
                return (bool) is_email($email);
            };
        }

        $phpmailer->clearAllRecipients();
        $phpmailer->clearAttachments();
        $phpmailer->clearCustomHeaders();
        $phpmailer->clearReplyTos();
        $phpmailer->Body = '';
        $phpmailer->AltBody = '';
    }
}