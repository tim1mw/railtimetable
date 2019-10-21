<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Hook for adding admin menus
add_action('admin_menu', 'mt_add_pages');

// action function for above hook
function mt_add_pages() {
    // Add a new top-level menu (ill-advised):
    add_menu_page(__('Rail Timetable','railtimetable'), __('Rail Timetable','railtimetable'), 'manage_options', 'railtimetable-top-level-handle', 'railtimetable_edit' );

    add_submenu_page('railtimetable-top-level-handle', __('Edit Stations','railtimetable'), __('Edit Stations','railtimetable'), 'manage_options', 'railtimetable-edit-stations', 'railtimetable_edit_stations');

    add_submenu_page('railtimetable-top-level-handle', __('Edit Timetable','railtimetable'), __('Edit Timetable','railtimetable'), 'manage_options', 'railtimetable-edit-timetable', 'railtimetable_edit_timetable');

    add_submenu_page('railtimetable-top-level-handle', __('Edit Calendar','railtimetable'), __('Edit Calendar','railtimetable'), 'manage_options', 'railtimetable-edit-calendar', 'railtimetable_edit_calendar');
}

function railtimetable_edit() {
    ?>
    <h1>Heritage Railway Timetable</h1>
    <p>Show some kind of summary here....</p>

    <?php
    if (array_key_exists('action', $_POST)) {
        switch ($_POST['action']) {
            case 'convertevents':
                railtimetable_convertevents();
        }
    }
    ?>
    <form action='' method='post'>
    <input type='hidden' name='action' value='convertevents' />
    <input type='submit' value='Convert Events Tables' />
    </form>
    <?php
}

function railtimetable_edit_stations() {
    ?>
    <h1>Heritage Railway Timetable - Stations</h1>

    <?php
}

function railtimetable_edit_timetable() {
    ?>
    <h1>Heritage Railway Timetable - Edit Timetable</h1>
    <?php
}

function railtimetable_edit_calendar() {
    ?>
    <h1>Heritage Railway Timetable - Calendar</h1>
    <form method='post' action=''>
        <input type='hidden' name='action' value='filtercalendar' />    
        <table><tr>
            <td>Select Year</td>
            <td><?php echo railtimetable_getyearselect();?></td>
        </tr><tr>
            <td>Month</td>
            <td><?php echo railtimetable_getmonthselect();?></td>
        </tr><tr>
            <td></td>
            <td><input type='submit' value='Show timetable' /></td>
        </tr></table>
    </form>
    <hr />
    <?php

    if (array_key_exists('action', $_POST)) {
        switch ($_POST['action']) {
            case 'updatecalendar':
                railtimetable_updatecalendar();
            case 'filtercalendar':
                railtimetable_showcalendaredit($_POST['year'], $_POST['month']);
        }
    }
}


function railtimetable_getyearselect() {
    global $wpdb;
    $currentyear = intval(date("Y"));

    if (array_key_exists('year', $_POST)) {
        $chosenyear = $_POST['year'];
    } else {
        $chosenyear = $currentyear;
    }

    $firstdate = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_dates ORDER BY date ASC LIMIT 1 ");
    if ($firstdate) {
        $d = reset($firstdate);
        $startdate = intval(explode('-', $d->date)[0]);
    } else {
        $startdate = $currentyear;
    }
    $enddate = $currentyear + 2;

    $sel = "<select name='year'>";
    for ($loop = $startdate; $loop < $enddate; $loop++) {
        if ($loop == $chosenyear) {
            $s = ' selected="selected" ';
        } else {
            $s = '';
        }
        $sel .= "<option value='".$loop."'".$s.">".$loop."</option>";
    }
    $sel .= "</select>";
    return $sel;
}

function railtimetable_getmonthselect() {
    if (array_key_exists('month', $_POST)) {
        $chosenmonth = intval($_POST['month']);
    } else {
        $chosenmonth = 1;
    }

    $sel = "<select name='month'>";
    for($m=1; $m<=12; ++$m){
        if ($m == $chosenmonth) {
            $s = ' selected="selected" ';
        } else {
            $s = '';
        }
        $sel .= "<option value='".$m."'".$s.">".date('F', mktime(0, 0, 0, $m, 1))."</option>";
    }
    $sel .= "</select>";
    return $sel;
}

function railtimetable_showcalendaredit($year, $month) {
    global $wpdb;
    $daysinmonth = intval(date("t", mktime(0, 0, 0, $month, 1, $year)));

    $timetables = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_timetables");
    ?>
    <form method='post' action=''>
    <input type='hidden' name='action' value='updatecalendar' />
    <input type='hidden' name='year' value='<?php echo $year; ?>' />
    <input type='hidden' name='month' value='<?php echo $month; ?>' />
    <table>
    <?php
    for ($day = 1; $day < $daysinmonth + 1; $day++) {
        $time = mktime(0, 0, 0, $month, $day, $year);
        echo "<tr>".
            "<td>".date('l', $time)."</td>".
            "<td>".date('jS', $time)."</td>".
            "<td><select name='timetable_".$day."'>";

        $current = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_dates WHERE date = '".$year."-".$month."-".$day."'");
        if ($current) {
            $ct = reset($current);
            $tt = $ct->timetable;
            echo "<option value='none'>None</option>";
        } else {
            $tt = "none";
            echo "<option value='none' selected='selected'>None</option>";
        }

        for ($loop=0; $loop<count($timetables); $loop++) {
            if ($tt == $timetables[$loop]->timetable) {
                $s = " selected='selected' ";
            } else {
                $s = "";
            }
            echo "<option value='".$timetables[$loop]->timetable."'".$s.">".ucfirst($timetables[$loop]->timetable)."</option>";
        }

        echo "</select></td>".
            "</tr>\n";
    }
    ?>
    </table>
    <input type="submit" value="Update Timetable" />
    </form>
    <?php
}

function railtimetable_updatecalendar() {
    global $wpdb;

    $year = $_POST['year'];
    $month = $_POST['month'];
    $daysinmonth = intval(date("t", mktime(0, 0, 0, $month, 1, $year)));
    for ($day = 1; $day < $daysinmonth + 1; $day++) {
        $newtt = $_POST['timetable_'.$day];

        $current = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_dates WHERE date = '".$year."-".$month."-".$day."'");
        if ($current) {
            $rec = reset($current);
            // We have a timetable in the DB for this date. If the new tt is none, delete it, otherwise update if they don't match
            if ($newtt == "none") {
                $wpdb->delete($wpdb->prefix.'railtimetable_dates', array('id' => $rec->id));
            } else {
                if ($newtt != $rec->timetable) {
                    $wpdb->update($wpdb->prefix.'railtimetable_dates', array('timetable' => $newtt, 'date' => $year."-".$month."-".$day),  array('id' => $rec->id));
                }
            }
        } else {
            // There is no record at the moment, if timetable isn't none, we need to add one in otherwise no action needed
            if ($newtt != "none") {
                $wpdb->insert($wpdb->prefix.'railtimetable_dates', array('timetable' => $newtt, 'date' => $year."-".$month."-".$day));
            }
        }
    }
}

function railtimetable_convertevents() {
    global $wpdb;

    $found_events = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_specialdates ORDER BY start ASC");

    for ($loop=0; $loop<count($found_events); $loop++) {
        $this_event = $found_events[$loop];
        $checknew = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}railtimetable_eventdetails WHERE title = %s", $this_event->title));
        if ($checknew) {
            $id = reset($checknew)->id;
        } else {
            $link = json_encode(array('en' => $this_event->link_en, 'cy' => $this_event->link_cy));
            $newevt = array('title' => $this_event->title,
                'description' => $this_event->description,
                'link' => $link);
            $wpdb->insert($wpdb->prefix.'railtimetable_eventdetails', $newevt);
            $checknew = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$wpdb->prefix}railtimetable_eventdetails WHERE title= %s", $this_event->title));
            $id = reset($checknew)->id;
        }

        if ($this_event->start == $this_event->end) {
            $evtd = array('date' => $this_event->start, 'event' => $id);
            $wpdb->insert($wpdb->prefix.'railtimetable_eventdays', $evtd);
        } else {
            $begin = new DateTime($this_event->start);
            $end = new DateTime($this_event->end);

            $interval = DateInterval::createFromDateString('1 day');
            $period = new DatePeriod($begin, $interval, $end);

            foreach ($period as $dt) {
                $evtd = array('date' => $dt->format("Y-m-d"), 'event' => $id);
                $wpdb->insert($wpdb->prefix.'railtimetable_eventdays', $evtd);
            }
            $evtd = array('date' => $this_event->end, 'event' => $id);
            $wpdb->insert($wpdb->prefix.'railtimetable_eventdays', $evtd);
        }
    }
}
