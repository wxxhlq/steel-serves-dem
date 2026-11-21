/* =========================================================
   БАЗА-МЕТАЛЛА — site interactions
   ========================================================= */
(function () {
    'use strict';

    var root = document.documentElement;
    root.classList.add('js');

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var finePointer = window.matchMedia('(pointer: fine)').matches;

    function $(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    function $$(sel, ctx) {
        return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
    }

    /* ---------- Header: scroll state + progress ---------- */

    var header = $('[data-header]');
    var progress = $('[data-progress]');
    var ticking = false;

    function onScroll() {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(function () {
            var y = window.scrollY || window.pageYOffset;
            if (header) header.classList.toggle('is-scrolled', y > 20);
            if (progress) {
                var max = document.documentElement.scrollHeight - window.innerHeight;
                progress.style.width = (max > 0 ? Math.min(100, (y / max) * 100) : 0) + '%';
            }
            ticking = false;
        });
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* ---------- Hero: интерактивный показ монтажей ---------- */

    function initHeroCarousel() {
        var carousel = $('[data-carousel]');
        if (!carousel) return;
        var slides = $$('[data-slide]', carousel);
        if (slides.length <= 1) return;

        var prev = $('[data-carousel-prev]', carousel);
        var next = $('[data-carousel-next]', carousel);
        var dots = $$('[data-carousel-go]', carousel);
        var index = 0;
        var timer = null;
        var pointerStartX = null;

        function show(nextIndex, userAction) {
            index = (nextIndex + slides.length) % slides.length;
            slides.forEach(function (slide, slideIndex) {
                var active = slideIndex === index;
                slide.classList.toggle('is-active', active);
                slide.setAttribute('aria-hidden', active ? 'false' : 'true');
            });
            dots.forEach(function (dot, dotIndex) {
                var active = dotIndex === index;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-current', active ? 'true' : 'false');
            });
            if (userAction) restartAutoplay();
        }

        function stopAutoplay() {
            if (timer) window.clearInterval(timer);
            timer = null;
        }

        function startAutoplay() {
            stopAutoplay();
            if (reduceMotion || document.hidden) return;
            timer = window.setInterval(function () {
                show(index + 1, false);
            }, 6200);
        }

        function restartAutoplay() {
            stopAutoplay();
            startAutoplay();
        }

        if (prev) prev.addEventListener('click', function () { show(index - 1, true); });
        if (next) next.addEventListener('click', function () { show(index + 1, true); });
        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                show(parseInt(dot.getAttribute('data-carousel-go') || '0', 10), true);
            });
        });

        carousel.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                show(index - 1, true);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                show(index + 1, true);
            }
        });

        carousel.addEventListener('pointerdown', function (event) {
            if (event.target.closest('button')) return;
            pointerStartX = event.clientX;
        }, { passive: true });
        carousel.addEventListener('pointerup', function (event) {
            if (pointerStartX === null) return;
            var delta = event.clientX - pointerStartX;
            pointerStartX = null;
            if (Math.abs(delta) < 48) return;
            show(delta < 0 ? index + 1 : index - 1, true);
        }, { passive: true });
        carousel.addEventListener('pointercancel', function () { pointerStartX = null; }, { passive: true });

        carousel.addEventListener('mouseenter', stopAutoplay);
        carousel.addEventListener('mouseleave', startAutoplay);
        carousel.addEventListener('focusin', stopAutoplay);
        carousel.addEventListener('focusout', startAutoplay);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stopAutoplay();
            else startAutoplay();
        });

        startAutoplay();
    }

    initHeroCarousel();

    /* ---------- Dialogs ---------- */

    var openDialogs = 0;

    function lockBody() {
        openDialogs++;
        document.body.classList.add('is-locked');
    }

    function unlockBody() {
        openDialogs = Math.max(0, openDialogs - 1);
        if (openDialogs === 0) document.body.classList.remove('is-locked');
    }

    function openDialog(dialog) {
        if (!dialog || typeof dialog.showModal !== 'function' || dialog.open) return;
        dialog.showModal();
        lockBody();
    }

    function closeDialog(dialog) {
        if (dialog && dialog.open) dialog.close();
    }

    $$('dialog').forEach(function (dialog) {
        dialog.addEventListener('close', unlockBody);
        dialog.addEventListener('click', function (e) {
            if (e.target === dialog && dialog.classList.contains('modal')) {
                dialog.close();
            }
        });
        $$('[data-close]', dialog).forEach(function (btn) {
            btn.addEventListener('click', function () {
                dialog.close();
            });
        });
    });

    /* ---------- Mobile menu ---------- */

    var menu = $('#mobile-menu');
    var burger = $('[data-burger]');

    if (menu && burger) {
        burger.addEventListener('click', function () {
            burger.setAttribute('aria-expanded', 'true');
            openDialog(menu);
        });

        $$('[data-menu-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                closeDialog(menu);
            });
        });

        menu.addEventListener('close', function () {
            burger.setAttribute('aria-expanded', 'false');
        });

        $$('[data-menu-link]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var href = link.getAttribute('href') || '';
                if (href.charAt(0) !== '#') return;
                e.preventDefault();
                closeDialog(menu);
                var target = document.querySelector(href);
                if (target) {
                    window.setTimeout(function () {
                        target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
                    }, 80);
                }
            });
        });
    }

    /* ---------- Lead modal ---------- */

    var leadModal = $('#lead-modal');
    var privacyModal = $('#privacy-modal');
    var lastLeadSource = 'cta';

    $$('[data-open-lead]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            lastLeadSource = btn.getAttribute('data-source') || 'cta';
            if (leadModal) {
                var form = $('[data-lead-form]', leadModal);
                if (form) form.setAttribute('data-source', lastLeadSource);
                openDialog(leadModal);
            }
        });
    });

    $$('[data-open-privacy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openDialog(privacyModal);
        });
    });

    if (leadModal) {
        leadModal.addEventListener('close', function () {
            var form = $('[data-lead-form]', leadModal);
            if (form) {
                form.reset();
                form.classList.remove('is-sent');
                $$('.field__error', form).forEach(function (el) { el.textContent = ''; });
                $$('.field__input', form).forEach(function (el) { el.removeAttribute('aria-invalid'); });
                var status = $('[data-form-status]', form);
                if (status) { status.textContent = ''; status.className = 'form-status'; }
                var btn = $('button[type="submit"]', form);
                if (btn) btn.disabled = false;
            }
        });
    }

    /* ---------- Reveal on scroll ---------- */

    var revealItems = $$('[data-reveal]');
    if ('IntersectionObserver' in window && !reduceMotion) {
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -6% 0px' });

        revealItems.forEach(function (el) { revealObserver.observe(el); });
    } else {
        revealItems.forEach(function (el) { el.classList.add('is-in'); });
    }

    /* ---------- Active nav link ---------- */

    var navLinks = $$('.nav__link');
    if (navLinks.length && 'IntersectionObserver' in window) {
        var sectionMap = {};
        navLinks.forEach(function (link) {
            var href = link.getAttribute('href');
            if (href && href.charAt(0) === '#') {
                var section = document.querySelector(href);
                if (section) sectionMap[section.id] = link;
            }
        });

        var navObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                navLinks.forEach(function (l) { l.classList.remove('is-active'); });
                var link = sectionMap[entry.target.id];
                if (link) link.classList.add('is-active');
            });
        }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });

        Object.keys(sectionMap).forEach(function (id) {
            navObserver.observe(document.getElementById(id));
        });
    }

    /* ---------- Services tabs ---------- */

    var servicesRoot = $('[data-services]');

    if (servicesRoot) {
        var tabs = $$('[role="tab"]', servicesRoot);
        var panels = $$('[data-panel]', servicesRoot);

        function activateTab(index, focus) {
            if (index < 0) index = tabs.length - 1;
            if (index >= tabs.length) index = 0;

            tabs.forEach(function (tab, i) {
                var active = i === index;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.setAttribute('tabindex', active ? '0' : '-1');
            });

            panels.forEach(function (panel, i) {
                var active = i === index;
                panel.classList.toggle('is-active', active);
                if (active) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', '');
                }
            });

            if (focus) tabs[index].focus();
        }

        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () {
                activateTab(i, false);
                if (tab.hasAttribute('data-scroll') && window.matchMedia('(max-width: 900px)').matches) {
                    var panel = document.getElementById('panel-' + (tab.getAttribute('id') || '0'));
                    if (panel) {
                        window.scrollTo({
                            top: panel.getBoundingClientRect().top + window.scrollY - 96,
                            behavior: reduceMotion ? 'auto' : 'smooth'
                        });
                    }
                }
            });

            tab.addEventListener('keydown', function (e) {
                var key = e.key;
                if (key === 'ArrowDown' || key === 'ArrowRight') {
                    e.preventDefault();
                    activateTab(i + 1, true);
                } else if (key === 'ArrowUp' || key === 'ArrowLeft') {
                    e.preventDefault();
                    activateTab(i - 1, true);
                } else if (key === 'Home') {
                    e.preventDefault();
                    activateTab(0, true);
                } else if (key === 'End') {
                    e.preventDefault();
                    activateTab(tabs.length - 1, true);
                }
            });
        });

        /* thumbs */
        servicesRoot.addEventListener('click', function (e) {
            var thumb = e.target.closest('[data-thumb]');
            if (!thumb) return;
            var panel = thumb.closest('[data-panel]');
            if (!panel) return;
            var img = $('[data-main-img]', panel);
            if (!img) return;

            $$('[data-thumb]', panel).forEach(function (t) {
                t.classList.toggle('is-active', t === thumb);
            });

            var src = thumb.getAttribute('data-src');
            var srcset = thumb.getAttribute('data-srcset');
            var alt = thumb.getAttribute('data-alt') || '';

            img.classList.add('is-switching');
            var preload = new Image();
            preload.onload = function () {
                img.src = src;
                if (srcset) img.srcset = srcset;
                img.alt = alt;
                window.requestAnimationFrame(function () {
                    img.classList.remove('is-switching');
                });
            };
            preload.onerror = function () {
                img.classList.remove('is-switching');
            };
            preload.src = src;
        });
    }

    /* ---------- Works scroller ---------- */

    var scroller = $('[data-scroller]');
    var scrollPrev = $('[data-scroll-prev]');
    var scrollNext = $('[data-scroll-next]');

    if (scroller) {
        var cards = $$('.work', scroller);

        function step() {
            if (!cards.length) return 320;
            var style = window.getComputedStyle(scroller.querySelector('.works__track'));
            var gap = parseFloat(style.columnGap || style.gap || 16) || 16;
            return cards[0].getBoundingClientRect().width + gap;
        }

        function updateArrows() {
            var max = scroller.scrollWidth - scroller.clientWidth - 2;
            if (scrollPrev) scrollPrev.disabled = scroller.scrollLeft <= 2;
            if (scrollNext) scrollNext.disabled = scroller.scrollLeft >= max;
        }

        if (scrollPrev) {
            scrollPrev.addEventListener('click', function () {
                scroller.scrollBy({ left: -step(), behavior: reduceMotion ? 'auto' : 'smooth' });
            });
        }

        if (scrollNext) {
            scrollNext.addEventListener('click', function () {
                scroller.scrollBy({ left: step(), behavior: reduceMotion ? 'auto' : 'smooth' });
            });
        }

        scroller.addEventListener('scroll', updateArrows, { passive: true });
        window.addEventListener('resize', updateArrows);
        updateArrows();
    }

    /* ---------- Count up ---------- */

    var counters = $$('[data-count]');
    if (counters.length) {
        var runCounter = function (el) {
            var target = parseInt(el.getAttribute('data-count'), 10) || 0;
            if (reduceMotion) {
                el.textContent = String(target);
                return;
            }
            var start = null;
            var duration = 1500;
            function frame(ts) {
                if (start === null) start = ts;
                var p = Math.min(1, (ts - start) / duration);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = String(Math.round(target * eased));
                if (p < 1) window.requestAnimationFrame(frame);
            }
            window.requestAnimationFrame(frame);
        };

        if ('IntersectionObserver' in window) {
            var counterObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        runCounter(entry.target);
                        counterObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.6 });
            counters.forEach(function (el) { counterObserver.observe(el); });
        } else {
            counters.forEach(runCounter);
        }
    }

    /* ---------- Process steps ---------- */

    var steps = $$('[data-step]');
    if (steps.length && 'IntersectionObserver' in window && !reduceMotion) {
        var stepObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                entry.target.classList.toggle('is-active', entry.isIntersecting);
            });
        }, { rootMargin: '-42% 0px -42% 0px', threshold: 0 });
        steps.forEach(function (step) { stepObserver.observe(step); });
    }

    /* ---------- Accordion ---------- */

    var accRoot = $('[data-accordion]');
    if (accRoot) {
        var timeout = reduceMotion ? 0 : 460;

        function closeAcc(btn, panel, instant) {
            if (btn.getAttribute('aria-expanded') !== 'true') return;
            btn.setAttribute('aria-expanded', 'false');
            if (instant) {
                panel.hidden = true;
                panel.style.maxHeight = '';
                return;
            }
            panel.style.maxHeight = panel.scrollHeight + 'px';
            window.requestAnimationFrame(function () {
                panel.style.maxHeight = '0px';
            });
            window.setTimeout(function () {
                panel.hidden = true;
                panel.style.maxHeight = '';
            }, timeout);
        }

        function openAcc(btn, panel) {
            panel.hidden = false;
            panel.style.maxHeight = '0px';
            btn.setAttribute('aria-expanded', 'true');
            window.requestAnimationFrame(function () {
                panel.style.maxHeight = panel.scrollHeight + 'px';
            });
            window.setTimeout(function () {
                if (btn.getAttribute('aria-expanded') === 'true') panel.style.maxHeight = 'none';
            }, timeout + 20);
        }

        $$('[data-acc]', accRoot).forEach(function (acc) {
            var btn = $('.acc__btn', acc);
            var panel = $('.acc__panel', acc);
            if (!btn || !panel) return;

            btn.addEventListener('click', function () {
                var isOpen = btn.getAttribute('aria-expanded') === 'true';
                $$('[data-acc] .acc__btn[aria-expanded="true"]', accRoot).forEach(function (other) {
                    if (other !== btn) {
                        closeAcc(other, other.closest('[data-acc]').querySelector('.acc__panel'));
                    }
                });
                if (isOpen) {
                    closeAcc(btn, panel);
                } else {
                    openAcc(btn, panel);
                }
            });
        });
    }

    /* ---------- Lightbox ---------- */

    var lightbox = $('#lightbox');
    var lbImg = $('[data-lightbox-img]');
    var lbCaption = $('[data-lightbox-caption]');
    var lbCounter = $('[data-lightbox-counter]');
    var lbItems = [];
    var lbIndex = 0;

    function renderLightbox() {
        if (!lbItems.length || !lbImg) return;
        var item = lbItems[lbIndex];
        lbImg.src = item.src;
        lbImg.alt = item.alt || '';
        if (lbCaption) lbCaption.textContent = item.alt || '';
        if (lbCounter) lbCounter.textContent = (lbIndex + 1) + ' / ' + lbItems.length;
    }

    function lbGo(delta) {
        if (!lbItems.length) return;
        lbIndex = (lbIndex + delta + lbItems.length) % lbItems.length;
        renderLightbox();
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-lightbox-src]');
        if (!trigger) return;
        e.preventDefault();

        var panel = trigger.closest('[data-panel]');
        if (panel) {
            var thumbs = $$('[data-thumb]', panel);
            var main = $('[data-main-img]', panel);
            lbItems = thumbs.map(function (t) {
                return {
                    src: t.getAttribute('data-src'),
                    alt: t.getAttribute('data-alt') || ''
                };
            });
            lbIndex = 0;
            if (main) {
                var current = main.getAttribute('src');
                thumbs.forEach(function (t, i) {
                    if (t.getAttribute('data-src') === current) lbIndex = i;
                });
            }
        } else {
            var group = trigger.getAttribute('data-lightbox-group') || 'default';
            var groupItems = $$('[data-lightbox-group="' + group + '"]');
            lbItems = groupItems.map(function (t) {
                return {
                    src: t.getAttribute('data-lightbox-src'),
                    alt: t.getAttribute('data-lightbox-alt') || ''
                };
            });
            lbIndex = Math.max(0, groupItems.indexOf(trigger));
        }

        renderLightbox();
        openDialog(lightbox);
    });

    if (lightbox) {
        var lbPrev = $('[data-lightbox-prev]');
        var lbNext = $('[data-lightbox-next]');
        var lbClose = $('[data-lightbox-close]');

        if (lbPrev) lbPrev.addEventListener('click', function () { lbGo(-1); });
        if (lbNext) lbNext.addEventListener('click', function () { lbGo(1); });
        if (lbClose) lbClose.addEventListener('click', function () { lightbox.close(); });

        lightbox.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { e.preventDefault(); lbGo(-1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); lbGo(1); }
        });
    }

    /* ---------- Form timestamp ---------- */

    var formTs = Math.floor(Date.now() / 1000);
    $$('input[name="ts"]').forEach(function (input) {
        input.value = String(formTs);
    });

    /* ---------- Phone mask ---------- */

    function formatPhone(value) {
        var digits = value.replace(/\D/g, '');
        if (!digits) return '';

        var local = digits;
        if (local.indexOf('375') === 0) local = local.slice(3);
        else if (local.indexOf('80') === 0) local = local.slice(2);
        else if (local.indexOf('0') === 0) local = local.slice(1);

        if (local.length > 9) local = local.slice(0, 9);
        if (local.length < 2) return '+375 (' + local;

        var out = '+375 (' + local.slice(0, 2) + ')';
        if (local.length > 2) out += ' ' + local.slice(2, 5);
        if (local.length > 5) out += '-' + local.slice(5, 7);
        if (local.length > 7) out += '-' + local.slice(7, 9);
        return out;
    }

    $$('input[type="tel"]').forEach(function (input) {
        input.addEventListener('input', function () {
            var start = input.selectionStart;
            var before = input.value;
            var next = formatPhone(input.value);
            if (next !== before) {
                input.value = next;
                if (start !== null) {
                    var pos = next.length;
                    try { input.setSelectionRange(pos, pos); } catch (err) { /* noop */ }
                }
            }
        });
    });

    /* ---------- Forms ---------- */

    function setFieldError(form, name, message) {
        var input = form.querySelector('[name="' + name + '"]');
        var error = form.querySelector('[data-error-for="' + name + '"]');
        if (input) {
            if (message) input.setAttribute('aria-invalid', 'true');
            else input.removeAttribute('aria-invalid');
        }
        if (error) error.textContent = message || '';
    }

    function validateForm(form) {
        var ok = true;
        var name = form.querySelector('[name="name"]');
        var phone = form.querySelector('[name="phone"]');
        var consent = form.querySelector('[name="consent"]');

        setFieldError(form, 'name', '');
        setFieldError(form, 'phone', '');

        if (!name || name.value.trim().length < 2) {
            setFieldError(form, 'name', 'Укажите имя — хотя бы 2 буквы');
            ok = false;
        }

        var digits = phone ? phone.value.replace(/\D/g, '') : '';
        if (!phone || digits.length < 9) {
            setFieldError(form, 'phone', 'Укажите номер телефона');
            ok = false;
        }

        if (consent && !consent.checked) {
            var statusEl = form.querySelector('[data-form-status]');
            if (statusEl) {
                statusEl.textContent = 'Отметьте согласие на обработку данных';
                statusEl.className = 'form-status is-error';
            }
            ok = false;
        }

        return ok;
    }

    $$('[data-lead-form]').forEach(function (form) {
        var status = form.querySelector('[data-form-status]');
        var submit = form.querySelector('button[type="submit"]');
        var submitText = form.querySelector('[data-submit-text]');
        var originalText = submitText ? submitText.textContent : '';
        var sendingText = form.getAttribute('data-sending') || 'Отправляем…';
        var errorText = form.getAttribute('data-error') || 'Не удалось отправить заявку. Позвоните нам — так быстрее.';

        form.addEventListener('input', function (e) {
            var name = e.target.getAttribute('name');
            if (name === 'name' || name === 'phone') {
                setFieldError(form, name, '');
                if (status) {
                    status.textContent = '';
                    status.className = 'form-status';
                }
            }
            if (name === 'consent' && status) {
                status.textContent = '';
                status.className = 'form-status';
            }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (form.classList.contains('is-sent')) return;

            if (status) {
                status.textContent = '';
                status.className = 'form-status';
            }

            if (!validateForm(form)) {
                var firstInvalid = form.querySelector('[aria-invalid="true"]');
                if (firstInvalid) firstInvalid.focus();
                return;
            }

            var pageInput = form.querySelector('[name="page"]');
            if (pageInput) pageInput.value = window.location.href;
            var utmInput = form.querySelector('[name="utm"]');
            if (utmInput) utmInput.value = window.location.search.replace(/^\?/, '');

            var data = new FormData(form);
            data.set('source', form.getAttribute('data-source') || 'cta');

            if (submit) submit.disabled = true;
            if (submitText) submitText.textContent = sendingText;

            fetch('api/lead.php', {
                method: 'POST',
                body: data,
                headers: { 'Accept': 'application/json' }
            })
                .then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (json) {
                        return { ok: res.ok, json: json };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.json && result.json.ok) {
                        form.classList.add('is-sent');
                        if (status) {
                            status.textContent = result.json.message || 'Заявка отправлена. Свяжемся с вами в ближайшее время.';
                            status.className = 'form-status is-success';
                        }
                        return;
                    }

                    var json = result.json || {};
                    if (json.errors) {
                        Object.keys(json.errors).forEach(function (key) {
                            setFieldError(form, key, json.errors[key]);
                        });
                    }
                    if (status) {
                        status.textContent = json.message || errorText;
                        status.className = 'form-status is-error';
                    }
                })
                .catch(function () {
                    if (status) {
                        status.textContent = 'Нет связи с сервером. Позвоните нам — так быстрее.';
                        status.className = 'form-status is-error';
                    }
                })
                .finally(function () {
                    if (submit) submit.disabled = false;
                    if (submitText) submitText.textContent = originalText;
                });
        });
    });

    /* ---------- Pointer effects ---------- */

    if (finePointer && !reduceMotion) {
        $$('.btn').forEach(function (btn) {
            btn.addEventListener('pointermove', function (e) {
                var rect = btn.getBoundingClientRect();
                var x = ((e.clientX - rect.left) / rect.width - 0.5) * 6;
                var y = ((e.clientY - rect.top) / rect.height - 0.5) * 4;
                btn.style.transform = 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)';
            });
            btn.addEventListener('pointerleave', function () {
                btn.style.transform = '';
            });
        });

        $$('.adv-card, .contacts__form').forEach(function (el) {
            el.addEventListener('pointermove', function (e) {
                var rect = el.getBoundingClientRect();
                el.style.setProperty('--mx', (((e.clientX - rect.left) / rect.width) * 100).toFixed(1) + '%');
                el.style.setProperty('--my', (((e.clientY - rect.top) / rect.height) * 100).toFixed(1) + '%');
            });
        });
    }

    /* ---------- Smooth anchor offset for older browsers ---------- */

    $$('a[href^="#"]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            var href = link.getAttribute('href');
            if (!href || href === '#' || href.length < 2) return;
            var target = document.querySelector(href);
            if (!target) return;
            if (link.hasAttribute('data-menu-link')) return;
            e.preventDefault();
            target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
            if (history.replaceState) history.replaceState(null, '', href);
        });
    });
})();
