// Poll for new notifications every 30s
function fetchNotifications() {
    fetch('api/notification.php?action=fetch')
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                const badge = document.querySelector('.badge.bg-danger');
                if (badge) badge.textContent = data.length;
                else {
                    const bell = document.querySelector('.bi-bell').closest('a');
                    bell.innerHTML += ' <span class="badge bg-danger">' + data.length + '</span>';
                }
            }
        });
}
setInterval(fetchNotifications, 30000);