(function () {
    'use strict';

    function parseNumber(value) {
        var parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function money(value) {
        return '$' + parseNumber(value).toFixed(2);
    }

    function formatPhone(value) {
        var digits = value.replace(/\D/g, '').slice(0, 10);
        var parts = [];

        if (digits.length > 0) {
            parts.push(digits.slice(0, 3));
        }

        if (digits.length >= 4) {
            parts.push(digits.slice(3, 6));
        }

        if (digits.length >= 7) {
            parts.push(digits.slice(6, 10));
        }

        return parts.join('-');
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function parseEmailList(value) {
        return (value || '').split(',').map(function (email) {
            return email.trim();
        }).filter(Boolean);
    }

    function isValidEmailList(value, allowEmpty) {
        var emails = parseEmailList(value);

        if (!emails.length) {
            return Boolean(allowEmpty);
        }

        return emails.every(isValidEmail);
    }

    function setButtonBusy(button, text) {
        if (!button) {
            return;
        }

        if (!button.getAttribute('data-ofqb-original-text')) {
            button.setAttribute('data-ofqb-original-text', button.textContent);
        }

        button.textContent = text;
        button.setAttribute('aria-busy', 'true');

        if ('disabled' in button) {
            button.disabled = true;
        }
    }

    function setButtonReady(button) {
        if (!button) {
            return;
        }

        if (button.getAttribute('data-ofqb-original-text')) {
            button.textContent = button.getAttribute('data-ofqb-original-text');
        }

        button.removeAttribute('aria-busy');

        if ('disabled' in button) {
            button.disabled = false;
        }
    }

    function setActionStatus(builder, message) {
        var status = builder.querySelector('[data-ofqb-action-status]');

        if (!status) {
            return;
        }

        status.textContent = message || '';
    }

    function setEmailStatus(builder, message, isError) {
        var status = builder.querySelector('[data-ofqb-email-status]');

        if (!status) {
            return;
        }

        status.textContent = message || '';
        status.classList.toggle('ofqb-modal__status--error', Boolean(isError));
        status.classList.toggle('ofqb-modal__status--success', Boolean(message && !isError));
    }

    function openEmailModal(builder) {
        var modal = builder.querySelector('[data-ofqb-email-modal]');
        var email = modal && modal.querySelector('[name="email_to"]');

        if (!modal) {
            return;
        }

        setEmailStatus(builder, '', false);
        modal.hidden = false;

        if (email) {
            email.focus();
            email.select();
        }
    }

    function closeEmailModal(builder) {
        var modal = builder.querySelector('[data-ofqb-email-modal]');

        if (!modal) {
            return;
        }

        modal.hidden = true;
        setEmailStatus(builder, '', false);
    }

    function sendQuoteEmail(builder, form) {
        var settings = window.ofqbSettings || {};
        var button = form.querySelector('[type="submit"]');
        var email = form.querySelector('[name="email_to"]');
        var cc = form.querySelector('[name="email_cc"]');
        var recipients = parseEmailList(email ? email.value : '');
        var body = new URLSearchParams(new FormData(form));

        if (!settings.ajaxUrl) {
            setEmailStatus(builder, 'Email is not available. Please refresh the page and try again.', true);
            return;
        }

        if (!email || !isValidEmailList(email.value, false)) {
            setEmailStatus(builder, 'Please enter valid To email addresses separated by commas.', true);

            if (email) {
                email.focus();
            }

            return;
        }

        if (cc && !isValidEmailList(cc.value, true)) {
            setEmailStatus(builder, 'Please enter valid CC email addresses separated by commas.', true);
            cc.focus();
            return;
        }

        body.append('action', 'ofqb_email_pdf');
        setButtonBusy(button, 'Sending...');
        setEmailStatus(builder, 'Creating PDF and sending email...', false);

        fetch(settings.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (response) {
                if (!response || !response.success) {
                    throw new Error(response && response.data && response.data.message ? response.data.message : 'The quote email could not be sent.');
                }

                setEmailStatus(builder, response.data.message || 'Quote PDF was emailed.', false);
                setActionStatus(builder, 'Quote emailed to ' + recipients.join(', ') + '.');

                window.setTimeout(function () {
                    closeEmailModal(builder);
                }, 1600);
            })
            .catch(function (error) {
                setEmailStatus(builder, error.message, true);
            })
            .finally(function () {
                setButtonReady(button);
            });
    }

    function clearFormAlert(builder) {
        var alert = builder.querySelector('[data-ofqb-form-alert]');

        if (!alert) {
            return;
        }

        alert.textContent = '';
        alert.hidden = true;
    }

    function showFormAlert(builder, message) {
        var alert = builder.querySelector('[data-ofqb-form-alert]');

        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.hidden = false;
        alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function getFieldLabel(field) {
        var label = field ? field.closest('label') : null;
        var text = label ? label.querySelector('span') : null;
        return text ? text.textContent.replace('*', '').trim() : 'Required field';
    }

    function hasQuoteLine(builder) {
        var hasService = Array.prototype.some.call(builder.querySelectorAll('[data-ofqb-service-row]'), function (row) {
            var description = row.querySelector('[name*="[description]"]');
            return description && description.value.trim();
        });
        var hasMaterial = Array.prototype.some.call(builder.querySelectorAll('[data-ofqb-material-row]'), function (row) {
            var description = row.querySelector('[name*="[description]"]');
            return description && description.value.trim();
        });

        return hasService || hasMaterial;
    }

    function getFormProblems(builder) {
        var requiredFields = builder.querySelectorAll('[data-ofqb-required]');
        var emailFields = builder.querySelectorAll('[data-ofqb-email]');
        var phoneFields = builder.querySelectorAll('[data-ofqb-phone]');

        for (var i = 0; i < requiredFields.length; i += 1) {
            if (!requiredFields[i].value.trim()) {
                return {
                    field: requiredFields[i],
                    message: 'Please fill out ' + getFieldLabel(requiredFields[i]) + ' before generating the quote.'
                };
            }
        }

        for (var emailIndex = 0; emailIndex < emailFields.length; emailIndex += 1) {
            if (!isValidEmail(emailFields[emailIndex].value.trim())) {
                return {
                    field: emailFields[emailIndex],
                    message: 'Please enter a valid ' + getFieldLabel(emailFields[emailIndex]) + ' email address.'
                };
            }
        }

        for (var phoneIndex = 0; phoneIndex < phoneFields.length; phoneIndex += 1) {
            if (!/^\d{3}-\d{3}-\d{4}$/.test(phoneFields[phoneIndex].value.trim())) {
                return {
                    field: phoneFields[phoneIndex],
                    message: 'Please enter ' + getFieldLabel(phoneFields[phoneIndex]) + ' in 000-000-0000 format.'
                };
            }
        }

        if (!hasQuoteLine(builder)) {
            return {
                field: builder.querySelector('[data-ofqb-service-row] textarea, [data-ofqb-material-row] input[type="text"]'),
                message: 'Please add at least one service or material line before generating the quote.'
            };
        }

        return null;
    }

    function getReadinessItems(builder) {
        var items = [];
        var requiredFields = builder.querySelectorAll('[data-ofqb-required]');
        var emailFields = builder.querySelectorAll('[data-ofqb-email]');
        var phoneFields = builder.querySelectorAll('[data-ofqb-phone]');

        requiredFields.forEach(function (field) {
            if (!field.value.trim()) {
                items.push('Missing ' + getFieldLabel(field));
            }
        });

        emailFields.forEach(function (field) {
            if (field.value.trim() && !isValidEmail(field.value.trim())) {
                items.push(getFieldLabel(field) + ' needs a valid email address');
            }
        });

        phoneFields.forEach(function (field) {
            if (field.value.trim() && !/^\d{3}-\d{3}-\d{4}$/.test(field.value.trim())) {
                items.push(getFieldLabel(field) + ' needs 000-000-0000 format');
            }
        });

        if (!hasQuoteLine(builder)) {
            items.push('Add at least one service or material line');
        }

        return items;
    }

    function updateReadiness(builder) {
        var panel = builder.querySelector('[data-ofqb-readiness]');
        var list = builder.querySelector('[data-ofqb-readiness-list]');
        var items = getReadinessItems(builder);

        if (!panel || !list) {
            return;
        }

        panel.classList.toggle('ofqb-readiness--ready', items.length === 0);
        list.innerHTML = '';

        if (!items.length) {
            var ready = document.createElement('li');
            ready.textContent = 'Ready to generate';
            list.appendChild(ready);
            return;
        }

        items.forEach(function (item) {
            var li = document.createElement('li');
            li.textContent = item;
            list.appendChild(li);
        });
    }

    function renumberRows(builder) {
        builder.querySelectorAll('[data-ofqb-service-row]').forEach(function (row, index) {
            var description = row.querySelector('[name*="[description]"]');
            var hours = row.querySelector('[data-ofqb-hours]');
            var rate = row.querySelector('[data-ofqb-rate]');

            if (description) {
                description.name = 'services[' + index + '][description]';
            }

            if (hours) {
                hours.name = 'services[' + index + '][hours]';
            }

            if (rate) {
                rate.name = 'services[' + index + '][rate]';
            }
        });

        builder.querySelectorAll('[data-ofqb-material-row]').forEach(function (row, index) {
            var description = row.querySelector('[name*="[description]"]');
            var unitCost = row.querySelector('[data-ofqb-unit-cost]');
            var quantity = row.querySelector('[data-ofqb-quantity]');

            if (description) {
                description.name = 'materials[' + index + '][description]';
            }

            if (unitCost) {
                unitCost.name = 'materials[' + index + '][unit_cost]';
            }

            if (quantity) {
                quantity.name = 'materials[' + index + '][quantity]';
            }
        });
    }

    function prepareClonedRow(row) {
        row.querySelectorAll('input, textarea').forEach(function (field) {
            if (field.matches('[type="number"]')) {
                field.value = field.matches('[data-ofqb-rate], [data-ofqb-unit-cost]') ? '0.00' : '0';
                return;
            }

            field.value = '';
        });

        var total = row.querySelector('[data-ofqb-line-total]');

        if (total) {
            total.textContent = money(0);
        }
    }

    function addRow(builder, selector) {
        var tbody = builder.querySelector(selector);
        var source = tbody ? tbody.querySelector('tr') : null;

        if (!tbody || !source) {
            return;
        }

        var clone = source.cloneNode(true);
        prepareClonedRow(clone);
        tbody.appendChild(clone);
        renumberRows(builder);
        updateTotals(builder);
        updateReadiness(builder);
    }

    function removeRow(builder, row) {
        var tbody = row ? row.parentElement : null;

        if (!tbody) {
            return;
        }

        if (tbody.querySelectorAll('tr').length === 1) {
            prepareClonedRow(row);
        } else {
            row.remove();
        }

        renumberRows(builder);
        updateTotals(builder);
        updateReadiness(builder);
    }

    function updateTotals(builder) {
        var subtotal = 0;

        builder.querySelectorAll('[data-ofqb-service-row]').forEach(function (row) {
            var hours = parseNumber(row.querySelector('[data-ofqb-hours]') && row.querySelector('[data-ofqb-hours]').value);
            var rate = parseNumber(row.querySelector('[data-ofqb-rate]') && row.querySelector('[data-ofqb-rate]').value);
            var lineTotal = hours * rate;
            var output = row.querySelector('[data-ofqb-line-total]');

            if (output) {
                output.textContent = money(lineTotal);
            }

            subtotal += lineTotal;
        });

        builder.querySelectorAll('[data-ofqb-material-row]').forEach(function (row) {
            var unitCost = parseNumber(row.querySelector('[data-ofqb-unit-cost]') && row.querySelector('[data-ofqb-unit-cost]').value);
            var quantity = parseNumber(row.querySelector('[data-ofqb-quantity]') && row.querySelector('[data-ofqb-quantity]').value);
            var lineTotal = unitCost * quantity;
            var output = row.querySelector('[data-ofqb-line-total]');

            if (output) {
                output.textContent = money(lineTotal);
            }

            subtotal += lineTotal;
        });

        var taxRate = parseNumber(builder.querySelector('[data-ofqb-tax-rate]') && builder.querySelector('[data-ofqb-tax-rate]').value);
        var taxAmount = subtotal * (taxRate / 100);
        var total = subtotal + taxAmount;
        var subtotalOutput = builder.querySelector('[data-ofqb-subtotal]');
        var taxOutput = builder.querySelector('[data-ofqb-tax-amount]');
        var totalOutput = builder.querySelector('[data-ofqb-total]');

        if (subtotalOutput) {
            subtotalOutput.textContent = money(subtotal);
        }

        if (taxOutput) {
            taxOutput.textContent = money(taxAmount);
        }

        if (totalOutput) {
            totalOutput.textContent = money(total);
        }
    }

    function initBuilder(builder) {
        var form = builder.querySelector('[data-ofqb-form]');
        var formIsDirty = false;
        var allowLeave = false;

        if (form) {
            window.addEventListener('beforeunload', function (event) {
                if (!formIsDirty || allowLeave) {
                    return;
                }

                event.preventDefault();
                event.returnValue = '';
            });
        }

        builder.addEventListener('click', function (event) {
            var serviceButton = event.target.closest('[data-ofqb-add-service]');
            var materialButton = event.target.closest('[data-ofqb-add-material]');
            var removeButton = event.target.closest('[data-ofqb-remove-row]');
            var printButton = event.target.closest('[data-ofqb-print-quote]');
            var openEmailButton = event.target.closest('[data-ofqb-open-email-modal]');
            var closeEmailButton = event.target.closest('[data-ofqb-close-email-modal]');
            var safeLeave = event.target.closest('[data-ofqb-safe-leave]');
            var confirmButton = event.target.closest('[data-ofqb-confirm]');

            if (safeLeave) {
                allowLeave = true;
            }

            if (confirmButton && !window.confirm(confirmButton.getAttribute('data-ofqb-confirm'))) {
                event.preventDefault();
                return;
            }

            if (serviceButton) {
                event.preventDefault();
                addRow(builder, '[data-ofqb-services]');
            }

            if (materialButton) {
                event.preventDefault();
                addRow(builder, '[data-ofqb-materials]');
            }

            if (removeButton) {
                event.preventDefault();
                removeRow(builder, removeButton.closest('tr'));
            }

            if (printButton) {
                event.preventDefault();
                window.print();
            }

            if (openEmailButton) {
                event.preventDefault();
                openEmailModal(builder);
            }

            if (closeEmailButton) {
                event.preventDefault();
                closeEmailModal(builder);
            }
        });

        builder.addEventListener('input', function (event) {
            if (event.target.closest('[data-ofqb-form]')) {
                formIsDirty = true;
            }

            if (event.target.matches('[data-ofqb-phone]')) {
                event.target.value = formatPhone(event.target.value);
            }

            if (
                event.target.matches('[data-ofqb-hours]') ||
                event.target.matches('[data-ofqb-rate]') ||
                event.target.matches('[data-ofqb-unit-cost]') ||
                event.target.matches('[data-ofqb-quantity]') ||
                event.target.matches('[data-ofqb-tax-rate]')
            ) {
                updateTotals(builder);
            }

            updateReadiness(builder);
        });

        builder.addEventListener('submit', function (event) {
            if (event.target.matches('[data-ofqb-email-form]')) {
                event.preventDefault();
                sendQuoteEmail(builder, event.target);
                return;
            }

            if (!event.target.matches('[data-ofqb-form]')) {
                return;
            }

            var submitIntent = event.submitter && event.submitter.name === 'ofqb_submit_intent' ? event.submitter.value : 'generate';
            var isDraftSave = submitIntent === 'draft';
            var problems = getFormProblems(builder);

            clearFormAlert(builder);

            if (!isDraftSave && problems) {
                event.preventDefault();
                event.stopPropagation();
                showFormAlert(builder, problems.message);

                if (problems.field) {
                    problems.field.focus();
                }
                return;
            }

            allowLeave = true;
        });

        updateTotals(builder);
        updateReadiness(builder);
    }

    document.documentElement.classList.add('ofqb-js');

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ofqb]').forEach(initBuilder);
    });
}());
