jQuery(document).ready(function(){
    jQuery('#bulk-index-all-prices').on("click", function(){
        indexAllPrices();
    })
})

function indexAllPrices(){
    jQuery("#wpwrap").append("<div id='index-placeholder'>");
    jQuery.ajax({
        type: "POST",
        dataType: "json",
        url: ajax_object.ajax_url,
        data: {
            action: "index_all_prices"
        },
        success: function(response) {
            jQuery('#index-placeholder').remove();
            jQuery(".index-all-prices > .success").show();
        },
    })
}