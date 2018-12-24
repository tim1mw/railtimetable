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
    $calendar = new Calendar();

    $str="";
    for ($loop=intval($attr['start']); $loop<intval($attr['end'])+1; $loop++) {
        $str .= "<div class='calendar-box-wrapper'>".$calendar->draw(date($attr['year']."-".$loop."-01"))."</div>";
    }

    return "<div class='calendar-wrapper'>".$str."</div>";
}

function railtimetable_times($attr) {
    global $wpdb;
    $results = $wpdb->get_results("SELECT html FROM {$wpdb->prefix}railtimetable_timetables WHERE timetable='".$attr['timetable']."'");
    if ($results) {
        return $results[0]->html;
    }
}

function railtimetable_today($attr) {

    return '<p><a href="/events"><img class="aligncenter size-small wp-image-697" '.
    ' src="/wp-content/uploads/2018/11/Maid_Marian.jpg" alt="" width="1024" height="732" /></a></p>'.
    "<p>This will show today's trains when we have a calendar from which to get them.</p>".
    '<p>In the mean time please look at our empty <a href="/timetable">timetable page</a>....</p>';

}

function railtimetable_events($attr) {
    return '<p><a href="/events"><img class="aligncenter size-small wp-image-697" '.
        ' src="/wp-content/uploads/2018/03/003_small.jpg" alt="" width="300" height="200" /></a></p>'.
        '<p>This will show the next special event (<strong>Gravity Train???</strong>) when we have a calendar from which to get it....</p>'.
        '<p>In the mean time please look at our empty <a href="/events">special events page</a>.</p>';
}

function railtimetable_script()
{
    //wp_enqueue_script('jquery');
    wp_register_script('railtimetable_script', plugins_url('railtimetable/script.js'));
    wp_enqueue_script('railtimetable_script');
}

function railtimetable_style()
{
    wp_register_style('railtimetable_style', plugins_url('railtimetable/style.css'));
    wp_enqueue_style('railtimetable_style');
}

add_shortcode('railtimetable_show', 'railtimetable_show');
add_shortcode('railtimetable_times', 'railtimetable_times');
add_shortcode('railtimetable_today', 'railtimetable_today');
add_shortcode('railtimetable_events', 'railtimetable_events');

add_action( 'wp_enqueue_scripts', 'railtimetable_script' );
add_action( 'wp_enqueue_scripts', 'railtimetable_style' );

