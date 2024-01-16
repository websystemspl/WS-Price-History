jQuery(document).ready(function () {
    jQuery('#bulk-index-all-prices').on("click", function () {
        indexAllPrices();
    })
    jQuery('#bulk-remove-all-old-prices').on("click", function () {
        removeOldPrices();
    })
})

function indexAllPrices() {
    jQuery("#wpwrap").append("<div id='index-placeholder'>");
    jQuery.ajax({
        type: "POST",
        dataType: "json",
        url: ajax_object.ajax_url,
        data: {
            action: "index_all_prices",
            nonce: ajax_object.nonce
        },
        success: function (response) {
            jQuery('#index-placeholder').remove();
            jQuery(".index-all-prices > .success").show();
        },
    })
}

function removeOldPrices() {
    jQuery("#wpwrap").append("<div id='index-placeholder'>");
    jQuery.ajax({
        type: "POST",
        dataType: "json",
        url: ajax_object.ajax_url,
        data: {
            action: "remove_old_prices",
            nonce: ajax_object.nonce
        },
        success: function (response) {
            jQuery('#index-placeholder').remove();
            jQuery(".index-all-prices > .success").show();
        },
    })
}