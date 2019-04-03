<?php

/*
 * This script generates a .bat file that can be used to
 * download master files using a CDM collection sqlite3
 * database. (See build_colldb.php)
 *
 * USAGE:
 * php download_masterfiles.php /path/to/sqlite3file.sqlite3 cdmalias
 *
 */

// CONFIG - EDIT TO MATCH SERVER
$cdmapipath = "https://library.digitalnc.org:82";
$cdmutilspath = "http://library.digitalnc.org";


// check for arguments
if(!isset($argv[1])||!isset($argv[2])){
    exit("Missing required arguments.");
} else {
    $dbpath = $argv[1];
    $cdmalias = $argv[2];
}

$file_db = new PDO('sqlite:'.$dbpath);
$collalias = $cdmalias;

// get all items that are parents or standalone: e.g., not children
$result = $file_db->query("SELECT * FROM records WHERE parent = ''");

$filestring = '';

foreach($result as $r) {

  $countchildren = "SELECT count(*) FROM records WHERE parent = ".$r['dmrecord']."";
  $countresult = $file_db->prepare($countchildren);
  $countresult->execute();
  $number_of_rows = $countresult->fetchColumn();

  //echo "Create directory for parent item \"".$r['title']."\" [cdm id# ".$r['dmrecord']."] with ".$number_of_rows." children<br />";
    $dirname = $collalias."_".sprintf('%06d',$r['dmrecord']);
    $filestring .= "mkdir ".$dirname."\r\n";
    if($number_of_rows>0){
    // if this is an item with children

        // if this is a pdf compound object with '.pdfpage' children we need to handle things differently
        $childrencheck = $file_db->query("SELECT find FROM records WHERE parent = ".$r['dmrecord']." ORDER BY childseq");
        $pdfpagecheck = $childrencheck->fetch(PDO::FETCH_ASSOC);
        if(strpos($pdfpagecheck['find'], 'page') !== false){
            // use the parent dmrecord to get the full pdf
            $extension = "pdf";
            $filename = $collalias . "_" . sprintf('%06d', $r['dmrecord']) . "_" . sprintf('%06d', 1) . "." . $extension;
            $downloadurl = $cdmutilspath."/utils/getfile/collection/".$collalias."/id/".$r['dmrecord']."/filename/".$filename;
            $filestring .= "wget -P ".$dirname." ".$downloadurl."\r\n";

        } else {
            $children = $file_db->query("SELECT * FROM records WHERE parent = ".$r['dmrecord']." ORDER BY childseq");
          // this is a normal compound object;
          foreach ($children as $c) {
            // construct the filename
            $pathinfo = pathinfo($c['find']);
            $extension = $pathinfo['extension'];
            $filename = $collalias . "_" . sprintf('%06d', $r['dmrecord']) . "_" . sprintf('%06d', $c['childseq']) . "." . $extension;

            $downloadurl = $cdmutilspath."/utils/getfile/collection/".$collalias."/id/".$c['dmrecord']."/filename/".$filename;
            //echo "\tDownload child item ".$c['childseq'].": [".$c['dmrecord']."] and save as \"".$filename."\": ".$downloadurl."<br />";
            $filestring .= "wget -P ".$dirname." ".$downloadurl."\r\n";
            }
        }
  } else {
        // if this is a single item
        $pathinfo = pathinfo($r['find']);
        $extension = $pathinfo['extension'];
        $filename = $collalias."_".sprintf('%06d',$r['dmrecord'])."_".sprintf('%06d', 1).".".$extension;
        $downloadurl = $cdmutilspath."/utils/getfile/collection/".$collalias."/id/".$r['dmrecord']."/filename/".$filename;
        //echo "\tSingle item: download item [".$r['dmrecord']."] and save as \"".$filename."\": ".$downloadurl."<br/>";
        $filestring .= "wget -P ".$dirname." ".$downloadurl."\r\n";
  }


}

$filestring .= "pause";
file_put_contents('download_'.$cdmalias.'.bat',$filestring);
