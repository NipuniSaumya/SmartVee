<?php

function get_email_config(): array
{
    $config_file = __DIR__ . '/email_config.php';
    if (!file_exists($config_file)) {
        return ['enabled' => false];
    }

    $config = require $config_file;
    return is_array($config) ? $config : ['enabled' => false];
}

function send_email(string $to, string $subject, string $html_body): array
{
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }

    $config = get_email_config();
    if (empty($config['enabled'])) {
        return ['success' => false, 'message' => 'Email sending is disabled. Configure includes/email_config.php.'];
    }

    if (($config['method'] ?? 'smtp') === 'smtp') {
        if (empty($config['smtp_user']) || empty($config['smtp_pass'])) {
            return ['success' => false, 'message' => 'SMTP username/password not configured in email_config.php.'];
        }
        return send_smtp_email($to, $subject, $html_body, $config);
    }

    $from_email = $config['from_email'] ?? 'noreply@localhost';
    $from_name = $config['from_name'] ?? 'SmartVee';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . encode_mail_header($from_name) . " <{$from_email}>\r\n";

    if (@mail($to, encode_mail_header($subject), $html_body, $headers)) {
        return ['success' => true, 'message' => 'Email sent successfully.'];
    }

    return ['success' => false, 'message' => 'Failed to send email using PHP mail().'];
}

function encode_mail_header(string $text): string
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function smtp_read($socket): string
{
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_write($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function smtp_expect($socket, array $codes): bool
{
    $response = smtp_read($socket);
    $code = (int)substr($response, 0, 3);
    return in_array($code, $codes, true);
}

function send_smtp_email(string $to, string $subject, string $html_body, array $config): array
{
    $host = $config['smtp_host'] ?? 'smtp.gmail.com';
    $port = (int)($config['smtp_port'] ?? 587);
    $secure = strtolower($config['smtp_secure'] ?? 'tls');
    $from_email = $config['from_email'] ?? $config['smtp_user'];
    $from_name = $config['from_name'] ?? 'SmartVee';

    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, 30);
    if (!$socket) {
        return ['success' => false, 'message' => "SMTP connection failed: {$errstr}"];
    }

    stream_set_timeout($socket, 30);

    if (!smtp_expect($socket, [220])) {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP server did not respond correctly.'];
    }

    smtp_write($socket, 'EHLO localhost');
    if (!smtp_expect($socket, [250])) {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP EHLO failed.'];
    }

    if ($secure === 'tls') {
        smtp_write($socket, 'STARTTLS');
        if (!smtp_expect($socket, [220])) {
            fclose($socket);
            return ['success' => false, 'message' => 'SMTP STARTTLS failed.'];
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return ['success' => false, 'message' => 'Could not enable TLS encryption.'];
        }
        smtp_write($socket, 'EHLO localhost');
        if (!smtp_expect($socket, [250])) {
            fclose($socket);
            return ['success' => false, 'message' => 'SMTP EHLO after TLS failed.'];
        }
    }

    smtp_write($socket, 'AUTH LOGIN');
    if (!smtp_expect($socket, [334])) {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP AUTH not accepted.'];
    }

    smtp_write($socket, base64_encode($config['smtp_user']));
    if (!smtp_expect($socket, [334])) {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP username rejected.'];
    }

    smtp_write($socket, base64_encode($config['smtp_pass']));
    if (!smtp_expect($socket, [235])) {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP password rejected. Check email_config.php credentials.'];
    }

    smtp_write($socket, 'MAIL FROM:<' . $from_email . '>');
    if (!smtp_expect($socket, [250])) {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP MAIL FROM failed.'];
    }

    smtp_write($socket, 'RCPT TO:<' . $to . '>');
    if (!smtp_expect($socket, [250, 251])) {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP RCPT TO failed.'];
    }

    smtp_write($socket, 'DATA');
    if (!smtp_expect($socket, [354])) {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP DATA command failed.'];
    }

    $message = '';
    $message .= 'From: ' . encode_mail_header($from_name) . " <{$from_email}>\r\n";
    $message .= "To: <{$to}>\r\n";
    $message .= 'Subject: ' . encode_mail_header($subject) . "\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $html_body . "\r\n.\r\n";

    fwrite($socket, $message);
    if (!smtp_expect($socket, [250])) {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP message was not accepted.'];
    }

    smtp_write($socket, 'QUIT');
    fclose($socket);

    return ['success' => true, 'message' => 'Invoice emailed successfully to ' . $to];
}
