document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('kvm_quantity_input');
    if (input) {
        const badge = document.getElementById('kvm-plan-badge');
        const title = document.getElementById('kvm-plan-title');
        const rateVal = document.getElementById('kvm-rate-val');
        const priceVal = document.getElementById('kvm-price-val');
        const period = document.getElementById('kvm-billing-period');

        const config = window.kvm_pricing || {
            starter_price: 49,
            starter_max: 9,
            pro_price: 39,
            pro_max: 99,
            ent_price: 30
        };

        function updatePricing() {
            const qty = parseInt(input.value) || 1;
            let tier = 'STARTER PLAN';
            let baseRate = config.starter_price;
            let color = '#2563eb';
            let isLifetime = false;

            const starterLimit = config.starter_max;
            const proLimit = config.pro_max;

            if (qty > proLimit) {
                tier = 'ENTERPRISE PLAN';
                baseRate = config.ent_price;
                color = '#d97706';
                isLifetime = true;
            } else if (qty > starterLimit) {
                tier = 'PROFESSIONAL PLAN';
                baseRate = config.pro_price;
                color = '#9333ea';
                isLifetime = false;
            } else {
                tier = 'STARTER PLAN';
                baseRate = config.starter_price;
                color = '#2563eb';
                isLifetime = false;
            }

            const total = qty * baseRate;
            const periodText = isLifetime ? ' / Lifetime' : ' / yr';

            if (badge) {
                badge.textContent = tier;
                badge.style.background = color;
            }
            if (title) {
                title.textContent = (qty <= starterLimit ? 'Starter Tier' : (qty <= proLimit ? 'Professional Tier' : 'Enterprise Tier')) + ' (' + qty + ' Website Slot' + (qty > 1 ? 's' : '') + ')';
            }
            if (rateVal) {
                rateVal.textContent = baseRate;
            }
            if (priceVal) {
                priceVal.textContent = '$' + total;
            }
            if (period) {
                period.textContent = periodText;
            }
        }

        input.addEventListener('input', updatePricing);
        updatePricing();
    }
});