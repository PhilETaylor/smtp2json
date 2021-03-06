<?php
/**
 * SMTPServer - A PHP / inetd fake smtp server.
 * Allows client<->server interaction
 * The comunication is based upon the SMPT standards defined in http://www.lesnikowski.com/mail/Rfc/rfc2821.txt
 */

class SMTPServer
{
    public $logFile = false;
    public $serverHello = 'SMTPServer ESMTP PHP Mail Server Ready';

    public function __construct()
    {
        $this->mail = array();
        $this->mail['ipaddress'] = false;
        $this->mail['sender'] = '';
        $this->mail['recipients'] = array();
        $this->mail['subject'] = false;
        $this->mail['rawEmail'] = false;
        $this->mail['emailHeaders'] = false;
        $this->mail['headers'] = false;
        $this->mail['TextBody'] = false;
    }

    public function getJSON(): string
    {
        unset ($this->mail['emailHeaders']);
        return json_encode($this->mail, JSON_PRETTY_PRINT);
    }

    public function receive()
    {
        $hasValidFrom = false;
        $hasValidTo = false;
        $receivingData = false;
        $header = true;
        $this->reply('220 ' . $this->serverHello);
        $this->mail['ipaddress'] = $this->detectIP();
        while ($data = fgets(STDIN)) {
            $data = preg_replace('@\r\n@', "\n", $data);

            if (!$receivingData) {
                $this->log($data);
            }

            if (!$receivingData && preg_match('/^MAIL FROM:\s?<(.*)>/i', $data, $match)) {
                if (preg_match('/(.*)@\[.*\]/i', $match[1]) || $match[1] != '' || $this->validateEmail($match[1])) {
                    $this->mail['sender'] = $match[1];
                    $this->reply('250 2.1.0 Ok');
                    $hasValidFrom = true;
                } else {
                    $this->reply('551 5.1.7 Bad sender address syntax');
                }
            } elseif (!$receivingData && preg_match('/^RCPT TO:\s?<(.*)>/i', $data, $match)) {
                if (!$hasValidFrom) {
                    $this->reply('503 5.5.1 Error: need MAIL command');
                } else {
                    if (preg_match('/postmaster@\[.*\]/i', $match[1]) || $this->validateEmail($match[1])) {
                        array_push($this->mail['recipients'], $match[1]);
                        $this->reply('250 2.1.5 Ok');
                        $hasValidTo = true;
                    } else {
                        $this->reply('501 5.1.3 Bad recipient address syntax ' . $match[1]);
                    }
                }
            } elseif (!$receivingData && preg_match('/^RSET$/i', trim($data))) {
                $this->reply('250 2.0.0 Ok');
                $hasValidFrom = false;
                $hasValidTo = false;
            } elseif (!$receivingData && preg_match('/^NOOP$/i', trim($data))) {
                $this->reply('250 2.0.0 Ok');
            } elseif (!$receivingData && preg_match('/^VRFY (.*)/i', trim($data), $match)) {
                $this->reply('250 2.0.0 ' . $match[1]);
            } elseif (!$receivingData && preg_match('/^DATA/i', trim($data))) {
                if (!$hasValidTo) {
                    $this->reply('503 5.5.1 Error: need RCPT command');
                } else {
                    $this->reply('354 Ok Send data ending with <CRLF>.<CRLF>');
                    $receivingData = true;
                }
            } elseif (!$receivingData && preg_match('/^(HELO|EHLO)/i', $data)) {
                $this->reply('250 HELO ' . $this->mail['ipaddress']);
            } elseif (!$receivingData && preg_match('/^QUIT/i', trim($data))) {
                break;
            } elseif (!$receivingData) {
                //~ $this->reply('250 Ok');
                $this->reply('502 5.5.2 Error: command not recognized');
            } elseif ($receivingData && $data == ".\n") {
                /* Email Received, now let's look at it */
                $receivingData = false;
                $this->reply('250 2.0.0 Ok: queued as ' . $this->generateRandom(10));
                $splitmail = explode("\n\n", $this->mail['rawEmail'], 2);
                if (count($splitmail) == 2) {
                    $this->mail['emailHeaders'] = $splitmail[0];
                    $this->mail['TextBody'] = $splitmail[1];
                    $headers = preg_replace("/ \s+/", ' ', preg_replace("/\n\s/", ' ', $this->mail['emailHeaders']));
                    $headerlines = explode("\n", $headers);
                    for ($i = 0; $i < count($headerlines); $i++) {

                        // store individual header in assoc array
                        $parts = explode(': ', $headerlines[$i]);
                        $this->mail['headers'][$parts[0]] = $parts[1];

                        if (preg_match('/^Subject: (.*)/i', $headerlines[$i], $matches)) {
                            $this->mail['subject'] = trim($matches[1]);
                        }
                    }
                } else {
                    $this->mail['TextBody'] = $splitmail[0];
                }
//                set_time_limit(5); // Just run the exit to prevent open threads / abuse
            } elseif ($receivingData) {
                $this->mail['rawEmail'] .= $data;
            }
        }
        /* Say good bye */
        $this->reply('221 2.0.0 Bye ' . $this->mail['ipaddress']);
    }

    public function log($s)
    {
        if ($this->logFile) {
            if (!file_exists($this->logFile)){
                file_put_contents($this->logFile,'#');
            }
            file_put_contents($this->logFile, trim($s) . "\n", FILE_APPEND);
        }
    }

    private function reply($s)
    {
        $this->log("REPLY:$s");
        fwrite(STDOUT, $s . "\r\n");
    }

    private function detectIP()
    {
        $raw = explode(':', stream_socket_get_name(STDIN, true));
        return $raw[0];
    }

    private function validateEmail($email)
    {
        return preg_match('/^[_a-z0-9-+]+(\.[_a-z0-9-+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/', strtolower($email));
    }

    /**
     * @param int $length
     *
     * @return string
     *
     * @throws Exception
     */
    private function generateRandom($length=8)
    {
        return substr(bin2hex(random_bytes($length)), $length);
    }
}
