<html>

<head>
    <title>SHL Hockey -> Bank Team Summary</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php include 'bankerOps.php';

    $myuid = getUserId($mybb);

    // if not logged in, go away
    if ($myuid <= 0) {
        echo 'You are not logged in';
        exit;
    }

    // Gets id of team from URL
    if (isset($_GET["id"]) && is_numeric($_GET["id"]))
        $currentTeamId = getSafeNumber($db, $_GET["id"]);
    else
        header('Location: http://simulationhockey.com/banksummary.php');

    $isBanker = checkIfBanker($mybb);

    $xQuery = $db->simple_select("teams", "*", "id=$currentTeamId", array("limit" => 1));
    if ($xRow = $db->fetch_array($xQuery)) {
        $teamname = $xRow['name'];
        $teamforumid = $xRow['rosterforumid'];

        // Get users with matching team
        $xQueryUser = $db->simple_select("users", "*", "teamid=$currentTeamId", array());
        while ($xRowUser = $db->fetch_array($xQueryUser)) {
            $userid = intval($xRowUser['uid']);
            $username = $xRowUser['username'];
            $userbalance = intval($xRowUser['bankbalance']);
            $teamusers[] = [
                "uid" => $userid,
                "username" => $username,
                "bankbalance" => $userbalance,
            ];
        }

        // Sorts by name
        usort($teamusers, function ($item1, $item2) {
            return $item1['username'] <=> $item2['username'];
        });
    }

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

                echo '<tr>
                        <td><a href="' . $userlink . '">' . $user["username"] . '</a></td>
                        <td>' . $bankoutput . '</td>
                      </tr>';
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