/**
 * Админка: редактор контента, загрузка фото, заявки, настройки.
 */
(function () {
    'use strict';

    var dataEl = document.getElementById('content-data');
    if (!dataEl) {
        return;
    }

    var state;
    try {
        state = JSON.parse(dataEl.value);
    } catch (error) {
        var editorFallback = document.getElementById('editor');
        if (editorFallback) {
            editorFallback.textContent = 'Не удалось прочитать content.json. Проверьте файл или восстановите резервную копию.';
        }
        return;
    }

    var editor = document.getElementById('editor');
    var toastEl = document.querySelector('[data-toast]');
    var saveBtn = document.querySelector('[data-save]');
    var saveStateEl = document.querySelector('[data-save-state]');
    var csrfMeta = document.querySelector('meta[name="csrf"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var dirty = false;
    var pendingOpen = '';
    var renderOpenState = {};
    var toastTimer = null;
    var visualFrame = document.querySelector('[data-visual-frame]');
    var visualLoaded = false;
    var visualChanged = false;

    /* ---------- Утилиты ---------- */

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function $$(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function appendChildren(node, children) {
        if (children === null || children === undefined) {
            return;
        }
        if (Array.isArray(children)) {
            children.forEach(function (child) {
                appendChildren(node, child);
            });
            return;
        }
        node.appendChild(typeof children === 'string' ? document.createTextNode(children) : children);
    }

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (key) {
                var value = attrs[key];
                if (value === null || value === undefined || value === false) {
                    return;
                }
                if (key === 'class') {
                    node.className = value;
                } else if (key === 'text') {
                    node.textContent = value;
                } else if (key === 'dataset') {
                    Object.keys(value).forEach(function (dataKey) {
                        node.dataset[dataKey] = value[dataKey];
                    });
                } else if (key === 'open') {
                    node.open = true;
                } else if (key === 'checked') {
                    node.checked = true;
                } else if (key === 'disabled') {
                    node.disabled = true;
                } else {
                    node.setAttribute(key, value);
                }
            });
        }
        appendChildren(node, children);
        return node;
    }

    function btn(text, className, onClick, title) {
        var node = el('button', { type: 'button', class: className, text: text, title: title || '' });
        node.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            onClick(event);
        });
        return node;
    }

    function getPath(path) {
        var node = state;
        for (var i = 0; i < path.length; i++) {
            if (node === null || node === undefined) {
                return undefined;
            }
            node = node[path[i]];
        }
        return node;
    }

    function setPath(path, value) {
        var node = state;
        for (var i = 0; i < path.length - 1; i++) {
            var key = path[i];
            if (node[key] === null || node[key] === undefined) {
                node[key] = typeof path[i + 1] === 'number' ? [] : {};
            }
            node = node[key];
        }
        node[path[path.length - 1]] = value;
        markDirty();
    }

    function pattern(path) {
        return path.map(function (segment) {
            return typeof segment === 'number' ? '*' : segment;
        }).join('.');
    }

    function imgUrl(src) {
        if (!src) {
            return '';
        }
        return '../' + String(src).replace(/^\/+/, '');
    }

    function toast(message, isError) {
        if (!toastEl) {
            return;
        }
        toastEl.textContent = message;
        toastEl.classList.toggle('is-error', Boolean(isError));
        toastEl.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toastEl.classList.remove('is-visible');
        }, 4200);
    }

    function markDirty() {
        if (!dirty) {
            dirty = true;
            syncSaveUi();
        }
    }

    function syncSaveUi() {
        if (!saveBtn || !saveStateEl) {
            return;
        }
        saveBtn.disabled = !dirty;
        saveBtn.classList.toggle('is-dirty', dirty);
        saveStateEl.classList.remove('is-error');
        saveStateEl.textContent = dirty ? 'Есть несохранённые изменения' : 'Всё сохранено';
    }

    function api(url, options) {
        options = options || {};
        options.headers = Object.assign({
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
        }, options.headers || {});

        return fetch(url, options).then(function (response) {
            return response.json().catch(function () {
                return {
                    ok: false,
                    message: 'Сервер вернул неожиданный ответ (' + response.status + '). Попробуйте ещё раз.'
                };
            });
        });
    }

    function uploadFile(file, onDone, busyNode) {
        if (!file) {
            return;
        }
        if (file.size > 12 * 1024 * 1024) {
            toast('Файл больше 12 МБ. Сожмите фото и попробуйте снова.', true);
            return;
        }

        var formData = new FormData();
        formData.append('file', file);

        if (busyNode) {
            busyNode.classList.add('is-busy');
            busyNode.setAttribute('aria-busy', 'true');
        }

        api('action.php?do=upload', { method: 'POST', body: formData })
            .then(function (response) {
                if (busyNode) {
                    busyNode.classList.remove('is-busy');
                    busyNode.removeAttribute('aria-busy');
                }
                if (!response.ok) {
                    toast(response.message || 'Не удалось загрузить фото.', true);
                    return;
                }
                onDone(response);
                toast('Фото загружено и сжато.');
            })
            .catch(function () {
                if (busyNode) {
                    busyNode.classList.remove('is-busy');
                    busyNode.removeAttribute('aria-busy');
                }
                toast('Сеть недоступна. Проверьте соединение и попробуйте ещё раз.', true);
            });
    }

    function filePicker(onFile) {
        var picker = el('input', {
            type: 'file',
            accept: 'image/jpeg,image/png,image/webp',
            style: 'display:none'
        });
        picker.addEventListener('change', function () {
            if (picker.files && picker.files[0]) {
                onFile(picker.files[0]);
            }
            if (picker.parentNode) {
                picker.parentNode.removeChild(picker);
            }
        });
        document.body.appendChild(picker);
        picker.click();
    }

    /* ---------- Подписи ---------- */

    var LABELS = {
        'site.domain': 'Адрес сайта',
        'site.title': 'Title страницы (для поиска)',
        'site.description': 'Description (для поиска)',
        'site.company': 'Название компании',
        'site.legal_name': 'Юридическое название',
        'site.unp': 'УНП',
        'site.legal_address': 'Юридический адрес',
        'site.phone': 'Телефон для ссылки',
        'site.phone_display': 'Телефон как показывать',
        'site.phone2': 'Второй телефон для ссылки',
        'site.phone2_display': 'Второй телефон как показывать',
        'site.email': 'E-mail для заявок',
        'site.geo': 'География работ',
        'site.delivery': 'Доставка',
        'site.hours': 'Часы работы',
        'site.copyright': 'Копирайт в подвале',
        'site.gtm': 'Google Tag Manager (ID)',
        'site.metrika': 'Яндекс.Метрика (номер)',
        'hero.kicker': 'Надзаголовок',
        'hero.title_top': 'Заголовок — строка 1',
        'hero.title_mid': 'Заголовок — строка 2',
        'hero.title_bottom': 'Заголовок — строка 3',
        'hero.lead': 'Подзаголовок',
        'hero.badges': 'Плашки под заголовком',
        'hero.cta': 'Текст кнопки',
        'hero.cta_note': 'Примечание под кнопкой',
        'hero.image': 'Главное фото',
        'hero.image_alt': 'Описание фото для поиска',
        'hero.image_caption': 'Подпись на фото',
        'hero.specs': 'Характеристики на фото',
        'hero.ticker': 'Бегущая строка',
        'nav.*.label': 'Название пункта',
        'nav.*.href': 'Ссылка (якорь)',
        'advantages.title': 'Заголовок раздела',
        'advantages.subtitle': 'Подзаголовок',
        'services.title': 'Заголовок раздела',
        'services.subtitle': 'Подзаголовок',
        'services.items.*.name': 'Название услуги',
        'services.items.*.short': 'Короткое название для вкладки',
        'services.items.*.desc': 'Описание',
        'services.items.*.badge': 'Плашка на фото',
        'services.items.*.cta': 'Текст кнопки',
        'services.items.*.specs': 'Характеристики',
        'services.items.*.photos': 'Фотографии',
        'services.items.*.photos.*.alt': 'Описание фото',
        'process.title': 'Заголовок раздела',
        'process.subtitle': 'Подзаголовок',
        'process.steps.*.title': 'Название этапа',
        'process.steps.*.text': 'Описание этапа',
        'stats.*.value': 'Число',
        'stats.*.suffix': 'После числа',
        'stats.*.label': 'Подпись',
        'stats.*.text': 'Пояснение',
        'works.title': 'Заголовок раздела',
        'works.subtitle': 'Подзаголовок',
        'works.items.*.src': 'Фотография',
        'works.items.*.alt': 'Описание фото для поиска',
        'works.items.*.caption': 'Подпись',
        'reviews.title': 'Заголовок раздела',
        'reviews.subtitle': 'Подзаголовок',
        'reviews.items.*.name': 'Имя клиента',
        'reviews.items.*.role': 'Объект / роль',
        'reviews.items.*.text': 'Текст отзыва',
        'faq.title': 'Заголовок раздела',
        'faq.items.*.q': 'Вопрос',
        'faq.items.*.a': 'Ответ',
        'contacts.title': 'Заголовок',
        'contacts.text': 'Текст',
        'contacts.cta': 'Текст кнопки',
        'contacts.form_title': 'Заголовок формы',
        'contacts.form_text': 'Текст формы',
        'forms.name_label': 'Подпись «Имя»',
        'forms.name_placeholder': 'Подсказка в поле «Имя»',
        'forms.phone_label': 'Подпись «Телефон»',
        'forms.phone_placeholder': 'Подсказка в поле «Телефон»',
        'forms.consent': 'Текст согласия',
        'forms.consent_link': 'Название ссылки на политику',
        'forms.submit': 'Текст кнопки отправки',
        'forms.sending': 'Текст во время отправки',
        'forms.success': 'Сообщение об успехе',
        'forms.error': 'Сообщение об ошибке',
        'forms.callback_title': 'Заголовок формы звонка',
        'forms.callback_text': 'Текст формы звонка',
        'forms.privacy_title': 'Заголовок политики',
        'footer.tagline': 'Описание в подвале',
        'footer.nav_title': 'Заголовок «Разделы»',
        'footer.contacts_title': 'Заголовок «Контакты»',
        'footer.legal_title': 'Заголовок «Документы»'
    };

    var KEY_LABELS = {
        title: 'Заголовок',
        subtitle: 'Подзаголовок',
        text: 'Текст',
        name: 'Название',
        label: 'Подпись',
        value: 'Значение',
        suffix: 'После числа',
        alt: 'Описание фото для поиска',
        caption: 'Подпись',
        q: 'Вопрос',
        a: 'Ответ',
        href: 'Ссылка',
        icon: 'Иконка',
        role: 'Объект / роль',
        kicker: 'Надзаголовок',
        cta: 'Текст кнопки',
        bullets: 'Пункты списка',
        badges: 'Плашки',
        specs: 'Характеристики',
        photos: 'Фотографии',
        src: 'Фотография'
    };

    var HINTS = {
        'site.domain': 'Например https://steel-serves.by — без слэша в конце.',
        'site.phone': 'Только цифры и «+», например +375293355068.',
        'site.phone_display': 'Как телефон выглядит на сайте.',
        'site.email': 'Сюда приходят заявки с формы.',
        'site.unp': 'Если УНП пока нет — оставьте пустым.',
        'site.gtm': 'Необязательно. Например GTM-XXXXXX.',
        'site.metrika': 'Необязательно. Только номер счётчика.',
        'hero.title_top': 'Первый экран. Крупный текст, лучше 1–2 слова.',
        'hero.image': 'Горизонтальное фото. Лучше 1600–2000 px по ширине.',
        'hero.ticker': 'Строка повторяется бегущей строкой.',
        'nav.*.href': 'Якорь: #services, #works, #process, #reviews, #contacts.',
        'services.items.*.short': 'Показывается в узкой вкладке.',
        'services.items.*.badge': 'Например «от 3 дней».',
        'services.items.*.photos': 'Первое фото — обложка. Кнопка «Обложка» переносит фото в начало.',
        'works.items.*.src': 'Горизонтальное фото работы.',
        'stats.*.value': 'Только число, без пробелов и знаков.',
        'stats.*.suffix': 'Например «+» или «%».',
        'privacy_html': 'Можно использовать теги p, h3, ul, li, a, strong. Скрипты удаляются автоматически.'
    };

    function labelFor(path) {
        var normalized = pattern(path);
        if (LABELS[normalized]) {
            return LABELS[normalized];
        }
        var key = path[path.length - 1];
        if (KEY_LABELS[key]) {
            return KEY_LABELS[key];
        }
        return String(key).replace(/_/g, ' ');
    }

    function hintFor(path) {
        return HINTS[pattern(path)] || '';
    }

    function isLongField(path) {
        var key = path[path.length - 1];
        return ['desc', 'text', 'a', 'lead', 'subtitle', 'description', 'tagline', 'success', 'error', 'note', 'callback_text', 'form_text', 'legal_address', 'delivery', 'hours', 'copyright', 'consent'].indexOf(key) !== -1;
    }

    function skipKey(path) {
        var key = path[path.length - 1];
        if (key === 'lang') {
            return true;
        }
        if (path[0] === 'site' && key === 'og_image') {
            return true;
        }
        if (path[0] === 'services' && path[1] === 'items' && path.length === 4) {
            if (['id', 'alt', 'cover_img', 'prefix', 'count', 'cover'].indexOf(key) !== -1) {
                return true;
            }
        }
        return false;
    }

    /* ---------- Контролы ---------- */

    function labelNode(text) {
        return el('span', { class: 'field__label', text: text });
    }

    function wrapField(label, control, hint) {
        var tag = ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(control.tagName) !== -1 ? 'label' : 'div';
        var wrapper = el(tag, { class: 'field' });
        if (label) {
            wrapper.appendChild(labelNode(label));
        }
        wrapper.appendChild(control);
        if (hint) {
            wrapper.appendChild(el('span', { class: 'field__hint', text: hint }));
        }
        return wrapper;
    }

    function textInput(value, onInput) {
        var control = el('input', { class: 'input', type: 'text' });
        control.value = value === null || value === undefined ? '' : String(value);
        control.addEventListener('input', function () {
            onInput(control.value);
        });
        return control;
    }

    function textArea(value, onInput, extraClass) {
        var control = el('textarea', { class: 'textarea' + (extraClass ? ' ' + extraClass : '') });
        control.value = value === null || value === undefined ? '' : String(value);
        control.addEventListener('input', function () {
            onInput(control.value);
        });
        return control;
    }

    function numberInput(value, onInput) {
        var control = el('input', { class: 'input', type: 'number', min: '0', step: '1' });
        control.value = value === null || value === undefined ? '0' : String(value);
        control.addEventListener('input', function () {
            var parsed = parseFloat(control.value);
            onInput(isNaN(parsed) ? 0 : parsed);
        });
        return control;
    }

    function checkbox(value, onInput) {
        var control = el('input', { type: 'checkbox' });
        control.checked = Boolean(value);
        control.addEventListener('change', function () {
            onInput(control.checked);
        });
        return control;
    }

    function textControl(value, path) {
        if (isLongField(path)) {
            return textArea(value, function (next) {
                setPath(path, next);
            });
        }
        return textInput(value, function (next) {
            setPath(path, next);
        });
    }

    function iconSelect(value, path) {
        var options = [
            ['factory', 'Производство'],
            ['clock', 'Сроки'],
            ['steel', 'Сталь'],
            ['shield', 'Гарантия'],
            ['ruler', 'Геометрия'],
            ['sparkle', 'Качество'],
            ['check', 'Галочка'],
            ['truck', 'Доставка'],
            ['wallet', 'Оплата']
        ];
        var select = el('select', { class: 'select' });
        options.forEach(function (option) {
            var node = el('option', { value: option[0], text: option[1] });
            if (option[0] === value) {
                node.selected = true;
            }
            select.appendChild(node);
        });
        select.addEventListener('change', function () {
            setPath(path, select.value);
        });
        return select;
    }

    /* ---------- Изображения ---------- */

    function isImageKey(path) {
        var key = path[path.length - 1];
        if (path[0] === 'hero' && key === 'image') {
            return true;
        }
        if (path[0] === 'works' && key === 'src') {
            return true;
        }
        return false;
    }

    function imageField(value, path, options) {
        options = options || {};
        var wrapper = el('div', { class: 'field' });
        wrapper.appendChild(labelNode(labelFor(path)));

        var row = el('div', { class: 'image-field' });
        var preview = el('div', { class: 'image-field__preview' });
        if (value) {
            preview.appendChild(el('img', { src: imgUrl(value), alt: '', loading: 'lazy' }));
        } else {
            preview.appendChild(el('span', { class: 'image-field__empty', text: 'Нет фото' }));
        }
        row.appendChild(preview);

        var side = el('div', { class: 'image-field__side' });
        var uploadBtn = btn(value ? 'Заменить фото' : 'Загрузить фото', 'mini-btn', function () {
            filePicker(function (file) {
                uploadFile(file, function (response) {
                    setPath(path, response.src);
                    renderEditor();
                }, uploadBtn);
            });
        });
        side.appendChild(uploadBtn);

        if (options.optional && value) {
            side.appendChild(btn('Убрать', 'mini-btn mini-btn--danger', function () {
                setPath(path, '');
                renderEditor();
            }));
        }

        side.appendChild(el('span', {
            class: 'image-field__path',
            text: value || 'Файл не выбран'
        }));
        row.appendChild(side);
        wrapper.appendChild(row);

        var hint = hintFor(path);
        if (hint) {
            wrapper.appendChild(el('span', { class: 'field__hint', text: hint }));
        }
        return wrapper;
    }

    function galleryField(photos, path) {
        var wrapper = el('div', { class: 'field' });
        wrapper.appendChild(labelNode(labelFor(path)));

        var grid = el('div', { class: 'gallery' });
        var parent = getPath(path.slice(0, -1)) || {};
        var altBase = (parent.name ? parent.name + ' — фото' : 'Фото услуги');

        function swap(index, direction) {
            var target = index + direction;
            if (target < 0 || target >= photos.length) {
                return;
            }
            var temp = photos[index];
            photos[index] = photos[target];
            photos[target] = temp;
            markDirty();
            renderEditor();
        }

        photos.forEach(function (photo, index) {
            var card = el('div', { class: 'photo' + (index === 0 ? ' is-cover' : '') });

            if (photo.src) {
                card.appendChild(el('img', {
                    class: 'photo__img',
                    src: imgUrl(photo.src),
                    alt: '',
                    loading: 'lazy'
                }));
                if (index === 0) {
                    card.appendChild(el('span', { class: 'photo__badge', text: 'Обложка' }));
                }
                card.appendChild(btn('✕', 'photo__remove', function () {
                    if (photos.length <= 1) {
                        window.alert('У услуги должно остаться хотя бы одно фото. Сначала загрузите новое.');
                        return;
                    }
                    if (!window.confirm('Удалить это фото из галереи? Сайт обновится после сохранения.')) {
                        return;
                    }
                    photos.splice(index, 1);
                    markDirty();
                    renderEditor();
                }, 'Удалить фото'));
            }

            var body = el('div', { class: 'photo__body' });
            var altInput = el('input', { class: 'photo__alt', type: 'text', placeholder: 'Описание фото' });
            altInput.value = photo.alt || '';
            altInput.addEventListener('input', function () {
                photo.alt = altInput.value;
                markDirty();
            });
            body.appendChild(altInput);

            var actions = el('div', { class: 'photo__actions' });
            if (index > 0) {
                actions.appendChild(btn('Обложка', 'mini-btn', function () {
                    var moved = photos.splice(index, 1)[0];
                    photos.unshift(moved);
                    markDirty();
                    renderEditor();
                }, 'Сделать обложкой'));
            }
            if (index > 0) {
                actions.appendChild(btn('←', 'mini-btn', function () {
                    swap(index, -1);
                }, 'Переместить влево'));
            }
            if (index < photos.length - 1) {
                actions.appendChild(btn('→', 'mini-btn', function () {
                    swap(index, 1);
                }, 'Переместить вправо'));
            }
            body.appendChild(actions);
            card.appendChild(body);
            grid.appendChild(card);
        });

        var add = el('button', { class: 'gallery-add', type: 'button' });
        add.appendChild(el('b', { text: '+' }));
        add.appendChild(el('span', { text: 'Добавить фото' }));
        add.addEventListener('click', function () {
            filePicker(function (file) {
                uploadFile(file, function (response) {
                    photos.push({ src: response.src, alt: altBase });
                    markDirty();
                    renderEditor();
                }, add);
            });
        });
        grid.appendChild(add);

        wrapper.appendChild(grid);

        var hint = hintFor(path);
        if (hint) {
            wrapper.appendChild(el('span', { class: 'field__hint', text: hint }));
        }
        return wrapper;
    }

    /* ---------- Списки ---------- */

    function pairsField(pairs, path) {
        var wrapper = el('div', { class: 'field' });
        wrapper.appendChild(labelNode(labelFor(path)));

        var rows = el('div', { class: 'pairs' });
        pairs.forEach(function (pair, index) {
            if (!Array.isArray(pair)) {
                pair = ['', ''];
                pairs[index] = pair;
            }
            var row = el('div', { class: 'pair' });
            var keyInput = textInput(pair[0] || '', function (value) {
                pair[0] = value;
                markDirty();
            });
            keyInput.setAttribute('placeholder', 'Название');
            keyInput.setAttribute('aria-label', labelFor(path) + ' — название');
            var valueInput = textInput(pair[1] || '', function (value) {
                pair[1] = value;
                markDirty();
            });
            valueInput.setAttribute('placeholder', 'Значение');
            valueInput.setAttribute('aria-label', labelFor(path) + ' — значение');
            row.appendChild(keyInput);
            row.appendChild(valueInput);
            row.appendChild(btn('✕', 'icon-btn', function () {
                pairs.splice(index, 1);
                markDirty();
                renderEditor();
            }, 'Удалить строку'));
            rows.appendChild(row);
        });
        wrapper.appendChild(rows);

        wrapper.appendChild(btn('+ Добавить строку', 'btn-add', function () {
            pairs.push(['', '']);
            markDirty();
            renderEditor();
        }));

        var hint = hintFor(path);
        if (hint) {
            wrapper.appendChild(el('span', { class: 'field__hint', text: hint }));
        }
        return wrapper;
    }

    function stringListField(list, path) {
        var wrapper = el('div', { class: 'field' });
        wrapper.appendChild(labelNode(labelFor(path)));

        var rows = el('div', { class: 'list-rows' });
        list.forEach(function (item, index) {
            var row = el('div', { class: 'list-row' });
            var itemInput = textInput(item, function (value) {
                list[index] = value;
                markDirty();
            });
            itemInput.setAttribute('aria-label', labelFor(path) + ' — пункт ' + (index + 1));
            row.appendChild(itemInput);
            row.appendChild(btn('✕', 'icon-btn', function () {
                list.splice(index, 1);
                markDirty();
                renderEditor();
            }, 'Удалить пункт'));
            rows.appendChild(row);
        });
        wrapper.appendChild(rows);

        wrapper.appendChild(btn('+ Добавить пункт', 'btn-add', function () {
            list.push('');
            markDirty();
            renderEditor();
        }));

        var hint = hintFor(path);
        if (hint) {
            wrapper.appendChild(el('span', { class: 'field__hint', text: hint }));
        }
        return wrapper;
    }

    function itemTitle(item, path, index) {
        if (path[0] === 'stats') {
            return String(item.value === undefined ? 0 : item.value) + (item.suffix || '') + ' ' + (item.label || '');
        }
        if (path[0] === 'works') {
            return item.caption || ('Работа ' + (index + 1));
        }
        return item.name || item.title || item.q || item.label || ('Элемент ' + (index + 1));
    }

    function addLabel(path) {
        var key = path[0];
        var map = {
            nav: 'Добавить пункт',
            advantages: 'Добавить преимущество',
            services: 'Добавить услугу',
            process: 'Добавить этап',
            stats: 'Добавить цифру',
            works: 'Добавить работу',
            reviews: 'Добавить отзыв',
            faq: 'Добавить вопрос'
        };
        return map[key] || 'Добавить элемент';
    }

    function templateFor(path) {
        var key = path[0];
        if (key === 'nav') {
            return { label: 'Новый пункт', href: '#' };
        }
        if (key === 'advantages') {
            return { icon: 'check', title: 'Новое преимущество', text: '' };
        }
        if (key === 'services') {
            return {
                id: '',
                name: 'Новая услуга',
                short: 'Новая',
                desc: '',
                alt: '',
                specs: [['Высота', 'от 900 мм'], ['Сталь', '????? 304']],
                photos: [],
                cover_img: '',
                badge: '',
                cta: 'Рассчитать'
            };
        }
        if (key === 'process') {
            return { title: 'Новый этап', text: '' };
        }
        if (key === 'stats') {
            return { value: 0, suffix: '', label: '', text: '' };
        }
        if (key === 'works') {
            return { src: '', alt: '', caption: '' };
        }
        if (key === 'reviews') {
            return { name: '', role: '', text: '' };
        }
        if (key === 'faq') {
            return { q: 'Новый вопрос', a: '' };
        }
        return {};
    }

    function objectListField(items, path) {
        var container = el('div', { class: 'object-list' });
        var list = el('div');

        items.forEach(function (item, index) {
            var itemPath = path.concat([index]);
            var details = el('details', { class: 'item-card', 'data-path': itemPath.join('.') });
            var defaultOpen = ['services', 'works', 'reviews'].indexOf(path[0]) === -1;
            var savedOpen = renderOpenState[itemPath.join('.')];
            if (defaultOpen || savedOpen === true || pendingOpen === itemPath.join('.')) {
                details.open = true;
            }

            var summary = el('summary', { class: 'item-card__head' });
            var title = el('span', { class: 'item-card__title', text: itemTitle(item, path, index) });
            summary.appendChild(title);

            var metaText = '';
            if (path[0] === 'services') {
                metaText = (Array.isArray(item.photos) ? item.photos.length : 0) + ' фото';
            } else if (path[0] === 'nav') {
                metaText = item.href || '';
            } else if (path[0] === 'reviews') {
                metaText = item.role || '';
            }
            if (metaText) {
                summary.appendChild(el('span', { class: 'item-card__meta', text: metaText }));
            }

            var actions = el('div', { class: 'item-card__actions' });
            actions.appendChild(btn('↑', 'icon-btn', function () {
                if (index === 0) {
                    return;
                }
                var moved = items.splice(index, 1)[0];
                items.splice(index - 1, 0, moved);
                markDirty();
                pendingOpen = path.concat([index - 1]).join('.');
                renderEditor();
            }, 'Переместить выше'));

            actions.appendChild(btn('↓', 'icon-btn', function () {
                if (index === items.length - 1) {
                    return;
                }
                var moved = items.splice(index, 1)[0];
                items.splice(index + 1, 0, moved);
                markDirty();
                pendingOpen = path.concat([index + 1]).join('.');
                renderEditor();
            }, 'Переместить ниже'));

            actions.appendChild(btn('✕', 'icon-btn', function () {
                if (!window.confirm('Удалить «' + itemTitle(item, path, index) + '»? Сайт обновится после сохранения.')) {
                    return;
                }
                items.splice(index, 1);
                markDirty();
                renderEditor();
            }, 'Удалить'));

            details.appendChild(summary);
            details.appendChild(actions);

            var body = el('div', { class: 'item-card__body' });
            if (item && typeof item === 'object' && !Array.isArray(item)) {
                body.appendChild(renderObject(item, itemPath));
            }
            details.appendChild(body);
            list.appendChild(details);
        });

        container.appendChild(list);
        container.appendChild(btn('+ ' + addLabel(path), 'btn-add', function () {
            var item = templateFor(path);
            items.push(item);
            markDirty();
            pendingOpen = path.concat([items.length - 1]).join('.');
            renderEditor();
        }));
        return container;
    }

    /* ---------- Рендер полей ---------- */

    function renderField(key, value, parentPath) {
        var path = parentPath.concat([key]);
        if (skipKey(path)) {
            return null;
        }

        if (key === 'photos' && Array.isArray(value)) {
            return galleryField(value, path);
        }
        if (key === 'specs' && Array.isArray(value)) {
            return pairsField(value, path);
        }
        if (isImageKey(path)) {
            return imageField(typeof value === 'string' ? value : '', path);
        }
        if (key === 'icon') {
            return wrapField(labelFor(path), iconSelect(value, path), hintFor(path));
        }

        if (Array.isArray(value)) {
            if (value.length === 0) {
                if (key === 'items' || key === 'steps') {
                    return wrapField('', objectListField(value, path), hintFor(path));
                }
                if (key === 'bullets' || key === 'badges') {
                    return stringListField(value, path);
                }
                return stringListField(value, path);
            }
            if (typeof value[0] === 'string') {
                return stringListField(value, path);
            }
            if (Array.isArray(value[0])) {
                return pairsField(value, path);
            }
            if (key === 'items' || key === 'steps') {
                return wrapField('', objectListField(value, path), hintFor(path));
            }
            return objectListField(value, path);
        }

        if (value && typeof value === 'object') {
            var card = el('div', { class: 'subcard' });
            var head = el('div', { class: 'subcard__head' });
            head.appendChild(el('span', { class: 'subcard__title', text: labelFor(path) }));
            card.appendChild(head);
            card.appendChild(renderObject(value, path));
            return card;
        }

        if (typeof value === 'number') {
            return wrapField(labelFor(path), numberInput(value, function (next) {
                setPath(path, next);
            }), hintFor(path));
        }

        if (typeof value === 'boolean') {
            return wrapField(labelFor(path), checkbox(value, function (next) {
                setPath(path, next);
            }), hintFor(path));
        }

        return wrapField(labelFor(path), textControl(value, path), hintFor(path));
    }

    function renderObject(object, path) {
        var container = el('div');
        Object.keys(object).forEach(function (key) {
            var field = renderField(key, object[key], path);
            if (field) {
                container.appendChild(field);
            }
        });
        return container;
    }

    function listBlock(label, list, path) {
        var wrapper = el('div', { class: 'field' });
        wrapper.appendChild(labelNode(label));
        wrapper.appendChild(objectListField(list, path));
        return wrapper;
    }

    /* ---------- Группы ---------- */

    var SECTIONS = [
        { key: 'site', title: 'Сайт и контакты', desc: 'Телефоны, почта, реквизиты и счётчики аналитики.' },
        { key: 'hero', title: 'Первый экран', desc: 'Заголовок, фото, кнопка и бегущая строка.' },
        { key: 'nav', title: 'Меню', desc: 'Пункты верхнего меню.' },
        { key: 'advantages', title: 'Преимущества', desc: 'Блок «Почему нас выбирают».' },
        { key: 'services', title: 'Услуги', desc: 'Услуги, характеристики и фотографии.' },
        { key: 'process', title: 'Как работаем', desc: 'Этапы работы.' },
        { key: 'stats', title: 'Цифры', desc: 'Счётчики с анимацией.' },
        { key: 'works', title: 'Наши работы', desc: 'Галерея выполненных работ.' },
        { key: 'reviews', title: 'Отзывы', desc: 'Отзывы и сканы благодарностей.' },
        { key: 'faq', title: 'Вопросы и ответы', desc: 'Частые вопросы.' },
        { key: 'contacts', title: 'Контакты', desc: 'Заголовок и тексты блока контактов.' },
        { key: 'forms', title: 'Тексты форм', desc: 'Подписи полей и сообщения формы.' },
        { key: 'privacy_html', title: 'Политика конфиденциальности', desc: 'Текст документа.' },
        { key: 'footer', title: 'Подвал', desc: 'Подписи и описание в подвале.' }
    ];

    function groupHint(key) {
        var value = state[key];
        if (key === 'nav') {
            return (value || []).length + ' пунктов';
        }
        if (key === 'stats') {
            return (value || []).length + ' цифры';
        }
        if (key === 'services') {
            return ((value && value.items) || []).length + ' услуг';
        }
        if (key === 'works') {
            return ((value && value.items) || []).length + ' работ';
        }
        if (key === 'reviews') {
            return ((value && value.items) || []).length + ' отзыва';
        }
        if (key === 'faq') {
            return ((value && value.items) || []).length + ' вопросов';
        }
        if (key === 'advantages') {
            return ((value && value.items) || []).length + ' преимуществ';
        }
        if (key === 'process') {
            return ((value && value.steps) || []).length + ' этапов';
        }
        return '';
    }

    function renderEditor() {
        if (!editor) {
            return;
        }

        var openState = {};
        $$('details[data-path]', editor).forEach(function (node) {
            openState[node.dataset.path] = node.open;
        });
        renderOpenState = openState;
        var scroll = window.scrollY;

        editor.textContent = '';

        SECTIONS.forEach(function (section, index) {
            if (!(section.key in state)) {
                return;
            }

            var details = el('details', {
                class: 'group',
                'data-path': section.key,
                title: section.desc
            });
            var savedOpen = openState[section.key];
            if (savedOpen === true || (savedOpen === undefined && index < 2)) {
                details.open = true;
            }

            var summary = el('summary');
            summary.appendChild(el('span', {
                class: 'group__num',
                text: String(index + 1).padStart(2, '0')
            }));
            summary.appendChild(el('span', { class: 'group__title', text: section.title }));
            var hint = groupHint(section.key);
            if (hint) {
                summary.appendChild(el('span', { class: 'group__hint', text: hint }));
            }
            details.appendChild(summary);

            var body = el('div', { class: 'group__body' });

            if (section.key === 'privacy_html') {
                var privacy = el('div', { class: 'field' });
                privacy.appendChild(labelNode('Текст политики'));
                var privacyArea = textArea(state.privacy_html, function (value) {
                    state.privacy_html = value;
                    markDirty();
                }, 'textarea--tall');
                privacyArea.setAttribute('aria-label', 'Текст политики конфиденциальности');
                privacy.appendChild(privacyArea);
                privacy.appendChild(el('span', {
                    class: 'field__hint',
                    text: hintFor(['privacy_html'])
                }));
                body.appendChild(privacy);
            } else if (Array.isArray(state[section.key])) {
                var listName = section.key === 'nav' ? 'Пункты меню' : 'Цифры';
                body.appendChild(listBlock(listName, state[section.key], [section.key]));
            } else {
                body.appendChild(renderObject(state[section.key], [section.key]));
            }

            details.appendChild(body);
            editor.appendChild(details);
        });

        if (pendingOpen) {
            var prefix = pendingOpen;
            $$('details[data-path]', editor).forEach(function (node) {
                if (node.dataset.path === prefix || prefix.indexOf(node.dataset.path + '.') === 0) {
                    node.open = true;
                }
            });
            pendingOpen = '';
        }

        window.scrollTo(0, scroll);
        syncSaveUi();
    }

    /* ---------- Сохранение ---------- */

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            if (!dirty || saveBtn.classList.contains('is-busy')) {
                return;
            }
            saveBtn.classList.add('is-busy');
            if (saveStateEl) {
                saveStateEl.textContent = 'Сохраняем…';
                saveStateEl.classList.remove('is-error');
            }

            api('action.php?do=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content: state })
            }).then(function (response) {
                saveBtn.classList.remove('is-busy');
                if (!response.ok) {
                    if (saveStateEl) {
                        saveStateEl.textContent = 'Не сохранено';
                        saveStateEl.classList.add('is-error');
                    }
                    toast(response.message || 'Не удалось сохранить изменения.', true);
                    return;
                }
                dirty = false;
                visualChanged = false;
                syncSaveUi();
                toast(response.message || 'Сохранено. Сайт обновлён.');
                if (visualLoaded) {
                    postToVisual({ type: 'bm-saved' });
                }
            }).catch(function () {
                saveBtn.classList.remove('is-busy');
                if (saveStateEl) {
                    saveStateEl.textContent = 'Нет связи';
                    saveStateEl.classList.add('is-error');
                }
                toast('Сеть недоступна. Изменения не сохранены.', true);
            });
        });
    }

    window.addEventListener('beforeunload', function (event) {
        if (!dirty) {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    });

    /* ---------- Навигация ---------- */

    var META = {
        content: ['Контент', 'Тексты, фотографии и цены. После правок нажмите «Сохранить».'],
        visual: ['Визуальный редактор', 'Кликните по тексту или фото прямо на странице. Затем нажмите «Сохранить».'],
        leads: ['Заявки', 'Последние заявки с сайта и выгрузка в Excel.'],
        backups: ['Резервные копии', 'Верните сайт к предыдущей версии, если что-то пошло не так.'],
        settings: ['Настройки', 'Почта отправителя, пароль и экспорт контента.'],
        help: ['Помощь', 'Короткая инструкция по работе с админкой.']
    };

    function switchView(view) {
        $$('[data-view]').forEach(function (button) {
            button.classList.toggle('is-active', button.dataset.view === view);
        });
        $$('[data-view-panel]').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.dataset.viewPanel === view);
        });

        var meta = META[view] || META.content;
        var title = $('[data-page-title]');
        var subtitle = $('[data-page-sub]');
        if (title) {
            title.textContent = meta[0];
        }
        if (subtitle) {
            subtitle.textContent = meta[1];
        }

        var actions = $('.topbar__actions');
        if (actions) {
            actions.hidden = view !== 'content' && view !== 'visual';
        }

        document.body.classList.toggle('is-visual', view === 'visual');

        if (view === 'visual' && visualFrame && !visualLoaded) {
            visualLoaded = true;
            visualFrame.src = '../preview.php?t=' + Date.now();
        }

        if (view === 'content' && visualChanged) {
            visualChanged = false;
            renderEditor();
        }

        if (view === 'backups') {
            refreshBackups();
        }

        window.scrollTo(0, 0);
    }

    $$('[data-view]').forEach(function (button) {
        button.addEventListener('click', function () {
            switchView(button.dataset.view);
        });
    });

    /* ---------- Настройки ---------- */

    var settingsForm = $('[data-settings-form]');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var status = $('[data-settings-status]');
            var val = function (name) {
                var el = settingsForm.querySelector('[name="' + name + '"]');
                return el ? el.value.trim() : '';
            };
            var mailFrom = val('mail_from');
            var leadEmail = val('lead_email');
            var payload = {
                mail_from: mailFrom,
                lead_email: leadEmail,
                smtp_host: val('smtp_host'),
                smtp_port: val('smtp_port'),
                smtp_secure: val('smtp_secure'),
                smtp_user: val('smtp_user'),
                smtp_pass: val('smtp_pass')
            };
            if (status) {
                status.className = 'form-note';
                status.textContent = 'Сохраняем…';
            }

            api('action.php?do=settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function (response) {
                if (!status) {
                    return;
                }
                status.textContent = response.message || '';
                status.className = 'form-note ' + (response.ok ? 'is-success' : 'is-error');
                if (response.ok) {
                    var meta = document.querySelector('meta[name="mail-from"]');
                    if (meta) {
                        meta.setAttribute('content', mailFrom);
                    }
                    var leadMeta = document.querySelector('meta[name="lead-to"]');
                    if (leadMeta) {
                        leadMeta.setAttribute('content', leadEmail);
                    }
                    var passField = settingsForm.querySelector('[name="smtp_pass"]');
                    if (passField) {
                        passField.value = '';
                    }
                }
            }).catch(function () {
                if (status) {
                    status.textContent = 'Нет связи с сервером.';
                    status.className = 'form-note is-error';
                }
            });
        });
    }

    var testMailBtn = $('[data-test-mail]');
    if (testMailBtn) {
        testMailBtn.addEventListener('click', function () {
            var status = $('[data-settings-status]');
            if (status) {
                status.className = 'form-note';
                status.textContent = 'Проверяем почту…';
            }
            api('action.php?do=testmail', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: '{}'
            }).then(function (response) {
                if (!status) {
                    return;
                }
                status.textContent = response.message || '';
                status.className = 'form-note ' + (response.ok ? 'is-success' : 'is-error');
            }).catch(function () {
                if (status) {
                    status.textContent = 'Нет связи с сервером.';
                    status.className = 'form-note is-error';
                }
            });
        });
    }

    var passwordForm = $('[data-password-form]');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var status = $('[data-password-status]');
            var body = {
                current_password: passwordForm.querySelector('[name="current_password"]').value,
                new_password: passwordForm.querySelector('[name="new_password"]').value,
                new_password2: passwordForm.querySelector('[name="new_password2"]').value
            };
            if (status) {
                status.className = 'form-note';
                status.textContent = 'Проверяем…';
            }

            api('action.php?do=password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            }).then(function (response) {
                if (status) {
                    status.textContent = response.message || '';
                    status.className = 'form-note ' + (response.ok ? 'is-success' : 'is-error');
                }
                if (response.ok) {
                    passwordForm.reset();
                    if (response.csrf) {
                        csrf = response.csrf;
                        if (csrfMeta) {
                            csrfMeta.setAttribute('content', csrf);
                        }
                    }
                    toast('Пароль изменён.');
                }
            }).catch(function () {
                if (status) {
                    status.textContent = 'Нет связи с сервером.';
                    status.className = 'form-note is-error';
                }
            });
        });
    }

    /* ---------- Восстановление копий ---------- */

    function bindRestore(scope) {
        $$('[data-restore]', scope || document).forEach(function (button) {
            if (button.dataset.bound) {
                return;
            }
            button.dataset.bound = '1';
            button.addEventListener('click', function () {
                var file = button.dataset.restore;
                if (!file) {
                    return;
                }
                if (!window.confirm('Восстановить копию от ' + file.replace(/^content-/, '').replace(/\.json$/, '').replace('-', ' ') + '?\nТекущая версия тоже сохранится в резервных копиях.')) {
                    return;
                }
                button.disabled = true;
                button.textContent = 'Восстанавливаем…';

                api('action.php?do=restore', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ file: file })
                }).then(function (response) {
                    if (!response.ok) {
                        button.disabled = false;
                        button.textContent = 'Восстановить';
                        toast(response.message || 'Не удалось восстановить копию.', true);
                        return;
                    }
                    toast(response.message || 'Копия восстановлена. Обновляем страницу…');
                    setTimeout(function () {
                        window.location.reload();
                    }, 800);
                }).catch(function () {
                    button.disabled = false;
                    button.textContent = 'Восстановить';
                    toast('Нет связи с сервером.', true);
                });
            });
        });
    }

    function refreshBackups() {
        var holder = $('[data-backups-list]');
        if (!holder) {
            return;
        }
        fetch('index.php?t=' + Date.now(), { credentials: 'same-origin' })
            .then(function (response) {
                return response.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.querySelector('[data-backups-list]');
                if (fresh) {
                    holder.innerHTML = fresh.innerHTML;
                    bindRestore(holder);
                }
            })
            .catch(function () {
                /* остаёмся с тем, что уже показано */
            });
    }

    bindRestore(document);

    /* ---------- Инструменты редактора ---------- */

    var expandAll = $('[data-expand-all]');
    var collapseAll = $('[data-collapse-all]');
    if (expandAll) {
        expandAll.addEventListener('click', function () {
            $$('details', editor).forEach(function (node) {
                node.open = true;
            });
        });
    }
    if (collapseAll) {
        collapseAll.addEventListener('click', function () {
            $$('details', editor).forEach(function (node) {
                node.open = false;
            });
        });
    }

    /* ---------- Визуальный редактор ---------- */

    function postToVisual(message) {
        if (!visualFrame || !visualFrame.contentWindow) {
            return;
        }
        try {
            visualFrame.contentWindow.postMessage(message, window.location.origin);
        } catch (error) {
            /* ignore */
        }
    }

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin) {
            return;
        }
        var data = event.data || {};

        if (data.type === 'bm-ready') {
            postToVisual({ type: 'bm-state', state: state });
            return;
        }

        if (data.type === 'bm-set' && data.path) {
            var value = data.value;
            if (typeof value === 'string' && /\.value$/.test(data.path) && /^stats\./.test(data.path)) {
                value = parseInt(value.replace(/[^\d-]/g, ''), 10) || 0;
            }
            if (getPath(data.path.split('.')) === value) {
                return;
            }
            setPath(data.path.split('.'), value);
            visualChanged = true;
            return;
        }

        if ((data.type === 'bm-image-file' || data.type === 'bm-image') && data.path) {
            var handleFile = function (file) {
                uploadFile(file, function (response) {
                    setPath(data.path.split('.'), response.src);
                    if (data.altPath) {
                        var oldAlt = getPath(data.altPath.split('.')) || '';
                        setPath(data.altPath.split('.'), oldAlt);
                    }
                    visualChanged = true;
                    postToVisual({
                        type: 'bm-image-set',
                        path: data.path,
                        src: response.src,
                        alt: data.altPath ? (getPath(data.altPath.split('.')) || '') : ''
                    });
                });
            };
            if (data.file) {
                handleFile(data.file);
            } else {
                filePicker(handleFile);
            }
        }
    });

    if (visualFrame) {
        visualFrame.addEventListener('load', function () {
            if (visualFrame.getAttribute('src') !== 'about:blank') {
                postToVisual({ type: 'bm-state', state: state });
            }
        });
    }

    /* ---------- Старт ---------- */

    renderEditor();
})();
