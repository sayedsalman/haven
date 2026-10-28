<?php
/**
 * GraphQL endpoint (#18) - built with webonyx/graphql-php (lib/graphql-php).
 * POST /api/graphql.php   {"query":"{ posts(limit:3){ id title likes } }"}
 * Public data only: published posts, never author identities (Haven posts are anonymous-first).
 */
require_once __DIR__ . '/_lib.php';

// tiny PSR-4 autoloader for the bundled library (no Composer needed on shared hosting)
spl_autoload_register(function (string $class) {
    if (strncmp($class, 'GraphQL\\', 8) !== 0) return;
    $file = __DIR__ . '/../lib/graphql-php/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
    if (is_file($file)) require $file;
});
require_once __DIR__ . '/../lib/graphql-php/src/Utils/Utils.php';

use GraphQL\GraphQL;
use GraphQL\Type\Definition\{ObjectType, Type};
use GraphQL\Type\Schema;
use GraphQL\Error\DebugFlag;

$pdo = hv_pdo();

$postFields = "p.id, p.title, p.category, p.mood, p.created_at, p.location, p.external_type, p.media_type,
    SUBSTRING(p.content, 1, 280) AS excerpt,
    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,
    (SELECT COUNT(*) FROM reactions r WHERE r.post_id = p.id AND r.reaction_type = 'like') AS likes,
    (SELECT COUNT(*) FROM reactions r WHERE r.post_id = p.id AND r.reaction_type = 'dislike') AS dislikes,
    (SELECT ROUND(AVG(stars), 2) FROM ratings x WHERE x.target_type = 'post' AND x.target_id = p.id) AS avg_rating,
    (SELECT COUNT(*) FROM ratings x WHERE x.target_type = 'post' AND x.target_id = p.id) AS rating_count,
    (SELECT a.emotion FROM ai_analysis a WHERE a.post_id = p.id ORDER BY a.id DESC LIMIT 1) AS emotion";

$postType = new ObjectType([
    'name' => 'Post',
    'description' => 'A published community post (author is never exposed).',
    'fields' => [
        'id'           => Type::nonNull(Type::int()),
        'title'        => Type::string(),
        'excerpt'      => ['type' => Type::string(), 'description' => 'First 280 characters'],
        'category'     => Type::string(),
        'mood'         => Type::string(),
        'location'     => Type::string(),
        'emotion'      => ['type' => Type::string(), 'description' => 'Emotion detected for the post'],
        'mediaType'    => ['type' => Type::string(), 'resolve' => fn($r) => $r['media_type']],
        'embedType'    => ['type' => Type::string(), 'resolve' => fn($r) => $r['external_type']],
        'createdAt'    => ['type' => Type::string(), 'resolve' => fn($r) => $r['created_at']],
        'likes'        => ['type' => Type::int(), 'resolve' => fn($r) => (int)$r['likes']],
        'dislikes'     => ['type' => Type::int(), 'resolve' => fn($r) => (int)$r['dislikes']],
        'commentCount' => ['type' => Type::int(), 'resolve' => fn($r) => (int)$r['comment_count']],
        'averageRating'=> ['type' => Type::float(), 'resolve' => fn($r) => $r['avg_rating'] === null ? null : (float)$r['avg_rating']],
        'ratingCount'  => ['type' => Type::int(), 'resolve' => fn($r) => (int)$r['rating_count']],
    ],
]);
$commentType = new ObjectType([
    'name' => 'Comment',
    'fields' => [
        'id' => Type::nonNull(Type::int()),
        'content' => Type::string(),
        'createdAt' => ['type' => Type::string(), 'resolve' => fn($r) => $r['created_at']],
    ],
]);
$emotionStatType = new ObjectType([
    'name' => 'EmotionStat',
    'fields' => ['emotion' => Type::string(), 'count' => Type::int()],
]);
$siteStatsType = new ObjectType([
    'name' => 'SiteStats',
    'fields' => [
        'posts' => Type::int(), 'comments' => Type::int(),
        'siteRating' => Type::float(), 'siteRatingCount' => Type::int(),
    ],
]);
$ratingResult = new ObjectType([
    'name' => 'RatingResult',
    'fields' => ['average' => Type::float(), 'count' => Type::int(), 'myRating' => Type::int()],
]);

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'posts' => [
            'type' => Type::nonNull(Type::listOf($postType)),
            'description' => 'Latest published posts',
            'args' => ['limit' => ['type' => Type::int(), 'defaultValue' => 10], 'offset' => ['type' => Type::int(), 'defaultValue' => 0], 'category' => Type::string()],
            'resolve' => function ($root, $a) use ($pdo, $postFields) {
                $limit = max(1, min(50, (int)$a['limit'])); $offset = max(0, (int)$a['offset']);
                $sql = "SELECT $postFields FROM posts p WHERE p.status = 'published'";
                $params = [];
                if (!empty($a['category'])) { $sql .= " AND p.category = ?"; $params[] = $a['category']; }
                $sql .= " ORDER BY p.created_at DESC, p.id DESC LIMIT $limit OFFSET $offset";
                $s = $pdo->prepare($sql); $s->execute($params);
                return $s->fetchAll(PDO::FETCH_ASSOC);
            },
        ],
        'post' => [
            'type' => $postType,
            'args' => ['id' => Type::nonNull(Type::int())],
            'resolve' => function ($root, $a) use ($pdo, $postFields) {
                $s = $pdo->prepare("SELECT $postFields FROM posts p WHERE p.id = ? AND p.status = 'published'");
                $s->execute([(int)$a['id']]);
                return $s->fetch(PDO::FETCH_ASSOC) ?: null;
            },
        ],
        'comments' => [
            'type' => Type::nonNull(Type::listOf($commentType)),
            'args' => ['postId' => Type::nonNull(Type::int()), 'limit' => ['type' => Type::int(), 'defaultValue' => 20]],
            'resolve' => function ($root, $a) use ($pdo) {
                $limit = max(1, min(100, (int)$a['limit']));
                $s = $pdo->prepare("SELECT c.id, c.content, c.created_at FROM comments c JOIN posts p ON p.id = c.post_id
                                    WHERE c.post_id = ? AND p.status = 'published' ORDER BY c.id DESC LIMIT $limit");
                $s->execute([(int)$a['postId']]);
                return $s->fetchAll(PDO::FETCH_ASSOC);
            },
        ],
        'emotionSummary' => [
            'type' => Type::nonNull(Type::listOf($emotionStatType)),
            'resolve' => fn() => $pdo->query("SELECT COALESCE(NULLIF(emotion,''),'unknown') emotion, COUNT(*) count FROM ai_analysis GROUP BY 1 ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC),
        ],
        'siteStats' => [
            'type' => $siteStatsType,
            'resolve' => function () use ($pdo) {
                $r = $pdo->query("SELECT (SELECT COUNT(*) FROM posts WHERE status='published') posts, (SELECT COUNT(*) FROM comments) comments,
                                         (SELECT ROUND(AVG(stars),2) FROM ratings WHERE target_type='site') siteRating,
                                         (SELECT COUNT(*) FROM ratings WHERE target_type='site') siteRatingCount")->fetch(PDO::FETCH_ASSOC);
                return ['posts' => (int)$r['posts'], 'comments' => (int)$r['comments'], 'siteRating' => (float)$r['siteRating'], 'siteRatingCount' => (int)$r['siteRatingCount']];
            },
        ],
    ],
]);

$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => [
        'ratePost' => [
            'type' => $ratingResult,
            'description' => 'Give a post 1-5 stars (login required)',
            'args' => ['postId' => Type::nonNull(Type::int()), 'stars' => Type::nonNull(Type::int())],
            'resolve' => function ($root, $a) use ($pdo) {
                $uid = hv_uid();
                if (!$uid) throw new GraphQL\Error\UserError('Please log in first.');
                $stars = (int)$a['stars'];
                if ($stars < 1 || $stars > 5) throw new GraphQL\Error\UserError('stars must be 1 to 5');
                $e = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND status = 'published'");
                $e->execute([(int)$a['postId']]);
                if (!$e->fetch()) throw new GraphQL\Error\UserError('Post not found');
                $pdo->prepare("INSERT INTO ratings (user_id, target_type, target_id, stars) VALUES (?, 'post', ?, ?)
                               ON DUPLICATE KEY UPDATE stars = VALUES(stars)")->execute([$uid, (int)$a['postId'], $stars]);
                $s = $pdo->prepare("SELECT COUNT(*) n, COALESCE(AVG(stars),0) a FROM ratings WHERE target_type='post' AND target_id = ?");
                $s->execute([(int)$a['postId']]);
                $r = $s->fetch(PDO::FETCH_ASSOC);
                return ['average' => round((float)$r['a'], 2), 'count' => (int)$r['n'], 'myRating' => $stars];
            },
        ],
    ],
]);

$schema = new Schema(['query' => $queryType, 'mutation' => $mutationType]);

$in = hv_input();
$query = $in['query'] ?? null;
if (!is_string($query) || trim($query) === '') hv_json(['errors' => [['message' => 'Send a JSON body with a "query" field.']]], 400);

try {
    $result = GraphQL::executeQuery($schema, $query, null, null, is_array($in['variables'] ?? null) ? $in['variables'] : null, $in['operationName'] ?? null);
    $debug = defined('DEBUG_MODE') && DEBUG_MODE ? DebugFlag::INCLUDE_MESSAGE : DebugFlag::NONE;
    hv_json($result->toArray($debug));
} catch (Throwable $e) {
    error_log('[Haven GraphQL] ' . $e->getMessage());
    hv_json(['errors' => [['message' => 'Internal server error']]], 500);
}
