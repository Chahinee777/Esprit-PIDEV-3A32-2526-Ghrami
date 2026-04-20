<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\PostLike;
use App\Entity\Story;
use App\Entity\User;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

class SocialService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function createFeedQueryBuilder(int $userId, string $searchQuery = '', string $sort = 'recent'): QueryBuilder
    {
        $qb = $this->em->getConnection()->createQueryBuilder();
        $qb
            ->select(
                'p.post_id',
                'p.user_id',
                'p.content',
                'p.image_url',
                'p.location',
                'p.mood',
                'p.hobby_tag',
                'p.visibility',
                'p.created_at',
                'p.updated_at',
                'p.is_hidden',
                'u.username',
                'u.full_name',
                'u.profile_picture',
                '(SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS likes_count',
                '(SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comments_count',
                'EXISTS(SELECT 1 FROM post_likes pl2 WHERE pl2.post_id = p.post_id AND pl2.user_id = :uid) AS liked_by_me'
            )
            ->from('posts', 'p')
            ->innerJoin('p', 'users', 'u', 'u.user_id = p.user_id')
            ->where(
                '(p.user_id = :uid
                    OR p.visibility = :publicVisibility
                    OR (
                        p.visibility = :friendsVisibility
                        AND p.user_id IN (
                            SELECT user2_id FROM friendships WHERE user1_id = :uid AND status = :acceptedStatus
                            UNION
                            SELECT user1_id FROM friendships WHERE user2_id = :uid AND status = :acceptedStatus
                        )
                    )
                )'
            )
            ->andWhere('(p.is_hidden = 0 OR p.user_id = :uid)')
            ->andWhere('NOT EXISTS (
                SELECT 1
                FROM hidden_posts hp
                WHERE hp.post_id = p.post_id
                  AND hp.user_id = :uid
            )')
            ->setParameter('uid', $userId, ParameterType::INTEGER)
            ->setParameter('publicVisibility', 'public')
            ->setParameter('friendsVisibility', 'friends')
            ->setParameter('acceptedStatus', 'ACCEPTED');

        if ($searchQuery !== '') {
            $qb
                ->andWhere('(u.username LIKE :search OR u.full_name LIKE :search OR p.content LIKE :search)')
                ->setParameter('search', '%' . $searchQuery . '%');
        }

        if ($sort === 'popular') {
            $qb
                ->addOrderBy('((SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) + (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id))', 'DESC')
                ->addOrderBy('p.created_at', 'DESC');
        } else {
            $qb->addOrderBy('p.created_at', 'DESC');
        }

        return $qb;
    }

    public function getFeedForUser(int $userId, int $page = 1, int $perPage = 20, string $searchQuery = '', string $sort = 'recent'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.post_id, p.user_id, p.content, p.image_url, p.location, p.mood, p.hobby_tag, p.visibility, p.created_at, p.updated_at, p.is_hidden,
                       u.username, u.full_name, u.profile_picture,
                       (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS likes_count,
                       (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comments_count,
                       EXISTS(SELECT 1 FROM post_likes pl2 WHERE pl2.post_id = p.post_id AND pl2.user_id = :uid) AS liked_by_me
                FROM posts p
                JOIN users u ON u.user_id = p.user_id
                WHERE (
                    p.user_id = :uid
                    OR p.visibility = 'public'
                    OR (
                        p.visibility = 'friends'
                        AND p.user_id IN (
                            SELECT user2_id FROM friendships WHERE user1_id = :uid AND status = 'ACCEPTED'
                            UNION
                            SELECT user1_id FROM friendships WHERE user2_id = :uid AND status = 'ACCEPTED'
                        )
                    )
                )
                AND (p.is_hidden = 0 OR p.user_id = :uid)";
        
        $params = ['uid' => $userId];
        
        // Add search filter
        if ($searchQuery !== '') {
            $sql .= " AND (u.username LIKE :search OR u.full_name LIKE :search OR p.content LIKE :search)";
            $params['search'] = '%' . $searchQuery . '%';
        }
        
        // Add sort
        if ($sort === 'popular') {
            $sql .= " ORDER BY ((SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) + (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id)) DESC, p.created_at DESC";
        } else {
            $sql .= " ORDER BY p.created_at DESC";
        }
        
        $sql .= " LIMIT :limit OFFSET :offset";
        $params['limit'] = $perPage;
        $params['offset'] = $offset;

        return $this->em->getConnection()->fetchAllAssociative($sql, $params, [
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ]);
    }

    public function createPost(int $userId, string $content, ?string $imageUrl = null, ?string $location = null, ?string $mood = null, ?string $hobbyTag = null, string $visibility = 'public'): Post
    {
        $user = $this->em->getRepository(User::class)->find($userId);
        $post = new Post();
        $post->user = $user;
        $post->content = $content;
        $post->imageUrl = $imageUrl;
        $post->location = $location;
        $post->mood = $mood;
        $post->hobbyTag = $hobbyTag;
        $post->visibility = $visibility;
        $post->createdAt = new \DateTime();
        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    public function addComment(int $postId, int $userId, ?string $content, ?string $imageUrl = null, ?string $mood = null): Comment
    {
        $post = $this->em->getRepository(Post::class)->find($postId);
        $user = $this->em->getRepository(User::class)->find($userId);

        $comment = new Comment();
        $comment->post = $post;
        $comment->user = $user;
        $comment->content = $content;
        $comment->imageUrl = $imageUrl;
        $comment->mood = $mood;
        $comment->createdAt = new \DateTime();
        $this->em->persist($comment);
        $this->em->flush();

        if ($post?->user?->id !== null && (int) $post->user->id !== $userId) {
            $this->createNotification(
                (int) $post->user->id,
                'COMMENT',
                'Someone commented on your post.',
                $userId
            );
        }

        return $comment;
    }

    public function toggleLike(int $postId, int $userId): bool
    {
        $post = $this->em->getRepository(Post::class)->find($postId);
        $user = $this->em->getRepository(User::class)->find($userId);

        $like = $this->em->getRepository(PostLike::class)->findOneBy(['post' => $post, 'user' => $user]);
        if ($like) {
            $this->em->remove($like);
            $this->em->flush();
            return false;
        }

        $like = new PostLike();
        $like->post = $post;
        $like->user = $user;
        $like->createdAt = new \DateTime();
        $this->em->persist($like);
        $this->em->flush();

        if ($post?->user?->id !== null && (int) $post->user->id !== $userId) {
            $this->createNotification(
                (int) $post->user->id,
                'POST_LIKE',
                'Someone liked your post.',
                $userId
            );
        }

        return true;
    }

    public function getActiveStoriesForUser(int $userId): array
    {
        $sql = "SELECT s.story_id, s.user_id, s.caption, s.image_url, s.created_at, s.expires_at,
                       u.username, u.full_name, u.profile_picture
                FROM stories s
                JOIN users u ON u.user_id = s.user_id
                WHERE s.expires_at > NOW()
                  AND (s.user_id = :uid
                    OR s.user_id IN (
                        SELECT user2_id FROM friendships WHERE user1_id = :uid AND status='ACCEPTED'
                        UNION
                        SELECT user1_id FROM friendships WHERE user2_id = :uid AND status='ACCEPTED'
                    )
                  )
                ORDER BY s.created_at DESC";

        return $this->em->getConnection()->fetchAllAssociative($sql, ['uid' => $userId]);
    }

    public function createStory(int $userId, ?string $caption, ?string $imageUrl): Story
    {
        $user = $this->em->getRepository(User::class)->find($userId);

        $story = new Story();
        $story->user = $user;
        $story->caption = $caption;
        $story->imageUrl = $imageUrl;
        $story->createdAt = new \DateTime();
        $story->expiresAt = (new \DateTime())->modify('+24 hours');
        $this->em->persist($story);
        $this->em->flush();

        return $story;
    }

    public function getCommentsForPosts(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT c.comment_id, c.post_id, c.user_id, c.content, c.image_url, c.mood, c.created_at, c.updated_at, u.username, u.full_name, u.profile_picture
             FROM comments c
             JOIN users u ON u.user_id = c.user_id
             WHERE c.post_id IN (:postIds)
             ORDER BY c.created_at ASC',
            ['postIds' => array_values($postIds)],
            ['postIds' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $pid = (int) $row['post_id'];
            if (!isset($grouped[$pid])) {
                $grouped[$pid] = [];
            }
            $row['is_reply'] = false;
            $row['reply_to_username'] = null;
            $row['replies'] = [];
            $grouped[$pid][] = $row;
        }

        return $grouped;
    }

    /**
     * Build comment threads based on @mentions (replies to specific users)
     * Comments starting with @username are grouped as threads with parent comments
     */
    public function buildCommentThreads(array $comments): array
    {
        $threads = [];
        $commentById = [];
        
        // Index comments by ID for quick lookup
        foreach ($comments as $comment) {
            $commentById[(int)$comment['comment_id']] = $comment;
        }

        // Detect replies (comments that mention other users with @username)
        foreach ($comments as &$comment) {
            $content = $comment['content'];
            $comment['is_reply'] = false;
            $comment['reply_to_username'] = null;
            $comment['replies'] = [];
            
            // Check if comment starts with @mention
            if (preg_match('/^@(\w+)\s+/', $content, $matches)) {
                $comment['reply_to_username'] = $matches[1];
                $comment['is_reply'] = true;
            }
        }

        // Build thread hierarchy
        foreach ($comments as &$comment) {
            if (!$comment['is_reply']) {
                // This is a top-level comment
                $threads[] = &$comment;
            } else {
                // This is a reply - find parent and attach as child
                $parentUsername = $comment['reply_to_username'];
                $parentFound = false;
                
                // Search backwards for the parent comment
                foreach (array_reverse($comments) as &$potentialParent) {
                    if ($potentialParent['username'] === $parentUsername && 
                        $potentialParent['comment_id'] !== $comment['comment_id']) {
                        if (!isset($potentialParent['replies'])) {
                            $potentialParent['replies'] = [];
                        }
                        $potentialParent['replies'][] = &$comment;
                        $parentFound = true;
                        break;
                    }
                }
                
                // If no parent found, treat as top-level
                if (!$parentFound) {
                    $threads[] = &$comment;
                }
            }
        }

        return $threads;
    }

    public function deletePost(int $postId, int $userId): bool
    {
        $post = $this->em->getRepository(Post::class)->find($postId);
        if (!$post instanceof Post || (int) ($post->user?->id ?? 0) !== $userId) {
            return false;
        }

        $this->em->remove($post);
        $this->em->flush();

        return true;
    }

    public function updatePostContent(int $postId, int $userId, string $content): bool
    {
        $post = $this->em->getRepository(Post::class)->find($postId);
        if (!$post instanceof Post || (int) ($post->user?->id ?? 0) !== $userId) {
            return false;
        }

        $post->content = $content;
        $post->updatedAt = new \DateTime();
        $this->em->flush();

        return true;
    }

    public function updateComment(int $commentId, int $userId, ?string $content, ?string $imageUrl = null, ?string $mood = null): bool
    {
        $comment = $this->em->getRepository(Comment::class)->find($commentId);
        if (!$comment instanceof Comment || (int) ($comment->user?->id ?? 0) !== $userId) {
            return false;
        }

        $comment->content = $content;
        $comment->imageUrl = $imageUrl;
        $comment->mood = $mood;
        $comment->updatedAt = new \DateTime();
        $this->em->flush();

        return true;
    }

    public function deleteComment(int $commentId, int $userId): bool
    {
        $comment = $this->em->getRepository(Comment::class)->find($commentId);
        if (!$comment instanceof Comment || (int) ($comment->user?->id ?? 0) !== $userId) {
            return false;
        }

        $this->em->remove($comment);
        $this->em->flush();

        return true;
    }

    private function createNotification(int $userId, string $type, string $content, ?int $relatedUserId = null): void
    {
        $this->em->getConnection()->insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'content' => $content,
            'related_user_id' => $relatedUserId,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'is_read' => 0,
        ]);
    }

    public function searchUsers(string $query, int $limit = 20): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT user_id, username, full_name, profile_picture 
             FROM users 
             WHERE (username LIKE :q OR full_name LIKE :q) 
             ORDER BY full_name ASC 
             LIMIT :lim",
            ['q' => "%{$query}%", 'lim' => $limit],
            ['lim' => \Doctrine\DBAL\ParameterType::INTEGER]
        );
    }

    public function getExpiredStories(): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT * FROM stories WHERE expires_at <= NOW()"
        );
    }

    public function deleteStory(int $storyId): bool
    {
        return (bool) $this->em->getConnection()->delete('stories', ['story_id' => $storyId]);
    }

    public function deleteExpiredStories(): int
    {
        return $this->em->getConnection()->executeStatement(
            "DELETE FROM stories WHERE expires_at <= NOW()"
        );
    }

    public function getStoriesForFeed(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT s.story_id, s.user_id, s.image_url, s.caption, s.created_at, s.expires_at,
                           u.username, u.full_name, u.profile_picture
                    FROM stories s
                    JOIN users u ON u.user_id = s.user_id
                    WHERE s.expires_at > NOW()
                      AND (s.user_id = :uid 
                           OR s.user_id IN (
                              SELECT user2_id FROM friendships WHERE user1_id = :uid AND status = 'ACCEPTED'
                              UNION
                              SELECT user1_id FROM friendships WHERE user2_id = :uid AND status = 'ACCEPTED'
                           ))
                    ORDER BY s.created_at DESC",
            ['uid' => $userId]
        );
    }

    public function searchMessages(int $userId, int $otherUserId, string $query): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT m.message_id, m.sender_id, m.receiver_id, m.content, m.sent_at, m.is_read
             FROM messages m
             WHERE ((m.sender_id = :u1 AND m.receiver_id = :u2) OR (m.sender_id = :u2 AND m.receiver_id = :u1))
               AND m.content LIKE :q
             ORDER BY m.sent_at DESC
             LIMIT 100",
            ['u1' => $userId, 'u2' => $otherUserId, 'q' => "%{$query}%"]
        );
    }

    public function hidePost(int $postId, int $userId): ?bool
    {
        $post = $this->em->getRepository(Post::class)->find($postId);
        if (!$post instanceof Post || $userId <= 0 || (int) ($post->user?->id ?? 0) === $userId) {
            return null;
        }

        $connection = $this->em->getConnection();
        $alreadyHidden = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM hidden_posts WHERE user_id = :userId AND post_id = :postId',
            ['userId' => $userId, 'postId' => $postId],
            ['userId' => ParameterType::INTEGER, 'postId' => ParameterType::INTEGER]
        ) > 0;

        if ($alreadyHidden) {
            $connection->delete('hidden_posts', ['user_id' => $userId, 'post_id' => $postId]);
            return false;
        }

        $connection->insert('hidden_posts', [
            'user_id' => $userId,
            'post_id' => $postId,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        return true;
    }
}