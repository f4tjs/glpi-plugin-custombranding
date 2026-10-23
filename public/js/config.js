/**
 * @author  F4tjs <https://github.com/f4tjs>
 * @license GPL-3.0-or-later
 *
 * Custom Branding — comportamento da tela de configuração.
 *
 *  1. Validação imediata da imagem escolhida (tipo real, peso, dimensões,
 *     proporção) usando AS MESMAS regras do servidor, recebidas em JSON
 *     (atributo data-cb-config, gerado por ConfigViewModel).
 *  2. Prévia ao vivo (login desktop/celular, menu e aba do navegador)
 *     antes de salvar.
 *  3. Avisos de contraste de cores, limite total do envio e alterações
 *     não salvas.
 *
 * O servidor continua sendo a validação definitiva (ImageValidator.php).
 * Sem dependências externas; JavaScript moderno suportado pelo GLPI 12.
 */
(() => {
    'use strict';

    const form = document.getElementById('cb-form');
    if (!form) {
        return;
    }

    /** @type {{slots: Object<string, any>, defaults: Object<string, string>, postMax: number, i18n: Object<string, string>}} */
    const cfg = JSON.parse(form.dataset.cbConfig);
    const t = cfg.i18n;

    /** Imagem efetiva de cada slot na prévia: nova (blob:), atual ou padrão. */
    const previewSrc = {};
    /** Tamanho dos arquivos selecionados (para o limite total do POST). */
    const selectedBytes = {};

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /** sprintf mínimo compatível com as strings do GLPI (%s, %1$s, %2$s). */
    const format = (str, ...args) => {
        let i = 0;
        return str.replace(/%(?:(\d+)\$)?s/g, (_, n) => String(args[n ? Number(n) - 1 : i++] ?? ''));
    };

    const bytes = (n) => {
        if (n >= 1048576) {
            return `${(n / 1048576).toFixed(1).replace('.0', '').replace('.', ',')} MB`;
        }
        return n >= 1024 ? `${Math.round(n / 1024)} KB` : `${n} B`;
    };
    const dims = (w, h) => `${w}×${h} px`;
    const ratioLabel = (w, h) => {
        const gcd = (a, b) => (b ? gcd(b, a % b) : a);
        const d = gcd(w, h) || 1;
        return `${w / d}:${h / d}`;
    };
    const FORMAT_LABEL = { 'image/png': 'PNG', 'image/jpeg': 'JPG', 'image/webp': 'WebP' };
    const formatList = (mimes) => mimes.map((m) => FORMAT_LABEL[m] || m).join(', ');

    const escapeHtml = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    /**
     * Detecta o tipo REAL pelos primeiros bytes ("magic numbers"),
     * igual ao finfo do servidor — a extensão do arquivo é ignorada.
     */
    const sniffMime = async (file) => {
        const b = new Uint8Array(await file.slice(0, 12).arrayBuffer());
        const ascii = (from, to) => String.fromCharCode(...b.slice(from, to));
        if (b[0] === 0x89 && ascii(1, 4) === 'PNG') return 'image/png';
        if (b[0] === 0xff && b[1] === 0xd8 && b[2] === 0xff) return 'image/jpeg';
        if (ascii(0, 4) === 'RIFF' && ascii(8, 12) === 'WEBP') return 'image/webp';
        return ''; // assinatura desconhecida: igual ao servidor, que recusará
    };

    /** Lê as dimensões decodificando a imagem no navegador. */
    const readDimensions = (url) => new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve([img.naturalWidth, img.naturalHeight]);
        img.onerror = reject;
        img.src = url;
    });

    // ------------------------------------------------------------------
    // Validação (espelho de ImageValidator.php)
    // ------------------------------------------------------------------

    const validate = async (spec, file, url) => {
        const r = { errors: [], warnings: [], width: 0, height: 0, mime: '', bytes: file.size };

        if (file.size > spec.maxBytes) {
            r.errors.push(format(t.tooLarge, bytes(file.size), bytes(spec.maxBytes)));
            return r;
        }

        r.mime = await sniffMime(file);
        if (!spec.mimes.includes(r.mime)) {
            r.errors.push(format(t.format, r.mime || t.unknown, formatList(spec.mimes)));
            return r;
        }

        try {
            [r.width, r.height] = await readDimensions(url);
        } catch {
            r.errors.push(t.corrupted);
            return r;
        }

        const [w, h] = [r.width, r.height];
        const [minW, minH] = spec.min;
        const [maxW, maxH] = spec.max;
        const [recW, recH] = spec.recommended;

        if (w < minW || h < minH) {
            r.errors.push(format(t.tooSmall, dims(w, h), dims(minW, minH)));
            return r;
        }
        if (w > maxW || h > maxH) {
            r.errors.push(format(t.tooBig, dims(w, h), dims(maxW, maxH)));
            return r;
        }
        if (w < recW && h < recH) {
            r.warnings.push(format(t.belowRec, dims(recW, recH)));
        }

        const ratio = w / h;
        const isSquare = Math.abs(ratio - 1) <= spec.squareTolerance;
        switch (spec.aspect) {
            case 'square_required':
                if (!isSquare) r.errors.push(format(t.squareReq, dims(w, h)));
                break;
            case 'square_preferred':
                if (!isSquare) r.warnings.push(t.squarePref);
                break;
            case 'landscape':
                if (ratio <= 1) r.warnings.push(t.landscape);
                break;
            case 'portrait':
                if (ratio >= 1) r.warnings.push(t.portrait);
                break;
        }
        if (spec.aspect === 'landscape' || spec.aspect === 'portrait') {
            const ideal = recW / recH;
            if (Math.abs(ratio - ideal) / ideal > 0.15) {
                r.warnings.push(format(t.ratio, ratioLabel(recW, recH)));
            }
        }

        return r;
    };

    // ------------------------------------------------------------------
    // Diagnóstico por campo
    // ------------------------------------------------------------------

    const renderStatus = (box, r) => {
        const level = r.errors.length ? 'danger' : (r.warnings.length ? 'warning' : 'success');
        const title = r.errors.length ? t.incompatible : (r.warnings.length ? t.attention : t.compatible);
        const icon = { danger: 'ti-circle-x', warning: 'ti-alert-triangle', success: 'ti-circle-check' }[level];
        const facts = r.width
            ? `${dims(r.width, r.height)} · ${bytes(r.bytes)} · ${FORMAT_LABEL[r.mime] || r.mime} · ${ratioLabel(r.width, r.height)}`
            : bytes(r.bytes);
        const items = [...r.errors, ...r.warnings].map((m) => `<li>${escapeHtml(m)}</li>`).join('');

        box.innerHTML = `
            <div class="alert alert-${level} py-2 px-3 mb-0">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <i class="ti ${icon}" aria-hidden="true"></i>
                    <strong>${escapeHtml(title)}</strong>
                    <span class="small">${escapeHtml(facts)}</span>
                </div>
                ${items ? `<ul class="small">${items}</ul>` : ''}
                ${r.errors.length ? '' : `<div class="small mt-1">${escapeHtml(t.newSelected)}</div>`}
            </div>`;
    };

    const setThumb = (row, src) => {
        const img = row.querySelector('[data-cb-thumb]');
        const empty = row.querySelector('[data-cb-thumb-empty]');
        if (src) {
            img.src = src;
            img.hidden = false;
            empty?.setAttribute('hidden', '');
        } else {
            img.hidden = true;
            empty?.removeAttribute('hidden');
        }
    };

    const initSlot = (row) => {
        const key = row.dataset.cbSlot;
        const spec = cfg.slots[key];
        const input = row.querySelector('.cb-file');
        const remove = row.querySelector('[data-cb-remove]');
        const status = row.querySelector('.cb-status');
        let blobUrl = null;

        previewSrc[key] = spec.current;

        const effectiveSaved = () => (remove?.checked ? null : spec.current);

        input.addEventListener('change', async () => {
            if (blobUrl) URL.revokeObjectURL(blobUrl);
            blobUrl = null;
            delete selectedBytes[key];

            const file = input.files?.[0];
            if (!file) {
                status.innerHTML = '';
                previewSrc[key] = effectiveSaved();
                setThumb(row, previewSrc[key]);
                refresh();
                return;
            }

            status.innerHTML = `<span class="small text-secondary">${escapeHtml(t.reading)}</span>`;
            blobUrl = URL.createObjectURL(file);
            const result = await validate(spec, file, blobUrl);
            renderStatus(status, result);

            if (result.errors.length) {
                // Arquivo recusado: não é enviado e a prévia volta ao salvo.
                input.value = '';
                URL.revokeObjectURL(blobUrl);
                blobUrl = null;
                previewSrc[key] = effectiveSaved();
            } else {
                selectedBytes[key] = file.size;
                previewSrc[key] = blobUrl;
                if (remove) remove.checked = false;
            }
            setThumb(row, previewSrc[key]);
            refresh();
        });

        remove?.addEventListener('change', () => {
            if (remove.checked) {
                input.value = '';
                delete selectedBytes[key];
                status.innerHTML = `<div class="small text-warning"><i class="ti ti-trash" aria-hidden="true"></i> ${escapeHtml(t.willRemove)}</div>`;
            } else {
                status.innerHTML = '';
            }
            previewSrc[key] = effectiveSaved();
            setThumb(row, previewSrc[key]);
            refresh();
        });
    };

    // ------------------------------------------------------------------
    // Prévia ao vivo
    // ------------------------------------------------------------------

    const value = (name) => form.elements.namedItem(name)?.value ?? '';
    const colorsOn = () => form.elements.namedItem('colors_enabled')?.checked;
    const cssUrl = (src) => (src ? `url("${src.replace(/"/g, '%22')}")` : 'none');
    const imageOrDefault = (key) => previewSrc[key] || cfg.defaults[key] || '';

    const updatePreview = () => {
        const root = form;

        // Logos e ícones
        root.querySelectorAll('[data-cb-img]').forEach((el) => {
            el.style.backgroundImage = cssUrl(imageOrDefault(el.dataset.cbImg));
        });
        root.querySelectorAll('[data-cb-img-src]').forEach((el) => {
            el.src = imageOrDefault(el.dataset.cbImgSrc);
        });

        // Fundos do login (celular usa o próprio ou recorta o de desktop)
        const desktopBg = previewSrc.login_background;
        const mobileBg = previewSrc.login_background_mobile || desktopBg;
        root.querySelectorAll('[data-cb-login]').forEach((el) => {
            const src = el.dataset.cbLogin === 'mobile' ? mobileBg : desktopBg;
            el.style.backgroundImage = cssUrl(src);
            el.style.setProperty('--cb-overlay', src ? String(Number(value('login_overlay')) / 100) : '0');
        });
        const overlayOut = document.getElementById('cb-overlay-value');
        if (overlayOut) overlayOut.textContent = `${value('login_overlay')}%`;

        // Textos
        root.querySelectorAll('[data-cb-text]').forEach((el) => {
            const key = el.dataset.cbText;
            el.textContent = value(key).trim() || (key === 'app_name' ? cfg.defaults.app_name : '');
        });

        // Cores
        ['primary_color', 'menu_bg_color', 'menu_fg_color'].forEach((name) => {
            const label = root.querySelector(`[data-cb-color-label="${name}"]`);
            if (label) label.textContent = value(name);
        });
        const on = colorsOn();
        document.getElementById('cb-colors')?.classList.toggle('opacity-50', !on);
        root.style.setProperty('--cb-primary', on ? value('primary_color') : '');
        root.style.setProperty('--cb-menu-bg', on ? value('menu_bg_color') : '');
        root.style.setProperty('--cb-menu-fg', on ? value('menu_fg_color') : '');
        if (on) {
            root.style.setProperty('--cb-primary-fg', readableOn(value('primary_color')));
        }
        updateContrast(on);
    };

    // Contraste WCAG (mesma fórmula de Color.php)
    const luminance = (hex) => {
        const c = [1, 3, 5].map((i) => parseInt(hex.substr(i, 2), 16) / 255)
            .map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
        return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    };
    const contrast = (a, b) => {
        const [la, lb] = [luminance(a), luminance(b)];
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    };
    const readableOn = (hex) => (contrast(hex, '#ffffff') >= contrast(hex, '#000000') ? '#ffffff' : '#000000');

    const updateContrast = (on) => {
        const box = document.getElementById('cb-contrast');
        if (!box) return;
        if (!on) {
            box.innerHTML = '';
            return;
        }
        const ratio = contrast(value('menu_bg_color'), value('menu_fg_color'));
        const ok = ratio >= 4.5;
        box.innerHTML = `<span class="text-${ok ? 'success' : 'danger'}"><i class="ti ti-${ok ? 'check' : 'alert-triangle'}" aria-hidden="true"></i> ${escapeHtml(format(ok ? t.goodContrast : t.lowContrast, ratio.toFixed(1)))}</span>`;
    };

    // ------------------------------------------------------------------
    // Limite total do envio, alterações não salvas e ações
    // ------------------------------------------------------------------

    let dirty = false;
    const totalSelected = () => Object.values(selectedBytes).reduce((a, b) => a + b, 0);

    const refresh = () => {
        updatePreview();
        dirty = true;
        document.getElementById('cb-dirty')?.classList.remove('d-none');

        const save = document.getElementById('cb-save');
        const total = totalSelected();
        const tooMuch = total > cfg.postMax;
        save.disabled = tooMuch;
        save.title = tooMuch ? format(t.postTooLarge, bytes(total), bytes(cfg.postMax)) : '';
    };

    form.querySelectorAll('[data-cb-slot]').forEach(initSlot);
    form.querySelectorAll('[data-cb-preview]').forEach((el) => {
        el.addEventListener('input', refresh);
        el.addEventListener('change', refresh);
    });

    form.addEventListener('submit', () => {
        dirty = false;
    });

    // "Restaurar padrão" é um botão comum (type="button"): Enter nunca o aciona
    // e, sem JavaScript, ele simplesmente não faz nada.
    const resetButton = document.getElementById('cb-reset');
    resetButton?.addEventListener('click', () => {
        if (!window.confirm(resetButton.dataset.cbConfirm)) {
            return;
        }
        form.querySelectorAll('.cb-file').forEach((i) => { i.value = ''; }); // nada a enviar
        document.getElementById('cb-reset-flag').value = '1';
        dirty = false;
        form.submit();
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirty) {
            event.preventDefault();
        }
    });

    updatePreview();
})();
