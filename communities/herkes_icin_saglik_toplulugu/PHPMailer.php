<?php
/**
 * Basit PHPMailer benzeri sınıf
 */
class PHPMailer {
    private $host = '';
    private $port = 587;
    private $username = '';
    private $password = '';
    private $from = '';
    private $to = '';
    private $subject = '';
    private $body = '';
    private $isHTML = false;
    
    public function isSMTP() {
        return true;
    }
    
    public function setHost($host) {
        $this->host = $host;
    }
    
    public function setPort($port) {
        $this->port = $port;
    }
    
    public function setSMTPAuth($auth) {
        return true;
    }
    
    public function setUsername($username) {
        $this->username = $username;
    }
    
    public function setPassword($password) {
        $this->password = $password;
    }
    
    public function setFrom($email, $name = '') {
        $this->from = $email;
    }
    
    public function addAddress($email) {
        $this->to = $email;
    }
    
    public function isHTML($isHTML) {
        $this->isHTML = $isHTML;
    }
    
    public function setSubject($subject) {
        $this->subject = $subject;
    }
    
    public function setBody($body) {
        $this->body = $body;
    }
    
    public function send() {
        try {
            // SMTP bağlantısı
            $socket = fsockopen($this->host, $this->port, $errno, $errstr, 30);
            if (!$socket) {
                throw new Exception("SMTP Bağlantı Hatası: $errstr ($errno)");
            }
            
            // SMTP komutları
            fputs($socket, "EHLO localhost\r\n");
            fputs($socket, "AUTH LOGIN\r\n");
            fputs($socket, base64_encode($this->username) . "\r\n");
            fputs($socket, base64_encode($this->password) . "\r\n");
            fputs($socket, "MAIL FROM: <{$this->username}>\r\n");
            fputs($socket, "RCPT TO: <{$this->to}>\r\n");
            fputs($socket, "DATA\r\n");
            
            // Mail başlıkları
            fputs($socket, "From: {$this->from}\r\n");
            fputs($socket, "To: {$this->to}\r\n");
            fputs($socket, "Subject: {$this->subject}\r\n");
            fputs($socket, "MIME-Version: 1.0\r\n");
            fputs($socket, "Content-Type: " . ($this->isHTML ? "text/html" : "text/plain") . "; charset=UTF-8\r\n");
            fputs($socket, "X-Mailer: PHPMailer\r\n");
            fputs($socket, "\r\n");
            
            // Mail içeriği
            fputs($socket, $this->body . "\r\n");
            fputs($socket, ".\r\n");
            fputs($socket, "QUIT\r\n");
            
            fclose($socket);
            return true;
            
        } catch (Exception $e) {
            throw new Exception("Mail gönderme hatası: " . $e->getMessage());
        }
    }
}
?>
