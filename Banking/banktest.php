<?php 

include 'bankerOps.php';

function getLatestTrainingDate($db, $userId) {
    $xQuery = $db->simple_select("banktransactions", "date", "uid=$userId AND title like 'Training +%'", array("limit" => 1, "order_by" => "id", "order_dir" => 'DESC'));
    if ($xRow = $db->fetch_array($xQuery)) { 
        $foundDate = $xRow['date']; 
        return strtotime($foundDate);
    }

    return null;
}

function canDoTraining($db, $userId) {

    // Server Timezone is GMT (UTC+0)
    // Deadline is midnight ET
    date_default_timezone_set('America/New_York');
    
    $lastTrainingDate = getLatestTrainingDate($db, $userId);
    
    if($lastTrainingDate == null) 
    {
        return false;
    }
    
    $lastMonday = strtotime('Monday this week');
    $nowDate = strtotime('now');
    
    echo date('Y-m-d H:i', $lastMonday) . ' -- monday';
    echo '<br />';
    echo date('Y-m-d H:i', $lastTrainingDate) . ' -- training';
    echo '<br />';
    echo date('Y-m-d H:i', $nowDate) . ' -- now';
    echo '<br />';

    return ($lastTrainingDate < $lastMonday) || ($lastTrainingDate > $nowDate);
} 

$myuid = getUserId($mybb);
if (isset($_GET["uid"]) && is_numeric($_GET["uid"])) {
    $myuid = getSafeNumber($db, $_GET["uid"]);
}

if (canDoTraining($db, $myuid))
{
    echo "can do training";
}
else
{
    echo "can not do training";
}

?>