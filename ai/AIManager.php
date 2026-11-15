<?php
// ============================================================
// AIManager.php – FORCED OpenRouter ONLY
// ============================================================
require_once __DIR__ . '/MindGuide.php';
require_once __DIR__ . '/MindShield.php';
require_once __DIR__ . '/MindInsight.php';
require_once __DIR__ . '/MindCoach.php';
require_once __DIR__ . '/providers/OpenRouterProvider.php';
require_once __DIR__ . '/config/ai.php';

class AIManager {
    private MindGuide $mindGuide;
    private MindShield $mindShield;
    private MindInsight $mindInsight;
    private MindCoach $mindCoach;
    private $provider;
    private $db;
    private $logFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/../logs/ai_manager.log';
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);

        $this->log("🚀 AIManager initialized.");

        // ============================================================
        // HARDCODE OPENROUTER – NO GEMINI
        // ============================================================
        $apiKey = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '';
        $model = defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'anthropic/claude-haiku-4.5';
        $fallbacks = defined('OPENROUTER_FALLBACK_MODELS') ? (array)OPENROUTER_FALLBACK_MODELS : [];

        $this->log("📡 Using OpenRouter with model: $model (fallbacks: " . implode(', ', $fallbacks) . ")");
        $this->log("🔑 API Key present: " . (empty($apiKey) ? 'NO' : 'YES'));

        $provider = new OpenRouterProvider($apiKey, $model, $fallbacks);
        
        // Log the actual class being used
        $this->log("📡 Provider class: " . get_class($provider));

        $this->mindGuide = new MindGuide($provider);
        $this->mindShield = new MindShield($provider);
        $this->mindInsight = new MindInsight($provider);
        $this->provider = $provider;
        $this->db = db();
        $this->mindCoach = new MindCoach($this->db->getConnection());
        $this->log("✅ AIManager ready.");
    }

    // Describes an uploaded image using a vision-capable model so image
    // posts get real content-aware moderation and replies instead of the
    // AI only ever reading the caption text.
    private function describeImage(string $imageUrl): string {
        if (!method_exists($this->provider, 'generateVisionText')) return '';
        try {
            $prompt = "Describe what is shown in this image in 1-2 plain sentences, focusing on anything relevant to someone's emotional wellbeing (e.g. visible distress, self-harm signs, unsafe situations, or simply the mood/context of the scene). Be factual, not speculative.";
            $desc = $this->provider->generateVisionText($prompt, $imageUrl, ['max_tokens' => 120, 'temperature' => 0.3]);
            $this->log("🖼️ Image description: " . substr($desc, 0, 200));
            return trim($desc);
        } catch (Throwable $e) {
            $this->log("❌ Image description failed: " . $e->getMessage());
            return '';
        }
    }

    private function log($msg) {
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
    }

    public function analyzePost(int $postId): void {
        $this->log("🔍 Starting analysis for post ID: $postId");

        $post = $this->db->select('posts', ['id' => $postId]);
        if (empty($post)) {
            $this->log("❌ Post $postId not found.");
            return;
        }
        $post = $post[0];
        $userId = $post['user_id'] ?? 0;
        $this->log("📝 Post found – user_id: $userId, title: " . ($post['title'] ?? 'no title'));

        // If the post has an attached image, describe it with a vision
        // model and fold that description into the text MindShield/MindGuide
        // analyze — otherwise image-only posts are invisible to the AI.
        $contentForAI = $post['content'] ?? '';
        if (!empty($post['media_url']) && ($post['media_type'] ?? '') === 'image') {
            $imageDescription = $this->describeImage($post['media_url']);
            if ($imageDescription !== '') {
                $contentForAI = trim($contentForAI . "\n\n[Attached image shows: " . $imageDescription . "]");
            }
        }

        // Shield
        try {
            $shieldResult = $this->mindShield->scan($contentForAI);
        } catch (Throwable $e) {
            $this->log("❌ Shield error: " . $e->getMessage());
            $shieldResult = ['risk_level' => 'low'];
        }
        $riskLevel = $shieldResult['risk_level'] ?? 'low';
        $riskScore = match($riskLevel) {
            'low' => 20, 'medium' => 50, 'high' => 80, 'critical' => 100,
            default => 20
        };
        $this->log("🛡️ Shield – risk: $riskLevel, score: $riskScore");

        // Guide
        $guideResult = [
            'reply' => null,
            'emotion' => 'neutral',
            'confidence' => 0,
            'tips' => [],
            'resources' => [],
            'volunteer_required' => false
        ];
        if ($riskLevel !== 'critical') {
            try {
                $this->log("🧠 Calling MindGuide->generateReply()...");
                $guideResult = $this->mindGuide->generateReply($contentForAI, $userId);
                $this->log("🧠 Guide – emotion: " . ($guideResult['emotion'] ?? 'null'));
                $this->log("🧠 Guide – reply: " . substr($guideResult['reply'] ?? '', 0, 100) . "...");
            } catch (Exception $e) {
                $this->log("❌ Guide error: " . $e->getMessage());
                $guideResult['reply'] = "Thank you for sharing. You're not alone. A volunteer will reach out soon. 💚";
                $guideResult['emotion'] = 'neutral';
            }
        } else {
            $guideResult['reply'] = "I'm here for you. Please know that you are valuable. A volunteer has been notified immediately. 💚";
            $guideResult['emotion'] = 'crisis';
        }

        // Force reply if empty
        if (empty($guideResult['reply'])) {
            $guideResult['reply'] = "I'm here to listen. You are not alone. 💚";
            $guideResult['emotion'] = 'neutral';
            $this->log("⚠️ Empty reply – using safety fallback.");
        }
        $this->log("📝 Final reply: " . substr($guideResult['reply'], 0, 100) . "...");

        // On-device TFLite (GoEmotions) result saved with the post, if the browser produced one
        $ml = null;
        try {
            require_once __DIR__ . '/../includes/EmotionML.php';
            $ml = EmotionML::ensure($this->db->getConnection(), $postId, (int)$userId, (string)($post['content'] ?? ''));
            if ($ml) $this->log("🤖 TFLite emotion: {$ml['top_label']} ({$ml['top_score']}) -> {$ml['app_emotion']}");
        } catch (Throwable $e) {
            $this->log("⚠️ TFLite emotion lookup failed: " . $e->getMessage());
        }
        $finalEmotion = ($ml && $riskLevel !== 'critical') ? $ml['app_emotion'] : ($guideResult['emotion'] ?? 'neutral');
        $finalSentiment = $ml ? (float)$ml['sentiment'] : 0.5;

        // Save analysis
        $analysisId = $this->db->insert('ai_analysis', [
            'post_id' => $postId,
            'user_id' => $userId,
            'emotion' => $finalEmotion,
            'sentiment' => $finalSentiment,
            'risk_score' => $riskScore,
            'category' => $shieldResult['category'] ?? 'other',
            'ai_reply' => $guideResult['reply'],
            'is_volunteer_notified' => ($riskLevel === 'high' || $riskLevel === 'critical') ? 1 : 0,
            'volunteer_id' => null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $this->log("💾 Analysis saved with ID: $analysisId");

        // Update post
        $this->db->update('posts', [
            'ai_analysis_id' => $analysisId,
            'risk_score' => $riskScore,
            'status' => 'published'
        ], ['id' => $postId]);
        $this->log("📊 Post $postId updated to published.");

        // Post AI comment
        $replyText = $guideResult['reply'];
        if (!empty($replyText)) {
            $aiBotId = $this->getOrCreateAIBot();
            if ($aiBotId) {
                $commentId = $this->db->insert('comments', [
                    'post_id' => $postId,
                    'user_id' => $aiBotId,
                    'content' => $replyText,
                    'is_anonymous' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                if ($commentId) {
                    $this->db->query("UPDATE posts SET comment_count = comment_count + 1 WHERE id = $postId");
                    $this->log("💬 AI comment posted (ID: $commentId)");
                } else {
                    $this->log("❌ Failed to insert AI comment.");
                }
            } else {
                $this->log("❌ AI bot user not found – comment not posted.");
            }
        }

        // High risk handling
        if ($riskLevel === 'high' || $riskLevel === 'critical') {
            $this->db->update('posts', ['status' => 'flagged'], ['id' => $postId]);
            $this->notifyModerators($postId, $riskLevel, $shieldResult['category'] ?? '');
            if ($riskLevel === 'critical') $this->notifyAdmins($postId);
            $this->db->insert('consultation_requests', [
                'user_id' => $userId,
                'post_id' => $postId,
                'message' => 'AI flagged high risk: ' . substr($post['content'], 0, 100),
                'priority' => ($riskLevel === 'critical' ? 'high' : 'medium'),
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $this->log("🚨 Emergency consultation created.");
        }

        // Recommendations
        if (!empty($guideResult['resources'])) {
            foreach ($guideResult['resources'] as $resId) {
                $this->db->insert('ai_recommendations', [
                    'reply_id' => $analysisId,
                    'resource_type' => 'article',
                    'resource_id' => $resId,
                    'title' => 'Recommended Article',
                    'url' => 'article.php?id=' . $resId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            $this->log("📚 " . count($guideResult['resources']) . " recommendations saved.");
        }

        // Log
        $this->db->insert('ai_logs', [
            'provider' => 'openrouter',
            'model' => defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'anthropic/claude-haiku-4.5',
            'tokens' => 0,
            'latency' => 0,
            'cost' => 0,
            'status' => 'success',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Notify user
        $this->db->insert('notifications', [
            'user_id' => $userId,
            'type' => 'ai_reply',
            'title' => 'MindGuide replied to your post',
            'message' => 'MindGuide AI provided support on your post: "' . substr($post['title'] ?? '', 0, 50) . '"',
            'link' => 'post.php?id=' . $postId,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $this->log("🔔 Notification sent.");

        $this->log("✅ Analysis complete for post $postId.");
    }

    public function getTodaySuggestion(int $userId): ?array {
        try {
            return $this->mindCoach->suggestTodayGoal($userId);
        } catch (Throwable $e) {
            $this->log("❌ MindCoach error: " . $e->getMessage());
            return null;
        }
    }

    public function getInsights(int $userId): array {
        $this->log("📊 Generating insights for user $userId");
        try {
            $result = $this->mindInsight->generateReport($userId);
            $this->log("✅ Insights generated.");
            return $result;
        } catch (Exception $e) {
            $this->log("❌ Insights error: " . $e->getMessage());
            return ['summary' => 'Unable to generate insights.', 'recommendations' => ['Keep logging your mood'], 'trend' => 'stable'];
        }
    }

    public function chat(string $message, int $userId, array $extraContext = []): array {
        $this->log("💬 Chat from user $userId" . (!empty($extraContext) ? " (with extra context)" : ""));
        try {
            $result = $this->mindGuide->generateReply($message, $userId, $extraContext);
            $this->log("✅ Chat reply generated.");
            return $result;
        } catch (Throwable $e) {
            // Real failure (bad key, no credits, retired model, DB error in context...).
            // Log the actual reason and flag the result as degraded so the caller
            // can tell this is NOT a genuine AI answer.
            $this->log("❌ Chat error (" . get_class($e) . "): " . $e->getMessage());
            return [
                'reply' => "I'm having a little trouble connecting right now, but I'm still here with you. Could you try sending that again in a moment? If you need someone right away, our volunteers are available. 💚",
                'emotion' => 'neutral',
                'confidence' => 0,
                'tips' => [],
                'resources' => [],
                'volunteer_required' => false,
                'degraded' => true,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getOrCreateAIBot() {
        $row = $this->db->query("SELECT id FROM users WHERE role = 'ai' LIMIT 1")->fetch();
        if ($row) {
            $this->log("🤖 AI bot found with ID: " . $row['id']);
            return $row['id'];
        }
        $this->log("🤖 Creating new AI bot user...");
        $this->db->insert('users', [
            'email' => 'ai@mindsphere.com',
            'password_hash' => '',
            'role' => 'ai',
            'is_active' => 1,
            'is_verified' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $aiId = $this->db->lastInsertId();
        $this->db->insert('profiles', [
            'user_id' => $aiId,
            'anonymous_name' => 'MindGuide AI',
            'avatar_color' => '#8B5CF6'
        ]);
        $this->log("🤖 AI bot created with ID: $aiId");
        return $aiId;
    }

    private function notifyModerators($postId, $riskLevel, $category) {
        $mods = $this->db->select('users', ['role' => 'moderator']);
        foreach ($mods as $m) {
            $this->db->insert('notifications', [
                'user_id' => $m['id'],
                'type' => 'ai_alert',
                'title' => "AI Alert: $riskLevel risk post",
                'message' => "Post #$postId flagged for $category. Risk: $riskLevel.",
                'link' => "post.php?id=$postId",
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    private function notifyAdmins($postId) {
        $admins = $this->db->select('users', ['role' => 'admin']);
        foreach ($admins as $a) {
            $this->db->insert('notifications', [
                'user_id' => $a['id'],
                'type' => 'critical_alert',
                'title' => '🚨 CRITICAL: Self-harm risk detected',
                'message' => "Post #$postId requires immediate attention.",
                'link' => "post.php?id=$postId",
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
?>