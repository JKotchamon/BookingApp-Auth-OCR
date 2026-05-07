<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/oauth-config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Build a configured PHPMailer instance from .env SMTP_* settings.
 */
function hbms_make_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = getenv('SMTP_HOST')      ?: 'smtp.gmail.com';
    $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('SMTP_USER')      ?: '';
    $mail->Password   = getenv('SMTP_PASS')      ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    $mail->SMTPDebug  = 0;
    $mail->CharSet    = 'UTF-8';

    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $mail->Username;
    $fromName  = getenv('SMTP_FROM_NAME')  ?: 'HBMS Hotel Booking';
    $mail->setFrom($fromEmail, $fromName);
    $mail->addReplyTo($fromEmail, $fromName);

    return $mail;
}

/**
 * Send a "Set your local password" email containing a one-time link.
 *
 * @return array{ok:bool,error?:string}
 */
function hbms_send_set_password_email(string $toEmail, string $toName, string $token): array
{
    $appUrl  = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
    $link    = $appUrl . '/set-password.php?token=' . urlencode($token);
    $expires = '30 minutes';

    $safeName = htmlspecialchars($toName !== '' ? $toName : 'there', ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
<!doctype html>
<html><body style="font-family:Arial,Helvetica,sans-serif;background:#f5f6f8;padding:24px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;border:1px solid #e5e7eb;">
    <tr><td style="padding:24px 28px;border-bottom:1px solid #eef0f3;">
      <h2 style="margin:0;color:#1f2937;">Set your password</h2>
    </td></tr>
    <tr><td style="padding:24px 28px;color:#374151;font-size:15px;line-height:1.55;">
      <p>Hi {$safeName},</p>
      <p>You're receiving this email because you signed in to <strong>HBMS Hotel Booking</strong> with Google
         and chose to set a password for local login.</p>
      <p style="text-align:center;margin:28px 0;">
        <a href="{$safeLink}"
           style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;
                  padding:12px 22px;border-radius:6px;font-weight:600;">Set my password</a>
      </p>
      <p style="font-size:13px;color:#6b7280;">
        This link expires in {$expires} and can only be used once.<br>
        If you didn't request this, you can safely ignore this email.
      </p>
      <p style="font-size:12px;color:#9ca3af;word-break:break-all;">
        Or copy this URL into your browser:<br>{$safeLink}
      </p>
    </td></tr>
  </table>
</body></html>
HTML;

    $text = "Hi {$toName},\n\n"
          . "You requested to set a password for HBMS Hotel Booking.\n"
          . "Open this link to set it (expires in {$expires}):\n\n"
          . "{$link}\n\n"
          . "If you didn't request this, ignore this email.\n";

    try {
        $mail = hbms_make_mailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Set your password for HBMS Hotel Booking';
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        return ['ok' => true];
    } catch (PHPMailerException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Generate, persist, and return a fresh set-password token for a user.
 * Invalidates any prior unused tokens for the same user.
 */
function hbms_create_set_password_token(PDO $dbh, int $userId, int $ttlMinutes = 30): string
{
    $invalidate = $dbh->prepare(
        "UPDATE tbl_password_set_tokens
         SET UsedAt = NOW()
         WHERE UserID = :uid AND UsedAt IS NULL"
    );
    $invalidate->execute([':uid' => $userId]);

    $token   = bin2hex(random_bytes(32));
    $expires = (new DateTime("+{$ttlMinutes} minutes"))->format('Y-m-d H:i:s');

    $ins = $dbh->prepare(
        "INSERT INTO tbl_password_set_tokens (Token, UserID, ExpiresAt)
         VALUES (:token, :uid, :exp)"
    );
    $ins->execute([
        ':token' => $token,
        ':uid'   => $userId,
        ':exp'   => $expires,
    ]);

    return $token;
}

/**
 * Create (or refresh) an account-link verification token used in Case 2:
 * existing local user → consenting to attach an OAuth provider.
 *
 * Snapshots the freshly-fetched OAuth profile so the link can be applied
 * later from any browser, even after the OAuth session is gone.
 *
 * @param array{
 *   provider: string,
 *   provider_user_id: string,
 *   provider_email: string,
 *   full_name?: ?string,
 *   photo_path?: ?string,
 *   date_of_birth?: ?string
 * } $profile
 */
function hbms_create_account_link_token(PDO $dbh, int $userId, array $profile, int $ttlMinutes = 30): string
{
    $invalidate = $dbh->prepare(
        "UPDATE tbl_email_verifications
         SET UsedAt = NOW()
         WHERE UserID = :uid AND Provider = :prov AND UsedAt IS NULL"
    );
    $invalidate->execute([
        ':uid'  => $userId,
        ':prov' => $profile['provider'],
    ]);

    $token   = bin2hex(random_bytes(32));
    $expires = (new DateTime("+{$ttlMinutes} minutes"))->format('Y-m-d H:i:s');

    $ins = $dbh->prepare(
        "INSERT INTO tbl_email_verifications
            (Token, UserID, Provider, ProviderUserID, ProviderEmail,
             EmailVerified, FullName, PhotoPath, DateOfBirth, ExpiresAt)
         VALUES
            (:token, :uid, :prov, :pid, :pemail,
             0, :fname, :photo, :dob, :exp)"
    );
    $ins->execute([
        ':token'  => $token,
        ':uid'    => $userId,
        ':prov'   => $profile['provider'],
        ':pid'    => $profile['provider_user_id'],
        ':pemail' => $profile['provider_email'],
        ':fname'  => $profile['full_name']     ?? null,
        ':photo'  => $profile['photo_path']    ?? null,
        ':dob'    => $profile['date_of_birth'] ?? null,
        ':exp'    => $expires,
    ]);

    return $token;
}

/**
 * Send a "Confirm linking your <Provider> account" email containing a
 * one-time link the recipient must click to attach OAuth to their local
 * account. The email is delivered to the local account's email address —
 * which is also the OAuth-side email (one email = one account).
 *
 * @return array{ok:bool,error?:string}
 */
function hbms_send_account_link_email(
    string $toEmail,
    string $toName,
    string $token,
    string $provider
): array {
    $appUrl  = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
    $link    = $appUrl . '/confirm-link-account.php?token=' . urlencode($token);
    $expires = '30 minutes';

    $providerLabel = ucfirst($provider);
    $safeName      = htmlspecialchars($toName !== '' ? $toName : 'there', ENT_QUOTES, 'UTF-8');
    $safeLink      = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $safeProvider  = htmlspecialchars($providerLabel, ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
<!doctype html>
<html><body style="font-family:Arial,Helvetica,sans-serif;background:#f5f6f8;padding:24px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;border:1px solid #e5e7eb;">
    <tr><td style="padding:24px 28px;border-bottom:1px solid #eef0f3;">
      <h2 style="margin:0;color:#1f2937;">Confirm linking your {$safeProvider} account</h2>
    </td></tr>
    <tr><td style="padding:24px 28px;color:#374151;font-size:15px;line-height:1.55;">
      <p>Hi {$safeName},</p>
      <p>We received a request to link your <strong>{$safeProvider}</strong> account to your
         existing <strong>HBMS Hotel Booking</strong> account.</p>
      <p>If this was you, please confirm by clicking the button below. After confirming,
         you'll be able to sign in with either your password <em>or</em> {$safeProvider}.</p>
      <p style="text-align:center;margin:28px 0;">
        <a href="{$safeLink}"
           style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;
                  padding:12px 22px;border-radius:6px;font-weight:600;">Yes, link my {$safeProvider} account</a>
      </p>
      <p style="font-size:13px;color:#6b7280;">
        This link expires in {$expires} and can only be used once.<br>
        If you didn't request this, you can safely ignore this email — your account
        will remain unchanged.
      </p>
      <p style="font-size:12px;color:#9ca3af;word-break:break-all;">
        Or copy this URL into your browser:<br>{$safeLink}
      </p>
    </td></tr>
  </table>
</body></html>
HTML;

    $text = "Hi {$toName},\n\n"
          . "Confirm linking your {$providerLabel} account to your HBMS Hotel Booking account.\n"
          . "Open this link to confirm (expires in {$expires}):\n\n"
          . "{$link}\n\n"
          . "If you didn't request this, you can safely ignore this email.\n";

    try {
        $mail = hbms_make_mailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = "Confirm linking your {$providerLabel} account — HBMS Hotel Booking";
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        return ['ok' => true];
    } catch (PHPMailerException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
