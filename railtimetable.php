<?php

/**
 * Plugin Name: Heritage Railway Timetable
 * Plugin URI:  http://www.autotrain.org
 * Description: The title says it all!
 * Author:      Tim Williams, AutoTrain (tmw@autotrain.org)
 * Author URI:  http://www.autotrain.org
 * Version:     0.0.1
 * Text Domain: railtimetable
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
**/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once('calendar.php');

function railtimetable_show($attr) {
    railtimetable_setlangage();
    $calendar = new Calendar();

    $str="";
    for ($loop=intval($attr['start']); $loop<intval($attr['end'])+1; $loop++) {
        $str .= "<div class='calendar-box-wrapper'>".$calendar->draw(date($attr['year']."-".$loop."-01"))."</div>";
    }

    $lang = "";
    if (function_exists("pll_current_language")) {
        $lang = "/".pll_current_language()."/";
    }

    $str .= '<script type="text/javascript">var baseurl = "'.$lang.get_site_url().'";var closetext="'.__("Close").'";initTrainTimes();</script>';
    $str .= '<div id="railtimetable-modal"></div>';

    return "<div class='calendar-wrapper'>".$str."</div>";
}

function railtimetable_times($attr) {
    global $wpdb;

    $tmeta = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_timetables WHERE timetable='".$attr['timetable']."'");

    if (!$tmeta) {
        return __("Error: Unknown timetable", "railtimetable");
    }

    $tmeta = $tmeta[0];

    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations");
    $text = "<div class='timetable-wrapper'><table style='margin-left:auto;margin-right:auto;'>";

    $text .= "<tr><td class='timetable-header' style='background:#".$tmeta->background.";color:#".$tmeta->colour.";' colspan='2'>".__("Timetable", "railtimetable").":&nbsp;".railtimetable_trans($tmeta->timetable)."</td>";

    $headers = explode(",", $tmeta->colsmeta);
    foreach ($headers as $header) {
        $text .= "<td style='background:#".$tmeta->background.";color:#".$tmeta->colour.";'>".railtimetable_trans($header)."</td>";
    }

    $text.= "</tr>";

    $text .= railtimetable_times_thalf($stations, "down", $attr['timetable']);
    $text .= "<tr><td colspan='".($tmeta->totaltrains+2)."'></td></tr>";
    $stations = array_reverse($stations);
    $text .= railtimetable_times_thalf($stations, "up", $attr['timetable']);
    $text .= "</table>";

    if (strlen($tmeta->html) > 0) {
        $text .= railtimetable_trans($tmeta->html);
    }

    $text .= "</div>";
    return $text;
}

function railtimetable_times_thalf($stations, $dir, $timetable) {
    global $wpdb;
    $text = "";
    foreach ($stations as $station) {
        $times = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_times WHERE timetable='".$timetable."' AND station=".$station->id);

        $text .= "<tr><td>".$station->name."</td>";

        $keydeps = $dir."_deps";
        $keyarrs = $dir."_arrs";

        if (strlen($times[0]->$keydeps) > 0) {
            $text .= "<td>".__("dep", "railtimetable")."</td>";
            $text .= railtimetable_times_gettimes($times[0]->$keydeps);
        }

        if (strlen($times[0]->$keyarrs) > 0) {
            $text .= "<td>".__("arr", "railtimetable")."</td>";
            $text .= railtimetable_times_gettimes($times[0]->$keyarrs);
        }

        $text .= "</tr>";
    }
    return $text;
}

function railtimetable_times_gettimes($times) {
    $times_arr = explode(',', $times);

    foreach ($times_arr as $time) {
        $text .= "<td>".__($time, "railtimetable")."</td>";
    }

    return $text;
}

function railtimetable_setlangage() {
    if (function_exists("pll_current_language")) {
        setlocale(LC_TIME, pll_current_language('locale'));
    }
}

function railtimetable_trans($str) {
    if (function_exists("pll__")) {
        return pll__($str);
    }
    return $str;
}

function railtimetable_today($attr) {
    global $wpdb;
    railtimetable_setlangage();

    $stations = explode(',', $attr['stations']);
    $times = array();
    $now = date("Y-m-d");
    $datetime = new DateTime('tomorrow');
    $tomorrow = $datetime->format('Y-m-d');

    // If it's after 15:00 then visitors probably want tomorrows train times.
    if (date('H') > 15) {
        $now = $tomorrow;
    }

    foreach ($stations as $index=>$station) {
        $results = railtimetable_timesforstation($station, "name", $now, ">=");

        if (!$results) {
            return $html.__("No trains today");
        }

        $times[$index] = $results[0];
    }

    $nextd = new DateTime($times[0]->date);
    $nextds = strftime("%e/%b/%Y", $nextd->getTimestamp());

    if ($times[0]->date == $now) {
        $heading .= __("Today's Trains", "railtimetable");
    }
    elseif ($times[0]->date == $tomorrow) {
        $heading .= __("Tomorrows's Trains", "railtimetable");
    }
    else {
        $heading .= __("Next trains on", "railtimetable")." ".$nextds;
    }

    $html = railtimetable_smalltimetable($times, $heading);

    return $html;
}

function railtimetable_smalltimetable($times, $heading, $extra = "") {

    $html = "<h4 style='text-align:center;margin-bottom:10px;'>".$heading."</h4>";
    $html .= $extra;
    $style = "style='vertical-align:top;padding:2px;background:#".$times[0]->background.";color:#".$times[0]->colour.";'";
    $html.="<table class='next-trains' ".$style."><tr><td ".$style.">".
        __("Timetable", "railtimetable")."</td><td ".$style.">".railtimetable_trans($times[0]->timetable, $lang)."</td></tr>";

    foreach ($times as $time) {
        $html .= "<tr><td ".$style.">".$time->name."</td><td ".$style.">";
        if (strlen($time->up_deps) > 0) {
            $html.= str_replace(",", ", ", $time->up_deps);
        } else {
            $html.= str_replace(",", ", ", $time->down_deps);
        }
        $html .= "</td></tr>";
    }

    $html .= "</table>";

    return $html;
}

function railtimetable_timesforstation($station, $stationfield, $date, $dateselector) {
    global $wpdb;
    $results = $wpdb->get_results("SELECT ".
        "wp_railtimetable_dates.date, ".
        "wp_railtimetable_dates.timetable, ".
        "wp_railtimetable_timetables.background, ".
        "wp_railtimetable_timetables.colour, ".
        "wp_railtimetable_timetables.html, ".
        "wp_railtimetable_times.up_deps, ".
        "wp_railtimetable_times.down_deps, ".
        "wp_railtimetable_stations.name ".
        "FROM `wp_railtimetable_dates` ".
        "LEFT JOIN wp_railtimetable_timetables ON wp_railtimetable_dates.timetable =  wp_railtimetable_timetables.timetable ".
        "LEFT JOIN wp_railtimetable_times ON wp_railtimetable_timetables.timetable = wp_railtimetable_times.timetable ".
        "LEFT JOIN wp_railtimetable_stations ON wp_railtimetable_times.station = wp_railtimetable_stations.id ".
        "WHERE wp_railtimetable_dates.date ".$dateselector." '".$date."' ".
        "AND wp_railtimetable_stations.".$stationfield." = '".$station."' ".
        "ORDER BY wp_railtimetable_dates.date ASC ".
        "LIMIT 1");
    return $results;
}

function railtimetable_events($attr) {
    return '<p><a href="/events"><img class="aligncenter size-small wp-image-697" '.
        ' src="/wp-content/uploads/2018/03/003_small.jpg" alt="" width="300" height="200" /></a></p>'.
        '<p>This will show the next special event (<strong>Gravity Train???</strong>) when we have a calendar from which to get it....</p>'.
        '<p>In the mean time please look at our empty <a href="/events">special events page</a>.</p>';
}

function railtimetable_script()
{
    wp_enqueue_script('jquery');

    wp_enqueue_script( 'jquery-ui-core' );
    wp_enqueue_script( 'jquery-ui-widget' );
    wp_enqueue_script( 'jquery-ui-mouse' );
    wp_enqueue_script( 'jquery-ui-accordion' );
    wp_enqueue_script( 'jquery-ui-autocomplete' );
    wp_enqueue_script( 'jquery-ui-slider' );
    wp_enqueue_script( 'jquery-ui-tabs' );
    wp_enqueue_script( 'jquery-ui-sortable' );
    wp_enqueue_script( 'jquery-ui-draggable' );
    wp_enqueue_script( 'jquery-ui-droppable' );
    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_script( 'jquery-ui-resize' );
    wp_enqueue_script( 'jquery-ui-dialog' );
    wp_enqueue_script( 'jquery-ui-button' );

    wp_enqueue_style('wp-jquery-ui');
    wp_enqueue_style( 'wp-jquery-ui-dialog' );

    wp_register_script('railtimetable_script', plugins_url('railtimetable/script.js'));
    wp_enqueue_script('railtimetable_script');
}

function railtimetable_style()
{
    global $wpdb;
    wp_register_style('railtimetable_style', plugins_url('railtimetable/style.css'));
    wp_enqueue_style('railtimetable_style');

    $timetables = $wpdb->get_results("SELECT id, timetable, colour, background FROM {$wpdb->prefix}railtimetable_timetables");
    $data = '';
    foreach ($timetables as $timetable) {
        $data .= ".railtimetable-".$timetable->timetable."{\n".
            "color:#".$timetable->colour.";\n".
            "background:#".$timetable->background.";\n".
            "}";
    }
    wp_add_inline_style('railtimetable_style', $data);
}

function railtimetable_load_textdomain() {
    load_plugin_textdomain( 'railtimetable' ); 

    if (function_exists('pll_register_string')) {
        global $wpdb;
        $events = $wpdb->get_results("SELECT id,title,description FROM {$wpdb->prefix}railtimetable_specialdates");
        foreach ($events as $event) {
            pll_register_string("railtimetable_title_".$event->id, $event->title, "railtimetable");
            pll_register_string("railtimetable_desc_".$event->id, $event->description, "railtimetable");
        }

        $tts = $wpdb->get_results("SELECT id,timetable,html,colsmeta FROM {$wpdb->prefix}railtimetable_timetables");
        foreach ($tts as $tt) {
            pll_register_string("railtimetable_timetable_".$tt->id, $tt->timetable, "railtimetable");
            $headers = explode(",", $tt->colsmeta);
            foreach ($headers as $index=>$header) {
                if (strlen($header) > 0) {
                    pll_register_string("railtimetable_header_".$tt->id."_".$index, $header, "railtimetable");
                }
            }
            if (strlen($tt->html) > 0) {
                pll_register_string("railtimetable_html_".$tt->id, $tt->html, "railtimetable");
            }
        }
    }

}

function railtimetable_popup() {
    if(strpos($_SERVER["REQUEST_URI"], 'railtimetable_popup') > 0) {
        global $wpdb;
        // Prevent SQL injection by parsing the date
        $date = DateTime::createFromFormat('Y-m-d', $_GET['date']);

        $found_events = $wpdb->get_results("SELECT id,title,link FROM {$wpdb->prefix}railtimetable_specialdates ".
            "WHERE '".$date->format('Y-m-d')."' >= start AND '".$date->format('Y-m-d')."' <= end");

        $extra = "";
        if ($found_events) {
            $extra .= "<div style='margin-top:1em;margin-bottom:1em;'><h5>".__("Special Event").": ";
            for ($loop=0; $loop<count($found_events); $loop++) {
                $extra .= "<a href='".$found_events[$loop]->link."'>".pll__($found_events[$loop]->title)."</a>";
                if ($loop < count($found_events)-1) {
                    $extra .= " & ";
                }
            }
            $extra .= "</h5></div>";
        }

        $first = railtimetable_timesforstation(0, "id", $date->format('Y-m-d'), "=");
        $last = railtimetable_timesforstation(2, "id", $date->format('Y-m-d'), "=");
        echo railtimetable_smalltimetable(array($first[0], $last[0]), __("Timetable for", "railtimetable")." ". strftime("%e/%b/%Y", $date->getTimestamp()), $extra);

        if (strlen($first[0]->html) > 0) {
            echo railtimetable_trans($first[0]->html);
        }

        exit();
   };

}

function railtimetable_nextspecial() {
    global $wpdb;

    
}

add_shortcode('railtimetable_show', 'railtimetable_show');
add_shortcode('railtimetable_times', 'railtimetable_times');
add_shortcode('railtimetable_today', 'railtimetable_today');
add_shortcode('railtimetable_events', 'railtimetable_events');

add_action( 'init', 'railtimetable_load_textdomain' );
add_action( 'wp_enqueue_scripts', 'railtimetable_script' );
add_action( 'wp_enqueue_scripts', 'railtimetable_style' );

add_action('parse_request', 'railtimetable_popup');

