<html>

<head>
    <title>SHL Hockey -> Bank Team Summary</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php 

    include 'bankerOps.php';

    $myuid = getUserId($mybb);

    // if not logged in, go away why are you even here
    if ($myuid <= 0) {
        echo 'You are not logged in';
        exit;
    }

    // Gets id of team from URL
    $tid = 0;
    if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
        $tid = getSafeNumber($db, $_GET["id"]);
    } else {
        // ... or redirects to your own page
        header('Location: http://simulationhockey.com/banksummary.php');
    }

    $isBanker = checkIfBanker($mybb);

    $teamquery = $db->simple_select("teams", "*", "id=$tid", array("limit" => 1));
    $teamrow = $db->fetch_array($teamquery);
    $teamname = $teamrow['name'];
    $teamforumid = $teamrow['rosterforumid'];

    $threadquery = $db->simple_select("users", "*", "teamid=$tid", array());
    while ($row = $db->fetch_array($threadquery)) {
        $userid = intval($row['uid']);
        $username = $row['username'];
        $userbalance = intval($row['bankbalance']);
        $teamusers[] = [
            "uid" => $userid,
            "username" => $username,
            "bankbalance" => $userbalance,
        ];
    }

    $threadquery = $db->simple_select("forums", "*", "pid=$teamforumid", array());
    while ($subrow = $db->fetch_array($threadquery)) {
        $prospectforumid = intval($subrow['fid']);
        $threadquery = $db->simple_select("threads", "*", "fid=$prospectforumid", array());
        while ($row = $db->fetch_array($threadquery)) {
            $userid = intval($row['uid']);
            $username = $row['username'];
            $userbalance = intval($row['bankbalance']);

            $isduplicate = false;
            foreach ($teamusers as $user) {
                if ($user["uid"] == $userid) { 
                    $isduplicate = true;
                    break;
                }
            }

            if ($isduplicate) {
                continue;
            }

            $teamusers[] = [
                "uid" => $userid,
                "username" => $username,
                "bankbalance" => $userbalance,
            ];
        }
    }

    usort($teamusers, function ($item1, $item2) {
        return $item1['username'] <=> $item2['username'];
    });

    array_unique($teamusers);

    ?>

    <!-- User Information -->
    <div class="bojoSection navigation">
        <h2><?php echo $teamname ?></h2>
        <table>
            <tr>
                <th>User</th>
                <th>Balance</th>
            </tr>
            <?php
            foreach ($teamusers as $user) {
                $negativeSign = ($user["bankbalance"] < 0) ? '-' : '';
                $bankoutput = $negativeSign . '$' . number_format(abs($user["bankbalance"]), 0);
                $userid = intval($user["uid"]);
                $userlink = getBankAccountLink($userid);

                echo '<tr>';
                echo '<td><a href="'.$userlink.'">' . $user["username"] . '</a></td>';
                echo '<td>' . $bankoutput . '</td>';
                echo '</tr>';
            }
            ?>
        </table>
    </div>

    <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html> 