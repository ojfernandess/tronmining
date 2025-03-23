<?php
namespace App\Models;

use App\Core\Model;

/**
 * SupportTicket Model
 * 
 * Handles support ticket-related database operations
 */
class SupportTicket extends Model {
    // Table name
    protected $table = 'support_tickets';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'user_id', 'subject', 'ticket_id', 'priority', 'status', 'last_reply', 'assigned_to'
    ];
    
    /**
     * Ticket priorities
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    
    /**
     * Ticket statuses
     */
    const STATUS_OPEN = 'open';
    const STATUS_ANSWERED = 'answered';
    const STATUS_CUSTOMER_REPLY = 'customer_reply';
    const STATUS_CLOSED = 'closed';
    
    /**
     * Get all tickets for a specific user
     *
     * @param int $userId User ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array Tickets
     */
    public function getUserTickets($userId, $limit = null, $offset = null) {
        $sql = "SELECT t.*, 
                (SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id = t.id) as message_count,
                (SELECT created_at FROM support_ticket_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message_date
                FROM {$this->table} t
                WHERE t.user_id = :user_id
                ORDER BY
                CASE 
                    WHEN t.status = '" . self::STATUS_OPEN . "' THEN 1
                    WHEN t.status = '" . self::STATUS_CUSTOMER_REPLY . "' THEN 2
                    WHEN t.status = '" . self::STATUS_ANSWERED . "' THEN 3
                    WHEN t.status = '" . self::STATUS_CLOSED . "' THEN 4
                    ELSE 5
                END, t.created_at DESC";
        
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
     * Get a specific ticket
     *
     * @param int $id Ticket ID
     * @param int|null $userId Optional user ID to validate ownership
     * @return object|null Ticket or null if not found or not owned by user
     */
    public function getTicket($id, $userId = null) {
        $sql = "SELECT t.*, u.username, u.email, u.role, 
                (SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id = t.id) as message_count,
                (SELECT created_at FROM support_ticket_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message_date,
                (SELECT username FROM users WHERE id = t.assigned_to) as assigned_to_name
                FROM {$this->table} t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.id = :id";
        
        $params = ['id' => $id];
        
        if ($userId !== null) {
            $sql .= " AND (t.user_id = :user_id OR :user_id IN (SELECT id FROM users WHERE role = 'admin'))";
            $params['user_id'] = $userId;
        }
        
        $result = $this->raw($sql, $params);
        return $result[0] ?? null;
    }
    
    /**
     * Get a ticket by ticket ID
     *
     * @param string $ticketId Ticket ID (e.g., TIC-123456)
     * @param int|null $userId Optional user ID to validate ownership
     * @return object|null Ticket or null if not found or not owned by user
     */
    public function getTicketByTicketId($ticketId, $userId = null) {
        $sql = "SELECT t.*, u.username, u.email, u.role, 
                (SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id = t.id) as message_count,
                (SELECT created_at FROM support_ticket_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message_date,
                (SELECT username FROM users WHERE id = t.assigned_to) as assigned_to_name
                FROM {$this->table} t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.ticket_id = :ticket_id";
        
        $params = ['ticket_id' => $ticketId];
        
        if ($userId !== null) {
            $sql .= " AND (t.user_id = :user_id OR :user_id IN (SELECT id FROM users WHERE role = 'admin'))";
            $params['user_id'] = $userId;
        }
        
        $result = $this->raw($sql, $params);
        return $result[0] ?? null;
    }
    
    /**
     * Create a new support ticket
     *
     * @param int $userId User ID
     * @param string $subject Ticket subject
     * @param string $message Initial message
     * @param string $priority Ticket priority (default: medium)
     * @param string|null $attachments File attachments, if any
     * @return int|false Ticket ID or false on failure
     */
    public function createTicket($userId, $subject, $message, $priority = self::PRIORITY_MEDIUM, $attachments = null) {
        // Generate a ticket ID
        $ticketId = $this->generateTicketId();
        
        // Create ticket data
        $ticketData = [
            'user_id' => $userId,
            'subject' => $subject,
            'ticket_id' => $ticketId,
            'priority' => $priority,
            'status' => self::STATUS_OPEN,
            'last_reply' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Use transaction to ensure both ticket and first message are created
        try {
            // Begin transaction
            $this->beginTransaction();
            
            // Create the ticket
            $id = $this->create($ticketData);
            
            if (!$id) {
                $this->rollback();
                return false;
            }
            
            // Create the first message
            $messageModel = new SupportTicketMessage();
            $messageData = [
                'ticket_id' => $id,
                'user_id' => $userId,
                'message' => $message,
                'attachments' => $attachments,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $messageId = $messageModel->create($messageData);
            
            if (!$messageId) {
                $this->rollback();
                return false;
            }
            
            // Commit transaction
            $this->commit();
            
            // Create notification for admin
            $notificationModel = new Notification();
            $notificationModel->createSupportTicketNotification(
                'admin', // Notify admin
                $id,
                'New support ticket created: ' . $subject,
                'new'
            );
            
            return $id;
        } catch (\Exception $e) {
            $this->rollback();
            // Log error
            error_log('Failed to create support ticket: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add a reply to a ticket
     *
     * @param int $ticketId Ticket ID
     * @param int $userId User ID
     * @param string $message Reply message
     * @param string|null $attachments File attachments, if any
     * @return int|false Message ID or false on failure
     */
    public function addReply($ticketId, $userId, $message, $attachments = null) {
        // Get the ticket
        $ticket = $this->find($ticketId);
        
        if (!$ticket) {
            return false;
        }
        
        // Create the message
        $messageModel = new SupportTicketMessage();
        $messageData = [
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'message' => $message,
            'attachments' => $attachments,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $messageId = $messageModel->create($messageData);
        
        if (!$messageId) {
            return false;
        }
        
        // Get user role to determine new status
        $userModel = new User();
        $user = $userModel->find($userId);
        
        // Update ticket status based on who replied
        $newStatus = null;
        $notifyUserId = null;
        $notificationType = null;
        
        if ($user && ($user->role === 'admin' || $user->role === 'staff')) {
            // Admin/staff replied, mark as answered
            $newStatus = self::STATUS_ANSWERED;
            $notifyUserId = $ticket->user_id; // Notify customer
            $notificationType = 'reply';
        } else {
            // Customer replied, mark as customer-reply
            $newStatus = self::STATUS_CUSTOMER_REPLY;
            $notifyUserId = $ticket->assigned_to ?: 'admin'; // Notify assigned staff or admin
            $notificationType = 'customer_reply';
        }
        
        // Update ticket status and last reply time
        $this->update($ticketId, [
            'status' => $newStatus,
            'last_reply' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Create notification
        $notificationModel = new Notification();
        $notificationModel->createSupportTicketNotification(
            $notifyUserId,
            $ticketId,
            'New reply to ticket: ' . $ticket->subject,
            $notificationType
        );
        
        return $messageId;
    }
    
    /**
     * Close a ticket
     *
     * @param int $ticketId Ticket ID
     * @param int $userId User ID (for security check)
     * @return bool Success or failure
     */
    public function closeTicket($ticketId, $userId) {
        $ticket = $this->find($ticketId);
        
        if (!$ticket) {
            return false;
        }
        
        // Security check - only ticket owner or admin can close
        $userModel = new User();
        $user = $userModel->find($userId);
        
        if (!$user || ($user->role !== 'admin' && $user->role !== 'staff' && $ticket->user_id !== $userId)) {
            return false;
        }
        
        // Update ticket status
        $result = $this->update($ticketId, [
            'status' => self::STATUS_CLOSED,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            // Create notification for the other party
            $notificationModel = new Notification();
            $notifyUserId = ($user->role === 'admin' || $user->role === 'staff') ? $ticket->user_id : 'admin';
            
            $notificationModel->createSupportTicketNotification(
                $notifyUserId,
                $ticketId,
                'Ticket closed: ' . $ticket->subject,
                'closed'
            );
        }
        
        return (bool) $result;
    }
    
    /**
     * Reopen a closed ticket
     *
     * @param int $ticketId Ticket ID
     * @param int $userId User ID (for security check)
     * @return bool Success or failure
     */
    public function reopenTicket($ticketId, $userId) {
        $ticket = $this->find($ticketId);
        
        if (!$ticket || $ticket->status !== self::STATUS_CLOSED) {
            return false;
        }
        
        // Security check - only ticket owner or admin can reopen
        $userModel = new User();
        $user = $userModel->find($userId);
        
        if (!$user || ($user->role !== 'admin' && $user->role !== 'staff' && $ticket->user_id !== $userId)) {
            return false;
        }
        
        // Update ticket status
        $result = $this->update($ticketId, [
            'status' => self::STATUS_OPEN,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            // Create notification for the other party
            $notificationModel = new Notification();
            $notifyUserId = ($user->role === 'admin' || $user->role === 'staff') ? $ticket->user_id : 'admin';
            
            $notificationModel->createSupportTicketNotification(
                $notifyUserId,
                $ticketId,
                'Ticket reopened: ' . $ticket->subject,
                'reopened'
            );
        }
        
        return (bool) $result;
    }
    
    /**
     * Assign a ticket to a staff member
     *
     * @param int $ticketId Ticket ID
     * @param int $staffId Staff user ID
     * @return bool Success or failure
     */
    public function assignTicket($ticketId, $staffId) {
        $ticket = $this->find($ticketId);
        
        if (!$ticket) {
            return false;
        }
        
        // Update ticket
        $result = $this->update($ticketId, [
            'assigned_to' => $staffId,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            // Notify staff member
            $notificationModel = new Notification();
            $notificationModel->createSupportTicketNotification(
                $staffId,
                $ticketId,
                'Ticket assigned to you: ' . $ticket->subject,
                'assigned'
            );
        }
        
        return (bool) $result;
    }
    
    /**
     * Get all messages for a ticket
     *
     * @param int $ticketId Ticket ID
     * @return array Ticket messages
     */
    public function getTicketMessages($ticketId) {
        $messageModel = new SupportTicketMessage();
        return $messageModel->getTicketMessages($ticketId);
    }
    
    /**
     * Generate a unique ticket ID
     *
     * @return string Ticket ID (e.g., TIC-123456)
     */
    public function generateTicketId() {
        $prefix = 'TIC-';
        $ticketId = $prefix . time() . rand(1000, 9999);
        
        // Check if already exists and regenerate if needed
        while ($this->getTicketByTicketId($ticketId)) {
            $ticketId = $prefix . time() . rand(1000, 9999);
        }
        
        return $ticketId;
    }
    
    /**
     * Get statistics about tickets
     *
     * @return array Ticket statistics
     */
    public function getTicketsStats() {
        $sql = "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = :open THEN 1 END) as open,
                COUNT(CASE WHEN status = :answered THEN 1 END) as answered,
                COUNT(CASE WHEN status = :customer_reply THEN 1 END) as customer_reply,
                COUNT(CASE WHEN status = :closed THEN 1 END) as closed
                FROM {$this->table}";
        
        $params = [
            'open' => self::STATUS_OPEN,
            'answered' => self::STATUS_ANSWERED,
            'customer_reply' => self::STATUS_CUSTOMER_REPLY,
            'closed' => self::STATUS_CLOSED
        ];
        
        $result = $this->raw($sql, $params);
        return $result[0] ?? [
            'total' => 0,
            'open' => 0,
            'answered' => 0,
            'customer_reply' => 0,
            'closed' => 0
        ];
    }
    
    /**
     * Get recent tickets (for admin dashboard)
     *
     * @param int $limit Limit
     * @return array Recent tickets
     */
    public function getRecentTickets($limit = 5) {
        $sql = "SELECT t.*, u.username 
                FROM {$this->table} t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.status != :closed
                ORDER BY t.last_reply DESC
                LIMIT :limit";
        
        return $this->raw($sql, [
            'closed' => self::STATUS_CLOSED,
            'limit' => (int) $limit
        ]);
    }
}

/**
 * SupportTicketMessage Model
 * 
 * Handles support ticket message-related database operations
 */
class SupportTicketMessage extends Model {
    // Table name
    protected $table = 'support_ticket_messages';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'ticket_id', 'user_id', 'message', 'attachments'
    ];
    
    /**
     * Get messages for a ticket
     *
     * @param int $ticketId Ticket ID
     * @return array Ticket messages
     */
    public function getTicketMessages($ticketId) {
        $sql = "SELECT tm.*, u.username, u.role, u.avatar
                FROM {$this->table} tm
                JOIN users u ON tm.user_id = u.id
                WHERE tm.ticket_id = :ticket_id
                ORDER BY tm.created_at ASC";
        
        return $this->raw($sql, ['ticket_id' => $ticketId]);
    }
    
    /**
     * Get latest message for a ticket
     *
     * @param int $ticketId Ticket ID
     * @return object|null Latest message or null if none found
     */
    public function getLatestMessage($ticketId) {
        $sql = "SELECT tm.*, u.username, u.role
                FROM {$this->table} tm
                JOIN users u ON tm.user_id = u.id
                WHERE tm.ticket_id = :ticket_id
                ORDER BY tm.created_at DESC
                LIMIT 1";
        
        return $this->raw($sql, ['ticket_id' => $ticketId], false);
    }
} 