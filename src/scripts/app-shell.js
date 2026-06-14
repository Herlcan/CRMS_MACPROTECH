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
    const modalContainerSelector = [
        '.modal',
        '.css-modal',
        '.payment-modal',
        '.refund-modal',
        '.report-export-status',
        '.macpro-dialog-overlay',
        '.add-client-modal-container',
        '.edit-client-modal-container',
        '.add-user-modal-container',
        '.category-modal-container',
        '.category-management-modal-container',
        '.edit-category-modal-container',
        '.edit-item-modal-container',
        '.delete-client-modal-container',
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
    let activeNavigationController = null;
    let pageScriptController = null;
    let navigationSerial = 0;
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
            isFormInsideVisibleModal(form) ||
            form.target && form.target !== '_self' ||
            method === 'dialog' ||
            !isSameAppUrl(actionUrl);
    }

    function shouldHandleAutoSubmitControl(control) {
        if (!control || !control.form) {
            return false;
        }

        return control.hasAttribute('onchange') ||
            control.form.hasAttribute('data-macpro-autosubmit') ||
            control.closest('[data-macpro-autosubmit]');
    }

    function isFormInsideVisibleModal(form) {
        const modal = form.closest(modalContainerSelector);

        return Boolean(modal && isVisibleModalElement(modal));
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
        if (queueLocalModalState.raf) {
            window.cancelAnimationFrame(queueLocalModalState.raf);
        }

        window.clearTimeout(queueLocalModalState.timer);

        queueLocalModalState.raf = window.requestAnimationFrame(function () {
            queueLocalModalState.raf = null;
            syncLocalModalState(notifyParent);
        });
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
            if (promoteFrameLocationIfNeeded(frame)) {
                return;
            }

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

    function cleanFrameLocation(frame) {
        try {
            return cleanUrl(frame.contentWindow.location.href);
        } catch (error) {
            return null;
        }
    }

    function shouldPromoteFrameLocation(url) {
        if (!url || url.origin !== window.location.origin) {
            return false;
        }

        const name = pageName(url);

        return name === 'login.php' ||
            name === 'admin-login.php' ||
            name === 'logout.php';
    }

    function promoteFrameLocationIfNeeded(frame) {
        const url = cleanFrameLocation(frame);

        if (!shouldPromoteFrameLocation(url)) {
            return false;
        }

        window.location.href = url.href;
        return true;
    }

    function abortPageScriptListeners() {
        if (pageScriptController) {
            pageScriptController.abort();
            pageScriptController = null;
        }
    }

    function pageContentStart(nextDocument) {
        const boundary = nextDocument.getElementById(boundaryId);

        if (boundary) {
            return boundary.nextSibling;
        }

        return nextDocument.querySelector('.mobile-menu-overlay') ||
            nextDocument.querySelector('.main-container');
    }

    function extractPageContent(nextDocument) {
        const start = pageContentStart(nextDocument);
        const nodes = [];
        let node = start;

        while (node) {
            nodes.push(node);
            node = node.nextSibling;
        }

        return nodes;
    }

    function importPageContent(nodes) {
        const fragment = document.createDocumentFragment();

        nodes.forEach(function (node) {
            fragment.appendChild(document.importNode(node, true));
        });

        return fragment;
    }

    function isExecutableScript(script) {
        const type = (script.getAttribute('type') || '').trim().toLowerCase();

        return type === '' ||
            type === 'text/javascript' ||
            type === 'application/javascript' ||
            type === 'application/ecmascript' ||
            type === 'module';
    }

    function copyScriptAttributes(source, target) {
        Array.prototype.forEach.call(source.attributes, function (attribute) {
            target.setAttribute(attribute.name, attribute.value);
        });

        target.dataset.macproContentScript = 'true';
    }

    function contentScriptFunctionNames(source) {
        const matcher = /^\s*(?:async\s+)?function\s+([A-Za-z_$][0-9A-Za-z_$]*)\s*\(/gm;
        const names = [];
        const seen = {};
        let match;

        while ((match = matcher.exec(source))) {
            if (!seen[match[1]]) {
                seen[match[1]] = true;
                names.push(match[1]);
            }
        }

        return names;
    }

    function wrappedInlineScript(source, pageUrl, index) {
        const exports = contentScriptFunctionNames(source).map(function (name) {
            const safeName = JSON.stringify(name);

            return 'if (typeof ' + name + ' === "function") { window[' + safeName + '] = ' + name + '; }';
        }).join('\n');
        const sourceUrl = pageUrl ? '\n//# sourceURL=' + pageUrl.href + '#macpro-content-script-' + index : '';

        return '(function () {\n' + source + '\n' + exports + '\n})();' + sourceUrl;
    }

    function executeExternalScript(script) {
        return new Promise(function (resolve) {
            const scriptUrl = safeUrl(script.src);
            const replacement = document.createElement('script');

            if (!scriptUrl || scriptUrl.origin !== window.location.origin) {
                script.remove();
                resolve();
                return;
            }

            copyScriptAttributes(script, replacement);
            replacement.async = false;
            replacement.src = scriptUrl.href;
            replacement.addEventListener('load', resolve, { once: true });
            replacement.addEventListener('error', resolve, { once: true });
            script.replaceWith(replacement);
        });
    }

    function executeInlineScript(script, pageUrl, index) {
        const replacement = document.createElement('script');
        const type = (script.getAttribute('type') || '').trim().toLowerCase();
        const source = script.textContent || '';

        copyScriptAttributes(script, replacement);

        if (type === 'module') {
            replacement.text = source;
        } else {
            replacement.text = wrappedInlineScript(source, pageUrl, index);
        }

        script.replaceWith(replacement);
        return Promise.resolve();
    }

    function optionsWithPageSignal(options) {
        if (!pageScriptController) {
            return options;
        }

        if (typeof options === 'boolean') {
            return {
                capture: options,
                signal: pageScriptController.signal
            };
        }

        if (options && typeof options === 'object') {
            if (options.signal) {
                return options;
            }

            return Object.assign({}, options, {
                signal: pageScriptController.signal
            });
        }

        return {
            signal: pageScriptController.signal
        };
    }

    function installPageScriptHooks(readyCallbacks) {
        const originalDocumentAdd = document.addEventListener;
        const originalWindowAdd = window.addEventListener;

        pageScriptController = 'AbortController' in window ? new AbortController() : null;

        document.addEventListener = function (type, listener, options) {
            if (type === 'DOMContentLoaded' && listener) {
                readyCallbacks.push({
                    target: document,
                    type: type,
                    listener: listener
                });
                return;
            }

            return originalDocumentAdd.call(document, type, listener, optionsWithPageSignal(options));
        };

        window.addEventListener = function (type, listener, options) {
            if (type === 'load' && listener && document.readyState === 'complete') {
                readyCallbacks.push({
                    target: window,
                    type: type,
                    listener: listener
                });
                return;
            }

            return originalWindowAdd.call(window, type, listener, optionsWithPageSignal(options));
        };

        return function () {
            document.addEventListener = originalDocumentAdd;
            window.addEventListener = originalWindowAdd;
        };
    }

    function runReadyCallback(callback) {
        const event = new Event(callback.type);

        try {
            if (typeof callback.listener === 'function') {
                callback.listener.call(callback.target, event);
                return;
            }

            if (callback.listener && typeof callback.listener.handleEvent === 'function') {
                callback.listener.handleEvent(event);
            }
        } catch (error) {
            window.console.error(error);
        }
    }

    function runContentScripts(root, pageUrl) {
        const scripts = Array.prototype.slice.call(root.querySelectorAll('script'));
        const readyCallbacks = [];
        const restoreHooks = installPageScriptHooks(readyCallbacks);

        return scripts.reduce(function (chain, script, index) {
            return chain.then(function () {
                if (!isExecutableScript(script)) {
                    return;
                }

                if (script.src) {
                    return executeExternalScript(script);
                }

                return executeInlineScript(script, pageUrl, index);
            });
        }, Promise.resolve()).then(function () {
            readyCallbacks.forEach(runReadyCallback);
        }).catch(function (error) {
            window.console.error(error);
        }).then(function () {
            restoreHooks();
        });
    }

    function assignedJsonValue(source, name) {
        const marker = 'window.' + name;
        const markerIndex = source.indexOf(marker);

        if (markerIndex === -1) {
            return undefined;
        }

        const equalsIndex = source.indexOf('=', markerIndex + marker.length);

        if (equalsIndex === -1) {
            return undefined;
        }

        let index = equalsIndex + 1;
        let quote = '';
        let depth = 0;
        let start;

        while (/\s/.test(source.charAt(index))) {
            index += 1;
        }

        start = index;

        for (; index < source.length; index += 1) {
            const char = source.charAt(index);
            const previous = source.charAt(index - 1);

            if (quote) {
                if (char === quote && previous !== '\\') {
                    quote = '';
                }
                continue;
            }

            if (char === '"' || char === "'") {
                quote = char;
                continue;
            }

            if (char === '{' || char === '[') {
                depth += 1;
                continue;
            }

            if (char === '}' || char === ']') {
                depth -= 1;
                if (depth === 0) {
                    index += 1;
                    break;
                }
                continue;
            }

            if (char === ';' && depth === 0) {
                break;
            }
        }

        try {
            return JSON.parse(source.slice(start, index));
        } catch (error) {
            return undefined;
        }
    }

    function readWindowAssignment(nextDocument, name) {
        const scripts = Array.prototype.slice.call(nextDocument.querySelectorAll('script'));
        let value;

        scripts.some(function (script) {
            value = assignedJsonValue(script.textContent || '', name);
            return value !== undefined;
        });

        return value;
    }

    function syncFetchedPageGlobals(nextDocument) {
        const csrfToken = readWindowAssignment(nextDocument, 'MACPRO_CSRF_TOKEN');
        const dialogFlash = readWindowAssignment(nextDocument, 'MACPRO_DIALOG_FLASH');

        if (csrfToken !== undefined) {
            window.MACPRO_CSRF_TOKEN = csrfToken;
        }

        if (dialogFlash && window.MacproDialog && typeof window.MacproDialog.alert === 'function') {
            window.MACPRO_DIALOG_FLASH = dialogFlash;
            window.MacproDialog.alert(dialogFlash);
        } else {
            window.MACPRO_DIALOG_FLASH = null;
        }
    }

    function removePortaledPageContent() {
        document.querySelectorAll('[data-macpro-page-portal]').forEach(function (element) {
            element.remove();
        });
    }

    function resetModalLocks() {
        document.documentElement.classList.remove('modal-open', 'macpro-frame-modal-open');
        document.body.classList.remove('modal-open', 'macpro-frame-modal-open');

        if (!document.querySelector('.macpro-dialog-overlay.show')) {
            document.body.classList.remove('macpro-dialog-open');
        }

        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
        syncLocalModalState(false);
    }

    function dispatchContentReady(url) {
        document.dispatchEvent(new CustomEvent(frameMessagePrefix + 'content-ready', {
            detail: {
                url: url.href
            }
        }));
    }

    function fallbackNavigation(url) {
        window.location.href = url.href;
    }

    function loadPageContent(nextCleanUrl, shouldPushState) {
        const shell = ensureShell();
        const navId = navigationSerial + 1;
        const controller = 'AbortController' in window ? new AbortController() : null;

        navigationSerial = navId;

        if (activeNavigationController) {
            activeNavigationController.abort();
        }

        activeNavigationController = controller;

        if (!shell || !window.fetch || !window.DOMParser) {
            fallbackNavigation(nextCleanUrl);
            return;
        }

        showLoading(nextCleanUrl.href);
        shell.setAttribute('aria-busy', 'true');

        fetch(nextCleanUrl.href, {
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined,
            headers: {
                'Accept': 'text/html,application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            const finalUrl = cleanUrl(response.url || nextCleanUrl.href);
            const contentType = response.headers.get('Content-Type') || '';

            if (!finalUrl || !isSameAppUrl(finalUrl)) {
                fallbackNavigation(finalUrl || nextCleanUrl);
                return null;
            }

            if (!response.ok || contentType.indexOf('text/html') === -1) {
                throw new Error('Unable to load page content.');
            }

            return response.text().then(function (html) {
                return {
                    html: html,
                    url: finalUrl
                };
            });
        }).then(function (result) {
            if (!result || navId !== navigationSerial) {
                return;
            }

            const nextDocument = new DOMParser().parseFromString(result.html, 'text/html');
            const contentNodes = extractPageContent(nextDocument);

            if (!contentNodes.length) {
                throw new Error('Page content boundary was not found.');
            }

            abortPageScriptListeners();
            removePortaledPageContent();
            resetModalLocks();
            shell.replaceChildren(importPageContent(contentNodes));
            document.title = nextDocument.title || document.title;
            setActiveNavigation(result.url.href);
            syncFetchedPageGlobals(nextDocument);

            if (shouldPushState) {
                window.history.pushState({ macproAppShell: true }, '', result.url.href);
            }

            window.scrollTo({ top: 0, left: 0, behavior: 'auto' });

            return runContentScripts(shell, result.url).then(function () {
                resetModalLocks();
                dispatchContentReady(result.url);
            });
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                return;
            }

            fallbackNavigation(nextCleanUrl);
        }).then(function () {
            if (navId !== navigationSerial) {
                return;
            }

            hideLoading();
            shell.removeAttribute('aria-busy');
        });
    }

    function navigate(href, shouldPushState) {
        const nextCleanUrl = cleanUrl(href);

        if (!nextCleanUrl || !isSameAppUrl(nextCleanUrl)) {
            return;
        }

        setActiveNavigation(nextCleanUrl.href);
        loadPageContent(nextCleanUrl, shouldPushState);
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

        document.addEventListener('change', function (event) {
            const control = event.target;
            const form = control && control.form;
            const method = form ? (form.method || 'get').toLowerCase() : '';

            if (!form || method !== 'get' || !shouldHandleAutoSubmitControl(control) || shouldSkipForm(form, event)) {
                return;
            }

            const nextUrl = formUrl(form);

            if (!nextUrl) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
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
