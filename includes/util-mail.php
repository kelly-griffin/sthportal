<?php
// includes/util-mail.php — SMTP mail helper.
// Tries PHPMailer if available, else native SMTP (TLS/SSL), else mail() fallback.
// Also exposes mail_last_error() for debugging.
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$GLOBALS['MAIL_LAST_ERROR'] = '';

if (!function_exists('mail_last_error')) {
  function mail_last_error(): string {
    return (string)($GLOBALS['MAIL_LAST_ERROR'] ?? '');
  }
}

function send_mail(string $to, string $subject, string $html, string $text = ''): bool
{
    $GLOBALS['MAIL_LAST_ERROR'] = '';

    // Build a safe text body if not provided
    if ($text === '') {
        $src = (string)$html;
        $plain = @preg_replace('/<br\s*\/?\>/i', "\n", $src);
        if ($plain === null) { $plain = $src; }
        $plain = strip_tags($plain);
        $text = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $useSmtp = defined('SMTP_ENABLED') && SMTP_ENABLED;
    $result = false;

    // 1) PHPMailer path (if available)
    if ($useSmtp) {
        foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../vendor/autoload.php'] as $autoload) {
            if (is_file($autoload)) { require_once $autoload; break; }
        }
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->CharSet = 'UTF-8';
                $mail->isSMTP();
                $mail->SMTPAuth = true;
                $mail->Host = defined('SMTP_HOST') ? (string)SMTP_HOST : 'localhost';
                $mail->Port = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
                $secure = defined('SMTP_SECURE') ? (string)SMTP_SECURE : 'tls';
                if ($secure === 'ssl' || $secure === 'tls') $mail->SMTPSecure = $secure;
                if (defined('SMTP_USER')) $mail->Username = (string)SMTP_USER;
                if (defined('SMTP_PASS')) $mail->Password = (string)SMTP_PASS;

                if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
                    $mail->SMTPDebug = 2;
                    $mail->Debugoutput = function($str, $level) {
                        $prev = (string)($GLOBALS['MAIL_LAST_ERROR'] ?? '');
                        $GLOBALS['MAIL_LAST_ERROR'] = $prev . (trim((string)$str) . "\n");
                    };
                }

                $fromAddr = defined('SMTP_FROM') ? (string)SMTP_FROM : (defined('SMTP_USER') ? (string)SMTP_USER : 'no-reply@example.com');
                $fromName = defined('SMTP_FROM_NAME') ? (string)SMTP_FROM_NAME : 'Portal';
                $mail->setFrom($fromAddr, $fromName);
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body = (string)$html;
                $mail->AltBody = (string)$text;

                $result = $mail->send();
                if (!$result && empty($GLOBALS['MAIL_LAST_ERROR'])) {
                    $GLOBALS['MAIL_LAST_ERROR'] = trim((string)$mail->ErrorInfo);
                }
            } catch (\Throwable $e) {
                $GLOBALS['MAIL_LAST_ERROR'] = $e->getMessage();
                $result = false; // try native SMTP below
            }
        }
    }

    // 2) Native SMTP (no PHPMailer needed)
    if ($useSmtp && !$result) {
        $ok = smtp_native_send($to, $subject, $html, $text);
        if ($ok) return true;
        // else fall through to mail()
    }

    // 3) Fallback to PHP mail()
    if (!$result) {
        // Build simple headers
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        if (defined('SMTP_FROM')) {
            $fromName = defined('SMTP_FROM_NAME') ? (string)SMTP_FROM_NAME : 'Portal';
            $headers .= 'From: ' . $fromName . ' <' . (string)SMTP_FROM . ">\r\n";
            $headers .= 'Reply-To: ' . $fromName . ' <' . (string)SMTP_FROM . ">\r\n";
        }
        try {
            $result = @mail($to, $subject, (string)$html, $headers);
            if (!$result && $GLOBALS['MAIL_LAST_ERROR'] === '') {
                $GLOBALS['MAIL_LAST_ERROR'] = 'mail() returned false';
            }
        } catch (\Throwable $e) {
            $GLOBALS['MAIL_LAST_ERROR'] = $e->getMessage();
            $result = false;
        }
    }

    return (bool)$result;
}

/**
 * Minimal native SMTP client (LOGIN auth, STARTTLS/SSL) — good enough for Gmail.
 * Uses SMTP_* constants from config.php.
 */
function smtp_native_send(string $to, string $subject, string $html, string $text): bool
{
    $host   = defined('SMTP_HOST') ? (string)SMTP_HOST : 'localhost';
    $port   = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
    $secure = defined('SMTP_SECURE') ? (string)SMTP_SECURE : 'tls'; // 'tls' or 'ssl' or ''
    $user   = defined('SMTP_USER') ? (string)SMTP_USER : '';
    $pass   = defined('SMTP_PASS') ? (string)SMTP_PASS : '';
    $from   = defined('SMTP_FROM') ? (string)SMTP_FROM : ($user ?: 'no-reply@example.com');
    $fromName = defined('SMTP_FROM_NAME') ? (string)SMTP_FROM_NAME : 'Portal';

    $timeout = 20;
    $errno = 0; $errstr = '';

    $transport = ($secure == 'ssl') ? "ssl://$host:$port" : "tcp://$host:$port";
    $fp = @stream_socket_client($transport, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$fp) { $GLOBALS['MAIL_LAST_ERROR'] = "Connect failed: $errstr ($errno)"; return false; }
    stream_set_timeout($fp, $timeout);

    $expect = function($prefix) use ($fp) {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) break;
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $data;
    };
    $send = function($cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };

    $greet = $expect('220');
    if (strpos($greet, '220') !== 0) { fclose($fp); $GLOBALS['MAIL_LAST_ERROR'] = "SMTP greet: $greet"; return false; }

    $domain = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
    $send("EHLO $domain");
    $ehlo = $expect('250');

    if ($secure == 'tls') {
        $send("STARTTLS");
        $tls = $expect('220');
        if (strpos($tls, '220') !== 0) { fclose($fp); $GLOBALS['MAIL_LAST_ERROR'] = "STARTTLS failed: $tls"; return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp); $GLOBALS['MAIL_LAST_ERROR'] = "TLS crypto negotiation failed"; return false;
        }
        $send("EHLO $domain");
        $ehlo = $expect('250');
        if (strpos($ehlo, '250') !== 0) { fclose($fp); $GLOBALS['MAIL_LAST_ERROR'] = "EHLO after TLS failed: $ehlo"; return false; }
    }

    if ($user !== '' && $pass !== '') {
        $send("AUTH LOGIN");
        $resp = $expect('334');
        if (strpos($resp, '334') !== 0) { fclose($fp); $GLOBALS['MAIL_LAST_ERROR'] = "AUTH LOGIN not accepted: $resp"; return false; }
        $send(base64_encode($user));
        $resp = $expect('334');
        if (strpos($resp, '334') !== 0) { fclose($fp); $GLOBALS['MAIL_LAST_ERROR'] = "Username rejected: $resp"; return false; }
        $send(base64_encode($pass));
        $resp = $expect('235');
        if (strpos($resp, '235') !== 0) { fclose($fp); $GLOBALS['MAIL_LAST_ERROR'] = "Password rejected: $resp"; return false; }
    }

    $send("MAIL FROM:<$from>");
    $resp = $expect('250');
    if (strpos($resp, '250') !== 0) { fclose($fp); $GLOBALS['MAIL_LAST_ERROR'] = "MAIL FROM failed: $resp"; return false; }

    $send("RCPT TO:<$to>");
    $resp = $expect('250');
    if (strpos($resp, '250') !== 0 && strpos($resp, '251') !== 0) { fclose($fp); $GLOBALS['MAIL_LAST_ERROR'] = "RCPT TO failed: $resp"; return false; }

    $send("DATA");
    $resp = $expect('354');
    if (strpos($resp, '354') !== 0) { fclose($fp); $GLOBALS['MAIL_LAST_ERROR'] = "DATA not accepted: $resp"; return false; }

    $boundary = 'bnd_' . bin2hex(random_bytes(6));
    $subjectEnc = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($subject, 'UTF-8', 'B') : $subject;
    $fromHeader = sprintf('From: %s <%s>', $fromName, $from);
    $toHeader   = sprintf('To: <%s>', $to);

    $msg  = "MIME-Version: 1.0\r\n";
    $msg .= "Date: " . date('r') . "\r\n";
    $msg .= $fromHeader . "\r\n";
    $msg .= $toHeader . "\r\n";
    $msg .= "Subject: $subjectEnc\r\n";
    $msg .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $msg .= "\r\n";
    $msg .= "--$boundary\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $msg .= (string)$text . "\r\n";
    $msg .= "--$boundary\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $msg .= (string)$html . "\r\n";
    $msg .= "--$boundary--\r\n";
    $msg .= ".\r\n";

    fwrite($fp, $msg);
    $resp = $expect('250');
    if (strpos($resp, '250') !== 0) { fclose($fp); $GLOBALS['MAIL_LAST_ERROR'] = "Message not accepted: $resp"; return false; }

    $send("QUIT");
    fclose($fp);
    return true;
}
