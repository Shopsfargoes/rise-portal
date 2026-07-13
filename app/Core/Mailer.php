<?php
// ============================================================
// RISE CAPITAL GROUP — Mailer
// PHPMailer wrapper. Handles invite emails + notifications.
// Requires: composer require phpmailer/phpmailer
// ============================================================

namespace Rise\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    // ── Send a raw email ──────────────────────────────────────

    /**
     * Send an HTML email.
     *
     * @param string $toEmail   Recipient email
     * @param string $toName    Recipient name
     * @param string $subject   Email subject
     * @param string $htmlBody  HTML content
     * @param string $textBody  Plain text fallback (auto-generated if empty)
     * @return bool
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): bool {
        $mail = new PHPMailer(true);

        try {
            // ── SMTP config ───────────────────────────────────
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) MAIL_PORT;

            // ── From ──────────────────────────────────────────
            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

            // ── To ────────────────────────────────────────────
            $mail->addAddress($toEmail, $toName);

            // ── Content ───────────────────────────────────────
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = self::wrapInLayout($htmlBody, $subject);
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log('Mailer error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    // ── Specific email types ──────────────────────────────────

    /**
     * Send an investor invite email with a one-time setup link.
     */
    public static function sendInvite(
        string $toEmail,
        string $toName,
        string $inviteToken,
        string $invitedByName
    ): bool {
        $link = APP_URL . '/accept-invite.php?token=' . urlencode($inviteToken);

        $html = "
            <h2 style='color:#C9922A; margin-bottom:8px;'>You've been invited</h2>
            <p style='color:#ccc; margin-bottom:16px;'>
                <strong style='color:#f0f0f0;'>{$invitedByName}</strong> has invited you to access
                the <strong style='color:#f0f0f0;'>RISE Capital Group</strong> investor portal.
            </p>
            <p style='color:#aaa; margin-bottom:24px;'>
                Click the button below to set up your password and access your account.
                This link expires in <strong style='color:#f0f0f0;'>48 hours</strong>.
            </p>
            <a href='{$link}'
               style='display:inline-block; background:#C9922A; color:#000; padding:13px 28px;
                      border-radius:8px; font-weight:700; text-decoration:none; font-size:15px;'>
                Accept Invitation →
            </a>
            <p style='color:#666; font-size:12px; margin-top:20px;'>
                Or copy this link into your browser:<br>
                <span style='color:#888;'>{$link}</span>
            </p>
            <p style='color:#666; font-size:12px; margin-top:16px;'>
                If you did not expect this invitation, you can safely ignore this email.
            </p>
        ";

        return self::send(
            $toEmail,
            $toName,
            'Your invitation to RISE Capital Group',
            $html
        );
    }

    /**
     * Notify investor their deposit request was received.
     */
    public static function sendDepositReceived(
        string $toEmail,
        string $toName,
        float  $amount
    ): bool {
        $formatted = '$' . number_format($amount, 2);

        $html = "
            <h2 style='color:#C9922A;'>Deposit Request Received</h2>
            <p style='color:#ccc; margin:12px 0;'>
                We've received your deposit request for <strong style='color:#f0f0f0;'>{$formatted}</strong>.
            </p>
            <p style='color:#aaa;'>
                A member of our team will contact you shortly with wire transfer instructions.
                You can track the status of this request in your portal.
            </p>
        ";

        return self::send($toEmail, $toName, 'Deposit Request Received — RISE Capital', $html);
    }

    /**
     * Notify investor their deposit has been confirmed.
     */
    public static function sendDepositConfirmed(
        string $toEmail,
        string $toName,
        float  $amount
    ): bool {
        $formatted = '$' . number_format($amount, 2);

        $html = "
            <h2 style='color:#4caf50;'>Deposit Confirmed ✓</h2>
            <p style='color:#ccc; margin:12px 0;'>
                Your deposit of <strong style='color:#f0f0f0;'>{$formatted}</strong> has been
                confirmed and added to your wallet balance.
            </p>
            <p style='color:#aaa;'>
                Log in to your portal to view your updated balance.
            </p>
        ";

        return self::send($toEmail, $toName, 'Deposit Confirmed — RISE Capital', $html);
    }

    /**
     * Notify investor their withdrawal is being processed.
     */
    public static function sendWithdrawalUpdate(
        string $toEmail,
        string $toName,
        float  $amount,
        string $status   // 'contacted' | 'confirmed' | 'rejected'
    ): bool {
        $formatted = '$' . number_format($amount, 2);

        $messages = [
            'contacted'  => ['color' => '#C9922A', 'title' => 'Withdrawal Request Update',   'body' => "Our team has reviewed your withdrawal request for <strong style='color:#f0f0f0;'>{$formatted}</strong> and will be in touch shortly to arrange payment."],
            'confirmed'  => ['color' => '#4caf50', 'title' => 'Withdrawal Confirmed ✓',        'body' => "Your withdrawal of <strong style='color:#f0f0f0;'>{$formatted}</strong> has been processed and is on its way to you."],
            'rejected'   => ['color' => '#e53935', 'title' => 'Withdrawal Request Declined',  'body' => "Unfortunately your withdrawal request for <strong style='color:#f0f0f0;'>{$formatted}</strong> could not be processed at this time. Please contact your fund manager for details."],
        ];

        $msg = $messages[$status] ?? $messages['contacted'];

        $html = "
            <h2 style='color:{$msg['color']};'>{$msg['title']}</h2>
            <p style='color:#ccc; margin:12px 0;'>{$msg['body']}</p>
        ";

        return self::send($toEmail, $toName, $msg['title'] . ' — RISE Capital', $html);
    }

    /**
     * Notify investor of a new message from admin.
     */
    public static function sendNewMessageNotification(
        string $toEmail,
        string $toName,
        string $senderName,
        string $threadSubject
    ): bool {
        $portalLink = APP_URL . '/investor/messages.php';

        $html = "
            <h2 style='color:#C9922A;'>New Message</h2>
            <p style='color:#ccc; margin:12px 0;'>
                <strong style='color:#f0f0f0;'>{$senderName}</strong> sent you a message
                regarding: <em style='color:#aaa;'>{$threadSubject}</em>
            </p>
            <a href='{$portalLink}'
               style='display:inline-block; background:#C9922A; color:#000; padding:12px 24px;
                      border-radius:8px; font-weight:700; text-decoration:none;'>
                View Message →
            </a>
        ";

        return self::send($toEmail, $toName, 'New message from RISE Capital', $html);
    }

    // ── Email layout wrapper ──────────────────────────────────

    /**
     * Wrap any HTML content in the branded email shell.
     */
    private static function wrapInLayout(string $content, string $subject): string
    {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'/>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'/>
            <title>{$subject}</title>
        </head>
        <body style='margin:0; padding:0; background:#0a0a0a; font-family: Arial, sans-serif;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background:#0a0a0a; padding:40px 20px;'>
                <tr><td align='center'>
                    <table width='580' cellpadding='0' cellspacing='0' style='max-width:580px; width:100%;'>

                        <!-- Header -->
                        <tr>
                            <td style='padding-bottom:28px; text-align:center;'>
                                <div style='display:inline-flex; align-items:center; gap:10px;'>
                                    <div style='width:38px; height:38px; background:#C9922A; border-radius:8px;
                                                display:inline-block; line-height:38px; text-align:center;
                                                font-weight:900; font-size:20px; color:#000;'>R</div>
                                    <span style='font-size:18px; font-weight:800; color:#C9922A;'>RISE Capital Group</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Body card -->
                        <tr>
                            <td style='background:#141414; border:1px solid #2a2a2a; border-radius:12px; padding:32px;'>
                                {$content}
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style='padding-top:24px; text-align:center;'>
                                <p style='color:#555; font-size:12px; line-height:1.6; margin:0;'>
                                    This email was sent by " . APP_NAME . "<br>
                                    You are receiving this because you are a registered investor or invitee.<br>
                                    <a href='" . APP_URL . "' style='color:#C9922A; text-decoration:none;'>Visit Portal</a>
                                </p>
                            </td>
                        </tr>

                    </table>
                </td></tr>
            </table>
        </body>
        </html>
        ";
    }
}