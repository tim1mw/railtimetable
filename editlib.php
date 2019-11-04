<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Hook for adding admin menus
add_action('admin_menu', 'mt_add_pages');

// action function for above hook
function mt_add_pages() {
    // Add a new top-level menu (ill-advised):
    add_menu_page(__('Rail Timetable','railtimetable'), __('Rail Timetable','railtimetable'), 'manage_options', 'railtimetable-top-level-handle', 'railtimetable_edit' );

    add_submenu_page('railtimetable-top-level-handle', __('Edit Stations','railtimetable'), __('Edit Stations','railtimetable'), 'manage_options', 'railtimetable-edit-stations', 'railtimetable_edit_stations');

    add_submenu_page('railtimetable-top-level-handle', __('Edit Timetables','railtimetable'), __('Edit Timetables','railtimetable'), 'manage_options', 'railtimetable-edit-timetable', 'railtimetable_edit_timetables');

    add_submenu_page('railtimetable-top-level-handle', __('Edit Events','railtimetable'), __('Edit Events','railtimetable'), 'manage_options', 'railtimetable-edit-events', 'railtimetable_edit_events');

    add_submenu_page('railtimetable-top-level-handle', __('Edit Calendar','railtimetable'), __('Edit Calendar','railtimetable'), 'manage_options', 'railtimetable-edit-calendar', 'railtimetable_edit_calendar');
}

function railtimetable_edit() {
    global $wpdb;
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
    if ($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}railtimetable_eventdetails" ) == 0) {
    ?>
    <form action='' method='post'>
    <input type='hidden' name='action' value='convertevents' />
    <input type='submit' value='Convert Events Tables' />
    </form>
    <?php
    }
}

function railtimetable_edit_stations() {
    global $wpdb;

    if (array_key_exists('action', $_POST)) {
        switch ($_POST['action']) {
            case 'editstations':
                railtimetable_updatestations();
        }
    }

    ?>
    <h1>Heritage Railway Timetable - Stations</h1>
    <form method='post' action=''>
    <input type='hidden' name='action' value='editstations' />
    <table><tr><th>Station</th><th>Actions</th><tr>
    <?php
    $ids = array();
    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");
    $count = count($stations);
    for ($loop = 0; $loop < $count; $loop++) {
        $ids[] = $stations[$loop]->id;
        echo "<tr>".
            "<td>".($stations[$loop]->sequence+1).
            ": <input type='text' name='station_name_".$stations[$loop]->id."' size='25' value='".$stations[$loop]->name."' /></td><td>";

        echo "<button name='station_del_".$stations[$loop]->id."'>X</button>&nbsp;&nbsp;";

        if ($stations[$loop]->sequence < $count -1) {
            echo "<button name='station_move_".$stations[$loop]->id."' value='1'>&darr;</button>";
        } else {
           echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        }

        if ($stations[$loop]->sequence > 0) {
            echo "<button name='station_move_".$stations[$loop]->id."' value='-1'>&uarr;</button>";
        }

        echo "</td></tr>";
    }
    ?>
    <tr><th colspan="">New Station</th></tr>
    <tr>
        <td><input type='text' size='25' name='station_name_new' value='' /></td>
        <td></td>
    </tr>
    </table>
    <input type='hidden' name='ids' value='<?php echo implode(",",$ids) ?>' />
    <input type="submit" value="Update Stations" />
    </form>
    <?php
}

function railtimetable_updatestations() {
    global $wpdb;

    if (strlen( $_POST['ids'] > 0)) {
        $ids = explode(',', $_POST['ids']);
    } else {
        $ids = array();
    }

    foreach ($ids as $id) {
        if (array_key_exists('station_del_'.$id, $_POST)) {
            $wpdb->delete($wpdb->prefix.'railtimetable_stations',  array('id' => $id));
            // Now fix the sequence....
            $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");

            for ($loop = 0; $loop < count($stations); $loop++) {

                $wpdb->update($wpdb->prefix.'railtimetable_stations', array('sequence' => $loop),  array('id' => $stations[$loop]->id));
            }
            continue;
        }

        $params = array('name' => $_POST['station_name_'.$id]);
        if (array_key_exists('station_move_'.$id, $_POST)) {
            $inc = intval($_POST['station_move_'.$id]);
            $current = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations WHERE id = '".$id."'")[0];
            $swap = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations WHERE sequence = '".($current->sequence+$inc)."'")[0];
            $wpdb->update($wpdb->prefix.'railtimetable_stations', array('sequence' => $current->sequence),  array('id' => $swap->id));
            $params['sequence'] = $swap->sequence;
        }
        $wpdb->update($wpdb->prefix.'railtimetable_stations', $params,  array('id' => $id));
    }

    if (strlen($_POST['station_name_new']) > 0) {
        $wpdb->insert($wpdb->prefix.'railtimetable_stations', array('name' => trim($_POST['station_name_new']), 'sequence' => count($ids)));
    }
}

function railtimetable_edit_timetables() {
    global $wpdb;

    if (array_key_exists('action', $_POST)) {
        switch ($_POST['action']) {
            case 'edittimetable':
                railtimetable_updatetimetable();
                break;
            case 'edittimes':
                railtimetable_updatetimes();
                break;
        }
    }

    // Do we have any edit or delete actions?
    if (array_key_exists('edit', $_POST)) {
        $tt = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_timetables WHERE id='".$_POST['edit']."' ");
        echo "<h2>Update Timetable Details</h2>";
        railtimetable_edit_timetable($_POST['edit'], $tt->timetable, $tt->background, $tt->colour, $tt->totaltrains, $tt->html, "Update Timetable" );
        return;
    }

    // Do we have any edit or delete actions?
    if (array_key_exists('edittimes', $_POST)) {
        railtimetable_edit_times($_POST['edittimes']);
        return;
    }

    if (array_key_exists('del', $_POST)) {
        $tt = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_timetables WHERE id='".$_POST['del']."' ");
        if (array_key_exists('confirm', $_POST)) {
            $wpdb->delete($wpdb->prefix."railtimetable_timetables", array('id' => $_POST['del']));
            $wpdb->delete($wpdb->prefix."railtimetable_times", array('timetableid' => $_POST['del']));
            $wpdb->delete($wpdb->prefix."railtimetable_dates", array('timetableid' => $_POST['del']));
            ?>
            <h2>"<?php echo $tt->timetable; ?>" Deleted.</h2>
            <?php
        } else {
            ?>
            <form method='post' action=''>
                <h2>Are you sure you want to delete the "<?php echo $tt->timetable; ?>" timetable ?</h2>
                <input type='hidden' name='confirm' value='1' />
                <input type='hidden' name='del' value='<?php echo $tt->id; ?>' />
                <input type='submit' value='Delete Event' />
            </form>
            <?php
            return;
        }
    }
    ?>
    <h1>Heritage Railway Timetable - Edit Timetables</h1>
    <form method='post' action=''>
    <table><tr><th>Timetable</th><th>Actions</th><tr>
    <?php
    $timetables = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_timetables ORDER BY totaltrains ASC");
    $count = count($timetables);
    for ($loop = 0; $loop < $count; $loop++) {
        ?>
        <tr>
            <th style='text-transform:capitalize;color:#<?php echo $timetables[$loop]->colour;?>;background:#<?php echo $timetables[$loop]->background;?>;'>
                <?php echo $timetables[$loop]->timetable; ?></th>
            <td>
                <button type='hidden' name='edit' value='<?php echo $timetables[$loop]->id; ?>'>Edit Details</button>
                <button type='hidden' name='edittimes' value='<?php echo $timetables[$loop]->id; ?>'>Edit Times</button>
                <button type='hidden' name='del' value='<?php echo $timetables[$loop]->id; ?>'>Delete</button>
            </td>
        </tr>
        <?php
    }
    ?>
    </table>
    </form>
    <h2>Add New Timetable</h2>
    <?php
    railtimetable_edit_timetable();
}

function railtimetable_edit_times($id) {
    global $wpdb;

    $tt = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_timetables WHERE id='".$id."' ");

    echo "<h2>Update Timetable Times: <span style='text-transform:capitalize'>".$tt->timetable."</span></h2>".
        "<form method='post' action=''>\n".
        "<input type='hidden' name='action' value='edittimes' />".
        "<input type='hidden' name='id' value='".$id."' />";

    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");
    $numstations = count($stations);
    if ($tt->colsmeta == false) {
        $tt->colsmeta = "";
    }
    railtimetable_edit_times_single($id, $stations, $numstations, "down", $tt->totaltrains, $tt->colsmeta);
    echo "<br/><br/><br/>\n";
    railtimetable_edit_times_single($id, $stations, $numstations, "up", $tt->totaltrains);
    echo "<input type='submit' value='Update Times' />".
        "</form>\n";
}

function railtimetable_edit_times_single($id, $stations, $numstations, $direction, $totaltrains, $colsmeta=false) {
    global $wpdb;
    echo "<table>\n";

    if ($direction == "up") {
        $startid = $numstations-1;
        $endid = 0;
        $inc = -1;
    } else {
        $startid = 0;
        $endid = $numstations-1;
        $inc = 1;
    }

    if ($colsmeta !== false) {
        echo "<tr><td>Notes</td><td></td>\n";
        railtimetable_edit_times_line($totaltrains, $colsmeta, 'notes_');
        echo "</tr>\n";
    }

    for ($loop = $startid; $loop>-1 && $loop<$numstations; $loop=$loop+$inc) {
        $station = $stations[$loop];
        $times = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_times WHERE timetableid='".$id."' AND station='".$station->sequence."'");
        if (!$times) {
            $times = new stdclass();
            $times->down_deps="";
            $times->down_arrs="";
            $times->up_deps="";
            $times->up_arrs="";
        }

        if ($station->sequence != $startid) {
            echo "<tr><th>".$station->name."</th><td>arr</td>\n";
            $key = $direction."_arrs";
            railtimetable_edit_times_line($totaltrains, $times->$key, $key."_".$station->sequence."_");
        }

        if ($station->sequence != $endid) {
            echo "<tr><th>".$station->name."</th><td>dep</td>\n";
            $key = $direction."_deps";
            railtimetable_edit_times_line($totaltrains, $times->$key, $key."_".$station->sequence."_");
        }
    }
    echo "</table>\n";
}

function railtimetable_edit_times_line($totaltrains, $line, $key) {
    $cols = explode(',',$line);
    for ($loop = 0; $loop < $totaltrains; $loop++) {
        if (array_key_exists($loop, $cols)) {
            echo "<td><input type='text' name='".$key.$loop."' value='".htmlentities($cols[$loop], ENT_QUOTES)."' size='6' /></td>\n";
        } else {
            echo "<td><input type='text' name='".$key.$loop."' value='' size='6' /></td>\n";
        }
    }
}

function railtimetable_updatetimes() {
    global $wpdb;
    $id = intval($_POST['id']);
    $tt = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_timetables WHERE id='".$id."' ");
    $notes = railtimetable_get_updatetimes("notes_", $tt->totaltrains, false);
    $wpdb->update($wpdb->prefix.'railtimetable_timetables', array('colsmeta' => $notes), array('id' => $id));

    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");
    //$numstations = count($stations);
    //$timetables = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_times WHERE timetableid='".$id."'");
    foreach ($stations as $station) {
        $down_deps = railtimetable_get_updatetimes('down_deps_'.$station->sequence.'_', $tt->totaltrains);
        $down_arrs = railtimetable_get_updatetimes('down_arrs_'.$station->sequence.'_', $tt->totaltrains);
        $up_deps = railtimetable_get_updatetimes('up_deps_'.$station->sequence.'_', $tt->totaltrains);
        $up_arrs = railtimetable_get_updatetimes('up_arrs_'.$station->sequence.'_', $tt->totaltrains);

        $timesrow = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}railtimetable_times WHERE station=".$station->sequence." AND timetableid=".$tt->id);
        if ($timesrow) {
            $wpdb->update($wpdb->prefix.'railtimetable_times',
                array('down_deps' => $down_deps, 'down_arrs' => $down_arrs, 'up_deps' => $up_deps, 'up_arrs' => $up_arrs),
                array('id' => $timesrow));
        } else {
            $wpdb->insert($wpdb->prefix.'railtimetable_times',
                array('station' => $station->sequence, 'timetableid' => $tt->id, 'down_deps' => $down_deps, 'down_arrs' => $down_arrs, 'up_deps' => $up_deps, 'up_arrs' => $up_arrs));
        }
    }
}

function railtimetable_get_updatetimes($key, $totaltrains) {
    $allempty = true;
    $strs = array();
    for ($loop=0; $loop < $totaltrains; $loop++) {
        $strs[$loop] = trim(stripslashes($_POST[$key.$loop]));
        if (strlen($strs[$loop]) > 0) {
            $allempty = false;
        }
    }

    if ($allempty) {
        $data = '';
    } else {
        $data = implode(',',$strs);
    }

    return $data;
}

function railtimetable_edit_timetable($id=-1, $timetable="", $background ="", $colour = "", $totaltrains = 1, $notes = "", $button="Add Timetable") {
    ?>
    <form method='post' action=''>
        <input type='hidden' name='action' value='edittimetable' />
        <input type='hidden' name='id' value='<?php echo htmlspecialchars($id); ?>' /> 
        <table><tr>
            <td>Timetable</td>
            <td><input type='text' name='timetable' size='12' value='<?php echo htmlspecialchars($timetable, ENT_QUOTES); ?>' /></td>
        </tr><tr>
            <td>Total Trips</td>
            <td><select name='totaltrains'><?php
                for ($loop=1; $loop<25; $loop++) {
                    if ($totaltrains == $loop) {
                        $s = " selected='selected' ";
                    } else {
                        $s = "";
                    }
                    echo "<option value='".$loop."'".$s.">".$loop."</option>";
                }
            ?></select></td> 
        </tr><tr>
            <td>Notes</td>
            <td><textarea name='html' cols='80' rows='3'><?php echo htmlspecialchars($notes, ENT_QUOTES); ?></textarea></td>
        </tr><tr>
            <td>Background colour</td>
            <td><input type='text' name='background' size='6' value='<?php echo htmlspecialchars($background); ?>' /></td>
        </tr><tr>
            <td>Text colour</td>
            <td><input type='text' name='colour' size='6' value='<?php echo htmlspecialchars($colour); ?>' /></td>
        </tr><tr>
            <td></td>
            <td><input type='submit' value='<?php echo $button; ?>' /></td>
        </tr></table>
    </form>
    <?php
}

function railtimetable_updatetimetable() {
    global $wpdb;

    $params = array('timetable' => strtolower(stripslashes($_POST['timetable'])), 'html' => stripslashes($_POST['html']), 'background' => trim($_POST['background']), 'colour' => trim($_POST['colour']), 'totaltrains' => intval($_POST['totaltrains']));
    if (intval($_POST['id']) > -1) {
        $wpdb->update($wpdb->prefix.'railtimetable_timetables', $params,
            array('id' => $_POST['id']));
    } else {
        $wpdb->insert($wpdb->prefix.'railtimetable_timetables', $params);
    }
}

function railtimetable_edit_events() {
    global $wpdb;

    if (array_key_exists('action', $_POST)) {
        if ($_POST['action'] == 'editevent') {
            $langs = railtimetable_alllangcode();
            $links = array();
            foreach ($langs as $lang) {
                $links[$lang] = $_POST["link_".$lang];
            }

            $linksjson = json_encode($links);
            $params = array('title' => stripslashes($_POST['title']), 'description' => stripslashes($_POST['desc']), 'link' => $linksjson, 'background' => trim($_POST['background']), 'colour' => trim($_POST['colour']));
            $id = intval($_POST['id']);
            if ($id > -1) {
                $wpdb->update($wpdb->prefix.'railtimetable_eventdetails', $params,
                    array('id' => $id));
            } else {
                $wpdb->insert($wpdb->prefix.'railtimetable_eventdetails', $params);
            }
        }
    }

    // Do we have any edit or delete actions?
    if (array_key_exists('edit', $_POST)) {
        $evt = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_eventdetails WHERE id='".$_POST['edit']."' ");
        echo "<h2>Update Event</h2>";
        railtimetable_edit_event($_POST['edit'], $evt->title, $evt->description, $evt->link, $evt->background, $evt->colour, $button="Update Event");
        return;
    }

    if (array_key_exists('del', $_POST)) {
        $evt = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_eventdetails WHERE id='".$_POST['del']."' ");
        if (array_key_exists('confirm', $_POST)) {
            $wpdb->delete($wpdb->prefix."railtimetable_eventdetails", array('id' => $_POST['del']));
            $wpdb->delete($wpdb->prefix."railtimetable_eventdays", array('event' => $_POST['del']));
            ?>
            <h2>"<?php echo $evt->title; ?>" Deleted.</h2>
            <?php
        } else {
            ?>
            <form method='post' action=''>
                <h2>Are you sure you want to delete the event "<?php echo $evt->title; ?>" ?</h2>
                <input type='hidden' name='confirm' value='1' />
                <input type='hidden' name='del' value='<?php echo $evt->id; ?>' />
                <input type='submit' value='Delete Event' />
            </form>
            <?php
            return;
        }
    }

    ?>
    <h1>Heritage Railway Timetable - Edit Events</h1>
    <form method='post' action=''>
    <table><tr>
        <th>Event Name</th>
        <th>Actions</th>
    </tr>
    <?php
    $events = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_eventdetails");
    for ($loop = 0; $loop < count($events); $loop++) {
        ?>
        <tr>
            <td><?php echo $events[$loop]->title; ?></td>
            <td>
                <button type='hidden' name='edit' value='<?php echo $events[$loop]->id; ?>'>Edit</button>
                <button type='hidden' name='del' value='<?php echo $events[$loop]->id; ?>'>Delete</button>
            </td>
        </tr>
        <?php
    }
    ?>
    </table>
    </form>
    <hr />
    <?php

    ?><h2>Add new event</h2><?php
    railtimetable_edit_event();
}

function railtimetable_edit_event($id=-1, $title="", $desc="", $linkjson="", $bg ="", $colour = "", $button="Add Event") {
    ?>
    <form method='post' action=''>
        <input type='hidden' name='action' value='editevent' />
        <input type='hidden' name='id' value='<?php echo htmlspecialchars($id); ?>' /> 
        <table><tr>
            <td>Title</td>
            <td><input type='text' name='title' size='50' value='<?php echo htmlspecialchars($title, ENT_QUOTES); ?>' /></td>
        </tr><tr>
            <td>Description</td>
            <td><textarea name='desc' cols='80' rows='5'><?php echo htmlspecialchars($desc, ENT_QUOTES); ?></textarea></td>
        </tr><?php
            if (strlen($linkjson) > 0) {
                $links = json_decode($linkjson);
            } else {
                $langs = railtimetable_alllangcode();
                $links = array();
                foreach ($langs as $lang) {
                    $links[$lang] = '';
                }
            }

            foreach ($links as $linklang => $linkvalue) {
                echo "<tr>\n<td>Link ".$linklang."</td>\n".
                    "<td><input type='text' size='80' value='".$linkvalue."' name='link_".$linklang."' /></td>".
                    "</tr>";
            }

        ?><tr>
            <td>Background colour</td>
            <td><input type='text' name='background' size='6' value='<?php echo htmlspecialchars($bg); ?>' /></td>
        </tr><tr>
            <td>Text colour</td>
            <td><input type='text' name='colour' size='6' value='<?php echo htmlspecialchars($colour); ?>' /></td>
        </tr><tr>
            <td></td>
            <td><input type='submit' value='<?php echo $button; ?>' /></td>
        </tr></table>
    </form>
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
    $events = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_eventdetails");
    ?>
    <form method='post' action=''>
    <input type='hidden' name='action' value='updatecalendar' />
    <input type='hidden' name='year' value='<?php echo $year; ?>' />
    <input type='hidden' name='month' value='<?php echo $month; ?>' />
    <table>
    <tr><th>Day</th><th>Date</th><th>Timetable</th><th>Events</th></tr>
    <tr>
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
            $tt = $ct->timetableid;
            echo "<option value='none'>None</option>";
        } else {
            $tt = "none";
            echo "<option value='none' selected='selected'>None</option>";
        }

        for ($loop=0; $loop<count($timetables); $loop++) {
            if ($tt == $timetables[$loop]->id) {
                $s = " selected='selected' ";
            } else {
                $s = "";
            }
            echo "<option value='".$timetables[$loop]->id."'".$s.">".ucfirst($timetables[$loop]->timetable)."</option>";
        }

        echo "</select></td>\n";
        
        $todaysevents = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_eventdays WHERE date = '".$year."-".$month."-".$day."'");
        for ($loop=0; $loop<count($todaysevents); $loop++) {
            railtimetable_showoptlist($events, $day, $todaysevents[$loop]->id, $todaysevents[$loop]->event);
        }

        railtimetable_showoptlist($events, $day);
        
        echo "</tr>\n";
    }
    ?>
    </table>
    <input type="submit" value="Update Timetable" />
    </form>
    <?php
}

function railtimetable_showoptlist($events, $day, $evtno = 'n', $selected = -1) {
    echo "<td><select name='event_".$day."_".$evtno."'>";

    if ($selected >= 0) {
        echo "<option value='-1'>None</option>";
    } else {
        echo "<option value='-1' selected='selected'>None</option>";
    }

    for ($loop=0; $loop<count($events); $loop++) {
        if ($selected == $events[$loop]->id) {
            $s = " selected='selected' ";
        } else {
            $s = "";
        }
        echo "<option value='".$events[$loop]->id."'".$s.">".$events[$loop]->title."</option>";  
    }
    echo "</select></td>";
}

function railtimetable_updatecalendar() {
    global $wpdb;

    $year = $_POST['year'];
    $month = $_POST['month'];
    $daysinmonth = intval(date("t", mktime(0, 0, 0, $month, 1, $year)));
    for ($day = 1; $day < $daysinmonth + 1; $day++) {
        // Do the timetable
        $newtt = $_POST['timetable_'.$day];

        $current = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_dates WHERE date = '".$year."-".$month."-".$day."'");
        if ($current) {
            $rec = reset($current);
            // We have a timetable in the DB for this date. If the new tt is none, delete it, otherwise update if they don't match
            if ($newtt == "none") {
                $wpdb->delete($wpdb->prefix.'railtimetable_dates', array('id' => $rec->id));
            } else {
                if ($newtt != $rec->timetable) {
                    $wpdb->update($wpdb->prefix.'railtimetable_dates', array('timetableid' => $newtt, 'date' => $year."-".$month."-".$day),  array('id' => $rec->id));
                }
            }
        } else {
            // There is no record at the moment, if timetable isn't none, we need to add one in otherwise no action needed
            if ($newtt != "none") {
                $wpdb->insert($wpdb->prefix.'railtimetable_dates', array('timetableid' => $newtt, 'date' => $year."-".$month."-".$day));
            }
        }

        //Do the events - find the existing ones and see if they have changed
        $todaysevents = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_eventdays WHERE date = '".$year."-".$month."-".$day."'");
        for ($loop=0; $loop<count($todaysevents); $loop++) {
            $key = 'event_'.$day.'_'.$todaysevents[$loop]->id;
            $value = $_POST[$key];
            if ($value != $todaysevents[$loop]->event) {
                if ($value == -1) {
                    $wpdb->delete($wpdb->prefix.'railtimetable_eventdays', array('id' => $todaysevents[$loop]->id));
                } else {
                    $wpdb->update($wpdb->prefix.'railtimetable_eventdays', array('event' => $value, 'date' => $year."-".$month."-".$day), array('id' => $todaysevents[$loop]->id));
                }
            }
        }

        // Handle new events
        if ($_POST['event_'.$day.'_n'] != -1) {
            $wpdb->insert($wpdb->prefix.'railtimetable_eventdays', array('date' => $year."-".$month."-".$day, 'event' => $_POST['event_'.$day.'_n']));
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
