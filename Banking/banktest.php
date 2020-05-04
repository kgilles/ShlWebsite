<?php 

include 'bankerOps.php';

$myuid = getUserId($mybb);
if (isset($_GET["uid"]) && is_numeric($_GET["uid"])) {
    $myuid = getSafeNumber($db, $_GET["uid"]);
}

echo "userid: " . $myuid;
echo "<br />";

if (canDoTraining($db, $myuid))
{
    echo "can do training";
}
else
{
    echo "can not do training";
}

?>