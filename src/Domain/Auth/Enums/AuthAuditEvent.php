<?php

declare(strict_types=1);

namespace Domain\Auth\Enums;

enum AuthAuditEvent: string
{
    case LoginSuccess = 'login_success';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case TokenRefreshed = 'token_refreshed';
    case TokenReuseDetected = 'token_reuse_detected';
    case MfaEnabled = 'mfa_enabled';
    case MfaVerified = 'mfa_verified';
    case MfaFailed = 'mfa_failed';
    case AccountLocked = 'account_locked';
}
