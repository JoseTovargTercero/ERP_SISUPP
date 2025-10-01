<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once APP_ROOT . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once APP_ROOT . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once APP_ROOT . '/vendor/phpmailer/phpmailer/src/SMTP.php';


class MailHelper
{
    private $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        // Configuración del servidor SMTP desde variables de entorno
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['MAIL_HOST'] ?? 'localhost';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['MAIL_USERNAME'] ?? '';
        $this->mailer->Password = $_ENV['MAIL_PASSWORD'] ?? '';
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = $_ENV['MAIL_PORT'] ?? 587;

        // Idioma y nombre del remitente
        $lang = $_SESSION['idioma'] ?? 'EN';
        $fromName = $lang === 'ES'
            ? ($_ENV['MAIL_FROM_NAME_es'] ?? 'Soporte')
            : ($_ENV['MAIL_FROM_NAME_en'] ?? 'Support');

        $this->mailer->setFrom($_ENV['MAIL_FROM'] ?? 'noreply@example.com', $fromName);
    }

    public function sendMail($to, $subject, $body, $isHTML = true)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Destinatario y contenido
            $this->mailer->addAddress($to);
            $this->mailer->isHTML($isHTML);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;

            $this->mailer->send();
            $this->mailer->smtpClose();

            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
}
