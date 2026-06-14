(function () {
    const frameParam = 'macpro_frame';
    const shellId = 'macproAppContent';
    const boundaryId = 'macproAppContentBoundary';
    const frameId = 'macproContentFrame';
    const frameMessagePrefix = 'macpro:';
    const minimumFrameHeight = 520;
    const modalToggleSelector = [
        '.profile-toggle:checked',
        '.add-client-toggle:checked',
        '.edit-client-toggle:checked',
        '.add-user-toggle:checked',
        '.add-item-toggle:checked',
        '.category-toggle:checked',
        '.edit-category-toggle:checked',
        '.edit-item-toggle:checked',
        '.delete-client-toggle:checked'
    ].join(',');
    const modalVisibleSelector = [
        '.modal.show',
        '.payment-modal.show',
        '.refund-modal.show',
        '.report-export-status.show',
        '.macpro-dialog-overlay.show',
        '#addInventoryStockModal',
        '#editInventoryTransactionModal',
        '#deleteWorkOrderModal'
    ].join(',');
    const scrollLockClasses = [
        'macpro-modal-open',
        'macpro-frame-modal-open',
        'macpro-dialog-open',
        'modal-open'
    ];
    let frameModalOpen = false;
    let lastLocalModalOpen = null;
    const layoutByPage = {
        'index.php': 'dashboard',
        'reports.php': 'reports',
        'notifications.php': 'feed',
        'settings.php': 'form',
        'client-view.php': 'detail',
        'stock_transaction.php': 'detail'
    };

    function safeUrl(href, base) {
        try {
            return new URL(href, base || window.location.href);
        } catch (error) {
            return null;
        }
    }

    function pageName(url) {
        const parts = url.pathname.split('/').filter(Boolean);
        return parts.pop() || 'index.php';
    }

    function cleanUrl(href) {
        const url = safeUrl(href);

        if (!url) {
            return null;
        }

        url.searchParams.delete(frameParam);
        return url;
    }

    function frameUrl(href) {
        const url = cleanUrl(href);

        if (!url) {
            return null;
        }

        url.searchParams.set(frameParam, '1');
        return url;
    }

    function isHttpUrl(url) {
        return url && (url.protocol === 'http:' || url.protocol === 'https:');
    }

    function isSameAppUrl(url) {
        if (!isHttpUrl(url) || url.origin !== window.location.origin) {
            return false;
        }

        const name = pageName(url);

        if (!/\.php$/i.test(name)) {
            return false;
        }

        if (
            name === 'logout.php' ||
            name === 'login.php' ||
            name === 'admin-login.php' ||
            name === 'export-reports.php'
        ) {
            return false;
        }

        return url.pathname.indexOf('/src/handlers/') === -1;
    }

    function isSameDocumentHashNavigation(nextUrl) {
        const currentUrl = new URL(window.location.href);

        return nextUrl.origin === currentUrl.origin &&
            nextUrl.pathname === currentUrl.pathname &&
            nextUrl.search === currentUrl.search &&
            nextUrl.hash &&
            nextUrl.hash !== currentUrl.hash;
    }

    function shouldSkipLink(link, event) {
        const href = link.getAttribute('href') || '';
        const normalizedHref = href.trim().toLowerCase();
        const nextUrl = safeUrl(link.href);

        return event.defaultPrevented ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey ||
            link.hasAttribute('download') ||
            link.hasAttribute('data-no-app-shell') ||
            link.hasAttribute('data-no-page-skeleton') ||
            link.hasAttribute('data-macpro-confirm') ||
            link.closest('[data-no-app-shell]') ||
            link.closest('.notification-preview-list') ||
            link.target && link.target !== '_self' ||
            href === '' ||
            href.charAt(0) === '#' ||
            normalizedHref.indexOf('javascript:') === 0 ||
            normalizedHref.indexOf('data:') === 0 ||
            normalizedHref.indexOf('vbscript:') === 0 ||
            normalizedHref.indexOf('mailto:') === 0 ||
            normalizedHref.indexOf('tel:') === 0 ||
            !isSameAppUrl(nextUrl) ||
            isSameDocumentHashNavigation(nextUrl);
    }

    function shouldSkipForm(form, event) {
        const method = (form.method || 'get').toLowerCase();
        const actionUrl = safeUrl(form.action || window.location.href);

        return event.defaultPrevented ||
            form.hasAttribute('data-no-app-shell') ||
            form.hasAttribute('data-no-page-skeleton') ||
            form.closest('[data-no-app-shell]') ||
            form.target && form.target !== '_self' ||
            method === 'dialog' ||
            !isSameAppUrl(actionUrl);
    }

    function formUrl(form) {
        const actionUrl = cleanUrl(form.action || window.location.href);
        const data = new FormData(form);

        if (!actionUrl) {
            return null;
        }

        actionUrl.search = '';
        data.forEach(function (value, key) {
            actionUrl.searchParams.append(key, value);
        });

        return actionUrl;
    }

    function resolveLayout(href) {
        const url = safeUrl(href);
        const name = url ? pageName(url) : 'index.php';

        if (layoutByPage[name]) {
            return layoutByPage[name];
        }

        if (name.indexOf('report') !== -1) {
            return 'reports';
        }

        if (name.indexOf('notification') !== -1) {
            return 'feed';
        }

        return 'table';
    }

    function showLoading(href) {
        document.documentElement.dataset.macproSkeleton = resolveLayout(href);
        document.documentElement.classList.add('macpro-page-boot-loading');
        document.body.classList.add('macpro-page-is-loading');

        const skeleton = document.getElementById('macproPageSkeleton');

        if (skeleton) {
            skeleton.setAttribute('aria-hidden', 'false');
        }
    }

    function hideLoading() {
        document.documentElement.classList.remove('macpro-page-boot-loading');
        document.body.classList.remove('macpro-page-is-loading');

        const skeleton = document.getElementById('macproPageSkeleton');

        if (skeleton) {
            skeleton.setAttribute('aria-hidden', 'true');
        }
    }

    function shouldDocumentScrollLock() {
        const html = document.documentElement;
        const body = document.body;

        return scrollLockClasses.some(function (className) {
            return html.classList.contains(className) || body.classList.contains(className);
        });
    }

    function refreshDocumentScrollLock() {
        const shouldLock = shouldDocumentScrollLock();

        document.documentElement.style.overflow = shouldLock ? 'hidden' : '';

        if (document.body) {
            document.body.style.overflow = shouldLock ? 'hidden' : '';
        }
    }

    function setDocumentScrollLockClass(className, isOpen) {
        document.documentElement.classList.toggle(className, isOpen);

        if (document.body) {
            document.body.classList.toggle(className, isOpen);
        }

        refreshDocumentScrollLock();
    }

    function isVisibleModalElement(element) {
        if (!element || !document.documentElement.contains(element)) {
            return false;
        }

        const style = window.getComputedStyle(element);

        return style.display !== 'none' &&
            style.visibility !== 'hidden' &&
            Number(style.opacity) !== 0 &&
            Boolean(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
    }

    function hasOpenLocalModal() {
        if (document.querySelector(modalToggleSelector)) {
            return true;
        }

        return Array.prototype.some.call(
            document.querySelectorAll(modalVisibleSelector),
            isVisibleModalElement
        );
    }

    function queueLocalModalState(notifyParent) {
        window.clearTimeout(queueLocalModalState.timer);
        queueLocalModalState.timer = window.setTimeout(function () {
            syncLocalModalState(notifyParent);
        }, 40);
    }

    function syncLocalModalState(notifyParent) {
        const isOpen = hasOpenLocalModal();

        setDocumentScrollLockClass('macpro-modal-open', isOpen);

        if (notifyParent && isOpen !== lastLocalModalOpen) {
            postToParent({
                type: frameMessagePrefix + 'modal-state',
                open: isOpen
            });
        }

        if (notifyParent && !isOpen && lastLocalModalOpen === true) {
            queueFrameHeight();
        }

        lastLocalModalOpen = isOpen;
    }

    function initLocalModalTracking(notifyParent) {
        syncLocalModalState(notifyParent);

        document.addEventListener('change', function () {
            queueLocalModalState(notifyParent);
        }, true);

        document.addEventListener('click', function () {
            queueLocalModalState(notifyParent);
        }, true);

        document.addEventListener('keydown', function () {
            queueLocalModalState(notifyParent);
        }, true);

        window.addEventListener('load', function () {
            queueLocalModalState(notifyParent);
        });

        if ('MutationObserver' in window) {
            const modalObserver = new MutationObserver(function () {
                queueLocalModalState(notifyParent);
            });

            modalObserver.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class', 'style', 'aria-hidden'],
                childList: true,
                subtree: true
            });
        }
    }

    function ensureShell() {
        let shell = document.getElementById(shellId);

        if (shell) {
            return shell;
        }

        const boundary = document.getElementById(boundaryId);
        const fallbackStart = document.querySelector('.mobile-menu-overlay') || document.querySelector('.main-container');
        const start = boundary ? boundary.nextSibling : fallbackStart;

        if (!start || !start.parentNode) {
            return null;
        }

        shell = document.createElement('div');
        shell.id = shellId;
        shell.className = 'macpro-app-content';
        start.parentNode.insertBefore(shell, start);

        let node = start;
        while (node) {
            const next = node.nextSibling;
            shell.appendChild(node);
            node = next;
        }

        return shell;
    }

    function ensureFrame() {
        const shell = ensureShell();
        let host = document.querySelector('.macpro-frame-host');
        let frame = document.getElementById(frameId);

        if (!shell) {
            return null;
        }

        if (host && frame) {
            return frame;
        }

        shell.textContent = '';

        host = document.createElement('div');
        host.className = 'main-container macpro-frame-host';
        host.setAttribute('aria-live', 'polite');

        frame = document.createElement('iframe');
        frame.id = frameId;
        frame.className = 'macpro-content-frame';
        frame.title = 'Page content';
        frame.setAttribute('loading', 'eager');
        frame.setAttribute('scrolling', 'no');

        host.appendChild(frame);
        shell.appendChild(host);

        frame.addEventListener('load', function () {
            setFrameModalOpen(false);
            hideLoading();
            updateHistoryFromFrame(frame);
            setActiveNavigation(frame.src);
        });

        frame.addEventListener('error', function () {
            const current = cleanUrl(frame.src);
            if (current) {
                window.location.href = current.href;
            }
        });

        return frame;
    }

    function setFrameHeight(height) {
        const frame = document.getElementById(frameId);
        const availableHeight = Math.max(minimumFrameHeight, window.innerHeight - 110);
        const nextHeight = Math.max(minimumFrameHeight, availableHeight, Number(height) || 0);

        if (frame) {
            frame.style.height = nextHeight + 'px';
        }
    }

    function visibleFrameViewport(frame) {
        const rect = frame.getBoundingClientRect();
        const header = document.querySelector('.header');
        const headerBottom = header ? Math.max(0, header.getBoundingClientRect().bottom) : 0;
        const visibleTop = Math.max(rect.top, headerBottom, 0);
        const visibleBottom = Math.min(rect.bottom, window.innerHeight);
        const fallbackHeight = Math.max(320, window.innerHeight - headerBottom);
        const visibleHeight = Math.max(320, visibleBottom - visibleTop || fallbackHeight);

        return {
            top: Math.max(0, Math.round(visibleTop - rect.top)),
            height: Math.round(visibleHeight)
        };
    }

    function postFrameModalViewport() {
        const frame = document.getElementById(frameId);

        if (!frame || !frame.contentWindow) {
            return;
        }

        const viewport = visibleFrameViewport(frame);

        frame.contentWindow.postMessage({
            type: frameMessagePrefix + 'modal-viewport',
            top: viewport.top,
            height: viewport.height
        }, window.location.origin);
    }

    function setFrameModalOpen(isOpen) {
        frameModalOpen = Boolean(isOpen);
        setDocumentScrollLockClass('macpro-frame-modal-open', frameModalOpen);

        if (frameModalOpen) {
            postFrameModalViewport();
        }
    }

    function applyFrameModalViewport(top, height) {
        const modalTop = Math.max(0, Number(top) || 0);
        const modalHeight = Math.max(320, Number(height) || window.innerHeight || minimumFrameHeight);

        document.documentElement.style.setProperty('--macpro-frame-modal-top', modalTop + 'px');
        document.documentElement.style.setProperty('--macpro-frame-modal-height', modalHeight + 'px');
    }

    function updateHistoryFromFrame(frame) {
        let frameLocation;

        try {
            frameLocation = frame.contentWindow.location.href;
        } catch (error) {
            return;
        }

        const nextUrl = cleanUrl(frameLocation);

        if (!nextUrl || !isSameAppUrl(nextUrl)) {
            return;
        }

        const currentUrl = cleanUrl(window.location.href);

        if (currentUrl && nextUrl.href !== currentUrl.href) {
            window.history.replaceState({ macproAppShell: true }, '', nextUrl.href);
        }
    }

    function navigate(href, shouldPushState) {
        const nextCleanUrl = cleanUrl(href);

        if (!nextCleanUrl) {
            return;
        }

        showLoading(nextCleanUrl.href);
        setActiveNavigation(nextCleanUrl.href);
        window.location.href = nextCleanUrl.href;
    }

    function setActiveNavigation(href) {
        const url = cleanUrl(href);

        if (!url) {
            return;
        }

        const currentPage = pageName(url);

        document.querySelectorAll('.sidebar-menu li').forEach(function (item) {
            const link = item.querySelector('a[href]');
            const linkUrl = link ? cleanUrl(link.href) : null;

            item.classList.toggle('active', Boolean(linkUrl && pageName(linkUrl) === currentPage));
        });
    }

    function initTopShell() {
        ensureShell();
        setActiveNavigation(window.location.href);

        document.addEventListener('click', function (event) {
            const link = event.target.closest('a[href]');

            if (!link || shouldSkipLink(link, event)) {
                return;
            }

            event.preventDefault();
            navigate(link.href, true);
        }, true);

        document.addEventListener('submit', function (event) {
            const form = event.target;
            const method = (form.method || 'get').toLowerCase();

            if (shouldSkipForm(form, event) || method !== 'get') {
                return;
            }

            const nextUrl = formUrl(form);

            if (!nextUrl) {
                return;
            }

            event.preventDefault();
            navigate(nextUrl.href, true);
        }, true);

        window.addEventListener('popstate', function () {
            navigate(window.location.href, false);
        });

        window.addEventListener('resize', function () {
            const frame = document.getElementById(frameId);
            if (frame) {
                setFrameHeight(parseInt(frame.style.height, 10));
            }

            if (frameModalOpen) {
                postFrameModalViewport();
            }
        });

        window.addEventListener('scroll', function () {
            if (frameModalOpen) {
                postFrameModalViewport();
            }
        }, { passive: true });

        initLocalModalTracking(false);
    }

    function postToParent(message) {
        if (window.parent === window) {
            return;
        }

        window.parent.postMessage(message, window.location.origin);
    }

    function notifyFrameHeight() {
        const body = document.body;
        const html = document.documentElement;
        const height = Math.max(
            body ? body.scrollHeight : 0,
            body ? body.offsetHeight : 0,
            html ? html.scrollHeight : 0,
            html ? html.offsetHeight : 0
        );

        postToParent({
            type: frameMessagePrefix + 'height',
            height: height
        });
    }

    function queueFrameHeight() {
        window.clearTimeout(queueFrameHeight.timer);
        queueFrameHeight.timer = window.setTimeout(notifyFrameHeight, 80);
    }

    function initFramePage() {
        document.addEventListener('click', function (event) {
            const link = event.target.closest('a[href]');

            if (!link || shouldSkipLink(link, event)) {
                return;
            }

            event.preventDefault();
            postToParent({
                type: frameMessagePrefix + 'navigate',
                url: cleanUrl(link.href).href
            });
        }, true);

        document.addEventListener('submit', function (event) {
            const form = event.target;
            const method = (form.method || 'get').toLowerCase();

            if (shouldSkipForm(form, event)) {
                return;
            }

            if (method === 'get') {
                const nextUrl = formUrl(form);

                if (!nextUrl) {
                    return;
                }

                event.preventDefault();
                postToParent({
                    type: frameMessagePrefix + 'navigate',
                    url: nextUrl.href
                });
                return;
            }

            const actionUrl = frameUrl(form.action || window.location.href);
            if (actionUrl) {
                form.action = actionUrl.href;
            }
        }, true);

        window.addEventListener('load', notifyFrameHeight);
        window.addEventListener('resize', queueFrameHeight);
        document.addEventListener('DOMContentLoaded', notifyFrameHeight);

        if ('MutationObserver' in window) {
            const observer = new MutationObserver(queueFrameHeight);
            observer.observe(document.documentElement, {
                attributes: true,
                childList: true,
                subtree: true
            });
        }

        initLocalModalTracking(true);
        queueFrameHeight();
    }

    window.addEventListener('message', function (event) {
        const data = event.data || {};

        if (event.origin !== window.location.origin || typeof data.type !== 'string') {
            return;
        }

        if (data.type === frameMessagePrefix + 'height') {
            if (frameModalOpen) {
                postFrameModalViewport();
            } else {
                setFrameHeight(data.height);
            }
            return;
        }

        if (data.type === frameMessagePrefix + 'modal-state') {
            setFrameModalOpen(Boolean(data.open));
            return;
        }

        if (data.type === frameMessagePrefix + 'modal-viewport') {
            applyFrameModalViewport(data.top, data.height);
            return;
        }

        if (data.type === frameMessagePrefix + 'navigate' && data.url) {
            navigate(data.url, true);
        }
    });

    if (window.top === window) {
        window.MacproAppShell = {
            navigate: function (href) {
                navigate(href, true);
            }
        };
    }

    if (window.top === window && !document.body.classList.contains('macpro-frame-page')) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initTopShell);
        } else {
            initTopShell();
        }
    } else {
        initFramePage();
    }
})();
