<?php


/**
 * Simple PHP Calendar Class.
 *
 * @copyright  Copyright (c) Benjamin Hall
 * @license https://github.com/benhall14/php-calendar
 * @package protocols
 * @version 1.1
 * @author Benjamin Hall <https://linkedin.com/in/benhall14>
*/
class Calendar
{
    /**
     * The internal date pointer.
     * @var DateTime
     */
    private $date;


    /**
     * Find the timetable from the database
     * @param  DateTime $date The date to match a timetable for.
     * @return array          Either an array of events or false.
     */
    private function findTimetable(DateTime $date)
    {
        global $wpdb;
        $found_events = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_dates ".
            "LEFT JOIN {$wpdb->prefix}railtimetable_timetables ON ".
            " {$wpdb->prefix}railtimetable_dates.timetableid = {$wpdb->prefix}railtimetable_timetables.id ".
            "WHERE {$wpdb->prefix}railtimetable_dates.date = '".$date->format('Y-m-d')."'", OBJECT );

        if (count($found_events) > 0) {
            return $found_events[0];
        } else {
            return false;
        }
    }

    /**
     * Find special events in the database
     * @param  DateTime $date The date to match an event for.
     * @return array          Either an array of events or false.
     */
    private function findSpecialEvents(DateTime $date)
    {
        global $wpdb;
        $tdate = $date->format('Y-m-d');
        $found_events = $wpdb->get_results("SELECT {$wpdb->prefix}railtimetable_eventdays.date, {$wpdb->prefix}railtimetable_eventdetails.* FROM {$wpdb->prefix}railtimetable_eventdays LEFT JOIN {$wpdb->prefix}railtimetable_eventdetails ON {$wpdb->prefix}railtimetable_eventdays.event = {$wpdb->prefix}railtimetable_eventdetails.id WHERE {$wpdb->prefix}railtimetable_eventdays.date = '".$tdate."'", OBJECT );

        return ($found_events) ? : false;
    }

    /**
     * Draw the calendar and echo out.
     * @param string $date    The date of this calendar.
     * @param string $format  The format of the preceding date.
     * @return string         The calendar
     */
    public function draw($date = false)
    {
        $calendar = '';

        if ($date) {
            $date = DateTime::createFromFormat('Y-m-d', $date);
            $date->modify('first day of this month');
        } else {
            $date = new DateTime();
            $date->modify('first day of this month');
        }

        $today = new DateTime();
        $total_days_in_month = (int) $date->format('t');
        $calendar .= '<table class="calendar">';
        $calendar .= '<thead>';
        $calendar .= '<tr class="calendar-title">';
        $calendar .= '<th colspan="7">';
        $calendar .= strftime('%B %Y', $date->getTimestamp());
        $calendar .= '</th>';
        $calendar .= '</tr>';
        $calendar .= '<tr class="calendar-header">';
        $calendar .= '<th>';
        $calendar .= implode('</th><th>', $this->daysofweek());
        $calendar .= '</th>';
        $calendar .= '</tr>';
        $calendar .= '</thead>';
        $calendar .= '<tbody>';
        $calendar .= '<tr>';

        # padding before the month start date IE. if the month starts on Wednesday
        for ($x = 0; $x < $date->format('w'); $x++) {
            $calendar .= '<td class="pad"> </td>';
        }

        $running_day = clone $date;
        $running_day_count = 1;
        $rowcount = 0;

        do {
            $timetable = $this->findTimetable($running_day);
            $specials = $this->findSpecialEvents($running_day);
            $class = '';
            $style = '';
            $event_summary = '';

            if ($timetable) {
                $style .= "background:#".$timetable->background.";color:#".$timetable->colour.";";
                //$event_summary = ;
            }

            if ($specials) {
                $class .= " calendar-special ";
                for ($loop=0; $loop< count($specials); $loop++) {
                    $event_summary .= railtimetable_trans($specials[$loop]->title);
                    if (strlen($specials[$loop]->background) > 0) {
                        $style .= "background:#".$specials[$loop]->background.";";
                    }
                    if (strlen($specials[$loop]->background) > 0 || strlen($specials[$loop]->colour) > 0) {
                        $style .= "color:#".$specials[$loop]->colour.";";
                    }
                    if ($loop < count($specials)-1) {
                        $event_summary .= " & ";
                    }
                }
            }

            $today_class = ($running_day->format('Y-m-d') == $today->format('Y-m-d')) ? ' today' : '';
            $calendar .= '<td style="'.$style.'" class="' . $class . $today_class . ' day" title="' . htmlentities($event_summary) . '">';

            if ($timetable) {
                $calendar .= "<a style='".$style."'  href=\"javascript:showTrainTimes('".$running_day->format("Y-m-d")."');\">";
                $calendar .= $running_day->format('j');
                $calendar .= '</a>';
            } else {
                if ($specials) {
                    $calendar .= "<a style='".$style."'  href=\"".railtimetable_get_lang_url(end($specials))."\">";
                    $calendar .= $running_day->format('j');
                    $calendar .= '</a>';
                } else {
                    $calendar .= $running_day->format('j');
                }
            }
            $calendar .= "</td>";

            # check if this calendar-row is full and if so push to a new calendar row
            if ($running_day->format('w') == 6) {
                $calendar .= '</tr>';

                # start a new calendar row if there are still days left in the month
                if (($running_day_count + 1) <= $total_days_in_month) {
                    $calendar .= '<tr>';
                }

                # reset padding because its a new calendar row
                $day_padding_offset = 0;
            }

            $running_day->modify('+1 Day');

            $running_day_count++;
        } while ($running_day_count <= $total_days_in_month);

        $padding_at_end_of_month = 7 - $running_day->format('w');

        # padding at the end of the month
        if ($padding_at_end_of_month && $padding_at_end_of_month < 7) {
            for ($x = 1; $x <= $padding_at_end_of_month; $x++) {
                $calendar .= '<td class="pad"> </td>';
            }
        }

        $calendar .= '</tr>';

        $calendar .= '</tbody>';
        $calendar .= '</table>';

        return $calendar;
    }

    private function daysofweek($chars = 1) {
        $timestamp = strtotime('next Sunday');
        $days = array();
        for ($i = 0; $i < 7; $i++) {
            $days[] = substr(strftime('%A', $timestamp), 0, 1);
            $timestamp = strtotime('+1 day', $timestamp);
        }
        return $days;
    }
}
 
