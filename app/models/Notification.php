<?php
namespace App\Models;

use App\Core\Model;

/**
 * Notification Model
 * 
 * Handles notification-related database operations
 */
class Notification extends Model {
    // Table name
    protected $table = 'notifications';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'link', 'is_read', 'data'
    ];
    
    /**
     * Notification types
     */
    const TYPE_SYSTEM = 'system';
    const TYPE_TRANSACTION = 'transaction';
    const TYPE_MINING = 'mining';
    const TYPE_SECURITY = 'security';
    const TYPE_SUPPORT = 'support';
    const TYPE_REFERRAL = 'referral';
    const TYPE_KYC = 'kyc';
    
    /**
     * Create a notification
     *
     * @param int|string $userId User ID (or 'admin' to notify all admins)
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string|null $link Optional link
     * @param array|null $data Additional data
     * @return int|false Notification ID or false on failure
     */
    public function createNotification($userId, $type, $title, $message, $link = null, $data = null) {
        $notification = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if ($link !== null) {
            $notification['link'] = $link;
        }
        
        if ($data !== null) {
            $notification['data'] = is_array($data) ? json_encode($data) : $data;
        }
        
        // Handle admin notifications (create for all admin users)
        if ($userId === 'admin') {
            $userModel = new User();
            $admins = $userModel->getAdminUsers();
            
            $success = true;
            foreach ($admins as $admin) {
                $notification['user_id'] = $admin->id;
                if (!$this->create($notification)) {
                    $success = false;
                }
            }
            
            return $success;
        } else {
            // Regular user notification
            $notification['user_id'] = $userId;
            return $this->create($notification);
        }
    }
    
    /**
     * Get user's notifications
     *
     * @param int $userId User ID
     * @param bool $unreadOnly Get only unread notifications
     * @param int|null $limit Limit
     * @param int|null $offset Offset
     * @return array Notifications
     */
    public function getUserNotifications($userId, $unreadOnly = false, $limit = null, $offset = null) {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = :user_id";
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $params = ['user_id' => $userId];
        
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params['limit'] = (int) $limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
                $params['offset'] = (int) $offset;
            }
        }
        
        return $this->raw($sql, $params);
    }
    
    /**
     * Mark a notification as read
     *
     * @param int $id Notification ID
     * @param int $userId User ID (for security)
     * @return bool Success or failure
     */
    public function markAsRead($id, $userId) {
        // Security check - notification must belong to user
        $notification = $this->find($id);
        
        if (!$notification || $notification->user_id != $userId) {
            return false;
        }
        
        return $this->update($id, [
            'is_read' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Mark all notifications as read for a user
     *
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function markAllAsRead($userId) {
        $sql = "UPDATE {$this->table} 
                SET is_read = 1, updated_at = :updated_at 
                WHERE user_id = :user_id AND is_read = 0";
        
        return $this->execute($sql, [
            'user_id' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Delete a notification
     *
     * @param int $id Notification ID
     * @param int $userId User ID (for security)
     * @return bool Success or failure
     */
    public function deleteNotification($id, $userId) {
        // Security check - notification must belong to user
        $notification = $this->find($id);
        
        if (!$notification || $notification->user_id != $userId) {
            return false;
        }
        
        return $this->delete($id);
    }
    
    /**
     * Clear all notifications for a user
     *
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function clearAllNotifications($userId) {
        $sql = "DELETE FROM {$this->table} WHERE user_id = :user_id";
        return $this->execute($sql, ['user_id' => $userId]);
    }
    
    /**
     * Count unread notifications for a user
     *
     * @param int $userId User ID
     * @return int Count
     */
    public function countUnreadNotifications($userId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = :user_id AND is_read = 0";
        $result = $this->raw($sql, ['user_id' => $userId]);
        return $result[0]->count ?? 0;
    }
    
    /**
     * Create a system notification
     *
     * @param int|string $userId User ID (or 'admin' for all admins)
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string|null $link Optional link
     * @return int|false Notification ID or false on failure
     */
    public function createSystemNotification($userId, $title, $message, $link = null) {
        return $this->createNotification(
            $userId,
            self::TYPE_SYSTEM,
            $title,
            $message,
            $link
        );
    }
    
    /**
     * Create a transaction notification
     *
     * @param int $userId User ID
     * @param int $transactionId Transaction ID
     * @param string $type Transaction type (deposit, withdrawal, etc.)
     * @param string $status Transaction status
     * @param float $amount Transaction amount
     * @param string $currency Currency
     * @return int|false Notification ID or false on failure
     */
    public function createTransactionNotification($userId, $transactionId, $type, $status, $amount, $currency) {
        $title = '';
        $message = '';
        $link = "/transactions/view/{$transactionId}";
        
        switch ($type) {
            case 'deposit':
                if ($status === 'completed') {
                    $title = 'Deposit Completed';
                    $message = "Your deposit of {$amount} {$currency} has been completed.";
                } elseif ($status === 'pending') {
                    $title = 'Deposit Pending';
                    $message = "Your deposit of {$amount} {$currency} is pending confirmation.";
                } elseif ($status === 'failed') {
                    $title = 'Deposit Failed';
                    $message = "Your deposit of {$amount} {$currency} has failed.";
                }
                break;
                
            case 'withdrawal':
                if ($status === 'completed') {
                    $title = 'Withdrawal Completed';
                    $message = "Your withdrawal of {$amount} {$currency} has been completed.";
                } elseif ($status === 'pending') {
                    $title = 'Withdrawal Pending';
                    $message = "Your withdrawal of {$amount} {$currency} is being processed.";
                } elseif ($status === 'failed') {
                    $title = 'Withdrawal Failed';
                    $message = "Your withdrawal of {$amount} {$currency} has failed.";
                }
                break;
                
            case 'purchase':
                if ($status === 'completed') {
                    $title = 'Purchase Completed';
                    $message = "Your purchase of {$amount} {$currency} has been completed.";
                } elseif ($status === 'pending') {
                    $title = 'Purchase Pending';
                    $message = "Your purchase of {$amount} {$currency} is being processed.";
                } elseif ($status === 'failed') {
                    $title = 'Purchase Failed';
                    $message = "Your purchase of {$amount} {$currency} has failed.";
                }
                break;
                
            default:
                $title = 'Transaction Update';
                $message = "Your {$type} transaction of {$amount} {$currency} has been updated to {$status}.";
        }
        
        $data = [
            'transaction_id' => $transactionId,
            'type' => $type,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency
        ];
        
        return $this->createNotification(
            $userId,
            self::TYPE_TRANSACTION,
            $title,
            $message,
            $link,
            $data
        );
    }
    
    /**
     * Create a KYC notification
     *
     * @param int $userId User ID
     * @param string $status KYC status
     * @param string|null $documentType Document type
     * @param string|null $reason Rejection reason (if applicable)
     * @return int|false Notification ID or false on failure
     */
    public function createKycNotification($userId, $status, $documentType = null, $reason = null) {
        $title = '';
        $message = '';
        $link = '/account/kyc';
        
        switch ($status) {
            case 'submitted':
                $title = 'KYC Document Submitted';
                $message = "Your {$documentType} document has been submitted for verification.";
                break;
                
            case 'approved':
                $title = 'KYC Document Approved';
                $message = "Your {$documentType} document has been approved.";
                break;
                
            case 'rejected':
                $title = 'KYC Document Rejected';
                $message = "Your {$documentType} document has been rejected" . ($reason ? ": {$reason}" : ".");
                break;
                
            case 'pending':
                $title = 'KYC Verification Pending';
                $message = "Your KYC verification is pending review.";
                break;
                
            default:
                $title = 'KYC Status Update';
                $message = "Your KYC status has been updated to {$status}.";
        }
        
        $data = [
            'status' => $status,
            'document_type' => $documentType,
            'reason' => $reason
        ];
        
        return $this->createNotification(
            $userId,
            self::TYPE_KYC,
            $title,
            $message,
            $link,
            $data
        );
    }
    
    /**
     * Create a support ticket notification
     *
     * @param int|string $userId User ID (or 'admin' for all admins)
     * @param int $ticketId Ticket ID
     * @param string $message Notification message
     * @param string $action Action (new, reply, closed, etc.)
     * @return int|false Notification ID or false on failure
     */
    public function createSupportTicketNotification($userId, $ticketId, $message, $action) {
        $title = '';
        $link = "/support/ticket/{$ticketId}";
        
        switch ($action) {
            case 'new':
                $title = 'New Support Ticket';
                break;
                
            case 'reply':
                $title = 'Support Ticket Reply';
                break;
                
            case 'customer_reply':
                $title = 'Customer Replied to Ticket';
                break;
                
            case 'closed':
                $title = 'Support Ticket Closed';
                break;
                
            case 'reopened':
                $title = 'Support Ticket Reopened';
                break;
                
            case 'assigned':
                $title = 'Support Ticket Assigned';
                break;
                
            default:
                $title = 'Support Ticket Update';
        }
        
        $data = [
            'ticket_id' => $ticketId,
            'action' => $action
        ];
        
        return $this->createNotification(
            $userId,
            self::TYPE_SUPPORT,
            $title,
            $message,
            $link,
            $data
        );
    }
    
    /**
     * Create a security notification
     *
     * @param int $userId User ID
     * @param string $action Security action (login, password_change, etc.)
     * @param array|null $details Additional details
     * @return int|false Notification ID or false on failure
     */
    public function createSecurityNotification($userId, $action, $details = null) {
        $title = '';
        $message = '';
        $link = '/account/security';
        
        switch ($action) {
            case 'login':
                $ip = $details['ip'] ?? 'unknown IP';
                $device = $details['device'] ?? 'unknown device';
                $title = 'New Login Detected';
                $message = "A new login to your account was detected from {$ip} using {$device}.";
                break;
                
            case 'password_change':
                $title = 'Password Changed';
                $message = "Your account password was changed successfully.";
                break;
                
            case 'email_change':
                $title = 'Email Address Changed';
                $message = "Your account email address was changed successfully.";
                break;
                
            case 'two_factor_enabled':
                $title = 'Two-Factor Authentication Enabled';
                $message = "Two-factor authentication has been enabled for your account.";
                break;
                
            case 'two_factor_disabled':
                $title = 'Two-Factor Authentication Disabled';
                $message = "Two-factor authentication has been disabled for your account.";
                break;
                
            case 'failed_login':
                $ip = $details['ip'] ?? 'unknown IP';
                $title = 'Failed Login Attempt';
                $message = "A failed login attempt to your account was detected from {$ip}.";
                break;
                
            default:
                $title = 'Security Alert';
                $message = "A security-related action ({$action}) was performed on your account.";
        }
        
        return $this->createNotification(
            $userId,
            self::TYPE_SECURITY,
            $title,
            $message,
            $link,
            $details
        );
    }
} 