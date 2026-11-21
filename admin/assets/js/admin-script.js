jQuery(document).ready(function($) {
    window.kvmOpenDeleteUser = function(btn) {
        var userId = $(btn).attr('data-id') || '';
        var userName = $(btn).attr('data-name') || '';
        
        var idInput = document.getElementById('kvm_del_user_id');
        var nameSpan = document.getElementById('kvm_del_user_name');
        
        if (idInput) idInput.value = userId;
        if (nameSpan) nameSpan.textContent = userName;
        
        var modal = document.getElementById('kvm-delete-user-modal');
        if (modal) modal.style.display = 'flex';
    };

    window.kvmOpenEditUser = function(btn) {
        var userId = $(btn).attr('data-id') || '';
        var userName = $(btn).attr('data-name') || '';
        var userEmail = $(btn).attr('data-email') || '';
        var userPass = $(btn).attr('data-pass') || '';
        
        document.getElementById('kvm_edit_user_id').value = userId;
        document.getElementById('kvm_edit_user_name').value = userName;
        document.getElementById('kvm_edit_user_email').value = userEmail;
        document.getElementById('kvm_edit_user_password').value = userPass;
        
        var modal = document.getElementById('kvm-edit-user-modal');
        if (modal) modal.style.display = 'flex';
    };
}); 


//license-list

jQuery(document).ready(function($) {
    // Select All Checkbox Handler
    $('#kvm-select-all-keys').on('click', function() {
        $('.kvm-key-cb').prop('checked', this.checked);
    });

    // Modal Open Handlers
    $(document).on('click', '[data-modal]', function(e) {
        e.preventDefault();
        var targetId = $(this).attr('data-modal');
        $('#' + targetId).css('display', 'flex');
    });

    // Modal Close Handlers
    $(document).on('click', '[data-close]', function(e) {
        e.preventDefault();
        var targetId = $(this).attr('data-close');
        $('#' + targetId).css('display', 'none');
    });

    // Expiry Rule Select Handler for Modal
    $(document).on('change', '.kvm-expiry-rule-select', function() {
        var targetId = $(this).attr('data-target');
        if (this.value === 'custom') {
            $('#' + targetId).show();
        } else {
            $('#' + targetId).hide();
        }
    });
});

//user

jQuery(document).ready(function($) {
    // Edit User Button Handler
    $(document).on('click', '.kvm-btn-edit-user', function() {
        var userId = $(this).attr('data-id') || '';
        var userName = $(this).attr('data-name') || '';
        var userEmail = $(this).attr('data-email') || '';
        var userPass = $(this).attr('data-pass') || '';
        
        $('#kvm_edit_user_id').val(userId);
        $('#kvm_edit_user_name').val(userName);
        $('#kvm_edit_user_email').val(userEmail);
        $('#kvm_edit_user_password').val(userPass);
        
        $('#kvm-edit-user-modal').css('display', 'flex');
    });

    // Delete User Button Handler
    $(document).on('click', '.kvm-btn-delete-user', function() {
        var userId = $(this).attr('data-id') || '';
        var userName = $(this).attr('data-name') || '';
        
        $('#kvm_del_user_id').val(userId);
        $('#kvm_del_user_name').text(userName);
        
        $('#kvm-delete-user-modal').css('display', 'flex');
    });
});

//


document.addEventListener('DOMContentLoaded', function() {
    const starterMaxInput = document.getElementById('kvm_starter_max_input');
    const proMaxInput = document.getElementById('kvm_pro_max_input');
    
    const displayStarterMax = document.getElementById('kvm_display_starter_max');
    const proMinDisplay = document.getElementById('kvm_pro_min_display');
    const displayProMin = document.getElementById('kvm_display_pro_min');
    const displayProMax = document.getElementById('kvm_display_pro_max');
    const entMinDisplay = document.getElementById('kvm_ent_min_display');
    const displayEntMin = document.getElementById('kvm_display_ent_min');

    function updateRanges() {
        if (!starterMaxInput || !proMaxInput) return;

        let sMax = parseInt(starterMaxInput.value) || 1;
        let pMax = parseInt(proMaxInput.value) || (sMax + 1);

        if (pMax <= sMax) {
            pMax = sMax + 1;
            proMaxInput.value = pMax;
        }

        let pMin = sMax + 1;
        let eMin = pMax + 1;

        if(displayStarterMax) displayStarterMax.textContent = sMax;
        if(proMinDisplay) proMinDisplay.value = pMin;
        if(displayProMin) displayProMin.textContent = pMin;
        if(displayProMax) displayProMax.textContent = pMax;
        if(entMinDisplay) entMinDisplay.value = eMin;
        if(displayEntMin) displayEntMin.textContent = eMin;

        proMaxInput.min = pMin;
    }

    if(starterMaxInput && proMaxInput) {
        starterMaxInput.addEventListener('input', updateRanges);
        starterMaxInput.addEventListener('change', updateRanges);
        proMaxInput.addEventListener('input', updateRanges);
        proMaxInput.addEventListener('change', updateRanges);
        updateRanges();
    }
});