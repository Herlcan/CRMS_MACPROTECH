<?php require_once __DIR__ . '/src/handlers/asset_helpers.php'; ?>

<div class="left-side-bar">
	<div class="brand-logo">
		<a href="index.php" class="brand-name">
			<img
				src="<?= asset_attr('src/images/MACPROTECH_LOGO_BANNER_SIDEBAR.png'); ?>"
				class="sidebar-logo is-banner"
				width="210"
				height="105"
				alt="MACPROTECH"
				decoding="async"
			>
			<img
				src="<?= asset_attr('src/images/MACPROTECH_LOGO_CIRCLE_SIDEBAR.png'); ?>"
				class="sidebar-logo is-circle"
				width="56"
				height="56"
				alt=""
				aria-hidden="true"
				decoding="async"
			>
		</a>
		<div class="close-sidebar" data-toggle="left-sidebar-close">
			<i class="ion-close-round"></i>
		</div>
	</div>
	<div class="menu-block customscroll">
		<div class="sidebar-menu">
			<ul id="accordion-menu">
				<li>
					<a href="index.php" class="dropdown-toggle no-arrow">
						<span class="micon dw dw-house">
							<img src="<?= asset_attr('src/images/dashboard.png'); ?>" width="20" height="20" alt="" aria-hidden="true" decoding="async">
						</span>
						<span class="mtext">Dashboard</span>
					</a>
				</li>
				<?php if ($_SESSION['role'] == 'Administrator' || $_SESSION['role'] == 'Cashier/Front Desk' || $_SESSION['role'] == 'Cashier/Front Desk Staff'): ?>
				<li>
					<a href="clients.php" class="dropdown-toggle no-arrow">
						<span class="micon dw dw-user">
							<img src="<?= asset_attr('src/images/users.png'); ?>" width="20" height="20" alt="" aria-hidden="true" decoding="async">
						</span>
						<span class="mtext">Customers</span>
					</a>
				</li>
				<?php endif; ?>
				<li>
					<a href="work-order.php" class="dropdown-toggle no-arrow">
						<span class="micon dw dw-shopping-basket">
								<img src="<?= asset_attr('src/images/repair.png'); ?>" width="20" height="20" alt="" aria-hidden="true" decoding="async">
						</span>
						<span class="mtext">Work Orders</span>
					</a>
				</li>
				<?php if ($_SESSION['role'] == 'Administrator' || $_SESSION['role'] == 'Cashier/Front Desk' || $_SESSION['role'] == 'Cashier/Front Desk Staff' || $_SESSION['role'] == 'Technician'): ?>
				<li>
					<a href="items.php" class="dropdown-toggle no-arrow">
						<span class="micon fa fa-cart-plus">
							<img src="<?= asset_attr('src/images/dolly-flatbed-alt.png'); ?>" width="20" height="20" alt="" aria-hidden="true" decoding="async">
						</span>
						<span class="mtext">Inventory</span>
					</a>
				</li>
				<?php endif; ?>
				<?php if ($_SESSION['role'] == 'Administrator' || $_SESSION['role'] == 'Cashier/Front Desk' || $_SESSION['role'] == 'Cashier/Front Desk Staff'): ?>
				<li>
					<a href="payment.php" class="dropdown-toggle no-arrow">
						<span class="micon dw dw-money">
							<img src="<?= asset_attr('src/images/money-bills-simple.png'); ?>" width="20" height="20" alt="" aria-hidden="true" decoding="async">
						</span>
						<span class="mtext">Payments</span>
					</a>
				</li>
				<?php endif; ?>
				<li>
					<a href="notifications.php" class="dropdown-toggle no-arrow">
						<span class="micon dw dw-bell">
							<img src="<?= asset_attr('src/images/bell-white.png'); ?>" width="20" height="20" alt="" aria-hidden="true" decoding="async">
						</span>
						<span class="mtext">Notifications</span>
					</a>
				</li>
				<?php if ($_SESSION['role'] == 'Administrator'): ?>
				<li>
					<a href="reports.php" class="dropdown-toggle no-arrow">
						<span class="micon dw dw-money">
							<img src="<?= asset_attr('src/images/reports.png'); ?>" width="20" height="20" alt="" aria-hidden="true" decoding="async">
						</span>
						<span class="mtext">Reports</span>
					</a>
				</li>
				<?php endif; ?>
				<?php if ($_SESSION['role'] == 'Administrator'): ?>
				<li>
					<a href="user.php" class="dropdown-toggle no-arrow">
						<span class="micon dw dw-user1">
							<img src="<?= asset_attr('src/images/circle-user.png'); ?>" width="20" height="20" alt="" aria-hidden="true" decoding="async">
						</span><span class="mtext">Users</span>
					</a>
				</li>
				<?php endif; ?>
			</ul>
		</div>
		<div class="sidebar-footer">
			<footer>Copyright © 2026 All Rights Reserved.</footer>
		</div>
	</div>
</div>

<script>
	(function () {
		const collapseToggle = document.getElementById('sidebarCollapseToggle');
		const storageKey = 'macprotechSidebarCollapsed';
		const transitionClass = 'sidebar-transitioning';
		const transitionDuration = 340;
		let transitionTimer = null;

		function getStoredCollapsed() {
			try {
				return localStorage.getItem(storageKey) === 'true';
			} catch (error) {
				return document.documentElement.classList.contains('sidebar-collapsed');
			}
		}

		function storeSidebarCollapsed(isCollapsed) {
			try {
				localStorage.setItem(storageKey, String(isCollapsed));
			} catch (error) {}
		}

		function markSidebarTransitioning() {
			if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
				return;
			}

			document.documentElement.classList.add(transitionClass);
			window.clearTimeout(transitionTimer);
			transitionTimer = window.setTimeout(function () {
				document.documentElement.classList.remove(transitionClass);
			}, transitionDuration);
		}

		function setSidebarCollapsed(isCollapsed, shouldAnimate) {
			if (shouldAnimate) {
				markSidebarTransitioning();
			}

			document.documentElement.classList.toggle('sidebar-collapsed', isCollapsed);
			document.body.classList.toggle('sidebar-collapsed', isCollapsed);
			if (collapseToggle) {
				collapseToggle.setAttribute('aria-expanded', String(!isCollapsed));
			}
		}

		setSidebarCollapsed(getStoredCollapsed(), false);

		if (collapseToggle) {
			collapseToggle.addEventListener('click', function () {
				const isCollapsed = !document.body.classList.contains('sidebar-collapsed');
				window.requestAnimationFrame(function () {
					setSidebarCollapsed(isCollapsed, true);
					storeSidebarCollapsed(isCollapsed);
				});
			});
		}
	})();
</script>
