<?php

/*
 * This script attempts to build an SQLite DB from a CDM collection exported
 * as "CONTENTdm standard XML" with "include child metadata" selected.
 * The SQLite DB will help with migration processes
 * (downloading files via CDM API, building METS representation).
 *
 * USAGE:
 * This script needs to be run from the command line. Not only does it require
 * arguments to run, it will most likely time out if run via the web.
 *
 * php buld_colldb.php [path to cdm xml file] [collection alias] override*
 *
 * e.g.:
 * php build_colldb.php cdm_data/avcdmstandard.xml avoralhist
 *
 * --Please provide the CDM collection alias without any slashes.
 *
 * --The override argument is optional: by default, the script will append
 * the records it pulls from the xml file to an existing collection database,
 * if one exists. The 'override' flag deletes any current databases and rebuilds.
 *
 */

// CONFIG - EDIT TO MATCH SERVER
$cdmapipath = "https://dc.lib.unc.edu:82";


// check for arguments
if(!isset($argv[1])||!isset($argv[2])){
  exit("Missing required arguments.");
} else {
  $filepath = $argv[1];
  $cdmalias = $argv[2];
}

// Load the XML file
if (file_exists($filepath)) {
     $cdmxml = simplexml_load_file($filepath);

  // Set default timezone
  date_default_timezone_set('EST');

  try {

    // Create (connect to) SQLite database in file
    $file_db = new PDO('sqlite:'.$cdmalias.'.sqlite3');
    // Set errormode to exceptions
    $file_db->setAttribute(PDO::ATTR_ERRMODE,
                            PDO::ERRMODE_EXCEPTION);

    // get collection field info from CDM
    $fieldstr = file_get_contents($cdmapipath.'/dmwebservices/index.php?q=dmGetCollectionFieldInfo/'.$cdmalias.'/json');
    $fieldinfo = json_decode($fieldstr);

    $tablecolsetup = "";
    $tablecols = array();
    $tableparams = array();

    $acceptedformats = array("TEXT","INTEGER","BLOB", "NULL");
    foreach($fieldinfo as $field){
      $tablecols[] = $field->nick;
      $tableparams[] = ":".$field->nick;

      if(in_array($field->type,$acceptedformats)){
      $tablecolsetup .= $field->nick." ".$field->type.", ";
      } else {
        if($field->type=='FTS'){
          // trap full text fields as BLOBs
          $tablecolsetup .= $field->nick." BLOB, ";
        } else {
          // trap all other unknown data types to TEXT
          $tablecolsetup .= $field->nick." TEXT, ";
        }
    }
    }
    $tablecolsetup .= "parent TEXT, childseq INTEGER";

    // check for override argument and drop records table if present
    if(isset($argv[3])){
      if($argv[3]=='override'){
        $file_db->exec("DROP TABLE IF EXISTS records");
      }
    }

    // Create records table
    $file_db->exec("CREATE TABLE IF NOT EXISTS records (
                    id INTEGER PRIMARY KEY, ".$tablecolsetup.")");


    $insertcols = implode(",",$tablecols);
    $insertparams = implode(",",$tableparams);

    // Loop thru all records and execute prepared insert statement
    $rownum = 1;
    foreach ($cdmxml->record as $row) {
      $itemptr = $row->cdmid;
      echo "Processing CDM ID $itemptr (record $rownum)\n";
      $itemstr = file_get_contents($cdmapipath.'/dmwebservices/index.php?q=dmGetItemInfo/'.$cdmalias.'/'.$itemptr.'/json');
      $iteminfo = json_decode($itemstr);

      $insert = "INSERT INTO records (".$insertcols.",parent,childseq)
                VALUES (".$insertparams.",:parent,:childseq)";
      $stmt = $file_db->prepare($insert);

      foreach($tablecols as $field){
        $paramname = ":".$field;
        if(is_object($iteminfo->$field)){
          $nothing = "";
          $stmt->bindParam($paramname, $nothing);
        } else {
          $stmt->bindParam($paramname, $iteminfo->$field);
        }
      }


      $parent = "";
      $childseq = "";
      $stmt->bindParam(':parent', $parent);
      $stmt->bindParam(':childseq', $childseq);
      $stmt->execute();

      if($row->cpd->page){
        // this record has child items
        $seq = "1";

        foreach($row->cpd->page as $child){
          $childptr = $child->pageptr;
          echo "Processing child item $childptr ($rownum.$seq)\n";
          $childstr = file_get_contents($cdmapipath.'/dmwebservices/index.php?q=dmGetItemInfo/'.$cdmalias.'/'.$childptr.'/json');
          $childinfo = json_decode($childstr);

          $cinsert = "INSERT INTO records (".$insertcols.",parent,childseq)
                    VALUES (".$insertparams.",:parent,:childseq)";
          $cstmt = $file_db->prepare($cinsert);


          foreach($tablecols as $field){
            $paramname = ":".$field;
            if(is_object($childinfo->$field)){
              //this field's data is Empty
              $nothing = "";
              $cstmt->bindParam($paramname, $nothing);
            } else {
              $cstmt->bindParam($paramname, $childinfo->$field);
            }
          }

          $parent = $itemptr;
          $childseq = $seq;
          $cstmt->bindParam(':parent', $parent);
          $cstmt->bindParam(':childseq', $childseq);
          $cstmt->execute();

          $seq++;
        }
      }
      $rownum++;
    }

    // Close db connection
    $file_db = null;

  }

  catch(PDOException $e) {
    // Print PDOException message
    echo $e->getMessage();
  }

 } else {
     exit('Failed to open xml file.');
 }

?>
