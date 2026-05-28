(function () {
    const typeConfig = {
        success: { icon: 'OK', className: 'success', defaultTitle: 'Success' },
        error: { icon: '!', className: 'error', defaultTitle: 'Something went wrong' },
        warning: { icon: '!', className: 'warning', defaultTitle: 'Please confirm' },
        danger: { icon: '!', className: 'danger', defaultTitle: 'Please confirm' },
        info: { icon: 'i', className: 'info', defaultTitle: 'Notice' }
    };

    function getConfig(type) {
        return typeConfig[type] || typeConfig.info;
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

    function closeDialog(overlay, resolve, result) {
        overlay.classList.remove('show');
        document.body.classList.remove('macpro-dialog-open');

        window.setTimeout(function () {
            overlay.remove();
            resolve(result);
        }, 160);
    }

    function showDialog(options) {
        const settings = Object.assign({
            type: 'info',
            title: '',
            message: '',
            confirmLabel: 'OK',
            cancelLabel: 'Cancel',
            showCancel: false,
            closeOnBackdrop: true,
            autoClose: 0
        }, options || {});

        const config = getConfig(settings.type);
        const title = settings.title || config.defaultTitle;

        return new Promise(function (resolve) {
            const overlay = document.createElement('div');
            overlay.className = 'macpro-dialog-overlay';
            overlay.setAttribute('role', 'presentation');
            overlay.innerHTML = [
                '<div class="macpro-dialog macpro-dialog-' + config.className + '" role="dialog" aria-modal="true" aria-labelledby="macpro-dialog-title">',
                '  <div class="macpro-dialog-icon" aria-hidden="true">' + config.icon + '</div>',
                '  <div class="macpro-dialog-content">',
                '    <h3 id="macpro-dialog-title">' + escapeHtml(title) + '</h3>',
                settings.message ? '    <p>' + escapeHtml(settings.message) + '</p>' : '',
                '  </div>',
                '  <div class="macpro-dialog-actions">',
                settings.showCancel ? '    <button type="button" class="macpro-dialog-btn macpro-dialog-btn-secondary" data-macpro-dialog-cancel>' + escapeHtml(settings.cancelLabel) + '</button>' : '',
                '    <button type="button" class="macpro-dialog-btn macpro-dialog-btn-primary" data-macpro-dialog-confirm>' + escapeHtml(settings.confirmLabel) + '</button>',
                '  </div>',
                '</div>'
            ].join('');

            document.body.appendChild(overlay);
            document.body.classList.add('macpro-dialog-open');

            const confirmButton = overlay.querySelector('[data-macpro-dialog-confirm]');
            const cancelButton = overlay.querySelector('[data-macpro-dialog-cancel]');

            const escHandler = function (event) {
                if (event.key === 'Escape') {
                    document.removeEventListener('keydown', escHandler);
                    closeDialog(overlay, resolve, false);
                }
            };

            document.addEventListener('keydown', escHandler);

            confirmButton.addEventListener('click', function () {
                document.removeEventListener('keydown', escHandler);
                closeDialog(overlay, resolve, true);
            });

            if (cancelButton) {
                cancelButton.addEventListener('click', function () {
                    document.removeEventListener('keydown', escHandler);
                    closeDialog(overlay, resolve, false);
                });
            }

            overlay.addEventListener('click', function (event) {
                if (event.target === overlay && settings.closeOnBackdrop) {
                    document.removeEventListener('keydown', escHandler);
                    closeDialog(overlay, resolve, false);
                }
            });

            window.requestAnimationFrame(function () {
                overlay.classList.add('show');
                confirmButton.focus();
            });

            if (settings.autoClose > 0) {
                window.setTimeout(function () {
                    if (document.body.contains(overlay)) {
                        document.removeEventListener('keydown', escHandler);
                        closeDialog(overlay, resolve, true);
                    }
                }, settings.autoClose);
            }
        });
    }

    window.MacproDialog = {
        show: showDialog,
        alert: function (options) {
            return showDialog(Object.assign({ showCancel: false }, options || {}));
        },
        success: function (options) {
            return showDialog(Object.assign({ type: 'success', showCancel: false }, options || {}));
        },
        error: function (options) {
            return showDialog(Object.assign({ type: 'error', showCancel: false }, options || {}));
        },
        info: function (options) {
            return showDialog(Object.assign({ type: 'info', showCancel: false }, options || {}));
        },
        confirm: function (options) {
            return showDialog(Object.assign({
                type: 'warning',
                showCancel: true,
                confirmLabel: 'Confirm',
                cancelLabel: 'Cancel',
                closeOnBackdrop: false
            }, options || {}));
        }
    };

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-macpro-confirm]');
        if (!trigger) {
            return;
        }

        event.preventDefault();

        window.MacproDialog.confirm({
            type: trigger.dataset.macproConfirmVariant || 'danger',
            title: trigger.dataset.macproConfirmTitle || 'Confirm Action',
            message: trigger.dataset.macproConfirmMessage || 'Are you sure you want to continue?',
            confirmLabel: trigger.dataset.macproConfirmLabel || 'Confirm',
            cancelLabel: trigger.dataset.macproCancelLabel || 'Cancel'
        }).then(function (confirmed) {
            if (!confirmed) {
                return;
            }

            if (trigger.tagName === 'A' && trigger.href) {
                window.location.href = trigger.href;
                return;
            }

            const form = trigger.closest('form');
            if (form) {
                form.submit();
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (window.MACPRO_DIALOG_FLASH) {
            window.MacproDialog.alert(window.MACPRO_DIALOG_FLASH);
        }
    });
})();
