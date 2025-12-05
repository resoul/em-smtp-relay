<?php

declare(strict_types=1);

namespace Emercury\Smtp\Config;

class SettingKeys
{
    // General Settings Keys
    public const USERNAME = 'em_smtp_username';
    public const PASSWORD = 'em_smtp_password';
    public const ENCRYPTION = 'em_smtp_encryption';
    public const FROM_EMAIL = 'em_smtp_from_email';
    public const FROM_NAME = 'em_smtp_from_name';
    public const FORCE_FROM_ADDRESS = 'em_smtp_force_from_address';
    public const HOST = 'em_smtp_host';
    public const AUTH = 'em_smtp_auth';
    public const PORT = 'em_smtp_port';

    // Advanced Settings Keys
    public const REPLY_TO_EMAIL = 'em_smtp_reply_to_email';
    public const REPLY_TO_NAME = 'em_smtp_reply_to_name';
    public const FORCE_REPLY_TO = 'em_smtp_force_reply_to';
    public const CC_EMAIL = 'em_smtp_cc_email';
    public const CC_NAME = 'em_smtp_cc_name';
    public const FORCE_CC = 'em_smtp_force_cc';
    public const BCC_EMAIL = 'em_smtp_bcc_email';
    public const BCC_NAME = 'em_smtp_bcc_name';
    public const FORCE_BCC = 'em_smtp_force_bcc';
}