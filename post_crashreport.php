<?php
    // Outputs all POST parameters to a text file. The file name is the date_time of the report reception
    $json = file_get_contents('php://input');

    //$fileName = "crashes/".date('Y-m-d_H-i-s').'.json';
    //$file = fopen($fileName,'w') or die('Could not create report file: ' . $fileName);
    //fwrite($file, $json) or die ('Could not write to report file ' . $reportLine);
    //fclose($file);

    $m = new Mongo(); // connect
    $db = $m->selectDB("sharengo");

    $report = json_decode($json);
    if ($report)    {
        $report->REPORT_DATETIME = new MongoDate();
        $db->crash_reports->insert($report);
    }


?>