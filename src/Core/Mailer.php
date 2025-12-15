<?php

declare(strict_types=1);

namespace Emercury\Smtp\Core;

use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Contracts\EmailLoggerInterface;
use Emercury\Smtp\Contracts\EncryptionInterface;
use Emercury\Smtp\Contracts\ConfigInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use WP_Error;

class Mailer
{
    private EncryptionInterface $encryption;
    private ConfigInterface $config;
    private EmailLoggerInterface $emailLogger;

    public function __construct(
        EncryptionInterface $encryption,
        ConfigInterface $config,
        EmailLoggerInterface $emailLogger
    ) {
        $this->encryption = $encryption;
        $this->config = $config;
        $this->emailLogger = $emailLogger;
    }

    public function sendMail($return, array $atts)
    {
        try {
            $settings = $this->getSettings();
            $advancedSettings = $this->config->getAdvancedSettings();
            $phpmailer = $this->configurePhpMailer($settings);


            $cc = $bcc = $reply_to = [];
            [$to, $subject, $message, $headers, $attachments] = $this->parseAttributes($atts);

            foreach ($headers as $header) {
                if (strpos($header, ':') === false) {
                    if (false !== stripos($header, 'boundary=' )) {
                        $parts    = preg_split('/boundary=/i', trim($header));
                        $boundary = trim(str_replace(["'", '"'], '', $parts[1]));
                    }
                    continue;
                }

                list($name, $content) = explode(':', trim($header), 2);
                $name = trim($name);
                $content = trim($content);

                switch (strtolower($name)) {
                    case 'from':
                        $bracket_pos = strpos($content, '<');
                        if (false !== $bracket_pos) {
                            if ($bracket_pos > 0) {
                                $from_name = substr($content, 0, $bracket_pos);
                                $from_name = str_replace('"', '', $from_name);
                                $from_name = trim($from_name);
                            }

                            $from_email = substr($content, $bracket_pos + 1);
                            $from_email = str_replace('>', '', $from_email);
                            $from_email = trim($from_email);
                        } else if ('' !== trim($content)) {
                            $from_email = trim($content);
                        }
                        break;
                    case 'cc':
                        $cc = array_merge((array) $cc, explode(',', $content));
                        break;
                    case 'content-type':
                        if (strpos($content, ';') !== false) {
                            list($type, $charset_content) = explode( ';', $content);
                            $content_type = trim($type);
                            if ( false !== stripos($charset_content, 'charset=')) {
                                $phpmailer->CharSet = trim( str_replace( array( 'charset=', '"' ), '', $charset_content));
                            } elseif ( false !== stripos( $charset_content, 'boundary=' ) ) {
                                $phpmailer->CharSet = '';
                                $boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset_content));
                            }
                        } else if ('' !== trim($content)) {
                            $content_type = trim($content);
                        }
                        break;
                    case 'reply-to':
                        $reply_to = array_merge((array) $reply_to, explode(',', $content));
                        break;
                    case 'bcc':
                        $bcc = array_merge((array) $bcc, explode(',', $content));
                        break;
                    default:
                        $headers[$name] = $content;
                        break;
                }
            }

            if (isset($headers['EM-SMTP-Debug']) && $headers['EM-SMTP-Debug'] === 'True') {
                $phpmailer->SMTPDebug = 4;
                $phpmailer->Debugoutput = function($str, $level) use (&$debugOutput) {
                    $debugOutput .= $str . "\n";
                };

                add_action('wp_mail_succeeded', function () use (&$debugOutput) {
                    set_transient('em_smtp_debug_info', $debugOutput, 60);
                });

                add_action('wp_mail_failed', function ($error) use (&$debugOutput) {
                    $debugOutput .= "Mail failed: " . print_r($error, true);
                    set_transient('em_smtp_debug_info', $debugOutput, 60);
                });
            }

            if (!isset($from_name)) {
                $from_name = $settings->fromName;
            }

            if (!isset($from_email)) {
                $from_email = $settings->fromEmail;
            }

            $from_email = apply_filters('wp_mail_from', $from_email);
            $from_name = apply_filters('wp_mail_from_name', $from_name);

            if ($settings->forceFromAddress) {
                $from_name = $settings->fromName;
                $from_email = $settings->fromEmail;
            }

            if (!isset($content_type)) {
                $content_type = 'text/plain';
            }

            $content_type = apply_filters('wp_mail_content_type', $content_type);
            $phpmailer->ContentType = $content_type;

            if ('text/html' === $content_type) {
                $phpmailer->isHTML(true);
            }

            $phpmailer->setFrom($from_email, $from_name, false);
            $phpmailer->Subject = $subject;
            $phpmailer->Body = $message;

            $address = compact( 'to', 'cc', 'bcc', 'reply_to');

            if (!empty($advancedSettings->replyToEmail)) {
                if ($advancedSettings->forceReplyTo) {
                    $address['reply_to'] = [];
                }
                $address['reply_to'][] = $this->createEmailHeader($advancedSettings->replyToEmail, $advancedSettings->replyToName);
            }

            if (!empty($advancedSettings->ccEmail)) {
                if ($advancedSettings->forceCc) {
                    $address['cc'] = [];
                }
                $address['cc'][] = $this->createEmailHeader($advancedSettings->ccEmail, $advancedSettings->ccName);
            }

            if (!empty($advancedSettings->bccEmail)) {
                if ($advancedSettings->forceBcc) {
                    $address['bcc'] = [];
                }
                $address['bcc'][] = $this->createEmailHeader($advancedSettings->bccEmail, $advancedSettings->bccName);
            }

            $recipients = [];
            foreach ($address as $header => $addresses) {
                if (empty($addresses)) {
                    continue;
                }

                foreach ($addresses as $contact) {
                    try {
                        $recipient_name = '';
                        if (preg_match('/(.*)<(.+)>/', $contact, $matches)) {
                            if (count($matches) === 3) {
                                $recipient_name = $matches[1];
                                $contact        = $matches[2];
                            }
                        }

                        switch ($header) {
                            case 'to':
                                $phpmailer->addAddress($contact, $recipient_name);
                                break;
                            case 'cc':
                                $recipients[] = $contact;
                                $phpmailer->addCc($contact, $recipient_name);
                                break;
                            case 'bcc':
                                $recipients[] = $contact;
                                $phpmailer->addBcc($contact, $recipient_name);
                                break;
                            case 'reply_to':
                                $phpmailer->addReplyTo($contact, $recipient_name);
                                break;
                        }
                    } catch (PHPMailer\PHPMailer\Exception $exception) {
                        continue;
                    }
                }
            }

            if (!empty($headers)) {
                if (false !== stripos($content_type, 'multipart') && !empty($boundary)) {
                    $phpmailer->addCustomHeader(sprintf( 'Content-Type: %s; boundary="%s"', $content_type, $boundary ) );
                }
            }

            $this->addAttachments($phpmailer, $attachments);
            do_action_ref_array('phpmailer_init', array(&$phpmailer));

            $result = $phpmailer->send();

            if ($result) {
                $this->emailLogger->logSent([
                    'to' => $atts['to'] ?? '',
                    'subject' => $atts['subject'] ?? '',
                ]);

                foreach ($recipients as $recipient) {
                    $this->emailLogger->logSent([
                        'to' => $recipient,
                        'subject' => $atts['subject'] ?? '',
                    ]);
                }

                do_action('wp_mail_succeeded', $atts);
            }

            return $result;
        } catch (\Exception $e) {
            $this->emailLogger->logFailed([
                'to' => $atts['to'] ?? '',
                'subject' => $atts['subject'] ?? '',
            ], $e->getMessage());

            do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $e->getMessage()));
            return false;
        }
    }

    private function parseAttributes(array $attributes): array
    {
        $to = $attributes['to'] ?? [];
        if (!is_array($to)) {
            $to = explode(',', $to);
        }

        $subject = $attributes['subject'] ?? '';
        $message = $attributes['message'] ?? '';

        $headers = $attributes['headers'] ?? [];
        if (!is_array($headers)) {
            $headers = explode("\n", str_replace("\r\n", "\n", $headers));
        }

        $attachments = $attributes['attachments'] ?? [];
        if (!is_array($attachments)) {
            $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
        }

        return [$to, $subject, $message, $headers, $attachments];
    }

    private function configurePhpMailer(SmtpSettingsDTO $settings): PHPMailer
    {
        global $phpmailer;

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

        $phpmailer->isSMTP();
        $phpmailer->SMTPAuth = true;
        $phpmailer->Host = $settings->smtpHost;
        $phpmailer->Username = $settings->smtpUsername;
        $password = $this->encryption->decrypt($settings->smtpPassword);

        if (empty($password)) {
            throw new \RuntimeException('Failed to decrypt SMTP password');
        }

        $phpmailer->Password = $password;
        $phpmailer->SMTPSecure = $settings->smtpEncryption;
        $phpmailer->Port = $this->config->getSmtpPort($settings->smtpEncryption);
        $phpmailer->CharSet = apply_filters('wp_mail_charset', get_bloginfo('charset'));

        return $phpmailer;
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

    private function createEmailHeader(string $email, string $name): string
    {
        if (empty($email)) {
            return '';
        }

        $email = sanitize_email($email);
        $name = sanitize_text_field($name);

        return empty($name) ? $email : "$name <$email>";
    }

    private function getSettings()
    {
        $settings = $this->config->getGeneralSettings();

        if (empty($settings->smtpUsername) || empty($settings->smtpPassword) || empty($settings->fromEmail)) {
            throw new \Exception('SMTP settings not configured');
        }

        return $settings;
    }
}