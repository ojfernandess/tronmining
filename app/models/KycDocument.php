<?php
namespace App\Models;

use App\Core\Model;

/**
 * KycDocument Model
 * 
 * Handles KYC document verification
 */
class KycDocument extends Model {
    // Table name
    protected $table = 'kyc_documents';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'user_id', 'document_type', 'document_number', 'file_path',
        'status', 'rejection_reason', 'verified_by', 'verified_at'
    ];
    
    // Document types
    const TYPE_ID_CARD = 'id_card';
    const TYPE_PASSPORT = 'passport';
    const TYPE_DRIVERS_LICENSE = 'drivers_license';
    const TYPE_RESIDENCE_PROOF = 'residence_proof';
    
    // Document statuses
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    
    /**
     * Submit a KYC document
     *
     * @param int $userId User ID
     * @param string $documentType Document type
     * @param string $documentNumber Document number
     * @param string $filePath Path to uploaded file
     * @return int|bool ID of the created document or false on failure
     */
    public function submitDocument($userId, $documentType, $documentNumber, $filePath) {
        // Check if already submitted
        $existingDoc = $this->getUserDocumentByType($userId, $documentType);
        
        if ($existingDoc) {
            // Update existing document
            return $this->update($existingDoc->id, [
                'document_number' => $documentNumber,
                'file_path' => $filePath,
                'status' => self::STATUS_PENDING,
                'rejection_reason' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Create new document
        return $this->create([
            'user_id' => $userId,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'file_path' => $filePath,
            'status' => self::STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get all documents for a user
     *
     * @param int $userId User ID
     * @return array User's documents
     */
    public function getUserDocuments($userId) {
        return $this->where('user_id', $userId);
    }
    
    /**
     * Get a specific document by type for a user
     *
     * @param int $userId User ID
     * @param string $documentType Document type
     * @return object|null Document or null if not found
     */
    public function getUserDocumentByType($userId, $documentType) {
        $query = "SELECT * FROM {$this->table} 
                 WHERE user_id = :user_id AND document_type = :document_type";
        
        $result = $this->raw($query, [
            'user_id' => $userId,
            'document_type' => $documentType
        ], false);
        
        return $result ?: null;
    }
    
    /**
     * Approve a document
     *
     * @param int $documentId Document ID
     * @param int $adminId Admin/verifier ID
     * @return bool Success or failure
     */
    public function approveDocument($documentId, $adminId) {
        return $this->update($documentId, [
            'status' => self::STATUS_APPROVED,
            'verified_by' => $adminId,
            'verified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Reject a document
     *
     * @param int $documentId Document ID
     * @param int $adminId Admin/verifier ID
     * @param string $reason Rejection reason
     * @return bool Success or failure
     */
    public function rejectDocument($documentId, $adminId, $reason) {
        return $this->update($documentId, [
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'verified_by' => $adminId,
            'verified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Check if a user has all required documents approved
     *
     * @param int $userId User ID
     * @param array $requiredTypes Required document types
     * @return bool Whether all required documents are approved
     */
    public function hasApprovedRequiredDocuments($userId, $requiredTypes = []) {
        if (empty($requiredTypes)) {
            $requiredTypes = [self::TYPE_ID_CARD, self::TYPE_RESIDENCE_PROOF];
        }
        
        $documents = $this->getUserDocuments($userId);
        $approvedTypes = [];
        
        foreach ($documents as $document) {
            if ($document->status === self::STATUS_APPROVED) {
                $approvedTypes[] = $document->document_type;
            }
        }
        
        // Check if all required types are in approved types
        foreach ($requiredTypes as $type) {
            if (!in_array($type, $approvedTypes)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Update user's KYC verification status when all documents are approved
     *
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function updateUserKycStatus($userId) {
        if ($this->hasApprovedRequiredDocuments($userId)) {
            $userModel = new User();
            return $userModel->update($userId, [
                'kyc_verified' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return false;
    }
    
    /**
     * Get pending documents for admin review
     *
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Pending documents
     */
    public function getPendingDocuments($limit = 20, $offset = 0) {
        $query = "SELECT d.*, u.username, u.email 
                 FROM {$this->table} d
                 LEFT JOIN users u ON d.user_id = u.id
                 WHERE d.status = :status
                 ORDER BY d.created_at ASC
                 LIMIT :limit OFFSET :offset";
        
        return $this->raw($query, [
            'status' => self::STATUS_PENDING,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Count pending documents
     *
     * @return int Number of pending documents
     */
    public function countPendingDocuments() {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = :status";
        $result = $this->raw($query, ['status' => self::STATUS_PENDING], false);
        
        return $result ? $result->count : 0;
    }
    
    /**
     * Search documents
     *
     * @param array $filters Search filters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Documents
     */
    public function searchDocuments($filters = [], $limit = 20, $offset = 0) {
        $query = "SELECT d.*, u.username, u.email 
                 FROM {$this->table} d
                 LEFT JOIN users u ON d.user_id = u.id
                 WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['user_id'])) {
            $query .= " AND d.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (isset($filters['status'])) {
            $query .= " AND d.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (isset($filters['document_type'])) {
            $query .= " AND d.document_type = :document_type";
            $params['document_type'] = $filters['document_type'];
        }
        
        if (isset($filters['document_number'])) {
            $query .= " AND d.document_number LIKE :document_number";
            $params['document_number'] = '%' . $filters['document_number'] . '%';
        }
        
        if (isset($filters['date_from'])) {
            $query .= " AND d.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $query .= " AND d.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $query .= " ORDER BY d.created_at DESC LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        return $this->raw($query, $params);
    }
    
    /**
     * Get document statistics
     *
     * @return array Statistics
     */
    public function getDocumentStats() {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'by_type' => []
        ];
        
        // Get counts by status
        $query = "SELECT status, COUNT(*) as count 
                 FROM {$this->table} 
                 GROUP BY status";
        
        $statusCounts = $this->raw($query);
        
        foreach ($statusCounts as $row) {
            $stats[strtolower($row->status)] = $row->count;
            $stats['total'] += $row->count;
        }
        
        // Get counts by type
        $query = "SELECT document_type, COUNT(*) as count 
                 FROM {$this->table} 
                 GROUP BY document_type";
        
        $typeCounts = $this->raw($query);
        
        foreach ($typeCounts as $row) {
            $stats['by_type'][$row->document_type] = $row->count;
        }
        
        return $stats;
    }
    
    /**
     * Get a readable label for document types
     *
     * @param string $type Document type
     * @return string Type label
     */
    public static function getTypeLabel($type) {
        $labels = [
            self::TYPE_ID_CARD => 'ID Card',
            self::TYPE_PASSPORT => 'Passport',
            self::TYPE_DRIVERS_LICENSE => 'Driver\'s License',
            self::TYPE_RESIDENCE_PROOF => 'Proof of Residence'
        ];
        
        return $labels[$type] ?? 'Unknown';
    }
    
    /**
     * Get a readable label for document statuses
     *
     * @param string $status Document status
     * @return string Status label
     */
    public static function getStatusLabel($status) {
        $labels = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected'
        ];
        
        return $labels[$status] ?? 'Unknown';
    }
} 