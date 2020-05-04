<?php 

include 'bankerOps.php';

function xxxgetLastTwoTrainingDates($db, $userId) {
    $xQuery = $db->simple_select("banktransactions", "id, date", "uid=$userId AND title like 'Training +%'", array("limit" => 2, "order_by" => "id", "order_dir" => 'DESC'));
    $cart = array();
    // while($xRow = $db->fetch_array($xQuery)) {
    //     array_push($cart, $xRow['date']);
    // }

    array_push($cart, '5/4/2020 2:00 am');
    array_push($cart, '5/4/2020 12:01 am');

    return $cart;
}

function xxxcanDoTraining($db, $userId) {

    // $tz = new DateTimeZone('America/New_York');
    $tz = new DateTimeZone('America/Los_Angeles');
    $newWeekStart = new DateTime('Monday this week', $tz);
    $newWeekEnd = new DateTime('Monday this week +3 hours', $tz);
    $oldWeekStart = new DateTime('Monday this week -7 days', $tz);
    $dateformat = "m-d H:i T";
   
    $trainings = xxxgetLastTwoTrainingDates($db, $userId);
    $trainingCount = count($trainings);
    $lastTraining = new DateTime($trainings[0], $tz);

    if ($trainingCount == 0) {
        return true;
    }
    else if ($trainingCount == 1) {
        echo 'only one training';
        echo '<br />';
        echo $newWeekEnd->format($dateformat) . " -- end<br />";
        echo $lastTraining->format($dateformat) . " -- last train<br />";
        echo '<br />---------------';
        echo '<br />';
        return $lastTraining < $newWeekEnd;
    }

    // $point = new DateTime('now', $tz);
    $point = new DateTime('5/4/2020 2:50am', $tz);

    $prevTraining = new DateTime($trainings[1], $tz);

    echo $oldWeekStart->format($dateformat) . " -- old week end<br />";
    echo $newWeekStart->format($dateformat) . " -- start<br />";
    echo $point->format($dateformat) . " -- now<br />";
    echo $newWeekEnd->format($dateformat) . " -- end<br />";
   
    echo '<br />';
    echo $lastTraining->format($dateformat) . " -- last train<br />";
    echo $prevTraining->format($dateformat) . " -- prev train<br />";

    $result = false;
    if ($newWeekStart < $point && $point < $newWeekEnd) {
        echo 'overlap';
        $result = ($prevTraining < $newWeekStart);
    }
    else {
        echo 'normal';
        $result = 
            ($lastTraining < $newWeekStart) ||
            ($lastTraining < $newWeekEnd && $prevTraining < $oldWeekStart);
    }

    echo '<br />';
    echo '<br />---------------';
    echo '<br />';

    return $result;
}

$myuid = getUserId($mybb);
if (isset($_GET["uid"]) && is_numeric($_GET["uid"])) {
    $myuid = getSafeNumber($db, $_GET["uid"]);
}

echo "userid: " . $myuid;
echo '<br />---------------';
echo '<br />';

if (xxxcanDoTraining($db, $myuid))
{
    echo "can do training";
}
else
{
    echo "can not do training";
}

?>