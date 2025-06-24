<?php
require_once '../config/email.php';

class EmailUtils {
    
    /**
     * 이메일 전송
     */
    public static function sendMail($to, $subject, $body, $isHtml = true) {
        try {
            // PHPMailer 사용 (Composer로 설치 필요)
            // composer require phpmailer/phpmailer
            
            /*
            use PHPMailer\PHPMailer\PHPMailer;
            use PHPMailer\PHPMailer\SMTP;
            
            $mail = new PHPMailer(true);
            
            // SMTP 설정
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            
            // 발신자 설정
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            // 수신자 설정
            $mail->addAddress($to);
            
            // 이메일 내용
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->send();
            return true;
            */
            
            // 임시로 PHP mail() 함수 사용 (실제 운영에서는 PHPMailer 사용 권장)
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8',
                'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
                'X-Mailer: PHP/' . phpversion()
            ];
            
            return mail($to, $subject, $body, implode("\r\n", $headers));
            
        } catch (Exception $e) {
            error_log('Email sending failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 이메일 인증 코드 전송
     */
    public static function sendVerificationCode($email, $code, $username = '') {
        $subject = '이메일 인증 코드';
        
        $body = self::getVerificationEmailTemplate($code, $username);
        
        return self::sendMail($email, $subject, $body, true);
    }
    
    /**
     * 비밀번호 재설정 이메일 전송
     */
    public static function sendPasswordResetEmail($email, $resetToken, $username = '') {
        $subject = '비밀번호 재설정';
        
        $resetUrl = FRONTEND_URL . '/reset-password?token=' . $resetToken;
        $body = self::getPasswordResetEmailTemplate($resetUrl, $username);
        
        return self::sendMail($email, $subject, $body, true);
    }
    
    /**
     * 환영 이메일 전송
     */
    public static function sendWelcomeEmail($email, $username) {
        $subject = '회원가입을 환영합니다!';
        
        $body = self::getWelcomeEmailTemplate($username);
        
        return self::sendMail($email, $subject, $body, true);
    }
    
    /**
     * 이메일 인증 템플릿
     */
    private static function getVerificationEmailTemplate($code, $username) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>이메일 인증</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2c5282;">이메일 인증</h2>
                
                <p>안녕하세요' . ($username ? ' ' . $username . '님' : '') . ',</p>
                
                <p>아래 인증 코드를 입력하여 이메일 인증을 완료해주세요.</p>
                
                <div style="background: #f7fafc; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;">
                    <h3 style="font-size: 24px; letter-spacing: 4px; color: #2d3748; margin: 0;">
                        ' . $code . '
                    </h3>
                </div>
                
                <p>이 인증 코드는 10분 동안 유효합니다.</p>
                
                <p>본인이 요청하지 않은 경우 이 이메일을 무시해주세요.</p>
                
                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
                
                <p style="font-size: 12px; color: #718096;">
                    이 이메일은 자동으로 발송된 메일입니다. 회신하지 마세요.
                </p>
            </div>
        </body>
        </html>';
    }
    
    /**
     * 비밀번호 재설정 템플릿
     */
    private static function getPasswordResetEmailTemplate($resetUrl, $username) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>비밀번호 재설정</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2c5282;">비밀번호 재설정</h2>
                
                <p>안녕하세요' . ($username ? ' ' . $username . '님' : '') . ',</p>
                
                <p>비밀번호 재설정을 요청하셨습니다. 아래 버튼을 클릭하여 새 비밀번호를 설정해주세요.</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $resetUrl . '" 
                       style="display: inline-block; background: #3182ce; color: white; padding: 12px 24px; 
                              text-decoration: none; border-radius: 6px; font-weight: bold;">
                        비밀번호 재설정
                    </a>
                </div>
                
                <p>또는 아래 링크를 복사하여 브라우저에 붙여넣으세요:</p>
                <p style="word-break: break-all; color: #3182ce;">' . $resetUrl . '</p>
                
                <p>이 링크는 1시간 동안 유효합니다.</p>
                
                <p>본인이 요청하지 않은 경우 이 이메일을 무시해주세요.</p>
                
                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
                
                <p style="font-size: 12px; color: #718096;">
                    이 이메일은 자동으로 발송된 메일입니다. 회신하지 마세요.
                </p>
            </div>
        </body>
        </html>';
    }
    
    /**
     * 환영 이메일 템플릿
     */
    private static function getWelcomeEmailTemplate($username) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>회원가입 환영</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2c5282;">환영합니다!</h2>
                
                <p>안녕하세요 ' . $username . '님,</p>
                
                <p>회원가입을 진심으로 환영합니다! 이제 모든 서비스를 이용하실 수 있습니다.</p>
                
                <div style="background: #f0fff4; padding: 20px; border-radius: 8px; border-left: 4px solid #48bb78; margin: 20px 0;">
                    <h3 style="color: #2f855a; margin-top: 0;">시작하기</h3>
                    <ul style="margin: 0;">
                        <li>프로필 설정 완료하기</li>
                        <li>이메일 인증 완료하기</li>
                        <li>첫 번째 프로젝트 시작하기</li>
                    </ul>
                </div>
                
                <p>문의사항이 있으시면 언제든 연락해주세요.</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . FRONTEND_URL . '" 
                       style="display: inline-block; background: #3182ce; color: white; padding: 12px 24px; 
                              text-decoration: none; border-radius: 6px; font-weight: bold;">
                        시작하기
                    </a>
                </div>
                
                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
                
                <p style="font-size: 12px; color: #718096;">
                    이 이메일은 자동으로 발송된 메일입니다. 회신하지 마세요.
                </p>
            </div>
        </body>
        </html>';
    }
}