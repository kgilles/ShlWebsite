<?php

define("IN_MYBB", 1);
include 'global.php';
include 'bankerOps.php';

// Gets id of user from URL
if (isset($_GET["uid"]) && is_numeric($_GET["uid"]))
    $userid = getSafeNumber($db, $_GET["uid"]);

$curruser = getUser($db, $userid);
if ($curruser == null) {
    header('Location: http://simulationhockey.com/bankaccount.php');
    exit;
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="shlbankaccount_'.$userid.'.csv"');

$myuid = getUserId($mybb);
logAction($db, "ACTION", "$myuid attempts to export a bank account");

if ($curruser['teamid'] !== null) {
    $xQuery = $db->simple_select("teams", "*", "id=" . $curruser['teamid'], array("limit" => 1));
    if ($xRow = $db->fetch_array($xQuery))
        $teamname = $xRow['name'];
    else
        $teamname = 'fjasdlkfjsd';
}
if ($teamname == null)
    $teamname = "Unassigned";

$exportDataCsv[] = "User," . $curruser['username'];
$exportDataCsv[] = "Team," . $teamname;
$exportDataCsv[] = "UserId,$userid";
$exportDataCsv[] = "Balance," . $curruser['bankbalance'];
$exportDataCsv[] = "";
$exportDataCsv[] = "Title,Amount,Date,Made By,Description";

$querytext =
    "SELECT bt.*, creator.username AS 'creator'
        FROM mybb_banktransactions bt
        LEFT JOIN mybb_users creator ON bt.createdbyuserid=creator.uid
        WHERE bt.uid=$userid
        ORDER BY bt.date DESC";

$xQuery = $db->query($querytext);
while ($xRow = $db->fetch_array($xQuery))
    $exportDataCsv[] = $xRow['title'] . "," . $xRow['amount'] . "," . $xRow['date'] . "," . $xRow['creator'] . "," . $xRow['description'];

$fp = fopen('php://output', 'wb');
foreach ($exportDataCsv as $line) {
    $val = explode(",", $line);
    fputcsv($fp, $val);
}
fclose($fp);

$db->close;

?>
--- 