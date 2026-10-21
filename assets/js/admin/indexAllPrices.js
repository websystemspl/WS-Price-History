jQuery(document).ready(function () {
    jQuery('#bulk-index-all-prices').on("click", function (e) {
        e.preventDefault();
        runPriceHistoryAction("index_all_prices", ".index-all-prices > .index-success");
    })
    jQuery('#bulk-remove-all-old-prices').on("click", function (e) {
        e.preventDefault();
        runPriceHistoryAction("remove_old_prices", ".remove-all-old-prices > .remove-success");
    })
})

function runPriceHistoryAction(action, successSelector) {
    jQuery("#wpwrap").append("<div id='index-placeholder'>");
    jQuery.ajax({
        type: "POST",
        dataType: "json",
        url: ajax_object.ajax_url,
        data: {
            action: action,
            nonce: ajax_object.nonce
        },
        success: function (response) {
            if (response && response.success) {
                jQuery(successSelector).show();
            }
        },
        complete: function () {
            jQuery('#index-placeholder').remove();
        }
    })
}
