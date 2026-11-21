/* KeyVault Manager — admin interactions (all admin JS lives in this file). */
(function ($) {
    'use strict';

    $(function () {

        /* ------------------------------------------------------ modals */
        function openModal(id) {
            var $m = $('#' + id);
            if ($m.length) { $m.addClass('is-open'); }
        }
        function closeModal(id) {
            var $m = $('#' + id);
            if ($m.length) { $m.removeClass('is-open'); }
        }

        $(document).on('click', '[data-kvm-close]', function (e) {
            e.preventDefault();
            closeModal($(this).attr('data-kvm-close'));
        });

        $(document).on('click', '.kvm-modal-overlay', function (e) {
            if (e.target === this) { $(this).removeClass('is-open'); }
        });

        $(document).on('keyup', function (e) {
            if (e.key === 'Escape') { $('.kvm-modal-overlay.is-open').removeClass('is-open'); }
        });

        /* ------------------------------------------------- flash dismiss */
        $(document).on('click', '[data-kvm-dismiss]', function () {
            $('#kvm-flash').fadeOut(150);
        });

        /* ------------------------------------------------ copy to clipboard */
        function copyText(value, done) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(function () { done(true); }, function () { done(false); });
            } else {
                var $tmp = $('<textarea>').val(value).css({ position: 'absolute', left: '-9999px' }).appendTo('body');
                $tmp[0].select();
                var ok = false;
                try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
                $tmp.remove();
                done(ok);
            }
        }

        $(document).on('click', '[data-kvm-copy]', function () {
            var $btn = $(this);
            copyText($btn.attr('data-kvm-copy') || '', function (ok) {
                var original = $btn.text();
                $btn.text(ok ? 'Copied ✓' : original);
                setTimeout(function () { $btn.text(original); }, 1600);
            });
        });

        $(document).on('click', '#kvm-copy-snippet', function () {
            var target = document.getElementById($(this).attr('data-kvm-copy-target'));
            if (!target) { return; }
            copyText(target.value, function (ok) {
                var $btn = $('#kvm-copy-snippet');
                $btn.text(ok ? 'Copied ✓' : 'Copy Updater Snippet');
                setTimeout(function () { $btn.text('Copy Updater Snippet'); }, 1600);
            });
        });

        /* ------------------------------------------- license details modal */
        $(document).on('click', '[data-kvm-details]', function () {
            var $row = $(this).closest('tr[data-kvm-id]');
            if (!$row.length) { return; }

            var data = $row.data();
            $('#kvm-detail-key').text(data.kvmKey || '');
            $('#kvm-detail-customer').text(data.kvmCustomer || '');
            $('#kvm-detail-plan').text(data.kvmPlan || '');
            $('#kvm-detail-quota').text(data.kvmQuota || '');
            $('#kvm-detail-expiry').text(data.kvmExpiry || '');
            $('#kvm-detail-status').text(data.kvmStatus || '');

            var $toggle = $('#kvm-detail-toggle-status');
            if (String(data.kvmStatus || '').toUpperCase() === 'ACTIVE') {
                $toggle.text('Revoke Key').attr('href', data.kvmRevokeUrl || '#').removeClass('kvm-activate-btn').addClass('kvm-revoke-btn');
            } else {
                $toggle.text('Set Active').attr('href', data.kvmActivateUrl || '#').removeClass('kvm-revoke-btn').addClass('kvm-activate-btn');
            }
            $('#kvm-detail-delete').attr('href', data.kvmDeleteUrl || '#');

            var domains = [];
            try { domains = JSON.parse($row.attr('data-kvm-domains') || '[]'); } catch (err) { domains = []; }

            var $list = $('#kvm-detail-domains').empty();
            if (!domains.length) {
                $list.append('<p class="kvm-no-domains">No websites currently activated with this license key.</p>');
            } else {
                $.each(domains, function (i, d) {
                    var $rowEl = $('<div class="kvm-domain-row"></div>');
                    var $code = $('<code class="kvm-domain-code"></code>').text(d.domain || '');
                    var $link = $('<a class="kvm-unlink-link"></a>').attr('href', d.unlink || '#').text('× Unlink');
                    $link.on('click', function (e) {
                        if (!window.confirm('Unlink domain ' + (d.domain || '') + '?')) { e.preventDefault(); }
                    });
                    $rowEl.append($code).append($link);
                    $list.append($rowEl);
                });
            }

            // Prepare the expiry sub-modal.
            $('#kvm-expiry-license-id').val(data.kvmId || '');
            $('#kvm-expiry-key').text(data.kvmKey || '');
            $('#kvm-expiry-date').val(data.kvmExpiryCurrent || '');

            openModal('kvm-details-modal');
        });

        $('#kvm-detail-copy').on('click', function () {
            copyText($('#kvm-detail-key').text(), function (ok) {
                var $btn = $('#kvm-detail-copy');
                $btn.text(ok ? 'Copied ✓' : 'Copy');
                setTimeout(function () { $btn.text('Copy'); }, 1600);
            });
        });

        $('#kvm-detail-edit-expiry').on('click', function () {
            openModal('kvm-expiry-modal');
        });

        $(document).on('change', '#kvm-expiry-rule', function () {
            $('#kvm-expiry-date-wrap').toggle(this.value === 'custom');
        });

        /* ------------------------------------------------------ users */
        $(document).on('click', '.kvm-btn-edit-user', function () {
            $('#kvm_edit_user_id').val($(this).attr('data-id') || '');
            $('#kvm_edit_user_name').val($(this).attr('data-name') || '');
            $('#kvm_edit_user_email').val($(this).attr('data-email') || '');
            $('#kvm_edit_user_password').val('');
            openModal('kvm-edit-user-modal');
        });

        $(document).on('click', '.kvm-btn-delete-user', function () {
            $('#kvm_del_user_id').val($(this).attr('data-id') || '');
            $('#kvm_del_user_name').text($(this).attr('data-name') || '');
            openModal('kvm-delete-user-modal');
        });

        /* -------------------------------------------- select all + confirms */
        $('#kvm-select-all-keys').on('click', function () {
            $('.kvm-key-cb').prop('checked', this.checked);
        });

        $(document).on('click', '[data-kvm-confirm]', function () {
            return window.confirm($(this).attr('data-kvm-confirm'));
        });

        /* --------------------------------------------------- order details */
        $(document).on('click', '[data-kvm-order-open]', function () {
            var $row = $(this).closest('tr[data-kvm-order]');
            var order = null;
            try { order = JSON.parse($row.attr('data-kvm-order')); } catch (err) { order = null; }
            if (!order) { return; }

            $('#modal-order-id').text(order.order_id || 'N/A');
            $('#modal-name').text(order.customer_name || 'N/A');
            $('#modal-email').text(order.customer_email || 'N/A');
            $('#modal-amount').text('$' + parseFloat(order.amount || 0).toFixed(2));
            $('#modal-gateway').text(order.gateway || 'N/A');
            $('#modal-qty').text(order.quantity || '1');
            $('#modal-mode').text(order.license_mode || 'multi');
            $('#modal-keys').val(order.license_keys || 'No keys recorded.');

            openModal('kvm-order-modal');
        });

        /* ------------------------------------------------- settings: ranges */
        var starterMaxInput = document.getElementById('kvm_starter_max_input');
        var proMaxInput = document.getElementById('kvm_pro_max_input');

        function updateRanges() {
            if (!starterMaxInput || !proMaxInput) { return; }
            var sMax = parseInt(starterMaxInput.value, 10) || 1;
            var pMax = parseInt(proMaxInput.value, 10) || (sMax + 1);

            if (pMax <= sMax) {
                pMax = sMax + 1;
                proMaxInput.value = pMax;
            }

            var pMin = sMax + 1;
            var eMin = pMax + 1;

            var $dispStarterMax = $('#kvm_display_starter_max');
            var $proMinDisplay = $('#kvm_pro_min_display');
            var $dispProMin = $('#kvm_display_pro_min');
            var $dispProMax = $('#kvm_display_pro_max');
            var $entMinDisplay = $('#kvm_ent_min_display');
            var $dispEntMin = $('#kvm_display_ent_min');

            $dispStarterMax.text(sMax);
            $proMinDisplay.val(pMin);
            $dispProMin.text(pMin);
            $dispProMax.text(pMax);
            $entMinDisplay.val(eMin);
            $dispEntMin.text(eMin);
            proMaxInput.min = pMin;
        }

        if (starterMaxInput && proMaxInput) {
            $(starterMaxInput).on('input change', updateRanges);
            $(proMaxInput).on('input change', updateRanges);
            updateRanges();
        }

        /* ------------------------------------------ settings: wipe passkey */
        $('#kvm-wipe-toggle').on('change', function () {
            var $row = $('#kvm-wipe-passkey-row');
            if (this.checked) {
                $row.show();
                $('#kvm_wipe_passkey').prop('required', true).trigger('focus');
            } else {
                $row.hide();
                $('#kvm_wipe_passkey').prop('required', false).val('');
            }
        });
        // Sync initial state (option may already be enabled).
        if ($('#kvm-wipe-toggle').length && $('#kvm-wipe-toggle').prop('checked')) {
            $('#kvm-wipe-passkey-row').show();
            $('#kvm_wipe_passkey').prop('required', true);
        }

        /* --------------------------------------------- gateways: env switch */
        $('#kvm_env_switch').on('change', function () {
            if (this.checked) {
                $('#kvm_env_label').text('Live / Production Mode');
                $('#kvm_test_creds').hide();
                $('#kvm_live_creds').show();
            } else {
                $('#kvm_env_label').text('Test / Sandbox Mode');
                $('#kvm_test_creds').show();
                $('#kvm_live_creds').hide();
            }
        });

        /* --------------------------------------------- emails: merge tags */
        var lastFocusedField = null;

        $(document).on('focusin', '.kvm-email-target', function () {
            lastFocusedField = this;
        });

        $(document).on('click', '.kvm-merge-tag-btn', function () {
            var tag = $(this).attr('data-tag') || '';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(tag).catch(function () {});
            }
            if (lastFocusedField) {
                var field = lastFocusedField;
                var start = field.selectionStart || field.value.length;
                var end = field.selectionEnd || field.value.length;
                field.value = field.value.slice(0, start) + tag + field.value.slice(end);
                field.focus();
                field.selectionStart = field.selectionEnd = start + tag.length;
            }
        });

    });
})(jQuery);
