/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: AFL-3.0
 */
(function () {
    'use strict';

    var EU_VAT_REGEX = /^((AT)?U[0-9]{8}|(BE)?0[0-9]{9}|(BG)?[0-9]{9,10}|(CY)?[0-9]{8}L|(CZ)?[0-9]{8,10}|(DE)?[0-9]{9}|(DK)?[0-9]{8}|(EE)?[0-9]{9}|(EL|GR)?[0-9]{9}|(ES)?[0-9A-Z][0-9]{7}[0-9A-Z]|(FI)?[0-9]{8}|(ESB)?[0-9]{8}|(FR)?[0-9A-Z]{2}[0-9]{9}|(GB)?([0-9]{9}([0-9]{3})?|[A-Z]{2}[0-9]{3})|(HU)?[0-9]{8}|(IE)?[0-9]S[0-9]{5}L|(IT)?[0-9]{11}|(LT)?([0-9]{9}|[0-9]{12})|(LU)?[0-9]{8}|(LV)?[0-9]{11}|(MT)?[0-9]{8}|(NL)?[0-9]{9}B[0-9]{2}|(PL)?[0-9]{10}|(PT)?[0-9]{9}|(RO)?[0-9]{2,10}|(SE)?[0-9]{12}|(SI)?[0-9]{8}|(SK)?[0-9]{10})$/;

    function t(s) {
        return (typeof Translator !== 'undefined') ? Translator.translate(s) : s;
    }

    function defineValidators() {
        if (typeof Validation === 'undefined') {
            return;
        }
        if (!Object.prototype.hasOwnProperty.call(Validation.methods, 'validate-taxvat')) {
            Validation.add('validate-taxvat', t('Invalid VAT number format.'), function (v) {
                return Validation.get('IsEmpty').test(v) || EU_VAT_REGEX.test(v);
            });
        }
        if (!Object.prototype.hasOwnProperty.call(Validation.methods, 'validate-codicefiscale')) {
            Validation.add('validate-codicefiscale', t('Invalid Codice Fiscale format.'), function (v) {
                return Validation.get('IsEmpty').test(v) || /^[A-Za-z]{6}[0-9]{2}[A-Za-z]{1}[0-9]{2}[A-Za-z]{1}[0-9]{3}[A-Za-z]{1}$/.test(v);
            });
        }
        if (!Object.prototype.hasOwnProperty.call(Validation.methods, 'validate-sdi')) {
            Validation.add('validate-sdi', t('Invalid SdI code.'), function (v) {
                return Validation.get('IsEmpty').test(v) || /^[a-zA-Z0-9]{7}$/.test(v);
            });
        }
        if (!Object.prototype.hasOwnProperty.call(Validation.methods, 'validate-fatturazione-elettronica')) {
            Validation.add('validate-fatturazione-elettronica', t('Fill at least one field between PEC Email and/or SdI code.'), function (v, elm) {
                var form = elm.closest('form');
                if (!form) {
                    return true;
                }
                // Match inputs only: the wrapping <li> ids (billing-div-email_pec)
                // also end with the field name but have no .value.
                var emailPec = form.querySelector('input[id$="email_pec"]');
                var codSdi = form.querySelector('input[id$="cod_sdi"]');
                if (!emailPec || !codSdi) {
                    return true;
                }
                if (Validation.get('IsEmpty').test(emailPec.value) && Validation.get('IsEmpty').test(codSdi.value)) {
                    return false;
                }
                Validation.reset(emailPec);
                Validation.reset(codSdi);
                return true;
            });
        }
    }

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'class') {
                    node.className = attrs[k];
                } else if (k === 'text') {
                    node.textContent = attrs[k];
                } else {
                    node.setAttribute(k, attrs[k]);
                }
            });
        }
        if (children) {
            children.forEach(function (c) {
                node.appendChild(c);
            });
        }
        return node;
    }

    function buildField(prefix, name, id, label, value, type) {
        var input = el('input', {
            type: type || 'text',
            name: prefix ? prefix + '[' + name + ']' : name,
            id: prefix ? prefix + ':' + name : name,
            title: t(label),
            value: value || '',
            'class': 'input-text',
            maxlength: '255'
        });
        return el('li', { 'class': 'fields', id: (prefix ? prefix + '-' : '') + 'div-' + name }, [
            el('div', { 'class': 'field' }, [
                el('label', { for: prefix ? prefix + ':' + name : name, text: t(label) }),
                el('div', { 'class': 'input-box' }, [input])
            ])
        ]);
    }

    function buildSelect(prefix, name, id, label, value) {
        var select = el('select', {
            'class': 'validate-select required-entry',
            name: prefix ? prefix + '[' + name + ']' : name,
            id: prefix ? prefix + ':' + name : name
        }, [
            el('option', { value: 'private', text: t('Private') }),
            el('option', { value: 'company', text: t('Company') })
        ]);
        select.value = value;
        return el('li', { 'class': 'wide', id: (prefix ? prefix + '-' : '') + 'div-' + name }, [
            el('label', { for: prefix ? prefix + ':' + name : name, 'class': 'required' }, [
                el('em', { text: '*' }),
                document.createTextNode(t(label))
            ]),
            el('div', { 'class': 'input-box' }, [select])
        ]);
    }

    function markRequired(input, required) {
        if (!input || !input.id) {
            return;
        }
        var label = input.ownerDocument.querySelector('label[for="' + input.id + '"]');
        if (!label) {
            return;
        }
        var em = label.querySelector('em');
        if (required) {
            label.classList.add('required');
            if (!em) {
                label.insertBefore(el('em', { text: '*' }), label.firstChild);
            }
        } else {
            label.classList.remove('required');
            if (em) {
                em.remove();
            }
        }
    }

    // The default checkout marks required fields with required-entry on the
    // input or "required" on the label, but often omits the visible asterisk
    // (label.required > em). Add it wherever missing, for the whole form;
    // add-only, so server-rendered marks are never removed.
    function markRequiredFields(form) {
        form.querySelectorAll('input, select, textarea').forEach(function (input) {
            if (input.id && input.classList.contains('required-entry')) {
                markRequired(input, true);
                return;
            }
            if (!input.id) {
                return;
            }
            var label = form.ownerDocument.querySelector('label[for="' + input.id + '"]');
            if (label && label.classList.contains('required')) {
                markRequired(input, true);
            }
        });
    }

    // The whole <li> when it holds only this field, otherwise just the .field
    // wrapper (the billing <li> is shared by company + email for guests).
    function extractFieldUnit(input) {
        if (!input) {
            return null;
        }
        var li = input.closest('li');
        if (!li) {
            return null;
        }
        var fieldDiv = input.closest('.field');
        if (fieldDiv && fieldDiv.parentElement === li && li.children.length > 1) {
            return el('li', { 'class': 'fields' }, [fieldDiv]);
        }
        return li;
    }

    function moveFieldBelow(input, anchorNode) {
        var unit = extractFieldUnit(input);
        if (!unit || !anchorNode || !anchorNode.parentNode) {
            return null;
        }
        anchorNode.parentNode.insertBefore(unit, anchorNode.nextSibling);
        return unit;
    }

    function fieldId(cfg, name) {
        return cfg.prefix ? cfg.prefix + ':' + name : name;
    }

    function updateVisibility(cfg) {
        var form = document.getElementById(cfg.formId);
        var typeSelect = document.getElementById(fieldId(cfg, 'customer_type'));
        var country = document.getElementById(cfg.countryId);
        if (!form || !typeSelect || !country) {
            return;
        }

        var company = typeSelect.value === 'company';
        var italy = country.value === 'IT';
        var fiscal = italy && (company || window.MM_ItalianFiscalFieldsConfig.codfisc_private_enabled);
        var companyIt = company && italy;

        var companyInput = document.getElementById(cfg.companyId);
        var vatId = document.getElementById(cfg.vatId);
        var codFiscInput = document.getElementById(fieldId(cfg, 'cod_fisc'));
        var emailPecInput = document.getElementById(fieldId(cfg, 'email_pec'));
        var codSdiInput = document.getElementById(fieldId(cfg, 'cod_sdi'));

        if (companyInput && companyInput.closest('li')) {
            companyInput.closest('li').style.display = company ? '' : 'none';
        }

        if (vatId && vatId.closest('li')) {
            vatId.closest('li').style.display = company ? '' : 'none';
            vatId.classList.toggle('validate-taxvat', company);
            vatId.classList.toggle('required-entry', company);
            markRequired(vatId, company);
        }

        if (codFiscInput) {
            codFiscInput.closest('li').style.display = fiscal ? '' : 'none';
            codFiscInput.classList.toggle('validate-codicefiscale', fiscal);
            codFiscInput.classList.toggle('required-entry', fiscal);
            markRequired(codFiscInput, fiscal);
        }

        // At least one between PEC and SdI is mandatory
        // (validate-fatturazione-elettronica), so both get the asterisk.
        if (emailPecInput && emailPecInput.closest('li')) {
            emailPecInput.closest('li').style.display = companyIt ? '' : 'none';
            emailPecInput.classList.toggle('validate-email', companyIt);
            emailPecInput.classList.toggle('validate-fatturazione-elettronica', companyIt);
            markRequired(emailPecInput, companyIt);
        }
        if (codSdiInput && codSdiInput.closest('li')) {
            codSdiInput.closest('li').style.display = companyIt ? '' : 'none';
            codSdiInput.classList.toggle('validate-sdi', companyIt);
            codSdiInput.classList.toggle('validate-fatturazione-elettronica', companyIt);
            markRequired(codSdiInput, companyIt);
        }

        markRequiredFields(form);
    }

    function initForm(cfg) {
        var form = document.getElementById(cfg.formId);
        if (!form) {
            return;
        }
        var country = document.getElementById(cfg.countryId);
        if (!country) {
            return;
        }

        var typeId = fieldId(cfg, 'customer_type');

        // Idempotent: the checkout re-renders the billing form via AJAX, so
        // this may run again on freshly recreated DOM nodes.
        if (!document.getElementById(typeId)) {
            var typeLi = buildSelect(cfg.prefix, 'customer_type', typeId, 'Customer Type',
                window.MM_ItalianFiscalFieldsConfig.vat_id.trim() !== '' ? 'company' : window.MM_ItalianFiscalFieldsConfig.default_customer_type);
            var codFisc = buildField(cfg.prefix, 'cod_fisc', fieldId(cfg, 'cod_fisc'), 'Codice Fiscale', window.MM_ItalianFiscalFieldsConfig.cod_fisc);
            var emailPec = buildField(cfg.prefix, 'email_pec', fieldId(cfg, 'email_pec'), 'Email PEC', window.MM_ItalianFiscalFieldsConfig.email_pec, 'email');
            var codSdi = buildField(cfg.prefix, 'cod_sdi', fieldId(cfg, 'cod_sdi'), 'Codice SdI', window.MM_ItalianFiscalFieldsConfig.cod_sdi);

            var countryLi = country.closest('li');
            var anchor = countryLi ? countryLi : country;
            [typeLi, codFisc, emailPec, codSdi].forEach(function (node) {
                anchor.parentNode.insertBefore(node, anchor.nextSibling);
                anchor = node;
            });

            var companyUnit = moveFieldBelow(document.getElementById(cfg.companyId), typeLi);
            moveFieldBelow(document.getElementById(cfg.vatId), companyUnit || typeLi);
        }

        updateVisibility(cfg);
    }

    function boot() {
        defineValidators();
        window.MM_ItalianFiscalFieldsConfig.forms.forEach(initForm);
    }

    function start() {
        boot();

        // Re-run boot() (idempotent) on the checkout CustomEvents fired after
        // each AJAX flow, so re-rendered forms get the fiscal fields again.
        document.body.addEventListener('billing-request:completed', boot);
        document.body.addEventListener('payment-method:switched', boot);

        // Delegated listener on <body> (never replaced by AJAX re-renders):
        // boot() can re-run any number of times without duplicating listeners.
        document.body.addEventListener('change', function (event) {
            var target = event.target;
            if (!target || !target.id) {
                return;
            }
            var form = target.closest('form');
            if (!form) {
                return;
            }
            window.MM_ItalianFiscalFieldsConfig.forms.forEach(function (cfg) {
                if (cfg.formId === form.id &&
                    (target.id === fieldId(cfg, 'customer_type') || target.id === cfg.countryId)) {
                    updateVisibility(cfg);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
