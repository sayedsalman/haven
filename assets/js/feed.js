// Load more posts
document.getElementById('load-more')?.addEventListener('click', function() {
    let page = this.dataset.page;
    fetch('feed.php?page=' + page)
        .then(res => res.text())
        .then(html => {
            document.getElementById('post-feed').insertAdjacentHTML('beforeend', html);
            this.dataset.page = parseInt(page) + 1;
        });
});

// Reaction buttons
document.querySelectorAll('.reaction-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        let postId = this.dataset.post;
        let type = this.dataset.type || 'like';
        fetch('api/reaction.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'post_id='+postId+'&type='+type
        })
        .then(res => res.json())
        .then(data => {
            // update count
            let countSpan = this.querySelector('.count');
            if (data.action === 'added') countSpan.textContent = parseInt(countSpan.textContent) + 1;
            else countSpan.textContent = parseInt(countSpan.textContent) - 1;
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    // Load more
    const loadMore = document.getElementById('load-more');
    if (loadMore) {
        loadMore.addEventListener('click', function() {
            const page = this.dataset.page;
            fetch('feed.php?page=' + page)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('post-feed').insertAdjacentHTML('beforeend', html);
                    this.dataset.page = parseInt(page) + 1;
                });
        });
    }

    // Reactions
    document.querySelectorAll('.reaction-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const postId = this.dataset.post;
            const type = this.dataset.type || 'like';
            const countSpan = this.querySelector('.count');
            fetch('api/reaction.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'post_id=' + postId + '&type=' + type
            })
            .then(res => res.json())
            .then(data => {
                if (data.action === 'added') {
                    countSpan.textContent = parseInt(countSpan.textContent) + 1;
                } else {
                    countSpan.textContent = parseInt(countSpan.textContent) - 1;
                }
            });
        });
    });

    // Bookmarks
    document.querySelectorAll('.bookmark-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const postId = this.dataset.post;
            fetch('api/bookmark.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'post_id=' + postId
            })
            .then(res => res.json())
            .then(data => {
                if (data.action === 'added') this.innerHTML = '<i class="bi bi-bookmark-fill"></i>';
                else this.innerHTML = '<i class="bi bi-bookmark"></i>';
            });
        });
    });
});