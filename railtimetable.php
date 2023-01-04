<?php

/**
 * Plugin Name: Heritage Railway Timetable
 * Plugin URI:  http://www.autotrain.org
 * Description: The title says it all!
 * Author:      Tim Williams, AutoTrain (tmw@autotrain.org)
 * Author URI:  http://www.autotrain.org
 * Version:     0.0.2
 * Text Domain: railtimetable
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
**/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once(ABSPATH . 'wp-includes/pluggable.php');
require_once('calendar.php');
require_once('editlib.php');

// Install the DB
register_activation_hook( __FILE__, 'railtimetable_create_db' );
add_action( 'upgrader_process_complete', 'railtimetable_create_db', 10, 2 );

function railtimetable_currentlang() {
    if (function_exists("pll_current_language")) {
        return "/".pll_current_language();
    }

    return railtimetable_default_locale();
}

function railtimetable_currentlangfield() {
    if (function_exists("pll_current_language")) {
        return "link_".pll_current_language();
    }

    return railtimetable_default_locale();
}

function railtimetable_currentlangcode() {
    if (function_exists("pll_current_language")) {
        return pll_current_language();
    }

    return railtimetable_default_locale();
}

function railtimetable_alllangcode() {
    if (function_exists("pll_languages_list")) {
        return pll_languages_list('locale');
    }

    return array(0 => railtimetable_default_locale());;
}

function railtimetable_default_locale() {
    return explode('_', get_locale())[0];
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

    $str .= '<script type="text/javascript">var baseurl = "'.railtimetable_currentlang()."/".get_site_url().'";var closetext="'.__("Close", "railtimetable").'";var scrollto="'.$scroll.'"; initTrainTimes();</script>';
    $str .= '<div id="railtimetable-modal"></div>';

    return "<div class='calendar-wrapper' id='railtimetable-cal'>".$str."</div>";
}

function railtimetable_times_all($attr) {
    global $wpdb;
    $tmetas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_timetables WHERE hidden = 0 ORDER BY totaltrains ASC");
    if (count($tmetas) == 0) {
        return __("The timetable is empty");
    }

    $html = '<div class="timetabletabs" id="timetabletabs"><ul style="margin:0px;">';
    $width = 100/count($tmetas);
    for($loop=0; $loop < count($tmetas); $loop++) {
        $html .= '<li class="railtimetable-'.$tmetas[$loop]->timetable.'"><a style="width:'.$width.'%" class="railtimetable-'.railtimetable_get_tt_style($tmetas[$loop]->timetable).'" href="#timetabletab'.$loop.'">'.railtimetable_trans($tmetas[$loop]->timetable).
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

function railtimetable_get_tt_style($tt) {
    //$tt = str_replace(' ', '_', $tt);
    //$tt = str_replace('&', '-', $tt);
    $tt = preg_replace('/[^A-Za-z0-9._-]/', '', $tt);
    $tt = strtolower($tt);
    return $tt;
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
    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations WHERE hidden = 0 ORDER BY sequence ASC");
    $text = "<div class='timetable-wrapper'><table style='margin-left:auto;margin-right:auto;'>";

    $text .= "<tr><td class='timetable-header' style='background:#".$tmeta->background.";color:#".$tmeta->colour.";' colspan='2'>".__("Timetable", "railtimetable").":&nbsp;".railtimetable_trans($tmeta->timetable)."</td>";

    $showrules = get_option('railtimetable_show_rules');


    $headers = json_decode($tmeta->colsmeta);
    for ($loop=0; $loop < $tmeta->totaltrains; $loop++) {
        if (array_key_exists($loop, $headers)) {
            $header = railtimetable_trans($headers[$loop]->notes);  
            if ($showrules) {
                $header .= railtimetable_ruleforcolumn($headers[$loop]->rules);
            } 
        } else {
            $header = "";
        }
        $text .= "<td class='timetable-header-notes' style='background:#".$tmeta->background.";color:#".$tmeta->colour.";'>".$header."</td>";
    }

    $text.= "</tr>";

    $text .= railtimetable_times_thalf($stations, "down", $tmeta->id);
    $text .= "<tr><td colspan='".($tmeta->totaltrains+2)."'></td></tr>";
    $stations = array_reverse($stations);
    $text .= railtimetable_times_thalf($stations, "up", $tmeta->id);
    $text .= "</table>";

    if (strlen($tmeta->html) > 0) {
        $text .= railtimetable_trans(stripslashes($tmeta->html));
    }

    $rqstations = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}railtimetable_stations WHERE hidden = 0 AND requeststop = 1");
    if ($rqstations > 0) {
        $text .= "&#x271D; ".__('Request Stop', 'railtimetable');
    }

    $text .= "</div>";
    return $text;
}

function railtimetable_ruleforcolumn($rules) {
    $not = array();
    $only = array();

    foreach ($rules as $rule) {
        switch ($rule->code) {
            case '*':
                $only[] = railtimetable_interpretstring($rule->str);
                break;
            case '!':
                $not[] = railtimetable_interpretstring($rule->str);
                break;
        }
    }

    $r = "";
    if (count($not) > 0) {
        $r .= __("Not", "railtimetable")." ".implode(',<br />', $not);
    }

    if (count($only) > 0) {
        if (strlen($r) > 0) {
            $r .= "<br />";
        }
        $r .= __("Runs", "railtimetable")." ".implode(', ', $only)."<br />";
    }

    return $r;
}

function railtimetable_interpretstring($str) {
    $strl = strlen($str);
    switch ($strl) {
        case 1:
            $days = array(false, 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays', 'Sundays');
            return __($days[$str], "railtimetable");
        case 8:
            $date = DateTime::createFromFormat("Ymd", $str);
            return strftime(get_option('railtimetable_date_format'), $date->getTimestamp());
    }
    return "Invalid";
}

function railtimetable_times_thalf($stations, $dir, $timetable) {
    global $wpdb;
    $text = "";
    foreach ($stations as $station) {
        $times = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stntimes WHERE timetableid='".$timetable."' AND station=".$station->id);

        if (count($times) == 0) {
            continue;
        }

        $keyarrs = $dir."_arrs";
        $keydeps = $dir."_deps";

        $timearrs = json_decode($times[0]->$keyarrs);
        $timedeps = json_decode($times[0]->$keydeps);

        if ($station->requeststop == 1) {
            $rs = '&nbsp;<sup>&#x271D</sup>';
        } else {
            $rs = '';
        }
        if ($station->closed == 1) {
            $class = " class='timetable-station-closed'";
            $closed = "<span class='timetable-station-closedtext'>&nbsp;(".__('Closed', 'railtimetable').")</span>";
        } else {
            $class = "";
            $closed = "";
        }

        if (count($timearrs) > 0) {
            $text .= "<tr ".$class."><td title='".htmlspecialchars($station->description, ENT_QUOTES)."'>".$station->name.$rs.$closed."</td>";
            $text .= "<td>".__("arr", "railtimetable")."</td>";
            $text .= railtimetable_times_gettimes($timearrs);
            $text .= "</tr>";
        }

        if (count($timedeps) > 0) {
            $text .= "<tr ".$class."><td title='".htmlspecialchars($station->description, ENT_QUOTES)."'>".$station->name.$rs.$closed."</td>";
            $text .= "<td>".__("dep", "railtimetable")."</td>";
            $text .= railtimetable_times_gettimes($timedeps);
            $text .= "</tr>";
        }
    }
    return $text;
}

function railtimetable_times_gettimes($times_arr) {
    $fmt = get_option('railtimetable_time_format');
    $text = '';
    foreach ($times_arr as $time) {
        $text .= "<td class='timetable-time-cell'>".railtimetable_format_time($time, $fmt)."</td>";
    }
    return $text;
}

function railtimetable_format_time($time, $fmt) {
    if (strlen($time->hour) == 0 && strlen($time->min) == 0) {
        return "-";
    }
    $timeobj = new DateTime();
    $timeobj->setTime($time->hour, $time->min);
    return strftime($fmt, $timeobj->getTimestamp());
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
    $timezone = new \DateTimeZone(get_option('timezone_string'));
    $now = new DateTime();
    $now->setTimezone($timezone);
    $now = $now->format("Y-m-d");
    $datetime = new DateTime('tomorrow');
    $datetime->setTimezone($timezone);
    $tomorrow = $datetime->format('Y-m-d');

    // If it's after 18:00 then visitors probably want tomorrows train times.
    $hour = new DateTime();
    $hour->setTimezone($timezone);
    $hour = $hour->format('H');

    if ($hour >= 18) {
        $now = $tomorrow;
    }

    $nextdate = false;
    foreach ($stations as $index=>$station) {
        $results = railtimetable_timesforstation($station, "name", $now, ">=");
        if ($results) {
            $times[$index] = $results[0];
            $nextdate = $results[0]->date;
        }
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

            $firstdate = false;
            for ($loop = 0; $loop < count($found_events); $loop++) {
                $evtdate = Datetime::createFromFormat('Y-m-d', $found_events[$loop]->date);
                $date = strftime(get_option('railtimetable_date_format'), $evtdate->getTimestamp());
                $html .= "<a class='timetable-special-front-head' href='".railtimetable_get_lang_url($found_events[$loop])."'>".
                    railtimetable_trans($found_events[$loop]->title)." - ".$date."</a><p>".
                    railtimetable_trans($found_events[$loop]->description)."</p>";
            }

            if ($now == $tomorrow && $found_events[0]->date == $tomorrow) {
                return "<h4 style='text-align:center;margin-bottom:10px;'>".__("Tomorrow's Trains", "railtimetable")."</h4>".$html;
            } 
            elseif ($times[0]->date == $now) {
                return "<h4 style='text-align:center;margin-bottom:10px;'>".__("Today's Trains", "railtimetable")."</h4>".$html;
            } else {
                if ($nextdate == $tomorrow) {
                    return "<h4 style='text-align:center;margin-bottom:10px;'>".__("Tomorrow's Trains", "railtimetable")."</h4>".$html;
                } else {
                    return "<h4 style='text-align:center;margin-bottom:10px;'>".__("Next Trains", "railtimetable")."</h4>".$html;
                }
            }
        }
    }

    // If we have gotten this far with no times and no events there are no trains to show. 
    if (count($results) == 0) {
        if ($now == $tomorrow) {
            return "<h4 style='text-align:center;margin-bottom:10px;'>".__("No trains tomorrow", "railtimetable")."</h4>";
        } else {
            return "<h4 style='text-align:center;margin-bottom:10px;'>".__("No trains today", "railtimetable")."</h4>";
        }
    }

    $nextd = new DateTime($times[0]->date);
    $nextds = strftime(get_option('railtimetable_date_format'), $nextd->getTimestamp());
    $adate = null;
    $heading = "";
    
    if ($now == $tomorrow && $times[0]->date == $tomorrow) {
        $heading = __("Tomorrow's Trains", "railtimetable");
        $adate = $tomorrow;
    }
    elseif ($times[0]->date == $now) {
        $heading = __("Today's Trains", "railtimetable");
        $adate = $now;
    }
    else {
        if ($nextd->format('Y-m-d') == $tomorrow) {
            $heading = __("Tomorrow's Trains", "railtimetable");
            $adate = $tomorrow;
        } else {
            $heading = __("Next Trains", "railtimetable")." ".$nextds;
            $adate = $nextds;
        }
    }

    $html = railtimetable_smalltimetable($times, $heading, $adate);

    return $html;
}

function railtimetable_smalltimetable($times, $heading, $fordate, $extra = "", $buylink = false) {
    railtimetable_setlangage();
    $html = "<h4 class='timetable-smallheading'>".$heading."</h4>";
    $html .= $extra;
    $style = "style='background:#".$times[0]->background.";color:#".$times[0]->colour.";'";
    $html.="<table class='next-trains' ".$style."><tr><td class='next-trains-cell' ".$style.">".
        __("Timetable", "railtimetable")."</td><td class='next-trains-cell' ".$style.">".railtimetable_trans($times[0]->timetable)."</td></tr>";

    $fmt = get_option('railtimetable_time_format');
    foreach ($times as $time) {
        $html .= "<tr><td class='next-trains-cell' ".$style." title='".$time->description."'>".$time->name."</td><td class='next-trains-cell' ".$style.">";
        $updeps = railtimetable_processtimes($time->up_deps, $time->colsmeta, $fordate);
        if (count($updeps) > 0) {
            $str = "";
            foreach ($updeps as $tt) {
                $str .= railtimetable_format_time($tt, $fmt).", ";
            }
            $html .= substr($str, 0, strlen($str)-2);
        } else {
            $t = railtimetable_processtimes($time->down_deps, $time->colsmeta, $fordate);
            $str = "";
            foreach ($t as $tt) {
                $str .= railtimetable_format_time($tt, $fmt).", ";
            }
            $html .= substr($str, 0, strlen($str)-2);
        }
        $html .= "</td></tr>";
    }
    $html .= "</table>";

    if ($buylink) {
        $html .= "<div class='timetable-buytickets-wrapper'>".$buylink."</div>";
    }

    if (strlen($times[0]->html) > 0) {
        $html .= "<p class='timetable-smallnotes'>".railtimetable_trans($times[0]->html)."</p>";
    }

    return $html;
}

function railtimetable_processtimes($times, $colsmeta, $date) {
    $filtered = array();
    $meta = json_decode($colsmeta);
    $times = json_decode($times);
    $count = 0;
    if (!$date instanceof DateTime) {
        $date = DateTime::createFromFormat("Y-m-d", $date);
    }

    foreach ($times as $time) {
        $rules = $meta[$count]->rules;
        $count++;
        foreach ($rules as $rule) {

            switch ($rule->code) {
                case '*':
                    if (railtimetable_isruleforday($rule->str, $date)) {
                        $filtered[] = $time;  
                        continue 3;
                    }
                    break;
                case '!':
                    if (railtimetable_isruleforday($rule->str, $date)) {  
                        continue 3;
                    }
                    break;
            }
        }
        $filtered[] = $time;
    }
    return $filtered;
}

function railtimetable_isruleforday($str, $date) {
    $len = strlen($str);
    switch ($len) {
        case 1:
            if ($date->format("N") == $str) {
                return true;
            }
            break;
        case 8:
            $tdate = DateTime::createFromFormat("Ymd", $str);
            if ($tdate == $date) {
                return true;
            }
            break;
    }
    return false;
}

function railtimetable_timesforstation($station, $stationfield, $date, $dateselector) {
    global $wpdb;
    $sql = "SELECT ".
        "wp_railtimetable_dates.date, ".
        "wp_railtimetable_timetables.timetable, ".
        "wp_railtimetable_timetables.background, ".
        "wp_railtimetable_timetables.colour, ".
        "wp_railtimetable_timetables.html, ".
        "wp_railtimetable_timetables.buylink, ".
        "wp_railtimetable_timetables.colsmeta, ".
        "wp_railtimetable_stntimes.up_deps, ".
        "wp_railtimetable_stntimes.down_deps, ".
        "wp_railtimetable_stations.name, ".
        "wp_railtimetable_stations.description ".
        "FROM `wp_railtimetable_dates` ".
        "LEFT JOIN wp_railtimetable_timetables ON wp_railtimetable_dates.timetableid =  wp_railtimetable_timetables.id ".
        "LEFT JOIN wp_railtimetable_stntimes ON wp_railtimetable_timetables.id = wp_railtimetable_stntimes.timetableid ".
        "LEFT JOIN wp_railtimetable_stations ON wp_railtimetable_stntimes.station = wp_railtimetable_stations.id ".
        "WHERE wp_railtimetable_dates.date ".$dateselector." '".$date."' ".
        "AND wp_railtimetable_stations.".$stationfield." = '".$station."' ".
        "ORDER BY wp_railtimetable_dates.date ASC ".
        "LIMIT 1";

    return $wpdb->get_results($sql);
}

function railtimetable_events($attr) {
    global $wpdb;
    railtimetable_setlangage();
    $now = date("Y-m-d");
    $now = new DateTime();
    $timezone = new \DateTimeZone(get_option('timezone_string'));
    $now->setTimezone($timezone);
    $now = $now->format("Y-m-d");
    $hour = new DateTime();
    $hour->setTimezone($timezone);
    $hour = $hour->format('H');

    // If it's after 18:00 then visitors probably want the next event.
    if ($hour >= 18) {
        $datetime = new DateTime('tomorrow');
        $now = $datetime->format('Y-m-d');
    }

    $found_events = $wpdb->get_results("SELECT {$wpdb->prefix}railtimetable_eventdays.date, {$wpdb->prefix}railtimetable_eventdetails.* FROM {$wpdb->prefix}railtimetable_eventdays LEFT JOIN {$wpdb->prefix}railtimetable_eventdetails ON {$wpdb->prefix}railtimetable_eventdays.event = {$wpdb->prefix}railtimetable_eventdetails.id WHERE {$wpdb->prefix}railtimetable_eventdays.date >= '".$now."' ORDER BY date,event ASC");

    $extra = "";
    $linecount = 0;
    if ($found_events) {
        $extra .= "<table>";
        for ($loop=0; $loop<count($found_events) && $linecount<$attr['number']; $loop++) {
            $start = Datetime::createFromFormat('Y-m-d', $found_events[$loop]->date);
            $dates = array(strftime(get_option('railtimetable_date_format'), $start->getTimestamp()));

            for ($iloop=$loop+1; $iloop<count($found_events); $iloop++) {
                if ($found_events[$loop]->id != $found_events[$iloop]->id) {
                    $loop = $iloop - 1;
                    break;
                } else {
                    $evtdate = Datetime::createFromFormat('Y-m-d', $found_events[$iloop]->date);
                    $dates[] = strftime(get_option('railtimetable_date_format'), $evtdate->getTimestamp());
                }
            }
            $date = implode(', ', $dates);

            $extra .= "<tr><td><a class='timetable-special-front' href='".railtimetable_get_lang_url($found_events[$loop])."'> ".railtimetable_trans($found_events[$loop]->title)."</a></td><td>".$date."</td></tr>";
            $linecount ++;

            // If we have two events with the same ID at the end, we'll get a duplicate without this check.
            if ($iloop == count($found_events)) {
                break;
            }
        }
        $extra .= "</table>";
    } else {
        $extra .= "<p class='timetable-smallheading timetable-special-front'>".__("No Upcoming Events", "railtimetable")."</p>";
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
            $dates = array(strftime(get_option('railtimetable_date_format'), $evtdate->getTimestamp()));

            for ($iloop=$loop+1; $iloop<count($found_events); $iloop++) {
                if ($found_events[$loop]->id != $found_events[$iloop]->id) {
                    $loop = $iloop - 1;
                    break;
                } else {
                    $evtdate = Datetime::createFromFormat('Y-m-d', $found_events[$iloop]->date);
                    $dates[] = strftime(get_option('railtimetable_date_format'), $evtdate->getTimestamp());
                }
            }
            $date = implode(', ', $dates);
            $extra .= "<tr><td><a class='timetable-special-front' href='".railtimetable_get_lang_url($found_events[$loop])."'> ".railtimetable_trans($found_events[$loop]->title)."</a></td><td>".$date."</td></tr>";

            // If we have two events with the same ID at the end, we'll get a duplicate without this check.
            if ($iloop == count($found_events)) {
                break;
            }
        }
        $extra .= "</table>";
    }

    return $extra;
}

function railtimetable_get_lang_url($item) {
    // Are we using an external URL?
    if ($item->page == -1) {
        return $item->link;
    }
    if (function_exists("pll_get_post")) {
        return get_the_permalink(pll_get_post($item->page));
    }
    return get_the_permalink($item->page);
}

function railtimetable_events_buy($attrs) {
    global $wpdb;
    railtimetable_setlangage();

    $found_events = $wpdb->get_results("SELECT {$wpdb->prefix}railtimetable_eventdays.date, {$wpdb->prefix}railtimetable_eventdetails.* FROM {$wpdb->prefix}railtimetable_eventdays LEFT JOIN {$wpdb->prefix}railtimetable_eventdetails ON {$wpdb->prefix}railtimetable_eventdays.event = {$wpdb->prefix}railtimetable_eventdetails.id WHERE {$wpdb->prefix}railtimetable_eventdetails.id = ".$attrs['id']." ORDER BY date ASC");

    $html = "<div class='timetable-buytickets-list'>";
    foreach ($found_events as $event) {
        $evtdate = Datetime::createFromFormat('Y-m-d', $event->date);
        $date = strftime(get_option('railtimetable_date_format'), $evtdate->getTimestamp());
        $html .= "<span class='timetable-buytickets-list-span'>".get_buylink($event->buylink, $evtdate->getTimestamp(), $date, '')."</span> ";
    }
    $html .= "</div>";

    return $html;
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
        $data .= ".railtimetable-".railtimetable_get_tt_style($timetable->timetable)."{\n".
            "color:#".$timetable->colour.";\n".
            "background:#".$timetable->background.";\n".
            "}";
    }
    wp_add_inline_style('railtimetable_style', $data);
}

function railtimetable_load_textdomain() {
    //load_plugin_textdomain( 'railtimetable' ); 

    $domain = 'railtimetable';
    $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

    // wp-content/languages/your-plugin/your-plugin-de_DE.mo
    load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );

    // wp-content/plugins/your-plugin/languages/your-plugin-de_DE.mo
    load_plugin_textdomain( $domain, FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );


    if (function_exists('pll_register_string')) {
        global $wpdb;
        $events = $wpdb->get_results("SELECT id,title,description FROM {$wpdb->prefix}railtimetable_eventdetails");
        foreach ($events as $event) {
            pll_register_string("railtimetable_title_".$event->id, $event->title, "railtimetable");
            pll_register_string("railtimetable_desc_".$event->id, $event->description, "railtimetable");
        }

        $tts = $wpdb->get_results("SELECT id,timetable,html,colsmeta FROM {$wpdb->prefix}railtimetable_timetables");
        foreach ($tts as $tt) {
            pll_register_string("railtimetable_timetable_".$tt->id, $tt->timetable, "railtimetable");
            $headers = json_decode($tt->colsmeta);
            foreach ($headers as $index=>$header) {
                if (strlen($header->notes) > 0) {
                    pll_register_string("railtimetable_header_".$tt->id."_".$index, $header->notes, "railtimetable");
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
        $buylink = false;
        if ($found_events) {
            $extra .= "<div class='timetable-popupevent'><h5>".__("Special Event", "railtimetable").":<br />";
            for ($loop=0; $loop<count($found_events); $loop++) {
                $extra .= "<a href='".railtimetable_get_lang_url($found_events[$loop])."'>".railtimetable_trans($found_events[$loop]->title)."</a>";
                if ($loop < count($found_events)-1) {
                    $extra .= " & ";
                }
                if (strlen($found_events[$loop]->buylink) > 0 && !$buylink) {
                    $buylink = get_buylink($found_events[$loop]->buylink, $date->getTimestamp());
                }
            }
            $extra .= "</h5></div>";
        }

        // Get the first and last stations
        $firstid = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}railtimetable_stations WHERE hidden = 0 AND closed = 0 ORDER BY sequence ASC LIMIT 1");
        $lastid = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}railtimetable_stations WHERE hidden = 0 AND closed = 0 ORDER BY sequence DESC LIMIT 1");
        $first = railtimetable_timesforstation($firstid, "sequence", $date->format('Y-m-d'), "=");
        $last = railtimetable_timesforstation($lastid, "id", $date->format('Y-m-d'), "=");
        if (!$buylink) {
            if (strlen($first[0]->buylink) >0) {
                $buylink = get_buylink($first[0]->buylink, $date->getTimestamp());
            }
        }

        echo railtimetable_smalltimetable(array($first[0], $last[0]), __("Timetable for", "railtimetable")."<br />".
            strftime(get_option('railtimetable_date_format'), $date->getTimestamp()), $date, $extra, $buylink);

        exit();
   };

}

function get_buylink($buylink, $datestamp, $text = false, $class = 'timetable-buytickets') {
    if (!$text) {
        $text = __("Buy Tickets", "railtimetable");
    }

    preg_match('/\[[^]]+\]/', $buylink, $matches);

    if (count($matches) > 0) {
        $fmt = substr($matches[0], 1, -1);
        $time = trim(strftime($fmt, $datestamp));
        $buylink = preg_replace('/\[[^]]+\]/', $time, $buylink);
    }

    return "<a class='".$class."' href='".$buylink."'>".$text."</a>";
}

// Method to disable lame emjoi substitution on WP core. WP Should not be making this decison for me and it's messing up
// my use of symbols.
function disable_wp_emojicons() {

  // all actions related to emojis
  remove_action( 'admin_print_styles', 'print_emoji_styles' );
  remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
  remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
  remove_action( 'wp_print_styles', 'print_emoji_styles' );
  remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
  remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
  remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );

  // filter to remove TinyMCE emojis
  //add_filter( 'tiny_mce_plugins', 'disable_emojicons_tinymce' );
}

add_action( 'init', 'disable_wp_emojicons' );

add_shortcode('railtimetable_show', 'railtimetable_show');
add_shortcode('railtimetable_times', 'railtimetable_times');
add_shortcode('railtimetable_times_all', 'railtimetable_times_all');
add_shortcode('railtimetable_today', 'railtimetable_today');
add_shortcode('railtimetable_events', 'railtimetable_events');
add_shortcode('railtimetable_events_full', 'railtimetable_events_full');
add_shortcode('railtimetable_events_buy', 'railtimetable_events_buy');

add_action( 'init', 'railtimetable_load_textdomain' );
add_action( 'wp_enqueue_scripts', 'railtimetable_script' );
add_action( 'wp_enqueue_scripts', 'railtimetable_style' );

add_action('parse_request', 'railtimetable_popup');

?>
