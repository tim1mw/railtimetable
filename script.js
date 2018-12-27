function initAllTimetable() {

    jQuery( document ).ready( function() {
    jQuery('#timetabletabs').each(function(){ 
        var active, content, links = jQuery(this).find('a');
        active = jQuery(links.filter('[href="'+location.hash+'"]')[0] || links[0]);
        active.addClass('active');
        content = jQuery(active[0].hash);

        links.not(active).each(function () {
            jQuery(this.hash).hide();
        });

       jQuery(this).on('click', 'a', function(e){
           active.removeClass('active');
           content.hide();
           active = jQuery(this);
           content = jQuery(this.hash);
           active.addClass('active');
           content.show();
           e.preventDefault();
           });
       });
   });
}


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
    var scroll = offsetblocks * (sele.offsetWidth+4);
    scroll = scroll - (ele.offsetWidth/2);
    scroll = scroll + (sele.offsetWidth/2)+25;
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
