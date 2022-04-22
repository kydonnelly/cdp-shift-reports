<?php
/**
 * Plugin Name: CDP Shift Reports
 * Plugin URI: https://www.cooperative4thecommunity.com/cdp-shift-reports
 * Description: A plugin to submit shift reports to the CDP schedule database.
 * Version: 1.0
 * Author: Kyle Donnelly
 * Author URI: https://www.cooperative4thecommunity.com
 */

defined( 'ABSPATH' ) || exit;

define("SHIFT_REPORT_COLUMNS", ['shift_id', 'gatherer', 'location_id', 'start_time', 'end_time', 'raw_signatures', 'validated_signatures']);

function cdp_echo_report_response_html($success) {
  if ($success != true) {
    echo '<p>Could not update :(</p>';
  }
}

function cdp_get_locations() {
  global $wpdb;
  $table_name = $wpdb->prefix . "gathering_locations";
  $query = "SELECT location_id, name from $table_name ORDER BY name;";
  return $wpdb->get_results($query);
}

function cdp_unreported_shifts() {
  global $wpdb;
  $table_name = $wpdb->prefix . "shifts_2022";
  $query = "SELECT " . implode(', ', SHIFT_REPORT_COLUMNS) . " from $table_name WHERE raw_signatures IS NULL OR validated_signatures IS NULL ORDER BY gatherer ASC, end_time DESC;";
  $results = $wpdb->get_results($query);
}

function cdp_submit_shift_result() {
  // ignore initial (or any) page load where the user didn't submit anything
  if (!isset( $_POST['submit'] )) {
    return;
  }

  // validate required fields
  if (!isset( $_POST['gatherer'] )) {
    echo '<p>Must include the gatherer\'s name!</p>';
    return;
  }
  if (!isset( $_POST['location_id'] )) {
    echo '<p>Must include the location!</p>';
    return;
  }
  if (!isset( $_POST['start_time'] )) {
    echo '<p>Must include the start time!</p>';
    return;
  }
  if (!isset( $_POST['end_time'] )) {
    echo '<p>Must include the end time!</p>';
    return;
  }

  $shift_id = sanitize_text_field( $_POST['shift_id'] );
  $gatherer = sanitize_text_field( $_POST['gatherer'] );
  $location_id = sanitize_text_field( $_POST['location_id'] );
  $start_time = sanitize_text_field( $_POST['start_time'] );
  $end_time = sanitize_text_field( $_POST['end_time'] );
  $raw_signatures = sanitize_text_field( $_POST['signature_count'] );
  $validated_signatures = sanitize_text_field( $_POST['validity_count'] );

  $insertions = array('gatherer' => $gatherer,
    'location_id' => $location_id,
    'start_time' => $start_time,
    'end_time' => $end_time);

  if (!empty($shift_id)) {
    $insertions['shift_id'] = $shift_id;
  }
  if (!empty($raw_signatures)) {
    $insertions['raw_signatures'] = intval($raw_signatures);
  }
  if (!empty($validated_signatures)) {
    $insertions['validated_signatures'] = intval($validated_signatures);
  }

  global $wpdb;
  $table_name = $wpdb->prefix . "shifts_2022";
  $success = $wpdb->insert($table_name, $insertions);

  cdp_echo_report_response_html($success);
}

function cdp_shift_report_form_code() {
  $locations = cdp_get_locations();
  $unreported_shifts = cdp_unreported_shifts();

  $location_map = array_combine(array_map(function($l) { return $l->location_id; }, $locations), $locations);

  // Shows the input form, keeping any values from previous submission
  echo '<form class="shift_report_form" action="" id="shift_report_form" method="post">';
  echo '<p>';
  echo '<label for="shift">Shift: </label>
  <select id="shift_id" class="shift_field" style="width:50%" name="location_field">
  <option value="none">Choose...</option>';
  foreach ($unreported_shifts as $shift) {
    $location_name = $location_map[$shift->location_id]->name;
    $display_text = $shift->gatherer . ' @ ' . $location_name . ', ' . $shift->start_time;
    echo '<option value="' . $shift->shift_id . '">' . ' ' . $display_text . '</option>';
  }
  echo '</select>';
  echo '<label for="location">Location: </label>
  <select id="location" class="location_field" style="width:50%" required="required" name="location_field">
  <option value="none">Choose...</option>';
  foreach ($locations as $location) {
    echo '<option value="' . $location->location_id . '">' . ' ' . $location->name . ' ' . cdp_location_quality_emoji($location) . '</option>';
  }
  echo '</select>';
  echo '<label for="gatherer">Name: </label><input autocapitalize="on" spellcheck="false" autocorrect="off" type="text" name="gatherer" id="gatherer" value="' . $_POST['gatherer'] . '" placeholder="Full name"><br />
  <label for="start_time">Start Time: </label><input type="time" id="start_time" name="start_time" step="900" value="' . $_POST['start_time'] . '" required><br />
  <label for="end_time">End Time: </label><input type="time" id="end_time" name="end_time" step="900" value="' . $_POST['end_time'] . '" required><br />
  <label for="signature_count">Signatures Collected: </label><input type="text" name="signature_count" id="signature_count" value="' . $_POST['signature_count'] . '" placeholder="27"><br />
  <label for="validity_count">Signatures Validated: </label><input type="text" name="validity_count" id="validity_count" value="' . $_POST['validity_count'] . '" placeholder="24">'
  echo '</p>';
  echo '<p><input type="submit" name="submit" id="submitButton" value="Submit">';
  echo '<input style="background-color:#c7c7c7" type="reset" name="clear" id="clearInput" value="Clear" onclick="return resetForm(this.form);"></p>';
  echo '</form>';
}

function cdp_shift_report_shortcode() {
  // wordpress entry point
  ob_start();
  cdp_shift_report_code();
  cdp_voter_lookup();

  return ob_get_clean();
}

add_shortcode( 'cdp_shift_report', 'cdp_shift_report_shortcode' );

?>
