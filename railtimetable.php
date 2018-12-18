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

add_action( 'wp_enqueue_scripts', 'railtimetable_script' );
add_action( 'wp_enqueue_scripts', 'railtimetable_style' );

