


function initTrainTimes() {
    jQuery( document ).ready( function() {
        jQuery( '#railtimetable-modal' ).dialog( {
            autoOpen: false,
            modal: true,
            draggable: false,
            resizable: false,

            buttons: {
                [closetext]: function() {
                    jQuery( this ).dialog( "close" );
                }
            }
        });
    });

}

function showTrainTimes(date) {
    jQuery.ajax({
          type: "GET", 
          url: baseurl+"/railtimetable_popup?date="+date,
          dataType : "HTML",
          success: function(data){
              jQuery(".ui-dialog-titlebar").hide();
              jQuery( '#railtimetable-modal' ).html(data);
              jQuery( '#railtimetable-modal' ).dialog( 'open' );
          }
        });
}
