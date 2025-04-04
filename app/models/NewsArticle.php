<?php

namespace App\Models;

use App\Core\Model;

class NewsArticle extends Model
{
    protected $table = 'news_articles';
    
    protected $fillable = [
        'title', 
        'slug', 
        'content', 
        'author_id', 
        'image', 
        'category', 
        'tags', 
        'status', 
        'featured', 
        'view_count'
    ];
    
    // Article statuses
    const STATUS_PUBLISHED = 'published';
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    
    // Article categories
    const CATEGORY_ANNOUNCEMENT = 'announcement';
    const CATEGORY_TUTORIAL = 'tutorial';
    const CATEGORY_UPDATE = 'update';
    const CATEGORY_NEWS = 'news';
    const CATEGORY_GUIDE = 'guide';
    
    /**
     * Get all published articles
     * 
     * @param array $filters Optional filters (category, tag, date_from, date_to)
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getPublishedArticles(array $filters = [], $limit = null, $offset = null)
    {
        $query = "SELECT a.*, u.username as author_name 
                 FROM {$this->table} a
                 LEFT JOIN users u ON a.author_id = u.id
                 WHERE a.status = ?";
        
        $params = [self::STATUS_PUBLISHED];
        
        // Apply filters
        if (isset($filters['category'])) {
            $query .= " AND a.category = ?";
            $params[] = $filters['category'];
        }
        
        if (isset($filters['tag'])) {
            $query .= " AND a.tags LIKE ?";
            $params[] = '%' . $filters['tag'] . '%';
        }
        
        if (isset($filters['date_from'])) {
            $query .= " AND a.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $query .= " AND a.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (isset($filters['featured'])) {
            $query .= " AND a.featured = ?";
            $params[] = $filters['featured'] ? 1 : 0;
        }
        
        $query .= " ORDER BY a.created_at DESC";
        
        if ($limit !== null) {
            $query .= " LIMIT ?";
            $params[] = $limit;
            
            if ($offset !== null) {
                $query .= " OFFSET ?";
                $params[] = $offset;
            }
        }
        
        return $this->query($query, $params);
    }
    
    /**
     * Get a single article by slug
     * 
     * @param string $slug
     * @param bool $incrementViews Whether to increment the view count
     * @return array|null
     */
    public function getArticleBySlug($slug, $incrementViews = true)
    {
        $query = "SELECT a.*, u.username as author_name 
                 FROM {$this->table} a
                 LEFT JOIN users u ON a.author_id = u.id
                 WHERE a.slug = ? AND a.status = ?";
        
        $result = $this->query($query, [$slug, self::STATUS_PUBLISHED]);
        
        if (empty($result)) {
            return null;
        }
        
        $article = $result[0];
        
        // Increment view count if requested
        if ($incrementViews) {
            $this->incrementViewCount($article['id']);
        }
        
        return $article;
    }
    
    /**
     * Get all articles for admin management
     * 
     * @param array $filters Optional filters (status, category, author_id)
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllArticles(array $filters = [], $limit = null, $offset = null)
    {
        $query = "SELECT a.*, u.username as author_name 
                 FROM {$this->table} a
                 LEFT JOIN users u ON a.author_id = u.id";
        
        $params = [];
        $whereAdded = false;
        
        // Apply filters
        if (isset($filters['status'])) {
            $query .= " WHERE a.status = ?";
            $params[] = $filters['status'];
            $whereAdded = true;
        }
        
        if (isset($filters['category'])) {
            $query .= $whereAdded ? " AND" : " WHERE";
            $query .= " a.category = ?";
            $params[] = $filters['category'];
            $whereAdded = true;
        }
        
        if (isset($filters['author_id'])) {
            $query .= $whereAdded ? " AND" : " WHERE";
            $query .= " a.author_id = ?";
            $params[] = $filters['author_id'];
            $whereAdded = true;
        }
        
        if (isset($filters['featured'])) {
            $query .= $whereAdded ? " AND" : " WHERE";
            $query .= " a.featured = ?";
            $params[] = $filters['featured'] ? 1 : 0;
            $whereAdded = true;
        }
        
        $query .= " ORDER BY a.created_at DESC";
        
        if ($limit !== null) {
            $query .= " LIMIT ?";
            $params[] = $limit;
            
            if ($offset !== null) {
                $query .= " OFFSET ?";
                $params[] = $offset;
            }
        }
        
        return $this->query($query, $params);
    }
    
    /**
     * Create a new article
     * 
     * @param array $data Article data
     * @return int|bool ID of the created article or false on failure
     */
    public function createArticle(array $data)
    {
        // Generate slug if not provided
        if (!isset($data['slug']) || empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }
        
        // JSON encode tags if they're an array
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }
        
        // Set default values
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['view_count'] = $data['view_count'] ?? 0;
        $data['featured'] = $data['featured'] ?? 0;
        
        return $this->create($data);
    }
    
    /**
     * Update an article
     * 
     * @param int $id Article ID
     * @param array $data Article data
     * @return bool Success or failure
     */
    public function updateArticle($id, array $data)
    {
        // Generate slug if title changed and slug not provided
        if (isset($data['title']) && (!isset($data['slug']) || empty($data['slug']))) {
            $data['slug'] = $this->generateSlug($data['title'], $id);
        }
        
        // JSON encode tags if they're an array
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->update($id, $data);
    }
    
    /**
     * Delete an article
     * 
     * @param int $id Article ID
     * @return bool Success or failure
     */
    public function deleteArticle($id)
    {
        return $this->delete($id);
    }
    
    /**
     * Generate a unique slug from a title
     * 
     * @param string $title Article title
     * @param int|null $excludeId ID to exclude when checking for duplicates
     * @return string Unique slug
     */
    protected function generateSlug($title, $excludeId = null)
    {
        // Create base slug
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $title), '-'));
        $slug = preg_replace('/-+/', '-', $slug); // Replace multiple dashes with a single dash
        
        // Check if slug exists
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId !== null) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->query($query, $params);
        $count = $result[0]['count'] ?? 0;
        
        // If slug exists, add a suffix
        if ($count > 0) {
            $baseSlug = $slug;
            $i = 1;
            
            do {
                $slug = $baseSlug . '-' . $i;
                $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE slug = ?";
                $params = [$slug];
                
                if ($excludeId !== null) {
                    $query .= " AND id != ?";
                    $params[] = $excludeId;
                }
                
                $result = $this->query($query, $params);
                $count = $result[0]['count'] ?? 0;
                $i++;
            } while ($count > 0);
        }
        
        return $slug;
    }
    
    /**
     * Increment article view count
     * 
     * @param int $id Article ID
     * @return bool Success or failure
     */
    protected function incrementViewCount($id)
    {
        $query = "UPDATE {$this->table} SET view_count = view_count + 1 WHERE id = ?";
        
        $this->db->prepare($query);
        $result = $this->db->execute([$id]);
        
        return $result && $this->db->rowCount() > 0;
    }
    
    /**
     * Get related articles
     * 
     * @param int $articleId Article ID
     * @param int $limit
     * @return array
     */
    public function getRelatedArticles($articleId, $limit = 3)
    {
        // Get the article to find related ones
        $article = $this->getById($articleId);
        
        if (!$article) {
            return [];
        }
        
        // Get articles in the same category, excluding the current one
        $query = "SELECT a.*, u.username as author_name 
                 FROM {$this->table} a
                 LEFT JOIN users u ON a.author_id = u.id
                 WHERE a.id != ? AND a.status = ? AND a.category = ?
                 ORDER BY a.created_at DESC
                 LIMIT ?";
        
        return $this->query($query, [
            $articleId, 
            self::STATUS_PUBLISHED,
            $article['category'],
            $limit
        ]);
    }
    
    /**
     * Get popular articles
     * 
     * @param int $limit
     * @return array
     */
    public function getPopularArticles($limit = 5)
    {
        $query = "SELECT a.*, u.username as author_name 
                 FROM {$this->table} a
                 LEFT JOIN users u ON a.author_id = u.id
                 WHERE a.status = ?
                 ORDER BY a.view_count DESC
                 LIMIT ?";
        
        return $this->query($query, [self::STATUS_PUBLISHED, $limit]);
    }
    
    /**
     * Get featured articles
     * 
     * @param int $limit
     * @return array
     */
    public function getFeaturedArticles($limit = 3)
    {
        $query = "SELECT a.*, u.username as author_name 
                 FROM {$this->table} a
                 LEFT JOIN users u ON a.author_id = u.id
                 WHERE a.status = ? AND a.featured = 1
                 ORDER BY a.created_at DESC
                 LIMIT ?";
        
        return $this->query($query, [self::STATUS_PUBLISHED, $limit]);
    }
    
    /**
     * Search articles
     * 
     * @param string $keyword
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function searchArticles($keyword, $limit = 10, $offset = 0)
    {
        $query = "SELECT a.*, u.username as author_name 
                 FROM {$this->table} a
                 LEFT JOIN users u ON a.author_id = u.id
                 WHERE a.status = ? AND (
                     a.title LIKE ? OR
                     a.content LIKE ? OR
                     a.tags LIKE ?
                 )
                 ORDER BY a.created_at DESC
                 LIMIT ? OFFSET ?";
        
        $likeParam = '%' . $keyword . '%';
        
        return $this->query($query, [
            self::STATUS_PUBLISHED,
            $likeParam,
            $likeParam,
            $likeParam,
            $limit,
            $offset
        ]);
    }
    
    /**
     * Get articles by tags
     * 
     * @param string $tag
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getArticlesByTag($tag, $limit = 10, $offset = 0)
    {
        $query = "SELECT a.*, u.username as author_name 
                 FROM {$this->table} a
                 LEFT JOIN users u ON a.author_id = u.id
                 WHERE a.status = ? AND a.tags LIKE ?
                 ORDER BY a.created_at DESC
                 LIMIT ? OFFSET ?";
        
        return $this->query($query, [
            self::STATUS_PUBLISHED,
            '%' . $tag . '%',
            $limit,
            $offset
        ]);
    }
    
    /**
     * Get article categories with count
     * 
     * @return array
     */
    public function getCategoriesWithCount()
    {
        $query = "SELECT category, COUNT(*) as count 
                 FROM {$this->table} 
                 WHERE status = ?
                 GROUP BY category
                 ORDER BY count DESC";
        
        return $this->query($query, [self::STATUS_PUBLISHED]);
    }
    
    /**
     * Get all tags used in published articles
     * 
     * @return array
     */
    public function getAllTags()
    {
        $query = "SELECT tags FROM {$this->table} WHERE status = ?";
        $result = $this->query($query, [self::STATUS_PUBLISHED]);
        
        $allTags = [];
        foreach ($result as $row) {
            if (!empty($row['tags'])) {
                $tags = json_decode($row['tags'], true);
                if (is_array($tags)) {
                    $allTags = array_merge($allTags, $tags);
                }
            }
        }
        
        // Count occurrences
        $tagCounts = array_count_values($allTags);
        
        // Format for return
        $formattedTags = [];
        foreach ($tagCounts as $tag => $count) {
            $formattedTags[] = [
                'name' => $tag,
                'count' => $count
            ];
        }
        
        // Sort by count
        usort($formattedTags, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return $formattedTags;
    }
    
    /**
     * Get a readable label for article statuses
     * 
     * @param string $status
     * @return string
     */
    public static function getStatusLabel($status)
    {
        $labels = [
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending Review'
        ];
        
        return $labels[$status] ?? 'Unknown';
    }
    
    /**
     * Get a readable label for article categories
     * 
     * @param string $category
     * @return string
     */
    public static function getCategoryLabel($category)
    {
        $labels = [
            self::CATEGORY_ANNOUNCEMENT => 'Announcement',
            self::CATEGORY_TUTORIAL => 'Tutorial',
            self::CATEGORY_UPDATE => 'Update',
            self::CATEGORY_NEWS => 'News',
            self::CATEGORY_GUIDE => 'Guide'
        ];
        
        return $labels[$category] ?? 'Uncategorized';
    }
}
