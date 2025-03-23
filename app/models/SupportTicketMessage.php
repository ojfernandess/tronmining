<?php
namespace App\Models;

use App\Core\Model;

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
     * Get messages for a specific ticket
     *
     * @param int $ticketId Ticket ID
     * @return array Messages
     */
    public function getTicketMessages($ticketId) {
        $sql = "SELECT m.*, u.username, u.email, u.role, u.avatar 
                FROM {$this->table} m
                LEFT JOIN users u ON m.user_id = u.id
                WHERE m.ticket_id = :ticket_id
                ORDER BY m.created_at ASC";
        
        return $this->raw($sql, ['ticket_id' => $ticketId]);
    }
    
    /**
     * Get the latest message for a ticket
     *
     * @param int $ticketId Ticket ID
     * @return object|null Latest message or null if none
     */
    public function getLatestMessage($ticketId) {
        $sql = "SELECT m.*, u.username, u.email, u.role, u.avatar 
                FROM {$this->table} m
                LEFT JOIN users u ON m.user_id = u.id
                WHERE m.ticket_id = :ticket_id
                ORDER BY m.created_at DESC
                LIMIT 1";
        
        $result = $this->raw($sql, ['ticket_id' => $ticketId]);
        return $result[0] ?? null;
    }
    
    /**
     * Count messages for a ticket
     *
     * @param int $ticketId Ticket ID
     * @return int Message count
     */
    public function countTicketMessages($ticketId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE ticket_id = :ticket_id";
        $result = $this->raw($sql, ['ticket_id' => $ticketId]);
        return $result[0]->count ?? 0;
    }
    
    /**
     * Delete a message
     * 
     * For security, only allows deletion of the latest message and by the original author
     *
     * @param int $messageId Message ID
     * @param int $userId User ID (for security check)
     * @return bool Success or failure
     */
    public function deleteMessage($messageId, $userId) {
        // Get message
        $message = $this->find($messageId);
        
        if (!$message || $message->user_id != $userId) {
            return false;
        }
        
        // Check if it's the latest message
        $sql = "SELECT id FROM {$this->table} 
                WHERE ticket_id = :ticket_id 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $latestMessage = $this->raw($sql, ['ticket_id' => $message->ticket_id])[0] ?? null;
        
        if (!$latestMessage || $latestMessage->id != $messageId) {
            return false; // Not the latest message
        }
        
        return $this->delete($messageId);
    }
    
    /**
     * Update a message
     * 
     * For security, only allows editing of the latest message and by the original author
     *
     * @param int $messageId Message ID
     * @param int $userId User ID (for security check)
     * @param string $message New message content
     * @param string|null $attachments New attachments (optional)
     * @return bool Success or failure
     */
    public function updateMessage($messageId, $userId, $message, $attachments = null) {
        // Get message
        $originalMessage = $this->find($messageId);
        
        if (!$originalMessage || $originalMessage->user_id != $userId) {
            return false;
        }
        
        // Check if it's the latest message
        $sql = "SELECT id FROM {$this->table} 
                WHERE ticket_id = :ticket_id 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $latestMessage = $this->raw($sql, ['ticket_id' => $originalMessage->ticket_id])[0] ?? null;
        
        if (!$latestMessage || $latestMessage->id != $messageId) {
            return false; // Not the latest message
        }
        
        $data = [
            'message' => $message,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($attachments !== null) {
            $data['attachments'] = $attachments;
        }
        
        return $this->update($messageId, $data);
    }
    
    /**
     * Get messages by user
     *
     * @param int $userId User ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array Messages
     */
    public function getMessagesByUser($userId, $limit = null, $offset = null) {
        $sql = "SELECT m.*, t.subject, t.ticket_id as ticket_identifier, t.status
                FROM {$this->table} m
                JOIN support_tickets t ON m.ticket_id = t.id
                WHERE m.user_id = :user_id
                ORDER BY m.created_at DESC";
        
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
     * Get messages with attachments
     *
     * @param int $ticketId Ticket ID
     * @return array Messages with attachments
     */
    public function getMessagesWithAttachments($ticketId) {
        $sql = "SELECT m.*, u.username
                FROM {$this->table} m
                LEFT JOIN users u ON m.user_id = u.id
                WHERE m.ticket_id = :ticket_id AND m.attachments IS NOT NULL AND m.attachments != ''
                ORDER BY m.created_at DESC";
        
        return $this->raw($sql, ['ticket_id' => $ticketId]);
    }
} 