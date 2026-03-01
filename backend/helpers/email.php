<?php
/**
 * Email Helper for SkillSwap
 * Sends emails using PHP's built-in mail() function or SMTP if configured.
 */

function sendEmail($to, $subject, $message, $isHtml = true) {
    if (empty($to)) return false;

    // Headers
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    if ($isHtml) {
        $headers[] = 'Content-type: text/html; charset=UTF-8';
    } else {
        $headers[] = 'Content-type: text/plain; charset=UTF-8';
    }
    
    // Sender Config - Update this with your actual domain/email
    $fromEmail = 'noreply@skillswap.local'; // Ideally defined in env
    $headers[] = "From: SkillSwap <$fromEmail>";
    $headers[] = "Reply-To: support@skillswap.local";
    $headers[] = "X-Mailer: PHP/" . phpversion();

    $headersStr = implode("\r\n", $headers);

    // Basic HTML template wrapper
    if ($isHtml) {
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; }
                .header { background-color: #1e293b; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; }
                .footer { font-size: 12px; color: #999; text-align: center; padding-top: 20px; border-top: 1px solid #eee; }
                .btn { display: inline-block; padding: 10px 20px; background-color: #1e293b; color: white !important; text-decoration: none; border-radius: 5px; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>SkillSwap Connect</h2>
                </div>
                <div class='content'>
                    $message
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " SkillSwap Connect. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        ";
    } else {
        $body = $message;
    }

    // DEV MODE: Log email to file for testing without SMTP
    $logDir = __DIR__ . '/../../logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: $to | SUBJECT: $subject\n" . 
                "--------------------------------------------------\n" . 
                ( $isHtml ? strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $body)) : $body ) . "\n" . 
                "==================================================\n\n";
    file_put_contents($logDir . '/email.log', $logEntry, FILE_APPEND);

    // Attempt to send
    // In a local environment like XAMPP without Mercury/Sendmail configured, this checks logic but won't send real email.
    // For specific user request "so that a user can get even though they not using the system", 
    // we assume the deploying server will have mail configured.
    try {
        if (@mail($to, $subject, $body, $headersStr)) {
            return true;
        } else {
            // Log failure
            error_log("Failed to send email to $to: Mail function returned false.");
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception sending email to $to: " . $e->getMessage());
        return false;
    }
}
?>
