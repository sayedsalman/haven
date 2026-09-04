<?php

$user_reaction = isset($post['user_reaction']) ? $post['user_reaction'] : null;
?>
<div class="glass-card p-3 mb-3 post-card" data-id="<?= $post['id'] ?>">
   
    <div class="d-flex align-items-center mb-2">
        <span class="avatar me-2" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#5B8DEF,#8B5CF6);display:inline-block;"></span>
        <strong><?= escape($post['anonymous_name']) ?></strong>
        <?php if ($post['mood']): ?>
            <span class="ms-2"><?= getMoodEmoji($post['mood']) ?></span>
        <?php endif; ?>
        <span class="text-muted ms-2 small">· <?= $post['category'] ? escape($post['category']) : 'General' ?></span>
        <span class="text-muted ms-auto small"><?= timeAgo($post['created_at']) ?></span>
    </div>

   
    <h5><?= escape($post['title']) ?></h5>
    <p><?= nl2br(escape($post['content'])) ?></p>

    
    <?php
    $ai_stmt = $pdo->prepare("SELECT ai_reply FROM ai_analysis WHERE post_id = ?");
    $ai_stmt->execute([$post['id']]);
    $ai = $ai_stmt->fetch();
    if ($ai && $ai['ai_reply']): ?>
        <div class="ai-reply p-2 rounded mb-2">
            <p><i class="bi bi-robot text-purple"></i> <strong>MindGuide AI</strong></p>
            <p class="mb-0 small"><?= nl2br(escape($ai['ai_reply'])) ?></p>
        </div>
    <?php endif; ?>

    
    <div class="d-flex flex-wrap gap-2 mt-2">
        <button class="btn btn-sm reaction-btn" data-post="<?= $post['id'] ?>" data-type="like">
            <i class="bi bi-heart<?= ($user_reaction == 'like') ? '-fill' : '' ?>"></i>
            <span class="count"><?= $post['reaction_count'] ?></span>
        </button>
        <button class="btn btn-sm support-btn" data-post="<?= $post['id'] ?>">
            <i class="bi bi-hand-thumbs-up"></i> Support
        </button>
        <button class="btn btn-sm bookmark-btn" data-post="<?= $post['id'] ?>">
            <i class="bi bi-bookmark"></i>
        </button>
        <a href="post.php?id=<?= $post['id'] ?>#comments" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-chat"></i> <?= $post['comment_count'] ?>
        </a>
        <button class="btn btn-sm btn-outline-secondary comment-toggle" data-post="<?= $post['id'] ?>">
            <i class="bi bi-chat-plus"></i>
        </button>
    </div>

  
    <div class="comment-form d-none mt-2" id="commentForm-<?= $post['id'] ?>">
        <form class="ajax-comment-form" data-post="<?= $post['id'] ?>">
            <div class="input-group">
                <input type="text" name="content" class="form-control form-control-sm" placeholder="Write a comment..." required>
                <button class="btn btn-primary btn-sm" type="submit">Post</button>
            </div>
        </form>
    </div>
</div>

<script>

document.querySelectorAll('.comment-toggle').forEach(btn => {
    btn.addEventListener('click', function() {
        const postId = this.dataset.post;
        const form = document.getElementById('commentForm-' + postId);
        if (form) form.classList.toggle('d-none');
    });
});


document.querySelectorAll('.ajax-comment-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const postId = this.dataset.post;
        const content = this.querySelector('input[name="content"]').value;
        if (!content) return;
        fetch('api/comment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=add&post_id=' + postId + '&content=' + encodeURIComponent(content)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Toastify({
                    text: "Comment added!",
                    duration: 2000,
                    gravity: "bottom",
                    position: "right",
                    style: { background: "linear-gradient(135deg, #5B8DEF, #8B5CF6)" }
                }).showToast();
                this.reset();
                this.closest('.comment-form').classList.add('d-none');
               
                setTimeout(() => location.reload(), 500);
            }
        });
    });
});
</script>
