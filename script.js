


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

    var d = new Date();
    var mn = d.getMonth()+1;
    if (mn < firstmonth) {
        mn = firstmonth;
    } else {
        if (mn > lastmonth) {
            mn = lastmonth;
        }
    }

    var ele = document.getElementById("railtimetable-cal");
    var sele = document.getElementById("railtimetable-cal-"+firstmonth);

    var offsetblocks = mn - firstmonth;
    var scroll = offsetblocks * sele.offsetWidth;
    scroll = scroll - (ele.clientWidth/2);
    scroll = scroll + (sele.clientWidth/2);
    if (scroll > 0) {
        ele.scrollLeft = scroll;
    }
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
