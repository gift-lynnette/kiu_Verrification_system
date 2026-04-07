<?php
/**
 * Notification Service Class
 * Handles in-app, email and SMS notifications.
 */

class NotificationService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * High-level notification helper used across modules.
     */
    public function notify($user_id, $event_type, $subject, $message, $priority = 'normal', $channels = null) {
        if ($channels === null) {
            $channels = ['in_app', 'email', 'sms'];
        }

        $channels = array_values(array_unique($channels));
        $queued = [];

        foreach ($channels as $channel) {
            if (!in_array($channel, [NOTIFY_IN_APP, NOTIFY_EMAIL, NOTIFY_SMS], true)) {
                continue;
            }

            $result = $this->queueNotification(
                $user_id,
                $channel,
                $subject,
                $message,
                $event_type,
                $priority
            );

            if (!empty($result['success'])) {
                $queued[] = $result['notification_id'];
            }
        }

        // Process in-app immediately so it appears in dashboard without cron.
        if (!empty($queued)) {
            $idPlaceholders = implode(',', array_fill(0, count($queued), '?'));
            $stmt = $this->db->prepare("
                UPDATE notifications
                SET delivery_status = 'sent',
                    sent_at = NOW(),
                    delivered_at = NOW()
                WHERE notification_id IN ($idPlaceholders)
                AND notification_type = 'in_app'
            ");
            $stmt->execute($queued);

            // Attempt immediate delivery for queued email/SMS notifications.
            $this->processQueue(25);
        }

        return ['success' => !empty($queued), 'queued_ids' => $queued];
    }

    /**
     * Queue a notification.
     */
    public function queueNotification($user_id, $type, $subject, $message, $event_type = null, $priority = 'normal') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (
                    user_id, notification_type, event_type, subject,
                    message_body, priority, delivery_status
                ) VALUES (
                    :user_id, :type, :event_type, :subject,
                    :message, :priority, 'queued'
                )
            ");

            $stmt->execute([
                'user_id' => $user_id,
                'type' => $type,
                'event_type' => $event_type,
                'subject' => $subject,
                'message' => $message,
                'priority' => $priority
            ]);

            return ['success' => true, 'notification_id' => $this->db->lastInsertId()];
        } catch (Exception $e) {
            error_log("Queue notification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send email notification.
     */
    public function sendEmail($to, $subject, $message) {
        try {
            if (empty($to)) {
                return false;
            }

            $headers = "From: " . SMTP_FROM_EMAIL . "\r\n";
            $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            $fullMessage = $this->getEmailTemplate($subject, $message);
            // Suppress PHP warning output from mail transport failures.
            return @mail($to, $subject, $fullMessage, $headers);
        } catch (Exception $e) {
            error_log("Send email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send SMS notification (placeholder transport).
     */
    public function sendSMS($phone, $message) {
        try {
            if (empty($phone)) {
                return false;
            }
            error_log("SMS to $phone: $message");
            return true;
        } catch (Exception $e) {
            error_log("Send SMS error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process queued notifications.
     */
    public function processQueue($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT n.*, u.email, sp.phone_number
                FROM notifications n
                INNER JOIN users u ON n.user_id = u.user_id
                LEFT JOIN student_profiles sp ON u.user_id = sp.user_id
                WHERE n.delivery_status = 'queued'
                ORDER BY n.priority DESC, n.created_at ASC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $notifications = $stmt->fetchAll();

            foreach ($notifications as $notification) {
                $this->processNotification($notification);
            }

            return ['success' => true, 'processed' => count($notifications)];
        } catch (Exception $e) {
            error_log("Process queue error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process single notification.
     */
    private function processNotification($notification) {
        $notificationId = $notification['notification_id'];
        $this->updateNotificationStatus($notificationId, 'sending');

        $success = false;

        try {
            if ($notification['notification_type'] === 'email') {
                $success = $this->sendEmail(
                    $notification['email'],
                    $notification['subject'],
                    $notification['message_body']
                );
            } elseif ($notification['notification_type'] === 'sms') {
                $success = $this->sendSMS(
                    $notification['phone_number'],
                    $notification['message_body']
                );
            } elseif ($notification['notification_type'] === 'in_app') {
                $success = true;
            }

            if ($success) {
                $this->updateNotificationStatus($notificationId, 'sent', null, date('Y-m-d H:i:s'));
            } else {
                $this->updateNotificationStatus($notificationId, 'failed', 'Delivery failed');
            }
        } catch (Exception $e) {
            $this->updateNotificationStatus($notificationId, 'failed', $e->getMessage());
        }
    }

    /**
     * Update notification status.
     */
    private function updateNotificationStatus($notificationId, $status, $errorMessage = null, $sentAt = null) {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications
                SET delivery_status = :status,
                    error_message = :error,
                    sent_at = :sent_at,
                    delivered_at = CASE WHEN :status = 'sent' THEN COALESCE(delivered_at, NOW()) ELSE delivered_at END,
                    delivery_attempts = delivery_attempts + 1
                WHERE notification_id = :notification_id
            ");

            $stmt->execute([
                'status' => $status,
                'error' => $errorMessage,
                'sent_at' => $sentAt,
                'notification_id' => $notificationId
            ]);
        } catch (Exception $e) {
            error_log("Update notification status error: " . $e->getMessage());
        }
    }

    /**
     * Get email template.
     */
    private function getEmailTemplate($subject, $message) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #3aa76d; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f8fafc; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>" . APP_NAME . "</h2>
                </div>
                <div class='content'>
                    <h3>$subject</h3>
                    <p>$message</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " KIU. All rights reserved.</p>
                    <p>This is an automated message. Please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
