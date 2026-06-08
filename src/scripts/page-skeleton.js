(function () {
    const navigatingKey = 'macproPageNavigating';
    const layoutKey = 'macproPageSkeletonLayout';
    const startedAtKey = 'macproPageNavigationStartedAt';
    const delayBeforeVisibleMs = 360;
    const minimumVisibleMs = 260;
    const validLayouts = ['dashboard', 'table', 'reports', 'feed', 'form', 'detail'];
    const layoutByPage = {
        'index.php': 'dashboard',
        'customer.php': 'dashboard',
        'reports.php': 'reports',
        'bar.php': 'reports',
        'pie.php': 'reports',
        'notifications.php': 'feed',
        'settings.php': 'form',
        'customer-work-order.php': 'form',
        'client-view.php': 'detail',
        'stock_transaction.php': 'detail'
    };

    let shownAt = 0;
    let hideTimer = null;
    let revealTimer = null;

    function storageGet(key) {
        try {
            return sessionStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function storageSet(key, value) {
        try {
            sessionStorage.setItem(key, value);
        } catch (error) {}
    }

    function storageRemove(key) {
        try {
            sessionStorage.removeItem(key);
        } catch (error) {}
    }

    function normalizeLayout(layout) {
        return validLayouts.indexOf(layout) === -1 ? 'table' : layout;
    }

    function pageNameFromUrl(href) {
        const url = new URL(href || window.location.href, window.location.href);
        const page = url.pathname.split('/').filter(Boolean).pop();
        return page || 'index.php';
    }

    function resolveLayout(href) {
        const page = pageNameFromUrl(href);

        if (layoutByPage[page]) {
            return layoutByPage[page];
        }

        if (page.indexOf('report') !== -1) {
            return 'reports';
        }

        if (page.indexOf('notification') !== -1) {
            return 'feed';
        }

        return 'table';
    }

    function inferCurrentLayout() {
        const mainContainer = document.querySelector('.main-container');

        if (!mainContainer) {
            return resolveLayout(window.location.href);
        }

        if (mainContainer.matches('.reports-page') || mainContainer.querySelector('.reports-page')) {
            return 'reports';
        }

        if (mainContainer.querySelector('.dashboard-grid, .dashboard-stats-grid')) {
            return 'dashboard';
        }

        if (mainContainer.querySelector('.notification-history')) {
            return 'feed';
        }

        if (!mainContainer.querySelector('table') && mainContainer.querySelector('.profile-form, .form-step, form')) {
            return 'form';
        }

        return resolveLayout(window.location.href);
    }

    function navigationStartedAt() {
        const startedAt = Number(storageGet(startedAtKey));
        return Number.isFinite(startedAt) && startedAt > 0 ? startedAt : Date.now();
    }

    function setNavigatingFlag(value, layout, startedAt) {
        if (value) {
            storageSet(navigatingKey, 'true');
            storageSet(layoutKey, normalizeLayout(layout));
            storageSet(startedAtKey, String(startedAt || Date.now()));
            return;
        }

        storageRemove(navigatingKey);
        storageRemove(layoutKey);
        storageRemove(startedAtKey);
    }

    function skeletonItem(kind, className) {
        const type = kind || 'line';
        const classAttr = className ? ' class="' + className + '"' : '';
        return '<span' + classAttr + ' data-skeleton="' + type + '"></span>';
    }

    function repeat(count, renderer) {
        const parts = [];

        for (let index = 0; index < count; index += 1) {
            parts.push(renderer(index));
        }

        return parts.join('');
    }

    function renderHead() {
        return [
            '<div class="macpro-page-skeleton-head">',
            skeletonItem('title', 'macpro-page-skeleton-title'),
            skeletonItem('button', 'macpro-page-skeleton-action'),
            '</div>'
        ].join('');
    }

    function renderMetrics(count) {
        return '<div class="macpro-page-skeleton-metrics">' + repeat(count || 4, function () {
            return skeletonItem('card');
        }) + '</div>';
    }

    function renderToolbar() {
        return [
            '<div class="macpro-page-skeleton-toolbar">',
            skeletonItem('button'),
            skeletonItem('button'),
            skeletonItem('input'),
            '</div>'
        ].join('');
    }

    function renderTable(columns, rows) {
        const total = (columns || 5) * (rows || 6);

        return '<div class="macpro-page-skeleton-table is-cols-' + (columns || 5) + '">' + repeat(total, function () {
            return skeletonItem('line');
        }) + '</div>';
    }

    function renderPanel(content, className) {
        return '<div class="macpro-page-skeleton-panel ' + (className || '') + '">' + content + '</div>';
    }

    function renderTableLayout() {
        return [
            renderHead(),
            renderToolbar(),
            renderPanel([
                skeletonItem('line', 'is-wide'),
                skeletonItem('line'),
                renderTable(5, 6)
            ].join(''), 'is-table')
        ].join('');
    }

    function renderDashboardLayout() {
        return [
            renderHead(),
            renderMetrics(4),
            '<div class="macpro-page-skeleton-dashboard-grid">',
            renderPanel([
                skeletonItem('line', 'is-wide'),
                '<div data-skeleton="chart" class="macpro-page-skeleton-chart"></div>'
            ].join(''), 'is-chart'),
            renderPanel([
                skeletonItem('line', 'is-wide'),
                skeletonItem('line'),
                skeletonItem('line', 'is-short'),
                renderTable(3, 4)
            ].join(''), 'is-summary'),
            '</div>',
            renderPanel(renderTable(5, 4), 'is-table')
        ].join('');
    }

    function renderReportsLayout() {
        return [
            renderHead(),
            renderMetrics(4),
            '<div class="macpro-page-skeleton-report-grid">',
            repeat(4, function () {
                return renderPanel([
                    skeletonItem('line', 'is-wide'),
                    skeletonItem('line'),
                    renderTable(3, 3)
                ].join(''), 'is-report');
            }),
            '</div>'
        ].join('');
    }

    function renderFeedLayout() {
        return [
            renderHead(),
            renderToolbar(),
            renderPanel(repeat(5, function () {
                return [
                    '<div class="macpro-page-skeleton-feed-item">',
                    skeletonItem('avatar'),
                    '<div class="macpro-page-skeleton-feed-copy">',
                    skeletonItem('line', 'is-wide'),
                    skeletonItem('line'),
                    skeletonItem('line', 'is-short'),
                    '</div>',
                    skeletonItem('button'),
                    '</div>'
                ].join('');
            }), 'is-feed')
        ].join('');
    }

    function renderFormLayout() {
        return [
            renderHead(),
            '<div class="macpro-page-skeleton-form-grid">',
            renderPanel(repeat(8, function () {
                return [
                    '<div class="macpro-page-skeleton-field">',
                    skeletonItem('line', 'is-label'),
                    skeletonItem('input'),
                    '</div>'
                ].join('');
            }), 'is-form'),
            renderPanel([
                skeletonItem('card'),
                skeletonItem('line', 'is-wide'),
                skeletonItem('line'),
                skeletonItem('button')
            ].join(''), 'is-side'),
            '</div>'
        ].join('');
    }

    function renderDetailLayout() {
        return [
            renderHead(),
            renderMetrics(3),
            '<div class="macpro-page-skeleton-detail-grid">',
            renderPanel([
                skeletonItem('line', 'is-wide'),
                skeletonItem('line'),
                skeletonItem('line', 'is-short'),
                renderTable(4, 4)
            ].join(''), 'is-detail-main'),
            renderPanel(repeat(5, function () {
                return [
                    '<div class="macpro-page-skeleton-field">',
                    skeletonItem('line', 'is-label'),
                    skeletonItem('input'),
                    '</div>'
                ].join('');
            }), 'is-detail-side'),
            '</div>'
        ].join('');
    }

    function renderLayout(layout) {
        const safeLayout = normalizeLayout(layout);
        const renderers = {
            dashboard: renderDashboardLayout,
            table: renderTableLayout,
            reports: renderReportsLayout,
            feed: renderFeedLayout,
            form: renderFormLayout,
            detail: renderDetailLayout
        };

        return '<div class="macpro-page-skeleton-inner macpro-page-skeleton-inner-' + safeLayout + '">' + renderers[safeLayout]() + '</div>';
    }

    function ensureFallbackStyles() {
        const hasSharedStyles = Boolean(document.querySelector('link[href*="style-improved.css"]'));

        if (hasSharedStyles || document.getElementById('macproPageSkeletonFallbackStyles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'macproPageSkeletonFallbackStyles';
        style.textContent = [
            '.macpro-page-skeleton{position:fixed;top:70px;right:0;bottom:0;left:260px;z-index:875;padding:24px;box-sizing:border-box;background:linear-gradient(180deg,rgba(244,247,255,.96),rgba(237,238,240,.94));opacity:0;visibility:hidden;pointer-events:none;overflow:hidden;transition:opacity .18s ease,visibility .18s ease}',
            'html.macpro-page-boot-loading .macpro-page-skeleton,body.macpro-page-is-loading .macpro-page-skeleton{opacity:1;visibility:visible;pointer-events:auto}',
            '.macpro-page-skeleton-inner{width:100%;height:100%;max-width:none;display:grid;gap:20px;min-height:0}.macpro-page-skeleton-inner-table,.macpro-page-skeleton-inner-feed,.macpro-page-skeleton-inner-form,.macpro-page-skeleton-inner-reports,.macpro-page-skeleton-inner-detail{grid-template-rows:auto auto minmax(0,1fr)}.macpro-page-skeleton-inner-dashboard{grid-template-rows:auto auto minmax(0,1fr) minmax(160px,.5fr)}.macpro-page-skeleton-head{display:flex;align-items:center;justify-content:space-between;gap:16px}.macpro-page-skeleton-toolbar{display:flex;gap:12px;flex-wrap:wrap}',
            '.macpro-page-skeleton [data-skeleton]{display:block;border-radius:999px;background:linear-gradient(90deg,#edeef0 0%,rgba(255,255,255,.95) 46%,#edeef0 100%);background-size:220% 100%;animation:macproSkeletonPulse 1.15s ease-in-out infinite}',
            '.macpro-page-skeleton [data-skeleton=title]{width:min(320px,68%);height:30px}.macpro-page-skeleton [data-skeleton=button]{width:112px;height:36px;border-radius:8px}.macpro-page-skeleton [data-skeleton=input]{height:38px;border-radius:8px}.macpro-page-skeleton [data-skeleton=card]{min-height:108px;border-radius:8px}.macpro-page-skeleton [data-skeleton=avatar]{width:42px;height:42px;border-radius:50%}.macpro-page-skeleton [data-skeleton=chart]{min-height:220px;border-radius:8px}',
            '.macpro-page-skeleton-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.macpro-page-skeleton-panel{display:grid;gap:12px;padding:20px;border-radius:8px;background:rgba(255,255,255,.72);min-height:0}.macpro-page-skeleton-panel.is-table,.macpro-page-skeleton-panel.is-feed,.macpro-page-skeleton-panel.is-form,.macpro-page-skeleton-panel.is-detail-main,.macpro-page-skeleton-panel.is-detail-side{height:100%}.macpro-page-skeleton-table{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px 20px}.macpro-page-skeleton-inner-table .macpro-page-skeleton-table,.macpro-page-skeleton-inner-detail .macpro-page-skeleton-table{height:100%;grid-auto-rows:minmax(14px,1fr)}.macpro-page-skeleton-table.is-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}.macpro-page-skeleton-table.is-cols-4{grid-template-columns:repeat(4,minmax(0,1fr))}.macpro-page-skeleton [data-skeleton=line]{height:14px}.macpro-page-skeleton .is-wide{width:62%}.macpro-page-skeleton .is-short{width:28%}.macpro-page-skeleton .is-label{width:35%;height:10px!important}',
            '.macpro-page-skeleton-dashboard-grid,.macpro-page-skeleton-form-grid,.macpro-page-skeleton-detail-grid{display:grid;grid-template-columns:1.35fr .9fr;gap:20px}.macpro-page-skeleton-report-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.macpro-page-skeleton-feed-item,.macpro-page-skeleton-field{display:grid;gap:10px}.macpro-page-skeleton-feed-item{grid-template-columns:auto 1fr auto;align-items:center}.macpro-page-skeleton-feed-copy{display:grid;gap:8px}',
            '@keyframes macproSkeletonPulse{from{background-position:120% 0}to{background-position:-120% 0}}@media(max-width:767px){.macpro-page-skeleton{left:0;padding:16px}.macpro-page-skeleton-head{align-items:flex-start;flex-direction:column}.macpro-page-skeleton-metrics,.macpro-page-skeleton-report-grid,.macpro-page-skeleton-dashboard-grid,.macpro-page-skeleton-form-grid,.macpro-page-skeleton-detail-grid{grid-template-columns:1fr}.macpro-page-skeleton-table{grid-template-columns:1fr 1fr}}'
        ].join('');

        document.head.appendChild(style);
    }

    function getSkeleton() {
        let skeleton = document.getElementById('macproPageSkeleton');

        if (skeleton) {
            return skeleton;
        }

        if (!document.body) {
            return null;
        }

        ensureFallbackStyles();

        skeleton = document.createElement('div');
        skeleton.className = 'macpro-page-skeleton';
        skeleton.id = 'macproPageSkeleton';
        skeleton.setAttribute('role', 'status');
        skeleton.setAttribute('aria-live', 'polite');
        skeleton.setAttribute('aria-label', 'Loading page');
        skeleton.setAttribute('aria-hidden', 'true');
        document.body.insertBefore(skeleton, document.body.firstChild);
        return skeleton;
    }

    function applySkeletonLayout(layout) {
        const safeLayout = normalizeLayout(layout);
        const skeleton = getSkeleton();

        if (!skeleton) {
            return;
        }

        skeleton.dataset.layout = safeLayout;
        document.documentElement.dataset.macproSkeleton = safeLayout;
        skeleton.innerHTML = renderLayout(safeLayout);
    }

    function hasMainContainer() {
        return Boolean(document.querySelector('.main-container'));
    }

    try {
        if (storageGet(navigatingKey) === 'true') {
            const storedLayout = normalizeLayout(storageGet(layoutKey));
            const remainingDelay = Math.max(0, delayBeforeVisibleMs - (Date.now() - navigationStartedAt()));

            document.documentElement.dataset.macproSkeleton = storedLayout;
            if (remainingDelay === 0) {
                revealPageSkeleton(storedLayout);
            } else {
                window.clearTimeout(revealTimer);
                revealTimer = window.setTimeout(function () {
                    if (storageGet(navigatingKey) === 'true') {
                        revealPageSkeleton(storedLayout);
                    }
                }, remainingDelay);
            }
        }
    } catch (error) {}

    function revealPageSkeleton(layout) {
        if (!hasMainContainer() || document.body.classList.contains('macpro-dialog-open')) {
            return;
        }

        const skeleton = getSkeleton();
        const safeLayout = normalizeLayout(layout || (skeleton && skeleton.dataset.layout) || storageGet(layoutKey) || inferCurrentLayout());

        applySkeletonLayout(safeLayout);
        window.clearTimeout(hideTimer);
        shownAt = Date.now();
        setNavigatingFlag(true, safeLayout, navigationStartedAt());
        document.documentElement.classList.add('macpro-page-boot-loading');
        document.body.classList.add('macpro-page-is-loading');

        const mainContainer = document.querySelector('.main-container');
        if (mainContainer) {
            mainContainer.setAttribute('aria-busy', 'true');
        }

        if (skeleton) {
            skeleton.setAttribute('aria-hidden', 'false');
        }
    }

    function schedulePageSkeleton(layout) {
        if (!hasMainContainer() || document.body.classList.contains('macpro-dialog-open')) {
            return;
        }

        const skeleton = getSkeleton();
        const safeLayout = normalizeLayout(layout || (skeleton && skeleton.dataset.layout) || storageGet(layoutKey) || inferCurrentLayout());
        const existingStartedAt = Number(storageGet(startedAtKey));
        const startedAt = Number.isFinite(existingStartedAt) && existingStartedAt > 0 ? existingStartedAt : Date.now();
        const remainingDelay = Math.max(0, delayBeforeVisibleMs - (Date.now() - startedAt));

        applySkeletonLayout(safeLayout);
        setNavigatingFlag(true, safeLayout, startedAt);
        window.clearTimeout(revealTimer);

        revealTimer = window.setTimeout(function () {
            if (storageGet(navigatingKey) === 'true') {
                revealPageSkeleton(safeLayout);
            }
        }, remainingDelay);
    }

    function hidePageSkeleton() {
        const elapsed = shownAt ? Date.now() - shownAt : minimumVisibleMs;
        const delay = Math.max(0, minimumVisibleMs - elapsed);

        window.clearTimeout(hideTimer);
        window.clearTimeout(revealTimer);
        hideTimer = window.setTimeout(function () {
            setNavigatingFlag(false, 'table');
            document.documentElement.classList.remove('macpro-page-boot-loading');
            document.body.classList.remove('macpro-page-is-loading');

            const mainContainer = document.querySelector('.main-container');
            if (mainContainer) {
                mainContainer.removeAttribute('aria-busy');
            }

            const skeleton = getSkeleton();
            if (skeleton) {
                skeleton.setAttribute('aria-hidden', 'true');
                applySkeletonLayout(inferCurrentLayout());
            }
        }, delay);
    }

    function shouldSkipLink(link, event) {
        const href = link.getAttribute('href') || '';

        if (
            event.defaultPrevented ||
            link.hasAttribute('download') ||
            link.target && link.target !== '_self' ||
            link.closest('[data-no-page-skeleton]') ||
            link.hasAttribute('data-macpro-confirm') ||
            href === '' ||
            href.charAt(0) === '#' ||
            href.indexOf('javascript:') === 0 ||
            href.indexOf('mailto:') === 0 ||
            href.indexOf('tel:') === 0
        ) {
            return true;
        }

        const nextUrl = new URL(link.href, window.location.href);
        const currentUrl = new URL(window.location.href);

        return (
            nextUrl.origin !== currentUrl.origin ||
            nextUrl.pathname === currentUrl.pathname &&
            nextUrl.search === currentUrl.search &&
            nextUrl.hash !== '' &&
            nextUrl.hash !== currentUrl.hash
        );
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[href]');

        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || shouldSkipLink(link, event)) {
            return;
        }

        schedulePageSkeleton(resolveLayout(link.href));
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (
            event.defaultPrevented ||
            form.matches('[data-no-page-skeleton]') ||
            form.target && form.target !== '_self' ||
            (form.method || '').toLowerCase() === 'dialog'
        ) {
            return;
        }

        schedulePageSkeleton(inferCurrentLayout());
    });

    window.addEventListener('beforeunload', function () {
        schedulePageSkeleton(storageGet(layoutKey) || inferCurrentLayout());
    });
    window.addEventListener('pageshow', hidePageSkeleton);

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        applySkeletonLayout(storageGet(layoutKey) || inferCurrentLayout());
        window.setTimeout(hidePageSkeleton, 120);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            applySkeletonLayout(storageGet(layoutKey) || inferCurrentLayout());
            window.setTimeout(hidePageSkeleton, 120);
        });
    }
})();
