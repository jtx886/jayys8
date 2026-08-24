<?php
/**
 * Jay影视 - SMTP邮件类
 */

class Mailer {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $from;
    private $from_name;
    private $socket;
    
    public function __construct() {
        $this->host = SMTP_HOST;
        $this->port = SMTP_PORT;
        $this->user = SMTP_USER;
        $this->pass = SMTP_PASS;
        $this->from = SMTP_FROM;
        $this->from_name = SMTP_FROM_NAME;
    }
    
    public function send($to, $subject, $html_body, $text_body = '') {
        try {
            $this->connect();
            $this->hello();
            $this->auth();
            $this->mail_from();
            $this->rcpt_to($to);
            $this->data($to, $subject, $html_body, $text_body);
            $this->quit();
            $this->log($to, $subject, $html_body, 'sent');
            return true;
        } catch (Exception $e) {
            $this->log($to, $subject, $html_body, 'failed', $e->getMessage());
            return false;
        }
    }
    
    private function connect() {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        $this->socket = stream_socket_client(
            "ssl://{$this->host}:{$this->port}",
            $errno, $errstr, 30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$this->socket) {
            throw new Exception("SMTP连接失败: $errstr ($errno)");
        }
        $this->get_response();
    }
    
    private function hello() {
        $this->send_command('EHLO ' . $_SERVER['HTTP_HOST'] ?: 'localhost');
    }
    
    private function auth() {
        $this->send_command('AUTH LOGIN');
        $this->send_command(base64_encode($this->user));
        $this->send_command(base64_encode($this->pass));
    }
    
    private function mail_from() {
        $this->send_command("MAIL FROM:<{$this->from}>");
    }
    
    private function rcpt_to($to) {
        $this->send_command("RCPT TO:<$to>");
    }
    
    private function data($to, $subject, $html, $text) {
        $this->send_command('DATA');
        
        $boundary = md5(uniqid(time()));
        $headers = "From: " . $this->encode_header($this->from_name) . " <{$this->from}>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: " . $this->encode_header($subject) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "X-Mailer: JayTV Mailer\r\n";
        
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($text ?: strip_tags($html))) . "\r\n";
        
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";
        $body .= "--$boundary--";
        
        fwrite($this->socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        $this->get_response();
    }
    
    private function quit() {
        $this->send_command('QUIT');
        fclose($this->socket);
    }
    
    private function send_command($cmd) {
        fwrite($this->socket, "$cmd\r\n");
        return $this->get_response();
    }
    
    private function get_response() {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = substr($response, 0, 3);
        if (!in_array($code, ['220','250','235','334','354'])) {
            throw new Exception("SMTP错误: $response");
        }
        return $response;
    }
    
    private function encode_header($str) {
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }
    
    private function log($to, $subject, $content, $status, $error = '') {
        try {
            $db = Database::getInstance();
            $db->insert('mail_logs', [
                'to_email' => $to,
                'subject' => $subject,
                'content' => $content,
                'status' => $status,
                'sent_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {}
    }
    
    public static function verify_code_template($code, $username = '') {
        $name = htmlspecialchars($username ?: '用户');
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0; padding:0; background:#f0f2f5; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px;">
                <tr><td align="center">
                    <table width="100%" max-width="500px" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                        <tr><td style="background:linear-gradient(135deg,#e94560 0%,#0f3460 100%); padding:30px; text-align:center;">
                            <h1 style="color:#fff; margin:0; font-size:28px;">🎬 Jay影视</h1>
                        </td></tr>
                        <tr><td style="padding:40px 30px;">
                            <h2 style="color:#1a1a2e; margin:0 0 20px; font-size:20px;">验证码邮件</h2>
                            <p style="color:#666; font-size:16px; line-height:1.6; margin:0 0 30px;">尊敬的 <strong style="color:#e94560;">' . $name . '</strong>，您好！</p>
                            <p style="color:#666; font-size:16px; line-height:1.6; margin:0 0 30px;">您的邮箱验证码如下，请在10分钟内完成验证：</p>
                            <div style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%); border-radius:12px; padding:25px; text-align:center; margin-bottom:30px;">
                                <span style="font-size:36px; font-weight:700; color:#fff; letter-spacing:8px; font-family:monospace;">' . $code . '</span>
                            </div>
                            <p style="color:#999; font-size:14px; line-height:1.6; margin:0;">⚠️ 如非本人操作，请忽略此邮件。</p>
                        </td></tr>
                        <tr><td style="background:#f8f9fa; padding:20px 30px; text-align:center; border-top:1px solid #eee;">
                            <p style="color:#999; font-size:13px; margin:0;">© ' . date('Y') . ' Jay影视 版权所有</p>
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body>
        </html>';
    }
    
    public static function ban_notice_template($username, $reason, $ban_start, $ban_end) {
        $name = htmlspecialchars($username);
        $end_text = $ban_end ? date('Y-m-d H:i', strtotime($ban_end)) : '永久';
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0; padding:0; background:#f0f2f5; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px;">
                <tr><td align="center">
                    <table width="100%" max-width="500px" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                        <tr><td style="background:linear-gradient(135deg,#dc2626 0%,#991b1b 100%); padding:30px; text-align:center;">
                            <h1 style="color:#fff; margin:0; font-size:24px;">⚠️ 账号封禁通知</h1>
                        </td></tr>
                        <tr><td style="padding:40px 30px;">
                            <p style="color:#666; font-size:16px; line-height:1.6; margin:0 0 20px;">尊敬的 <strong>' . $name . '</strong>，您好！</p>
                            <div style="background:#fef2f2; border-left:4px solid #dc2626; padding:20px; border-radius:0 8px 8px 0; margin-bottom:25px;">
                                <p style="color:#991b1b; margin:0 0 10px; font-size:15px;"><strong>封禁原因：</strong>' . htmlspecialchars($reason) . '</p>
                                <p style="color:#991b1b; margin:0 0 10px; font-size:15px;"><strong>封禁时间：</strong>' . $ban_start . '</p>
                                <p style="color:#991b1b; margin:0; font-size:15px;"><strong>解除时间：</strong>' . $end_text . '</p>
                            </div>
                            <p style="color:#666; font-size:14px; line-height:1.6; margin:0;">如有异议，请联系管理员申诉。</p>
                        </td></tr>
                        <tr><td style="background:#f8f9fa; padding:20px 30px; text-align:center; border-top:1px solid #eee;">
                            <p style="color:#999; font-size:13px; margin:0;">© ' . date('Y') . ' Jay影视 版权所有</p>
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body>
        </html>';
    }
}
