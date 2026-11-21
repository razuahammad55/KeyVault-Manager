/* KeyVault Manager — frontend interactions (order form pricing, copy keys). */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        /* ------------------------------------------------ order form pricing */
        var input = document.getElementById('kvm_quantity_input');
        if (input) {
            var badge = document.getElementById('kvm-plan-badge');
            var title = document.getElementById('kvm-plan-title');
            var rateVal = document.getElementById('kvm-rate-val');
            var priceVal = document.getElementById('kvm-price-val');
            var period = document.getElementById('kvm-billing-period');

            var config = window.kvm_pricing || {
                starter_price: 49,
                starter_max: 9,
                pro_price: 39,
                pro_max: 99,
                ent_price: 30
            };

            function money(value) {
                var num = Number(value) || 0;
                var formatted = num % 1 === 0 ? String(num) : num.toFixed(2);
                return formatted;
            }

            function updatePricing() {
                var qty = parseInt(input.value, 10) || 1;
                var tier, baseRate, color, isLifetime;

                if (qty > config.pro_max) {
                    tier = 'ENTERPRISE PLAN';
                    baseRate = config.ent_price;
                    color = '#d97706';
                    isLifetime = true;
                } else if (qty > config.starter_max) {
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

                var total = qty * baseRate;

                if (badge) {
                    badge.textContent = tier;
                    badge.style.background = color;
                }
                if (title) {
                    var tierName = qty <= config.starter_max ? 'Starter Tier' : (qty <= config.pro_max ? 'Professional Tier' : 'Enterprise Tier');
                    title.textContent = tierName + ' (' + qty + ' Website Slot' + (qty > 1 ? 's' : '') + ')';
                }
                if (rateVal) {
                    rateVal.textContent = money(baseRate);
                }
                if (priceVal) {
                    priceVal.textContent = '$' + money(total);
                }
                if (period) {
                    period.textContent = isLifetime ? ' / Lifetime' : ' / yr';
                }
            }

            input.addEventListener('input', updatePricing);
            input.addEventListener('change', updatePricing);
            updatePricing();
        }

        /* --------------------------------------------------------- copy keys */
        document.querySelectorAll('.kvm-copy-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var value = btn.getAttribute('data-kvm-copy') || '';
                var original = btn.textContent;

                function done(ok) {
                    btn.textContent = ok ? 'Copied ✓' : original;
                    setTimeout(function () { btn.textContent = original; }, 1600);
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(function () { done(true); }, function () { done(false); });
                } else {
                    var tmp = document.createElement('textarea');
                    tmp.value = value;
                    tmp.setAttribute('readonly', '');
                    tmp.style.position = 'absolute';
                    tmp.style.left = '-9999px';
                    document.body.appendChild(tmp);
                    tmp.select();
                    try {
                        document.execCommand('copy');
                        done(true);
                    } catch (e) {
                        done(false);
                    }
                    document.body.removeChild(tmp);
                }
            });
        });
    });
})();
