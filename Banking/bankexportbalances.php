<?php

define("IN_MYBB", 1);
include 'global.php';
include 'bankerOps.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="shlbankbalances.csv"');

$querytext =
    "SELECT username, bankbalance
        FROM mybb_users
        WHERE bankbalance > 0 OR bankbalance < 0
        ORDER BY username ASC";

$xQuery = $db->query($querytext);
while ($xRow = $db->fetch_array($xQuery))
    $exportDataCsv[] = $xRow['username'] . "," . $xRow['bankbalance'];

$fp = fopen('php://output', 'wb');
foreach ($exportDataCsv as $line) {
    $val = explode(",", $line);
    fputcsv($fp, $val);
}
fclose($fp);

?>
--- 