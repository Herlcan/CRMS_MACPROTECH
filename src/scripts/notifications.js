(function () {
    const bell = document.getElementById('notificationBellToggle');
    const menu = document.getElementById('notificationDropdownMenu');
    const list = document.getElementById('notificationPreviewList');
    const badge = document.getElementById('notificationUnreadBadge');
    const markAllButton = document.getElementById('notificationMarkAll');

    if (!bell || !menu || !list || !badge) {
        return;
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function renderBadge(count) {
        const unread = Number(count) || 0;
        badge.textContent = unread > 99 ? '99+' : String(unread);
        badge.hidden = unread === 0;
    }

    function renderNotifications(notifications) {
        if (!notifications.length) {
            list.innerHTML = '<div class="notification-empty">No notifications yet.</div>';
            return;
        }

        list.innerHTML = notifications.map(function (item) {
            const link = item.link || 'notifications.php';
            const unreadClass = Number(item.is_read) === 0 ? ' unread' : '';

            return [
                '<a class="notification-preview-item notification-type-' + escapeHtml(item.type) + unreadClass + '" href="' + escapeHtml(link) + '" data-notification-id="' + item.id + '">',
                '  <span class="notification-dot"></span>',
                '  <span class="notification-preview-body">',
                '    <strong>' + escapeHtml(item.title) + '</strong>',
                '    <span>' + escapeHtml(item.message) + '</span>',
                '    <small>' + escapeHtml(item.time_ago) + '</small>',
                '  </span>',
                '</a>'
            ].join('');
        }).join('');
    }

    function loadNotifications() {
        fetch('src/handlers/get_notifications.php?limit=5', {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Unable to load notifications');
            }
            return response.json();
        })
        .then(function (data) {
            if (!data.success) {
                throw new Error(data.message || 'Unable to load notifications');
            }

            renderBadge(data.unread_count);
            renderNotifications(data.notifications || []);
        })
        .catch(function () {
            list.innerHTML = '<div class="notification-empty">Notifications unavailable.</div>';
        });
    }

    function markNotification(id, action) {
        const formData = new FormData();
        formData.append('id', id || '0');
        formData.append('action', action || 'read');

        return fetch('src/handlers/mark_notification_read.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json();
        });
    }

    bell.addEventListener('click', function (event) {
        event.preventDefault();
        menu.classList.toggle('show');
        loadNotifications();
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.notification-dropdown')) {
            menu.classList.remove('show');
        }
    });

    list.addEventListener('click', function (event) {
        const item = event.target.closest('[data-notification-id]');
        if (!item) {
            return;
        }

        event.preventDefault();
        markNotification(item.dataset.notificationId, 'read').finally(function () {
            window.location.href = item.href;
        });
    });

    if (markAllButton) {
        markAllButton.addEventListener('click', function () {
            markNotification(0, 'read_all').then(loadNotifications);
        });
    }

    loadNotifications();
    window.setInterval(loadNotifications, 30000);
})();
