/**
 * Визуальный редактор: работает только внутри iframe админки.
 * Клик по тексту — правим на месте, клик по фото — заменяем файл.
 * Все изменения отправляются в родительское окно и сохраняются общей кнопкой.
 */
(function () {
    'use strict';

    if (window.parent === window || !window.parent) {
        return;
    }

    var active = null;
    var original = '';
    var originalHtml = '';
    var state = null;
    var hasState = false;

    function getPath(root, path) {
        var parts = String(path).split('.');
        var node = root;
        for (var i = 0; i < parts.length; i++) {
            if (node === null || node === undefined) return undefined;
            node = node[parts[i]];
        }
        return node;
    }

    function post(message) {
        try {
            window.parent.postMessage(message, window.location.origin);
        } catch (error) {
            /* старый браузер — молча игнорируем */
        }
    }

    function all(selector) {
        return Array.prototype.slice.call(document.querySelectorAll(selector));
    }

    function setText(node, value) {
        if (!node || value === null || value === undefined) return;
        if (node.getAttribute('data-bm-type') === 'number') {
            var digits = String(value).replace(/[^\d-]/g, '');
            node.textContent = digits === '' ? '0' : digits;
            if (node.hasAttribute('data-count')) node.setAttribute('data-count', digits || '0');
            return;
        }
        node.textContent = String(value);
    }

    function updateEmailLinks() {
        var email = '';
        var sample = document.querySelector('[data-bm="site.email"]');
        if (sample) email = sample.textContent.trim();
        if (!email) return;
        all('a[href^="mailto:"]').forEach(function (link) {
            link.setAttribute('href', 'mailto:' + email);
        });
    }

    function hydrate(nextState) {
        state = nextState || {};
        hasState = true;

        all('[data-bm]').forEach(function (node) {
            if (node === active) return;
            var value = getPath(state, node.getAttribute('data-bm'));
            if (value === undefined) return;
            setText(node, value);
        });

        all('[data-bm-img]').forEach(function (img) {
            var value = getPath(state, img.getAttribute('data-bm-img'));
            if (typeof value !== 'string' || value === '') return;
            if (img.getAttribute('src') === value) return;
            img.removeAttribute('srcset');
            var picture = img.closest('picture');
            if (picture) {
                Array.prototype.slice.call(picture.querySelectorAll('source')).forEach(function (source) {
                    source.parentNode.removeChild(source);
                });
            }
            img.setAttribute('src', value);
            var altPath = img.getAttribute('data-bm-alt');
            if (altPath) {
                var alt = getPath(state, altPath);
                if (typeof alt === 'string') img.setAttribute('alt', alt);
            }
        });

        updateEmailLinks();
    }

    function selectAll(node) {
        try {
            var range = document.createRange();
            range.selectNodeContents(node);
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        } catch (error) {
            /* ignore */
        }
    }

    function startEdit(node) {
        if (active) finishEdit();
        active = node;
        original = node.textContent;
        originalHtml = node.innerHTML;
        node.classList.add('bm-editing');
        node.setAttribute('contenteditable', 'true');
        node.setAttribute('spellcheck', 'false');
        node.focus();
        selectAll(node);
    }

    function finishEdit(cancel) {
        if (!active) return;
        var node = active;
        active = null;

        node.removeAttribute('contenteditable');
        node.removeAttribute('spellcheck');
        node.classList.remove('bm-editing');

        if (cancel) {
            node.innerHTML = originalHtml;
            return;
        }

        var value = (node.innerText || node.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
        if (node.getAttribute('data-bm-type') === 'number') {
            value = value.replace(/[^\d-]/g, '');
        }
        if (value === '') {
            node.innerHTML = originalHtml;
            return;
        }
        if (value === original.trim()) {
            node.textContent = value;
            return;
        }

        var path = node.getAttribute('data-bm');
        node.textContent = value;

        all('[data-bm="' + path.replace(/"/g, '\\"') + '"]').forEach(function (twin) {
            if (twin !== node) setText(twin, value);
        });

        if (path === 'site.email') {
            all('a[href^="mailto:"]').forEach(function (link) {
                link.setAttribute('href', 'mailto:' + value);
            });
        }

        if (hasState) {
            var parts = path.split('.');
            var target = state;
            for (var i = 0; i < parts.length - 1; i++) {
                if (target[parts[i]] === null || target[parts[i]] === undefined) {
                    target[parts[i]] = {};
                }
                target = target[parts[i]];
            }
            target[parts[parts.length - 1]] = node.getAttribute('data-bm-type') === 'number'
                ? parseInt(value, 10) || 0
                : value;
        }

        post({ type: 'bm-set', path: path, value: value });
    }

    function requestImage(img) {
        // Файловый диалог открываем прямо здесь, пока браузер считает клик
        // пользовательским действием. Сам файл передаём в админку.
        var picker = document.createElement('input');
        picker.type = 'file';
        picker.accept = 'image/jpeg,image/png,image/webp';
        picker.style.display = 'none';
        picker.addEventListener('change', function () {
            var file = picker.files && picker.files[0];
            if (file) {
                post({
                    type: 'bm-image-file',
                    path: img.getAttribute('data-bm-img'),
                    altPath: img.getAttribute('data-bm-alt') || '',
                    file: file
                });
            }
            if (picker.parentNode) picker.parentNode.removeChild(picker);
        });
        document.body.appendChild(picker);
        picker.click();
    }

    function setImage(message) {
        if (!message || !message.path) return;
        all('[data-bm-img="' + String(message.path).replace(/"/g, '\\"') + '"]').forEach(function (img) {
            var picture = img.closest('picture');
            if (picture) {
                Array.prototype.slice.call(picture.querySelectorAll('source')).forEach(function (source) {
                    source.parentNode.removeChild(source);
                });
            }
            img.removeAttribute('srcset');
            img.setAttribute('src', message.src);
            if (message.alt) img.setAttribute('alt', message.alt);
        });
    }

    document.addEventListener('click', function (event) {
        var image = event.target.closest ? event.target.closest('[data-bm-img]') : null;
        if (image) {
            event.preventDefault();
            event.stopPropagation();
            requestImage(image);
            return;
        }

        var node = event.target.closest ? event.target.closest('[data-bm]') : null;
        if (node) {
            event.preventDefault();
            event.stopPropagation();
            startEdit(node);
            return;
        }

        if (active) finishEdit();
    }, true);

    document.addEventListener('keydown', function (event) {
        if (!active) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            finishEdit(true);
        } else if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            event.stopPropagation();
            finishEdit();
        }
    }, true);

    document.addEventListener('paste', function (event) {
        if (!active) return;
        event.preventDefault();
        var text = (event.clipboardData || window.clipboardData).getData('text');
        document.execCommand('insertText', false, text.replace(/\s+/g, ' '));
    }, true);

    document.addEventListener('focusout', function (event) {
        if (active && event.target === active) {
            window.setTimeout(function () {
                if (active === event.target) finishEdit();
            }, 0);
        }
    }, true);

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin) return;
        var data = event.data || {};
        if (data.type === 'bm-state') {
            hydrate(data.state);
        } else if (data.type === 'bm-image-set') {
            setImage(data);
        } else if (data.type === 'bm-saved') {
            window.location.reload();
        }
    });

    function decorate() {
        var style = document.createElement('style');
        style.textContent = [
            '[data-bm]{cursor:text;border-radius:2px;transition:background .12s,box-shadow .12s}',
            '[data-bm]:hover{background:rgba(183,242,58,.16);box-shadow:0 0 0 2px rgba(183,242,58,.55)}',
            '[data-bm].bm-editing{background:rgba(183,242,58,.22);box-shadow:0 0 0 2px #b7f23a;outline:none}',
            '[data-bm-img]{cursor:pointer;transition:filter .12s,box-shadow .12s}',
            '[data-bm-img]:hover{filter:brightness(.82);box-shadow:0 0 0 4px #b7f23a}',
            '.bm-edit-hint{position:fixed;left:50%;top:14px;transform:translateX(-50%);z-index:2147483000;',
            'display:flex;gap:10px;align-items:center;padding:9px 14px;border-radius:999px;',
            'background:#0a0c0d;color:#e9ecee;font:500 12px/1.2 system-ui,sans-serif;letter-spacing:.01em;',
            'box-shadow:0 8px 28px rgba(0,0,0,.28);pointer-events:none;max-width:calc(100vw - 24px);text-align:center}',
            '.bm-edit-hint b{color:#b7f23a;font-weight:700}',
            '@media (max-width:700px){.bm-edit-hint{top:auto;bottom:76px;font-size:11px;padding:8px 12px}}'
        ].join('');
        document.head.appendChild(style);

        var hint = document.createElement('div');
        hint.className = 'bm-edit-hint';
        hint.innerHTML = '<b>Редактирование</b><span>кликните по тексту или фото</span>';
        document.body.appendChild(hint);
    }

    function ready() {
        decorate();
        post({ type: 'bm-ready' });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ready);
    } else {
        ready();
    }
})();
