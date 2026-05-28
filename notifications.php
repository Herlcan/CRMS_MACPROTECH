<?php
	include 'header.php';
	include 'sidebar.php';
	include 'src/db/connection.php';
	require_once 'src/handlers/notification_helpers.php';

	ensure_notifications_table($conn);

	$userId = (int) $_SESSION['user_id'];
	$filter = $_GET['filter'] ?? 'all';
	$allowedFilters = ['all', 'unread', 'read', 'archived'];
	if (!in_array($filter, $allowedFilters, true)) {
		$filter = 'all';
	}

	$where = "user_id = ?";
	$types = "i";
	$params = [$userId];

	if ($filter === 'archived') {
		$where .= " AND is_archived = 1";
	} else {
		$where .= " AND is_archived = 0";
	}

	if ($filter === 'unread') {
		$where .= " AND is_read = 0";
	} elseif ($filter === 'read') {
		$where .= " AND is_read = 1";
	}

	$query = mysqli_prepare($conn, "
		SELECT id, title, message, type, link, is_read, is_archived, created_at
		FROM notifications
		WHERE {$where}
		ORDER BY created_at DESC, id DESC
		LIMIT 100
	");
	notification_bind_params($query, $types, $params);
	mysqli_stmt_execute($query);
	$result = mysqli_stmt_get_result($query);

	$notifications = [];
	while ($row = mysqli_fetch_assoc($result)) {
		$notifications[] = $row;
	}
	mysqli_stmt_close($query);

	function notification_filter_url(string $filter): string {
		return 'notifications.php?filter=' . urlencode($filter);
	}
?>

<div class="mobile-menu-overlay"></div>

<div class="main-container">
	<div class="pd-ltr-20 xs-pd-20-10">
		<div class="page-header">
			<div class="row">
				<div class="col-md-6 col-sm-12">
					<div class="title">
						<h4>Notifications</h4>
					</div>
				</div>
			</div>
		</div>

		<div class="notification-page-toolbar">
			<a class="<?= $filter === 'all' ? 'active' : '' ?>" href="<?= notification_filter_url('all') ?>">All</a>
			<a class="<?= $filter === 'unread' ? 'active' : '' ?>" href="<?= notification_filter_url('unread') ?>">Unread</a>
			<a class="<?= $filter === 'read' ? 'active' : '' ?>" href="<?= notification_filter_url('read') ?>">Read</a>
			<a class="<?= $filter === 'archived' ? 'active' : '' ?>" href="<?= notification_filter_url('archived') ?>">Archived</a>
			<button type="button" id="notificationsPageMarkAll">Mark All Read</button>
		</div>

		<div class="notification-history">
			<?php if (empty($notifications)): ?>
				<div class="notification-history-empty">No notifications found.</div>
			<?php endif; ?>

			<?php foreach ($notifications as $notification): ?>
				<div class="notification-history-item notification-type-<?= htmlspecialchars($notification['type']) ?> <?= (int) $notification['is_read'] === 0 ? 'unread' : '' ?>">
					<a href="<?= htmlspecialchars($notification['link'] ?: 'notifications.php') ?>" data-notification-id="<?= (int) $notification['id'] ?>">
						<span class="notification-dot"></span>
						<span class="notification-history-content">
							<strong><?= htmlspecialchars($notification['title']) ?></strong>
							<span><?= htmlspecialchars($notification['message']) ?></span>
							<small><?= htmlspecialchars(notification_time_ago((string) $notification['created_at'])) ?></small>
						</span>
					</a>
					<div class="notification-history-actions">
						<?php if ((int) $notification['is_archived'] === 1): ?>
							<button type="button" data-notification-action="restore" data-notification-id="<?= (int) $notification['id'] ?>">Restore</button>
						<?php else: ?>
							<button type="button" data-notification-action="archive" data-notification-id="<?= (int) $notification['id'] ?>">Archive</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<script>
	document.addEventListener('click', function (event) {
		const link = event.target.closest('.notification-history-item a[data-notification-id]');
		if (link) {
			event.preventDefault();
			const formData = new FormData();
			formData.append('id', link.dataset.notificationId);
			formData.append('action', 'read');
			fetch('src/handlers/mark_notification_read.php', { method: 'POST', body: formData })
				.finally(function () {
					window.location.href = link.href;
				});
			return;
		}

		const actionButton = event.target.closest('[data-notification-action]');
		if (!actionButton) {
			return;
		}

		const formData = new FormData();
		formData.append('id', actionButton.dataset.notificationId);
		formData.append('action', actionButton.dataset.notificationAction);
		fetch('src/handlers/mark_notification_read.php', { method: 'POST', body: formData })
			.then(function () {
				window.location.reload();
			});
	});

	const markAllButton = document.getElementById('notificationsPageMarkAll');
	if (markAllButton) {
		markAllButton.addEventListener('click', function () {
			const formData = new FormData();
			formData.append('action', 'read_all');
			fetch('src/handlers/mark_notification_read.php', { method: 'POST', body: formData })
				.then(function () {
					window.location.reload();
				});
		});
	}
</script>
