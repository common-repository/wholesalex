(function($) {
    'use strict';
    $(document).on('click', '.wholesalex-discount-new', function(e) {
        e.preventDefault();
        $('.wholesalex-repetable').append($('.wholesalex-predefined').html())
    });

    $(document).on('click', '.wholesalex-discount-remove', function(e) {
        e.preventDefault();
        $(this).closest('.wholesalex-discount').remove();
    });

    $(document).on('change', '#wholesalex_quantity_based', function(e) {
        e.preventDefault();
        if ($(this).is(':checked')) {
            $('.wholesalex-discount-wrap').show()
        } else {
            $('.wholesalex-discount-wrap').hide()
        }
    });

    $(document).on('click', '.wholesalex-single-group-clone', function(e) {
        e.preventDefault();
        $('.wholesalex-single-repeated-field').append($('.wholesalex-single-repeated-clone-hidden').html());
    });
    $(document).on('click', '.wholesalex-single-remove', function(e) {
        e.preventDefault();
        $(this).closest('.wholesalex-repetable-field').remove();
    });

    // Search Users
    $(document).on('paste keyup', '.wsx-search-box', function(e){
        e.preventDefault();
        $.ajax({
            url: wholesalex.ajax,
            type: 'POST',
            data: {
                action: 'wsx_users', 
                term: $('.wsx-search-box').val(),
                wpnonce: wholesalex.nonce
            },
            success: function(res) {
                $('.wsx-search-result').html(res.data);
            },
            error: function(xhr) {
                console.log('Error occured.please try again' + xhr.statusText + xhr.responseText );
            },
        });
    });
    $(document).on('click', 'ul.wsx-search-result li', function(e){
        e.preventDefault();
        $('input[name="started_by"]').val( $(this).data('id') );
        $('.wsx-search-box').val('')
        $('.wsx-search-result').html('');
        $('.wsx-search-label').html( $(this).text() );
    });


    // Addons Enable Option
    $(document).on('click', '.wsx-addons-enable', function(e) {
        const that = this
        const addonName = $(that).attr('id');
        $.ajax({
            url: wholesalex.ajax,
            type: 'POST',
            data: {
                action: 'wsx_addon', 
                addon: addonName,
                value: (that.checked ? 'yes' : ''),
                wpnonce: wholesalex.nonce
            },
            success: function(data) {
                if(data.success) {
                    location.reload();
                } else {
                    
                    var msg= '';
                    msg= '<div class="notice notice-error is-dismissible whx_addon_notice"><p><strong>ERROR: </strong>'+data['data']+'.</p><button type="button" class="notice-dismiss" onclick="javascript: return jQuery(this).parent().remove();"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
                    jQuery('.wholesalex-editor__row.wholesalex-editor__heading').before(msg);
                    document.getElementById(addonName).checked = false
                }
            },
            error: function(xhr) {
                console.log('Error occured.please try again' + xhr.statusText + xhr.responseText );
            },
        });
    });

    // Email Enable Option
    $(document).on('click', '.wsx-email-enable', function(e) {
        const that = this
        const emailName = $(that).attr('id');
        $.ajax({
            url: wholesalex.ajax,
            type: 'POST',
            data: {
                action: 'save_wholesalex_email_settings', 
                id: emailName,
                value: (that.checked),
                nonce: wholesalex.nonce
            },
            success: function(data) {
                if(data.success) {
                    location.reload();
                } else {
                    document.getElementById(emailName).checked = false
                }
            },
            error: function(xhr) {
                console.log('Error occured.please try again' + xhr.statusText + xhr.responseText );
            },
        });
    });

    $(document).on('click','.wholesalex_rule_on_more', function (e) {
        jQuery(".wholesalex_rule_modal").css('display','none');
        let element = ".wholesalex_rule_modal."+e.target.id;
        jQuery(element).css('display','block');
    });
    $(document).on('click','.modal-close-btn', function (e) {
        jQuery(".wholesalex_rule_modal").css('display','none');
    });

    const count = document.querySelectorAll("#quote_accept_button").length;
    if(count) {
        document.querySelectorAll("#quote_accept_button").forEach(element => {
            element.remove();
        });
    }

    // if(document.body.contains(document.getElementById("wholesalex_initial_setup_wizard"))) {
    //     $(".admin_page_wholesalex-setup-wizard #wpadminbar").remove();
    //     $(".admin_page_wholesalex-setup-wizard #adminmenumain").remove();
    // }

    const wholesalex_email_templates = [
        'wholesalex_new_user_approval_required',
        'wholesalex_new_user_approved',
        'wholesalex_new_user_auto_approve',
        'wholesalex_new_user_email_verified',
        'wholesalex_registration_pending',
        'wholesalex_new_user_registered',
        'wholesalex_registration_rejected',
        'wholesalex_new_user_email_verification',
        'wholesalex_raq_make_offer',
        'wholesalex_raq_expiring_offer',
        'wholesalex_subaccount_create',
        'wholesalex_subaccount_order_approval_require',
        'wholesalex_subaccount_order_pending',
        'wholesalex_subaccount_order_placed',
        'wholesalex_subaccount_order_reject',
    ];
    // In admin emails, modify email path for theme folder.
    wholesalex_email_templates.forEach(element => {
        if (($(`#woocommerce_${element}_enabled`).val() !== undefined)){
            var text = $('.template_html').html();
            var newtext = text.replace("/woocommerce/", "/");
            $('.template_html').html(newtext);
            $('.template_html p a:nth-child(2)').remove();
            $('.template_html a').remove();
        }
    });

     // Move notice into after heading
    //  $(document).ready( function() {
    //     const noticeWrapper = $('.wsx-notice-wrapper');
    //     if ( noticeWrapper.length > 0  ) {
    //         setTimeout( function() {
    //             noticeWrapper.each(function(e){
    //                 const notice = $(this);
    //                 if($('#wpwrap .wrap .wp-header-end').length>0) {
    //                     $('#wpwrap .wrap .wp-header-end').after(notice);
    //                 } else {
    //                     $('#wpwrap .wrap h1').after(notice);
    //                 }
    //             });
    //         }, 100);
    //     }
    // });

    $('a[href="admin.php?page=go_wholesalex_pro"]').each(function() {
        $(this).attr('target', '_blank');
      });

})(jQuery);

const openWholesaleXGetProPopUp = () => {
    const proPopup = document.getElementById('wholesalex-pro-popup');
    if(proPopup) {
        proPopup.style.display='flex';
    }
}

const closeWholesaleXGetProPopUp = () => {
    const proPopup = document.getElementById('wholesalex-pro-popup');
    const closeButton = document.getElementById('wholesalex-close-pro-popup');
    
    if(proPopup && closeButton ) {
        closeButton.onclick = function(event) {
            event.preventDefault();
            proPopup.style.display = "none";
        }
    }
}

