<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Hook for adding admin menus
add_action('admin_menu', 'railtimetable_add_pages');
add_action('admin_init', 'railtimetable_register_settings' );

// action function for above hook
function railtimetable_add_pages() {
    // Add a new top-level menu (ill-advised):
    add_menu_page(__('Rail Timetable','railtimetable'), __('Rail Timetable','railtimetable'), 'manage_options', 'railtimetable-top-level-handle', 'railtimetable_edit', '', 30);

    add_submenu_page('railtimetable-top-level-handle', __('Edit Stations','railtimetable'), __('Edit Stations','railtimetable'), 'manage_options', 'railtimetable-edit-stations', 'railtimetable_edit_stations');

    add_submenu_page('railtimetable-top-level-handle', __('Edit Timetables','railtimetable'), __('Edit Timetables','railtimetable'), 'manage_options', 'railtimetable-edit-timetable', 'railtimetable_edit_timetables');

    add_submenu_page('railtimetable-top-level-handle', __('Edit Events','railtimetable'), __('Edit Events','railtimetable'), 'manage_options', 'railtimetable-edit-events', 'railtimetable_edit_events');

    add_submenu_page('railtimetable-top-level-handle', __('Edit Calendar','railtimetable'), __('Edit Calendar','railtimetable'), 'manage_options', 'railtimetable-edit-calendar', 'railtimetable_edit_calendar');

}

function railtimetable_register_settings() {
   add_option( 'railtimetable_date_format', '%e-%b-%y');
   register_setting( 'railtimetable_options_main', 'railtimetable_date_format'); 
   add_option( 'railtimetable_time_format', '%l.%M');
   register_setting( 'railtimetable_options_main', 'railtimetable_time_format'); 
   add_option( 'railtimetable_show_rules', '0');
   register_setting( 'railtimetable_options_main', 'railtimetable_show_rules'); 
}

function railtimetable_verify_nonce() {
    if(!isset( $_POST['railtimetable-nonce'])) {
        wp_die( __( 'Missing nonce', 'railtimetable' ), __( 'Error', 'railtimetable'), array(
            'response' => 403,
            'back_link' => 'admin.php?page='.'railtimetable'));
    }

    if (!wp_verify_nonce( $_POST['railtimetable-nonce'], 'railtimetable-nonce') ) {
        wp_die( __( 'Invalid nonce specified', 'railtimetable' ), __( 'Error', 'railtimetable'), array(
            'response' => 403,
            'back_link' => 'admin.php?page='.'railtimetable'));
    }
}

function railtimetable_edit() {
    global $wpdb;
    ?>
    <h1>Heritage Railway Timetable</h1>
    <form method="post" action="options.php">

    <?php settings_fields('railtimetable_options_main'); ?>
    <table>
        <tr valign="top">
            <th scope="row"><label for="railtimetable_date_format">Display Date format</label></th>
            <td><input type="text" id="railtimetable_date_format" name="railtimetable_date_format" value="<?php echo get_option('railtimetable_date_format'); ?>" /> Use <a href='https://www.php.net/manual/en/function.strftime' target='_blank'>PHP strftime formatting parameters</a> here</td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="railtimetable_time_format">Display Time format</label></th>
            <td><input type="text" id="railtimetable_time_format" name="railtimetable_time_format" value="<?php echo get_option('railtimetable_time_format'); ?>" /> Use <a href='https://www.php.net/manual/en/function.strftime' target='_blank'>PHP strftime formatting parameters</a> here</td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="railtimetable_show_rules">Show rules in timetable header</label></th>
            <td><input type="checkbox" id="railtimetable_show_rules" name="railtimetable_show_rules" value="1"
                <?php checked(get_option('railtimetable_show_rules')); ?>" /></td>
        </tr>
    </table>
    <?php submit_button(); ?>
    </form>

    <h2>Data Export</h2>
    <p>Click the link below to export data for the ticket system or another copy of the timetable module.</p>
    <form method='post' action='<?php echo esc_url( admin_url('admin-post.php') ); ?>'>
        <input type='hidden' name='action' value='railtimetable-exportdata' />
        <table><tr>
            <td>Start from date</td><td><input type='date' name='startdate' value="<?php echo date("Y-m-d"); ?>"/></td>
        </tr><table>
        <?php submit_button("Export Data"); ?>  
    </form>
    <?php
    if (array_key_exists('action', $_POST)) {
        switch ($_POST['action']) {
            case 'converttimes':
                railtimetable_converttimes();
        }
    }
    if ($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}railtimetable_stntimes" ) == 0) {
    ?>
    <form action='' method='post'>
    <input type='hidden' name='action' value='converttimes' />
    <input type='submit' value='Convert Times table' />
    </form>
    <?php
    }
}

add_action('admin_post_railtimetable-exportdata', 'railtimetable_exportdata');

function railtimetable_exportdata() {
    global $wpdb;
    $export = new stdclass();

    $startdate = sanitize_text_field($_POST['startdate']);

    $export->source = get_site_url();
    $export->stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations");
    $export->stntimes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stntimes");
    $export->timetables = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_timetables");
    $export->eventdetails = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_eventdetails");
    $export->dates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_dates WHERE date >= '".$startdate."'");
    $export->eventdays = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_eventdays WHERE date >= '".$startdate."'");
    
    header("Content-type: text/json");
    header("Content-Disposition: attachment; filename=railtimetable.json");
    header("Pragma: no-cache");
    echo json_encode($export, JSON_PRETTY_PRINT);
    exit;
}


function railtimetable_converttimes() {
    global $wpdb;
    $times = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_times");
    foreach ($times as $time) {
        $nt = array();
        $nt['station'] = $time->station;
        $nt['timetableid'] = $time->timetableid;

        $nt['down_deps'] = railtimetable_converttime($time->down_deps);
        $nt['up_deps'] = railtimetable_converttime($time->up_deps);
        $nt['down_arrs'] = railtimetable_converttime($time->down_arrs);
        $nt['up_arrs'] = railtimetable_converttime($time->up_arrs);
        $wpdb->insert($wpdb->prefix.'railtimetable_stntimes', $nt);
    }

    $tts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_timetables");
    foreach ($tts as $t) {
        $data = array();
        if (strlen($t->colsmeta) == 0) {
            for ($loop = 0; $loop< $t->totaltrains; $loop++) {
                $nd = new stdclass();
                $nd->notes = '';
                $nd->rules = array();
                $data[] = $nd;
            }
        } else {
            $metas = explode(',',$t->colsmeta);
            foreach ($metas as $meta) {
                $nd = new stdclass();
                $nd->notes = $meta;
                $nd->rules = array();
                $data[] = $nd;
            }
        }
        
        $wpdb->update($wpdb->prefix.'railtimetable_timetables', array('colsmeta' => json_encode($data)) , array('id' => $t->id));
    }
}

function railtimetable_converttime($str) {
   $times = explode(',', $str);
   $nt = array();
   foreach ($times as $time) {
       $parts = explode('.', $time);
       if (count($parts) == 0 || strlen(trim($time)) == 0 ) {
           continue;
       }
       $data = new stdclass();
       $data->hour = $parts[0];
       $data->min = $parts[1];
       $nt[] = $data;
   }

   return json_encode($nt);
}

function railtimetable_edit_stations() {
    global $wpdb;
    $nonce = wp_create_nonce('railtimetable-nonce');
    ?>
    <h1>Heritage Railway Timetable - Stations</h1>
    <form method='post' action='<?php echo esc_url( admin_url('admin-post.php') ); ?>'>
    <input type='hidden' name='action' value='railtimetable-editstations' />
    <input type="hidden" name="railtimetable-nonce" value="<?php echo $nonce ?>" />
    <table><tr><th>Station</th><th>Description</th><th>Request<br />Stop</th><th>Closed</th><th>Hidden</th><th>Actions</th><tr>
    <?php
    $ids = array();
    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");
    $count = count($stations);
    for ($loop = 0; $loop < $count; $loop++) {
        $ids[] = $stations[$loop]->id;
        if ($stations[$loop]->hidden == 1) {
            $hidden = 'checked';
        } else {
            $hidden = '';
        }
        if ($stations[$loop]->requeststop == 1) {
            $rs = 'checked';
        } else {
            $rs = '';
        }
        if ($stations[$loop]->closed == 1) {
            $closed = 'checked';
        } else {
            $closed = '';
        }

        echo "<tr>".
            "<td>".($stations[$loop]->sequence+1).
            ": <input type='text' name='station_name_".$stations[$loop]->id."' size='25' value='".$stations[$loop]->name."' /></td>".
            "<td><input type='text' name='station_description_".$stations[$loop]->id."' size='50' value='".$stations[$loop]->description."' /></td>".
            "<td><input type='checkbox' name='station_requeststop_".$stations[$loop]->id."' value='1' ".$rs." /></td>".
            "<td><input type='checkbox' name='station_closed_".$stations[$loop]->id."' value='1' ".$closed." /></td>".
            "<td><input type='checkbox' name='station_hidden_".$stations[$loop]->id."' value='1' ".$hidden." /></td>".
            "<td>";

        echo "<button name='station_del_".$stations[$loop]->id."' value='1'>X</button>&nbsp;&nbsp;";

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
        <td><input type='text' size='50' name='station_description_new' value='' /></td>
        <td><input type='checkbox' name='station_requeststop_new' value='1' /></td>
        <td><input type='checkbox' name='station_closed_new' value='1' /></td>
        <td><input type='checkbox' name='station_hidden_new' value='1' /></td>
        <td></td>
    </tr>
    </table>
    <input type='hidden' name='ids' value='<?php echo implode(",",$ids) ?>' />
    <input type="submit" value="Update Stations" />
    </form>
    <?php
}

add_action('admin_post_railtimetable-editstations', 'railtimetable_updatestations');

function railtimetable_updatestations() {
    global $wpdb;

    railtimetable_verify_nonce();

    $ids = sanitize_text_field($_POST['ids']);
    if (strlen($ids) > 0) {
        $ids = explode(',', $ids);
    } else {

        $ids = array();
    }

    foreach ($ids as $id) {
        if (array_key_exists('station_del_'.$id, $_POST) && $_POST['station_del_'.$id] == 1) {
            $wpdb->delete($wpdb->prefix.'railtimetable_stations',  array('id' => $id));
            // Now fix the sequence....
            $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");

            for ($loop = 0; $loop < count($stations); $loop++) {
                $wpdb->update($wpdb->prefix.'railtimetable_stations', array('sequence' => $loop),  array('id' => $stations[$loop]->id));
            }
            continue;
        }

        $hidden = railtimetable_get_cbval('station_hidden_'.$id);
        $rs = railtimetable_get_cbval('station_requeststop_'.$id);
        $closed = railtimetable_get_cbval('station_closed_'.$id);

        $params = array(
            'name' => sanitize_text_field($_POST['station_name_'.$id]),
            'hidden' => $hidden,
            'requeststop' => $rs,
            'closed' => $closed,
            'description' => sanitize_text_field($_POST['station_description_'.$id]));

        if (array_key_exists('station_move_'.$id, $_POST)) {
            $inc = intval($_POST['station_move_'.$id]);
            $current = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations WHERE id = '".$id."'")[0];
            $swap = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations WHERE sequence = '".($current->sequence+$inc)."'")[0];
            $wpdb->update($wpdb->prefix.'railtimetable_stations', array('sequence' => $current->sequence),  array('id' => $swap->id));
            $params['sequence'] = $swap->sequence;
        }
        $wpdb->update($wpdb->prefix.'railtimetable_stations', $params,  array('id' => $id));
    }
    $stnnew = trim(sanitize_text_field($_POST['station_name_new']));
    if (strlen($stnnew) > 0) {
        $hidden = railtimetable_get_cbval('station_hidden_new');
        $rs = railtimetable_get_cbval('station_requeststop_new');
        $closed = railtimetable_get_cbval('station_closed_new');

        $wpdb->insert($wpdb->prefix.'railtimetable_stations',
            array('name' => $stnnew, 
            'description' => trim(sanitize_text_field($_POST['station_description_new'])), 
            'hidden' =>  $hidden,
            'requeststop' => $rs,
            'closed' => $closed,
            'sequence' => count($ids)));
    }

    wp_redirect(site_url().'/wp-admin/admin.php?page=railtimetable-edit-stations');
    exit;
}

function railtimetable_get_cbval($cbname) {
    if (array_key_exists($cbname, $_POST)) {
        return sanitize_text_field($_POST[$cbname]);
    } else {
        return 0;
    }
}

function railtimetable_edit_timetables() {
    global $wpdb;

    if (array_key_exists('action', $_POST)) {
        switch ($_POST['action']) {
            case 'railtimetable-edittimetable':
                railtimetable_updatetimetable();
                break;
            case 'railtimetable-edittimes':
                railtimetable_updatetimes();
                break;
        }
    }

    // Do we have any edit or delete actions?
    if (array_key_exists('edit', $_POST)) {
        $tt = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_timetables WHERE id='".sanitize_text_field($_POST['edit'])."' ");
        echo "<h2>Update Timetable Details</h2>";
        railtimetable_edit_timetable($_POST['edit'], $tt->timetable, $tt->background, $tt->colour, $tt->totaltrains, $tt->html, $tt->buylink, $tt->hidden, "Update Timetable" );
        return;
    }

    // Do we have any edit or delete actions?
    if (array_key_exists('edittimes', $_POST)) {
        railtimetable_edit_times($_POST['edittimes']);
        return;
    }

    if (array_key_exists('del', $_POST)) {
        $del = sanitize_text_field($_POST['del']);
        $tt = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_timetables WHERE id='".$del."' ");
        if (array_key_exists('confirm', $_POST)) {
            $wpdb->delete($wpdb->prefix."railtimetable_timetables", array('id' => $del));
            $wpdb->delete($wpdb->prefix."railtimetable_stntimes", array('timetableid' => $del));
            $wpdb->delete($wpdb->prefix."railtimetable_dates", array('timetableid' => $del));
            ?>
            <h2>"<?php echo $tt->timetable; ?>" Deleted.</h2>
            <?php
        } else {
            ?>
            <form method='post' action=''>
                <h2>Are you sure you want to delete the "<?php echo $tt->timetable; ?>" timetable ?</h2>
                <input type='hidden' name='confirm' value='1' />
                <input type='hidden' name='del' value='<?php echo $tt->id; ?>' />
                <input type='submit' value='Delete Timetable' />
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
        "<p>Please use 24 hour clock here and set the Display Time format to control how it is displayed</p>".
        "<p>Rules determin special cases when trains don't run (or only run) on certain days of the week or dates. To specify that the train doesn't run on a day, ".
        "start the line with !, for trains that only run on a specified date or date, use *. Follow that with either the day of the week as a number (monday=1, sunday=7) ".
        " or the date written as YYYYMMDD. eg Train doesn't run on Friday '!5', train only runs on 27th June 2021 '*20200627'. Put one rule per line.</p>".
        "<form method='post' action=''>\n".
        "<input type='hidden' name='action' value='railtimetable-edittimes' />".
        "<input type='hidden' name='id' value='".$id."' />";

    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");
    $numstations = count($stations);

    railtimetable_edit_times_single($id, $stations, $numstations, "down", $tt->totaltrains, $tt->colsmeta);
    echo "<br/>\n";
    railtimetable_edit_times_single($id, $stations, $numstations, "up", $tt->totaltrains);
    echo "<input type='submit' value='Update Times' />".
        "</form>\n";
}

function railtimetable_edit_times_single($id, $stations, $numstations, $direction, $totaltrains, $colsmeta=false) {
    global $wpdb;
    echo "<table style='border-collapse: collapse;'>\n";

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
        $decode = json_decode($colsmeta);
        echo "<tr><td>Notes</td><td></td>\n";
        railtimetable_edit_notes_line($totaltrains, $decode, 'notes');
        echo "</tr>\n";
        echo "<tr><td>Rules</td><td></td>\n";
        railtimetable_edit_rules_line($totaltrains, $decode);
        echo "</tr>\n";
    }

    for ($loop = $startid; $loop>-1 && $loop<$numstations; $loop=$loop+$inc) {
        $station = $stations[$loop];
        $times = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_stntimes WHERE timetableid='".$id."' AND station='".$station->id."'");
        if (!$times) {
            $times = new stdclass();
            $times->down_deps="[]";
            $times->down_arrs="[]";
            $times->up_deps="[]";
            $times->up_arrs="[]";
        }

        if ($station->sequence != $startid) {
            echo "<tr><th>".$station->name."</th><td>arr</td>\n";
            $key = $direction."_arrs";
            railtimetable_edit_times_line($totaltrains, json_decode($times->$key), $key."_".$station->id);
        }

        if ($station->sequence != $endid) {
            echo "<tr><th>".$station->name."</th><td>dep</td>\n";
            $key = $direction."_deps";
            railtimetable_edit_times_line($totaltrains, json_decode($times->$key), $key."_".$station->id);
        }
    }

    echo "</table>\n";
}

function railtimetable_edit_times_line($totaltrains, $line, $key) {
    for ($loop = 0; $loop < $totaltrains; $loop++) {
        $hour = '';
        $min = '';
        if (array_key_exists($loop, $line)) {
            if (property_exists($line[$loop], 'hour')) {
                $hour = $line[$loop]->hour;
                $min = $line[$loop]->min;
            }
        }

        echo "<td style='border-left:2px solid black;'>".
            "<input type='text' name='".$key."_hour_".$loop."' value='".$hour."' minlength='0' maxlength='2' style='width:38px' />:".
            "<input type='text' name='".$key."_min_".$loop."' value='".$min."' minlength='0' maxlength='2' style='width:38px' />".
            "</td>\n";
    }
}

function railtimetable_edit_notes_line($totaltrains, $line, $key) {
    for ($loop = 0; $loop < $totaltrains; $loop++) {
        echo "<td style='border-left:2px solid black;'><textarea type='text' name='notes_".$loop."' rows='2' style='width:80px;font-size:small;' />".htmlentities($line[$loop]->notes, ENT_QUOTES)."</textarea></td>\n";
    }
}
function railtimetable_edit_rules_line($totaltrains, $line) {
    for ($loop = 0; $loop < $totaltrains; $loop++) {
        $rulesc = array();
        foreach ($line[$loop]->rules as $rule) {
            $rulesc[] = $rule->code.$rule->str;
        }

        echo "<td style='border-left:2px solid black;'><textarea name='rules_".$loop."' rows='4' style='width:80px;font-size:small;' />".htmlentities(implode("\r\n", $rulesc), ENT_QUOTES)."</textarea></td>\n";
    }
}

function railtimetable_updatetimes() {
    global $wpdb;
    $id = intval($_POST['id']);
    $tt = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_timetables WHERE id='".$id."' ");
    $meta = railtimetable_get_times_meta($tt->totaltrains);
    $wpdb->update($wpdb->prefix.'railtimetable_timetables', array('colsmeta' => $meta), array('id' => $id));

    $stations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_stations ORDER BY sequence ASC");

    foreach ($stations as $station) {
        $down_deps = railtimetable_get_updatetimes('down_deps_'.$station->id.'_', $tt->totaltrains);
        $down_arrs = railtimetable_get_updatetimes('down_arrs_'.$station->id.'_', $tt->totaltrains);
        $up_deps = railtimetable_get_updatetimes('up_deps_'.$station->id.'_', $tt->totaltrains);
        $up_arrs = railtimetable_get_updatetimes('up_arrs_'.$station->id.'_', $tt->totaltrains);

        $timesrow = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}railtimetable_stntimes WHERE station=".$station->id." AND timetableid=".$tt->id);
        if ($timesrow) {
            $wpdb->update($wpdb->prefix.'railtimetable_stntimes',
                array('down_deps' => $down_deps, 'down_arrs' => $down_arrs, 'up_deps' => $up_deps, 'up_arrs' => $up_arrs),
                array('id' => $timesrow));
        } else {
            $wpdb->insert($wpdb->prefix.'railtimetable_stntimes',
                array('station' => $station->id, 'timetableid' => $tt->id, 'down_deps' => $down_deps, 'down_arrs' => $down_arrs, 'up_deps' => $up_deps, 'up_arrs' => $up_arrs));
        }
    }
}

function railtimetable_get_times_meta($totaltrains) {
    $allempty = true;
    $strs = array();
    for ($loop=0; $loop < $totaltrains; $loop++) {
       $strs[$loop] = new stdclass();
       if (array_key_exists('notes_'.$loop, $_POST)) {
           $strs[$loop]->notes =  wp_kses($_POST["notes_".$loop], array('br' => array()));
           $allempty = false;
       } else {
           $strs[$loop]->notes = '';
       }
       if (array_key_exists('rules_'.$loop, $_POST)) {
           $rules =  sanitize_textarea_field($_POST["rules_".$loop]);
           $rules = explode("\r\n", $rules);
           $prules = array();
           foreach ($rules as $rule) {
               $rule = trim($rule);
               // Get the rule code, first char
               $code = substr($rule, 0, 1);
               if ($code != "!" && $code != "*") {
                   continue;
               }
               // Get the following string
               $str = substr($rule, 1);
               $strl = strlen($str);
               // Should be either 1 char for a day rule or 8 for a date rule
               if ($strl != 1 && $strl != 8) {
                   continue;
               }
               // If it's a date, parse it and check the parsed version matches what was typed in
               if ($strl == 8) {
                   $date = DateTime::createFromFormat('Ymd', $str);
                   $df = $date->format('Ymd');
                   if ($df != $str) {
                       continue;
                   }
               }
               $r = new stdclass();
               $r->code = $code;
               $r->str = $str;
               $prules[] = $r;
           }
           $strs[$loop]->rules = $prules;
           $allempty = false;
       } else {
           $strs[$loop]->rules = array();
       }
    }

    if ($allempty) {
        $strs = array();
    }

    return json_encode($strs);
}

function railtimetable_get_updatetimes($key, $totaltrains) {
    $allempty = true;
    $strs = array();
    for ($loop=0; $loop < $totaltrains; $loop++) {
       $strs[$loop] = new stdclass();
       if (array_key_exists($key.'hour_'.$loop, $_POST)) {
           $strs[$loop]->hour = sanitize_text_field($_POST[$key.'hour_'.$loop]);
           $strs[$loop]->min = sanitize_text_field($_POST[$key.'min_'.$loop]);
           if (strlen($strs[$loop]->hour) > 0 || strlen($strs[$loop]->min) > 0) {
               $allempty = false;
           }
           if ($strs[$loop]->hour > 23) {
               $strs[$loop]->hour = 23;
           }
           if ($strs[$loop]->hour < 0) {
               $strs[$loop]->hour = 0;
           }
           if ($strs[$loop]->min > 59) {
               $strs[$loop]->min = 59;
           }
           if ($strs[$loop]->min < 0) {
               $strs[$loop]->min = 0;
           }
       } else {
           $strs[$loop]->hour = '';
           $strs[$loop]->min = '';
       }
    }

    if ($allempty) {
        $strs = array();
    }

    return json_encode($strs);
}

function railtimetable_edit_timetable($id=-1, $timetable="", $background ="666666", $colour = "ffffff", $totaltrains = 1, $notes = "", $buylink="", $hidden=0, $button="Add Timetable") {
    ?>
    <form method='post' action=''>
        <input type='hidden' name='action' value='railtimetable-edittimetable' />
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
            <td><input type='color' name='background' size='6' value='#<?php echo htmlspecialchars($background); ?>' /></td>
        </tr><tr>
            <td>Text colour</td>
            <td><input type='color' name='colour' size='6' value='#<?php echo htmlspecialchars($colour); ?>' /></td>
        </tr><tr>
            <td>Hidden</td>
            <td><input type='checkbox' name='hidden' value='1' <?php if ($hidden == 1) {echo "checked";} ?>/> (note, only hides in "all timetables" display, will still be shown on calendar where set)</td>
        </tr><tr>
            <td>Buy link</td>
            <td><input type='text' name='buylink' size='80' value='<?php echo htmlspecialchars($buylink); ?>' /></td>
        </tr><tr>
            <td></td><td>Use <a href='https://www.php.net/manual/en/datetime.format.php' target='_blank'>PHP strftime formatting parameters</a> here to insert a date</td>
        </tr><tr>
            <td></td>
            <td><input type='submit' value='<?php echo $button; ?>' /></td>
        </tr></table>
    </form>
    <?php
}

function railtimetable_updatetimetable() {
    global $wpdb;

    $hidden = railtimetable_get_cbval('hidden');
    $params = array('timetable' => strtolower(sanitize_text_field($_POST['timetable'])),
        'html' => sanitize_textarea_field($_POST['html']),
        'background' => substr(trim(sanitize_text_field($_POST['background'])), 1),
        'colour' => substr(trim(sanitize_text_field($_POST['colour'])),1),
        'totaltrains' => intval($_POST['totaltrains']),
        'hidden' => $hidden,
        'buylink' => trim(sanitize_text_field($_POST['buylink'])));
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
                $links[$lang] = sanitize_text_field($_POST["link_".$lang]);
            }

            if (railtimetable_get_cbval('custombackcolour')) {
                $background = substr(trim(sanitize_text_field($_POST['background'])), 1);
            } else {
                $background = '';
            }
            $linksjson = json_encode($links);
            $params = array(
                'title' => sanitize_text_field($_POST['title']),
                'description' => sanitize_text_field($_POST['desc']),
                'link' => $linksjson, 'background' => $background,
                'colour' => substr(trim(sanitize_text_field($_POST['colour'])),1),
                'buylink' => trim(sanitize_text_field($_POST['buylink'])));
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
        $edit = sanitize_text_field($_POST['edit']);
        $evt = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_eventdetails WHERE id='".$edit."' ");
        echo "<h2>Update Event</h2>";
        railtimetable_edit_event($edit, $evt->title, $evt->description, $evt->link, $evt->background, $evt->colour, $evt->buylink, $button="Update Event");
        return;
    }

    if (array_key_exists('del', $_POST)) {
        $del = sanitize_text_field($_POST['del']);
        $evt = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}railtimetable_eventdetails WHERE id='".$del."' ");
        if (array_key_exists('confirm', $_POST)) {
            $wpdb->delete($wpdb->prefix."railtimetable_eventdetails", array('id' => $del));
            $wpdb->delete($wpdb->prefix."railtimetable_eventdays", array('event' => $del));
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
        <th>ID</th>
        <th>Event Name</th>
        <th>Actions</th>
    </tr>
    <?php
    $events = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_eventdetails");
    for ($loop = 0; $loop < count($events); $loop++) {
        ?>
        <tr>
            <td><?php echo $events[$loop]->id; ?></td>
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

function railtimetable_edit_event($id=-1, $title="", $desc="", $linkjson="", $bg = "", $colour = "000000", $buylink = "", $button="Add Event") {
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

        ?>
       <tr>
            <td>Text colour</td>
            <td><input type='color' name='colour' value='#<?php echo htmlspecialchars($colour); ?>' /></td>
        </tr><tr>
            <td>Buy link</td>
            <td><input type='text' name='buylink' size='80' value='<?php echo htmlspecialchars($buylink); ?>' /></td>
        </tr><tr>
            <td></td><td>Use <a href='https://www.php.net/manual/en/function.strftime' target='_blank'>PHP strftime formatting parameters</a> here to insert a date</td>
        </tr>
        <tr>
            <td>Use custom background colour</td>
            <td><input type='checkbox' name='custombackcolour' value='1' <?php if (strlen($bg) > 0) {echo 'checked';} ?> />
                Choose: <input type='color' name='background' value='#<?php echo htmlspecialchars($bg); ?>' /></td>
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
                railtimetable_showcalendaredit(sanitize_text_field($_POST['year']), sanitize_text_field($_POST['month']));
        }
    }
}


function railtimetable_getyearselect($currentyear = false) {
    global $wpdb;
    if ($currentyear == false) {
        $currentyear = intval(date("Y"));
    }
    if (array_key_exists('year', $_POST)) {
        $chosenyear = sanitize_text_field($_POST['year']);
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

function railtimetable_getmonthselect($chosenmonth = false) {
    if ($chosenmonth == false) {
        $chosenmonth = 1;
    }

    if (array_key_exists('month', $_POST)) {
        $chosenmonth = intval($_POST['month']);
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

    $year = sanitize_text_field($_POST['year']);
    $month = sanitize_text_field($_POST['month']);
    $daysinmonth = intval(date("t", mktime(0, 0, 0, $month, 1, $year)));
    for ($day = 1; $day < $daysinmonth + 1; $day++) {
        // Do the timetable
        $newtt = sanitize_text_field($_POST['timetable_'.$day]);

        $current = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}railtimetable_dates WHERE date = '".$year."-".$month."-".$day."'");
        if ($current) {
            $rec = reset($current);
            // We have a timetable in the DB for this date. If the new tt is none, delete it, otherwise update if they don't match
            if ($newtt == "none") {
                $wpdb->delete($wpdb->prefix.'railtimetable_dates', array('id' => $rec->id));
            } else {
                if ($newtt != $rec->timetableid) {
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
            $value = sanitize_text_field($_POST[$key]);
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
