<?php

declare(strict_types=1);

namespace Muh\Services;

/**
 * Minimal SMTP client (no external library) supporting STARTTLS / SSL and
 * AUTH LOGIN. Used by the Mailer when a real mail relay is configured.
 */
final class SmtpMailer
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $from;

    /** @var resource|null */
    private $conn = null;

    public function __construct(string $host, int $port, string $username, string $password, string $encryption, string $from)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = strtolower($encryption);
        $this->from = $from;
    }

    public function send(string $to, string $subject, string $html): bool
    {
        // Parse sender e-mail from the "Name <email>" form.
        $fromAddr = preg_match('/<([^>]+)>/', $this->from, $m) ? $m[1] : trim($this->from);
        $toAddr = preg_match('/<([^>]+)>/', $to, $m) ? $m[1] : trim($to);

        try {
            $this->connect();
            $this->ehlo();

            if ($this->encryption === 'tls') {
                $this->starttls();
            }

            if ($this->username !== '') {
                $this->auth();
            }

            $this->command('MAIL FROM:<' . $fromAddr . '>', 250);
            $this->command('RCPT TO:<' . $toAddr . '>', 250);

            $this->command('DATA', 354);
            $headers = "From: {$this->from}\r\nTo: {$to}\r\n"
                . "Subject: {$subject}\r\nMIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $this->sendRaw($headers . $html . "\r\n.");
            $this->readLine(); // 250 OK

            $this->command('QUIT', 221);
        } catch (\Throwable $e) {
            $this->close();
            return false;
        }

        $this->close();
        return true;
    }

    private function connect(): void
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl://' : '';
        $errno = 0;
        $errstr = '';
        $this->conn = @stream_socket_client(
            $scheme . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT
        );
        if (!$this->conn) {
            throw new \RuntimeException('SMTP connect failed: ' . $errstr);
        }
        $this->readLine();
    }

    private function ehlo(): void
    {
        $this->command('EHLO muh.local', [250, 220]);
    }

    private function starttls(): void
    {
        $this->command('STARTTLS', 220);
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (!@stream_socket_enable_crypto($this->conn, true, $crypto)) {
            throw new \RuntimeException('STARTTLS crypto failed');
        }
        $this->ehlo();
    }

    private function auth(): void
    {
        $this->command('AUTH LOGIN', 334);
        $this->sendRaw(base64_encode($this->username));
        $this->readLine(); // 334 (password prompt)
        $this->sendRaw(base64_encode($this->password));
        $this->readLine(); // 235 authenticated
    }

    private function command(string $cmd, int|array $expected): string
    {
        $this->sendRaw($cmd);
        return $this->readResponse($expected);
    }

    /**
     * Read a (possibly multiline) SMTP reply and validate its final status
     * code. A multiline reply is signalled by a 'NNN-' line; we keep reading
     * until we reach the last 'NNN ' line so leftover lines never corrupt the
     * next command/response pairing (e.g. EHLO returns several '250-' lines
     * on real relays like Postfix / Sendmail / Gmail).
     */
    private function readResponse(int|array $expected): string
    {
        $expected = (array) $expected;
        $last = '';
        while (true) {
            $line = $this->readLine();
            $last = $line;
            $isContinuation = strlen($line) >= 4 && $line[3] === '-';
            if ($isContinuation) {
                continue; // intermediate 'NNN-' line, more reply lines follow
            }
            $code = (int) substr($line, 0, 3);
            if (!in_array($code, $expected, true)) {
                throw new \RuntimeException('SMTP error (' . $code . '): ' . $line);
            }
            return $line;
        }
    }

    private function sendRaw(string $data): void
    {
        if (!is_resource($this->conn)) {
            throw new \RuntimeException('SMTP not connected');
        }
        fwrite($this->conn, $data . "\r\n");
    }

    private function readLine(): string
    {
        if (!is_resource($this->conn)) {
            throw new \RuntimeException('SMTP not connected');
        }
        $line = fgets($this->conn);
        if ($line === false) {
            throw new \RuntimeException('SMTP closed connection');
        }
        return rtrim($line, "\r\n");
    }

    private function close(): void
    {
        if (is_resource($this->conn)) {
            @fclose($this->conn);
        }
        $this->conn = null;
    }
}
