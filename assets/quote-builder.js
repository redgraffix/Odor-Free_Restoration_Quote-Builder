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
        builder.addEventListener('click', function (event) {
            var serviceButton = event.target.closest('[data-ofqb-add-service]');
            var materialButton = event.target.closest('[data-ofqb-add-material]');
            var removeButton = event.target.closest('[data-ofqb-remove-row]');
            var printButton = event.target.closest('[data-ofqb-print-quote]');

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
        });

        builder.addEventListener('input', function (event) {
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
        });

        updateTotals(builder);
    }

    document.documentElement.classList.add('ofqb-js');

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ofqb]').forEach(initBuilder);
    });
}());
