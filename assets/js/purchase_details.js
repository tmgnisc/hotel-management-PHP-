function parseNumber(value) {
    const n = parseFloat(value);
    return Number.isFinite(n) ? n : 0;
}

function updateAmount() {
    const qty = parseNumber(document.getElementById('quantity')?.value || '0');
    const rate = parseNumber(document.getElementById('rate')?.value || '0');
    const amount = qty * rate;
    const amountInput = document.getElementById('amount_display');
    if (amountInput) {
        amountInput.value = amount.toFixed(2);
    }
}

function setFilter(period) {
    const periodInput = document.getElementById('period');
    const customRange = document.getElementById('customRange');
    const buttons = document.querySelectorAll('.filter-btn');

    if (!periodInput || !customRange) {
        return;
    }

    periodInput.value = period;

    buttons.forEach((btn) => {
        btn.classList.remove('bg-indigo-600', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-700');
    });

    const activeBtn = document.getElementById('btn-' + period);
    if (activeBtn) {
        activeBtn.classList.remove('bg-gray-200', 'text-gray-700');
        activeBtn.classList.add('bg-indigo-600', 'text-white');
    }

    if (period === 'custom') {
        customRange.classList.remove('hidden');
        customRange.classList.add('grid');
    } else {
        customRange.classList.remove('grid');
        customRange.classList.add('hidden');
    }
}

window.setFilter = setFilter;

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('quantity')?.addEventListener('input', updateAmount);
    document.getElementById('rate')?.addEventListener('input', updateAmount);
    updateAmount();

    document.getElementById('purchaseForm')?.addEventListener('submit', function (e) {
        const quantity = parseNumber(document.getElementById('quantity')?.value || '0');
        const rate = parseNumber(document.getElementById('rate')?.value || '0');

        if (quantity <= 0) {
            e.preventDefault();
            alert('Quantity must be greater than 0.');
            return;
        }

        if (rate < 0) {
            e.preventDefault();
            alert('Rate must be 0 or greater.');
        }
    });
});
