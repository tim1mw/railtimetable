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

require_once(ABSPATH . 'wp-includes/pluggable.php');
require_once('calendar.php');
require_once('editlib.php');

function railtimetable_currentlang() {
    if (function_exists("pll_current_language")) {
        return "/".pll_current_language();
    }

    return "";
}

function railtimetable_currentlangfield() {
    if (function_exists("pll_current_language")) {
        return "link_".pll_current_language();
    }

    return "";
}

function railtimetable_currentlangcode() {
    if (function_exists("pll_current_language")) {
        return pll_current_language();
    }

    return "none";
}

function railtimetable_alllangcode() {
    if (function_exists("pll_languages_list")) {
        return pll_languages_list('locale');
    }

    return array('default' => '');;
}


function railtimetable_show($attr) {
    railtimetable_setlangage();
    $calendar = new Calendar();

    $start = explode('-', $attr['start']);
    $startyear = intval($start[0]);
    $startmonth = intval($start[1]);
    $end = explode('-', $attr['end']);
    $endyear = intval($end[0]);
    $endmonth = intval($end[1]);
    $stop = 13;

    $str="";
    for ($year=$startyear; $year<$endyear+1; $year++) {
        for ($month=$startmonth; $month<$stop; $month++) {
            $str .= "<div class='calendar-box-wrapper' id='railtimetable-cal-".$year."-".$month."'>".$calendar->draw(date($year."-".$month."-01"))."</div>";
         }
         $startmonth = 1;
         $stop = $endmonth+1;
    }

    $scroll = date("Y-n");

    $str .= '<script type="text/javascript">var baseurl = "'.railtimetable_currentlang()."/".get_site_url().'";var closetext="'.__("Close").'";var scrollto="'.$scroll.'"; initTrainTimes();</script>';
    $str .= '<div id="railtimetable-modal"></div>';

    return "<div class='calendar-wrapper' id='railtimetable-cal'>".$str."</div>";
}

function railtimetable_times_all($attr) {
    global $wpdb;
    $html = '<div class="timetabletabs" id="timetabletabs"><ul style="margin:0px;">';

    $tmetas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_timetables ORDER BY totaltrains ASC");
    $width = 100/count($tmetas);
    for($loop=0; $loop < count($tmetas); $loop++) {
        $html .= '<li class="railtimetable-'.$tmetas[$loop]->timetable.'"><a style="width:'.$width.'%" class="railtimetable-'.$tmetas[$loop]->timetable.'" href="#timetabletab'.$loop.'">'.railtimetable_trans($tmetas[$loop]->timetable).
            '</a></li>';
    }

    $html .= "</ul>";

    for($loop=0; $loop < count($tmetas); $loop++) {
        $html .= '<div id="timetabletab'.$loop.'" class="timetabletabs-div" style="border-color:#'.$tmetas[$loop]->background.';" >'.
            railtimetable_render_times($tmetas[$loop]).'</div>';
    }

    $html .= "<script type='text/javascript'>initAllTimetable();</script></div>";

    return $html;
}

function railtimetable_times($attr) {
    global $wpdb;

    $tmeta = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_timetables WHERE timetable='".$attr['timetable']."'");

    if (!$tmeta) {
        return __("Error: Unknown timetable", "railtimetable");
    }

    return railtimetable_render_times($tmeta[0]);
}

function railtimetable_render_times($tmeta) {
    global $wpdb;
    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");
    $text = "<div class='timetable-wrapper'><table style='margin-left:auto;margin-right:auto;'>";

    $text .= "<tr><td class='timetable-header' style='background:#".$tmeta->background.";color:#".$tmeta->colour.";' colspan='2'>".__("Timetable", "railtimetable").":&nbsp;".railtimetable_trans($tmeta->timetable)."</td>";

    $headers = explode(",", $tmeta->colsmeta);
    for ($loop=0; $loop < $tmeta->totaltrains; $loop++) {
        if (array_key_exists($loop, $headers)) {
            $header = railtimetable_trans($headers[$loop]);
        } else {
            $header = "";
        }
        $text .= "<td style='background:#".$tmeta->background.";color:#".$tmeta->colour.";'>".$header."</td>";
    }

    $text.= "</tr>";

    $text .= railtimetable_times_thalf($stations, "down", $tmeta->id);
    $text .= "<tr><td colspan='".($tmeta->totaltrains+2)."'></td></tr>";
    $stations = array_reverse($stations);
    $text .= railtimetable_times_thalf($stations, "up", $tmeta->id);
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
        $times = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_times WHERE timetableid='".$timetable."' AND station=".$station->sequence);

        $keyarrs = $dir."_arrs";
        $keydeps = $dir."_deps";

        if (strlen($times[0]->$keyarrs) > 0) {
            $text .= "<tr><td>".$station->name."</td>";
            $text .= "<td>".__("arr", "railtimetable")."</td>";
            $text .= railtimetable_times_gettimes($times[0]->$keyarrs);
            $text .= "</tr>";
        }

        if (strlen($times[0]->$keydeps) > 0) {
            $text .= "<tr><td>".$station->name."</td>";
            $text .= "<td>".__("dep", "railtimetable")."</td>";
            $text .= railtimetable_times_gettimes($times[0]->$keydeps);
            $text .= "</tr>";
        }
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

    // If it's after 17:00 then visitors probably want tomorrows train times.
    if (date('H') > 17) {
        $now = $tomorrow;
    }

    $nextdate = false;
    foreach ($stations as $index=>$station) {
        $results = railtimetable_timesforstation($station, "name", $now, ">=");
        if ($results) {
            $times[$index] = $results[0];
        }
        $nextdate = $results[0]->date;
    }

    // 
    if ($nextdate != $now) {
        if ($nextdate) {
            $extra = " AND {$wpdb->prefix}railtimetable_eventdays.date < '".$nextdate."'";
        } else {
            $extra = "";
        }

        $found_events = $wpdb->get_results("SELECT {$wpdb->prefix}railtimetable_eventdays.date, {$wpdb->prefix}railtimetable_eventdetails.* FROM {$wpdb->prefix}railtimetable_eventdays LEFT JOIN {$wpdb->prefix}railtimetable_eventdetails ON {$wpdb->prefix}railtimetable_eventdays.event = {$wpdb->prefix}railtimetable_eventdetails.id WHERE {$wpdb->prefix}railtimetable_eventdays.date >= '".$now."'".$extra." ORDER BY {$wpdb->prefix}railtimetable_eventdays.date ASC LIMIT 1", OBJECT );

        if ($found_events) {
            $linkfield = railtimetable_currentlangcode();

            $firstdate = false;
            for ($loop = 0; $loop < count($found_events); $loop++) {
                $links = json_decode($found_events[$loop]->link);
                $evtdate = Datetime::createFromFormat('Y-m-d', $found_events[$loop]->date);
                $date = strftime("%e-%b-%Y", $evtdate->getTimestamp());
                $html .= "<a class='timetable-special-front-head' href='".$links->$linkfield."'>".__($found_events[$loop]->title)." - ".$date."</a><p>".__($found_events[$loop]->description)."</p>";
            }

            if ($now == $tomorrow && $found_events[0]->date == $tomorrow) {
                return "<h4 style='text-align:center;margin-bottom:10px;'>".__("Tomorrow's Trains")."</h4>".$html;
            } 
            elseif ($times[0]->date == $now) {
                return "<h4 style='text-align:center;margin-bottom:10px;'>".__("Today's Trains")."</h4>".$html;
            } else {
                return "<h4 style='text-align:center;margin-bottom:10px;'>".__("Next Trains")."</h4>".$html;
            }
        }
    }

    // If we have gotten this far with no times and no events there are no trains to show. 
    if (count($results) == 0) {
        if ($now == $tomorrow) {
            return "<h4 style='text-align:center;margin-bottom:10px;'>".__("No trains tomorrow")."</h4>";
        } else {
            return "<h4 style='text-align:center;margin-bottom:10px;'>".__("No trains today")."</h4>";
        }
    }

    $nextd = new DateTime($times[0]->date);
    $nextds = strftime("%e-%b-%Y", $nextd->getTimestamp());

    if ($now == $tomorrow && $times[0]->date == $tomorrow) {
        $heading .= __("Tomorrow's Trains", "railtimetable");
    }
    elseif ($times[0]->date == $now) {
        $heading .= __("Today's Trains", "railtimetable");
    }
    else {
        $heading .= __("Next trains on", "railtimetable")." ".$nextds;
    }

    $html = railtimetable_smalltimetable($times, $heading);

    return $html;
}

function railtimetable_smalltimetable($times, $heading, $extra = "") {
    railtimetable_setlangage();
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
    if (strlen($times[0]->html) > 0) {
        $html .= "<p style='margin-top:0px;padding-top:0px;text-align:right;font-size:small;'>".$times[0]->html."</p>";
    }
    return $html;
}

function railtimetable_timesforstation($station, $stationfield, $date, $dateselector) {
    global $wpdb;
    $results = $wpdb->get_results("SELECT ".
        "wp_railtimetable_dates.date, ".
        "wp_railtimetable_timetables.timetable, ".
        "wp_railtimetable_timetables.background, ".
        "wp_railtimetable_timetables.colour, ".
        "wp_railtimetable_timetables.html, ".
        "wp_railtimetable_times.up_deps, ".
        "wp_railtimetable_times.down_deps, ".
        "wp_railtimetable_stations.name ".
        "FROM `wp_railtimetable_dates` ".
        "LEFT JOIN wp_railtimetable_timetables ON wp_railtimetable_dates.timetableid =  wp_railtimetable_timetables.id ".
        "LEFT JOIN wp_railtimetable_times ON wp_railtimetable_timetables.id = wp_railtimetable_times.timetableid ".
        "LEFT JOIN wp_railtimetable_stations ON wp_railtimetable_times.station = wp_railtimetable_stations.sequence ".
        "WHERE wp_railtimetable_dates.date ".$dateselector." '".$date."' ".
        "AND wp_railtimetable_stations.".$stationfield." = '".$station."' ".
        "ORDER BY wp_railtimetable_dates.date ASC ".
        "LIMIT 1");
    return $results;
}

function railtimetable_events($attr) {
    global $wpdb;
    railtimetable_setlangage();
    $now = date("Y-m-d");

    // If it's after 19:00 then visitors probably want the next event.
    if (date('H') > 19) {
        $datetime = new DateTime('tomorrow');
        $now = $datetime->format('Y-m-d');
    }

    $found_events = $wpdb->get_results("SELECT {$wpdb->prefix}railtimetable_eventdays.date, {$wpdb->prefix}railtimetable_eventdetails.* FROM {$wpdb->prefix}railtimetable_eventdays LEFT JOIN {$wpdb->prefix}railtimetable_eventdetails ON {$wpdb->prefix}railtimetable_eventdays.event = {$wpdb->prefix}railtimetable_eventdetails.id WHERE {$wpdb->prefix}railtimetable_eventdays.date > '".$now."' ORDER BY date,event ASC");

    $extra = "";
    $linecount = 0;
    if ($found_events) {
        $extra .= "<table>";
        for ($loop=0; $loop<count($found_events) && $linecount<$attr['number']; $loop++) {
            $start = Datetime::createFromFormat('Y-m-d', $found_events[$loop]->date);
            $dates = array(strftime("%e-%b-%Y", $start->getTimestamp()));

            $linkfield = railtimetable_currentlangcode();
            $links = json_decode($found_events[$loop]->link);

            for ($iloop=$loop+1; $iloop<count($found_events); $iloop++) {
                if ($found_events[$loop]->id != $found_events[$iloop]->id) {
                    $loop = $iloop - 1;
                    break;
                } else {
                    $evtdate = Datetime::createFromFormat('Y-m-d', $found_events[$iloop]->date);
                    $dates[] = strftime("%e-%b-%Y", $evtdate->getTimestamp());
                }
            }
            $date = implode(', ', $dates);

            $extra .= "<tr><td><a class='timetable-special-front' href='".$links->$linkfield."'> ".pll__($found_events[$loop]->title)."</a></td><td>".$date."</td></tr>";
            $linecount ++;

            // If we have two events with the same ID at the end, we'll get a duplicate without this check.
            if ($iloop == count($found_events)) {
                break;
            }
        }
        $extra .= "</table>";
    }

    return $extra;
}

function railtimetable_events_full($attr) {
    global $wpdb;
    railtimetable_setlangage();
    if (is_array($attr) && array_key_exists('start', $attr)) {
        $start = $attr['start'];
    } else {
        $start = date("Y")."-01-01";
    }

    if (is_array($attr) && array_key_exists('end', $attr)) {
        $end = $attr['end'];
    } else {
        $end = date("Y")."-12-31";
    }

    $found_events = $wpdb->get_results("SELECT {$wpdb->prefix}railtimetable_eventdays.date, {$wpdb->prefix}railtimetable_eventdetails.* FROM {$wpdb->prefix}railtimetable_eventdays LEFT JOIN {$wpdb->prefix}railtimetable_eventdetails ON {$wpdb->prefix}railtimetable_eventdays.event = {$wpdb->prefix}railtimetable_eventdetails.id WHERE date >= '".$start."' AND date <= '".$end."' ORDER BY date,event ASC");

    $extra = "";
    if ($found_events) {
        $extra .= "<table>";
        for ($loop=0; $loop<count($found_events); $loop++) {
            $evtdate = Datetime::createFromFormat('Y-m-d', $found_events[$loop]->date);
            $dates = array(strftime("%e-%b-%Y", $evtdate->getTimestamp()));

            $linkfield = railtimetable_currentlangcode();
            $links = json_decode($found_events[$loop]->link);

            for ($iloop=$loop+1; $iloop<count($found_events); $iloop++) {
                if ($found_events[$loop]->id != $found_events[$iloop]->id) {
                    $loop = $iloop - 1;
                    break;
                } else {
                    $evtdate = Datetime::createFromFormat('Y-m-d', $found_events[$iloop]->date);
                    $dates[] = strftime("%e-%b-%Y", $evtdate->getTimestamp());
                }
            }
            $date = implode(', ', $dates);
            $extra .= "<tr><td><a class='timetable-special-front' href='".$links->$linkfield."'> ".pll__($found_events[$loop]->title)."</a></td><td>".$date."</td></tr>";

            // If we have two events with the same ID at the end, we'll get a duplicate without this check.
            if ($iloop == count($found_events)) {
                break;
            }
        }
        $extra .= "</table>";
    }

    return $extra;
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
    wp_enqueue_style( 'wp-jquery-ui-tabs' );

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
        $events = $wpdb->get_results("SELECT id,title,description FROM {$wpdb->prefix}railtimetable_eventdetails");
        foreach ($events as $event) {
            pll_register_string("railtimetable_title_".$event->id, $event->title, "railtimetable");
            //pll_register_string("railtimetable_desc_".$event->id, $event->description, "railtimetable");
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

        $found_events = $wpdb->get_results("SELECT {$wpdb->prefix}railtimetable_eventdays.date, {$wpdb->prefix}railtimetable_eventdetails.* FROM {$wpdb->prefix}railtimetable_eventdays LEFT JOIN {$wpdb->prefix}railtimetable_eventdetails ON {$wpdb->prefix}railtimetable_eventdays.event = {$wpdb->prefix}railtimetable_eventdetails.id WHERE {$wpdb->prefix}railtimetable_eventdays.date = '".$date->format('Y-m-d')."'");

        $extra = "";
        $linkfield = railtimetable_currentlangcode();
        if ($found_events) {
            $extra .= "<div style='margin-top:1em;margin-bottom:1em;'><h5>".__("Special Event").": ";
            for ($loop=0; $loop<count($found_events); $loop++) {
                $links = json_decode($found_events[$loop]->link);
                $extra .= "<a href='".$links->$linkfield."'>".pll__($found_events[$loop]->title)."</a>";
                if ($loop < count($found_events)-1) {
                    $extra .= " & ";
                }
            }
            $extra .= "</h5></div>";
        }

        // Get the first station
        $numstations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");

        $first = railtimetable_timesforstation(0, "sequence", $date->format('Y-m-d'), "=");
        $last = railtimetable_timesforstation($numstations - 1, "sequence", $date->format('Y-m-d'), "=");
        echo railtimetable_smalltimetable(array($first[0], $last[0]), __("Timetable for", "railtimetable")." ". strftime("%e-%b-%Y", $date->getTimestamp()), $extra);

        if (strlen($first[0]->html) > 0) {
            echo railtimetable_trans($first[0]->html);
        }

        exit();
   };

}


add_shortcode('railtimetable_show', 'railtimetable_show');
add_shortcode('railtimetable_times', 'railtimetable_times');
add_shortcode('railtimetable_times_all', 'railtimetable_times_all');
add_shortcode('railtimetable_today', 'railtimetable_today');
add_shortcode('railtimetable_events', 'railtimetable_events');
add_shortcode('railtimetable_events_full', 'railtimetable_events_full');

add_action( 'init', 'railtimetable_load_textdomain' );
add_action( 'wp_enqueue_scripts', 'railtimetable_script' );
add_action( 'wp_enqueue_scripts', 'railtimetable_style' );

add_action('parse_request', 'railtimetable_popup');

?>
