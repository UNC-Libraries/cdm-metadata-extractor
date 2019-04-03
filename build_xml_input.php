#!/usr/bin/env php
<?php

/**
 * based on CONTENTdm Field Inspector, tool to a produce reports of various types about
 * CONTENTdm collections.
 *
 * This main script iterates through every parent-level object in a collection; getting
 * information about the objects is done using plugins.
 *
 * See README.md for usage examples.
 */

/*****************************
 * Get command-line options. *
 *****************************/
$line_separator = PHP_EOL;

$options = getopt('', array(
  'alias:'
));

$alias = '/' . $options['alias'];

$batch_plugin = 'plugins/get_cpd_files.plugin';
if (!file_exists($batch_plugin)) {
  exit("Can't find plugin $batch_plugin." . PHP_EOL);
}

// URL to the CONTENTdm web services API.

$set_url = 'https://dc.lib.unc.edu:82/dmwebservices/index.php?q=';

// Set num for progress_bar_chunks

$num_progress_bar_chunks = 50;


/**********************************************************************
 * Set up some additional values for accessing the CONTENTdm web API. *
***********************************************************************/
// Don't change $chunk_size unless CONTENTdm is timing out.
$chunk_size = 100;
// Don't change $start_at unless from 1 you are exporting a range of records. If you
// want to export a range, use the number of the first record in the range.
$start_at = 1;
// The last record in subset, not the entire record set. Don't change $last_rec from 0
// unless you are exporting a subset of records. If you want to export a range, use the
// number of records in the subset, e.g., if you want to export 200 records, use that value.
$last_rec = 0;
// CONTENTdm nicknames for administrative fields.
$admin_fields = array('fullrs', 'find', 'dmaccess', 'dmimage', 'dmcreated', 'dmmodified', 'dmoclcno', 'dmrecord');
// Record counter.
$rec_num = 0;
// Output subset size
$output_subset_size = 100;

$field_config = get_collection_field_config($alias);
$query_map = array(
    'alias' => $alias,
    'searchstrings' => '0',
    // We query for as little possible info at this point since we'll be doing another query
    // on each item later.
    'fields' => 'dmcreated',
    'sortby' => 'dmcreated!dmrecord',
    'maxrecs' => $chunk_size,
    'start' => $start_at,
    // We only want parent-level items, not pages/children. It appears that changing 'suppress'
    // to 0, as documented, has no effect anyway.
    'supress' => 1,
    'docptr' => 0,
    'suggest' => 0,
    'facets' => 0,
    'format' => 'json'
  );


/********************************************************************************************
 * Perform a preliminary query to determine how many records are in the current collection, *
 * and to determine the number of queries required to get all the records.                  *
 ********************************************************************************************/

$prelim_results = query_contentdm($start_at);
// We add one chunk, then round down using sprintf().
$num_chunks = $prelim_results['pager']['total'] / $chunk_size + 1;
$num_chunks = sprintf('%d', $num_chunks);

// Uncomment these lines to print out some informative eye candy.
if ($last_rec == 0) {
  // print "Retrieving " . $prelim_results['pager']['total'] .
  //  " records from $alias, and processing them in $num_chunks chunks of $chunk_size records each.\n";
} else {
  // print "Retrieving $last_rec records starting at $start_at of " . $prelim_results['pager']['total'] . " records\n";
}

// Die if there are no records.
if (!$prelim_results['pager']['total']) {
  print "Sorry, CONTENTdm didn't return any records.\n";
  exit;
}

/*****************************************************************************************************
 * Main program loop is handled by the plugin, which is invoked here and performs as many queries as *
 * required to get all the records in the collection identified by the alias.                        *
 ****************************************************************************************************/

// Include the indicated plugin, which contains the run_batch() function.
include $batch_plugin;

run_batch($prelim_results['pager']['total'], $num_chunks);
$output_dir = ltrim($alias, '/') . '_cpd_files';
$output_file_path = $output_dir . DIRECTORY_SEPARATOR . $alias . '_cpd.xml';
file_put_contents($output_file_path, '</bulk>', FILE_APPEND);
print $line_separator . "Done." . $line_separator;

/*************
 * Functions *
 ************/

/**
 * Query CONTENTdm with the values in $query_map and return an array of records.
 */
function query_contentdm($start_at, $current_chunk = NULL, $num_chunks = NULL) {
  global $set_url;
  global $query_map;
  global $error_log;
  global $last_rec;
  $qm = $query_map;
  $query = $set_url . 'dmQuery'. $qm['alias'] . '/'. $qm['searchstrings'] . '/'. $qm['fields'] . '/'.
    $qm['sortby'] . '/'. $qm['maxrecs'] . '/'. $start_at . '/'. $qm['supress'] . '/'. $qm['docptr'] . '/'.
    $qm['suggest'] . '/'. $qm['facets'] . '/' . $qm['format'];

  // Query CONTENTdm and return records; if failure, log problem.
  if ($json = file_get_contents($query, false, NULL)) {
    return json_decode($json, true);
  } else {
    $message = date('c') . "\t". 'Query failed:' . "\t" . $query . "\n";
    error_log($message, 3, $error_log);
    return FALSE;
  }
}


/**
 * Gets the item's compound info. "code" contains '-2' if the item is not compound.
 */
function get_compound_object_info($alias, $pointer, $format = 'json') {
  global $set_url;
  if ($format == 'json') {
    $query = $set_url . 'dmGetCompoundObjectInfo' . $alias . '/' . $pointer . '/json';
    $json = file_get_contents($query, false, NULL);
    return json_decode($json, true);
  }
  if ($format == 'xml') {
    $query = $set_url . 'dmGetCompoundObjectInfo' . $alias . '/' . $pointer . '/xml';
    $xml = file_get_contents($query, false, NULL);
    return $xml;
  }
}

/**
 * Gets the collection's field configuration from CONTENTdm.
 */
function get_collection_field_config($alias) {
  global $set_url;
  $query = $set_url . 'dmGetCollectionFieldInfo' . $alias . '/json';
  $json = file_get_contents($query, false, NULL);
  return json_decode($json, true);
}

/**
  * Print out a progress bar.
  */
function print_progress_bar($total_recs, $current_rec_num) {
  global $num_progress_bar_chunks;
  // Print 1 per object.
  if ($total_recs < $num_progress_bar_chunks) {
    print "#";
  }
  else {
    // Print 1 per $progress_bar_chunk_size objects.
    $progress_bar_chunk_size = $total_recs / $num_progress_bar_chunks;
    if ($current_rec_num % $progress_bar_chunk_size == 0) {
      print "#";
    }
  }
}
