<html>

<head>
    <title>SHL Hockey -> Change User Teams</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php include 'bankerOps.php';

    $myuid = getUserId($mybb);

    // if not logged in, go away why are you even here
    if ($myuid <= 0) {
        echo 'You are not logged in';
        exit;
    }

    $isBanker = checkIfBanker($mybb);
    if (!$isBanker) {
        echo "You're not a banker, shoo";
        exit;
    }

    function displaySuccessMsg($message)
    {
        echo '<div class="successSection"><h4>Success: ' . $message . '</h4></div>';
        // TODO: team change summary
    }

    function getTeamDropDown($shlTeams, $rowIndex)
    {
        echo '<select name="massteamid_' . $rowIndex . '" id="massteamid_' . $rowIndex . '">
        <option value="999">Unassigned</option>';

        // echo '<option value="0">---- SHL TEAMS ----</option>';

        foreach ($shlTeams as $item)
            echo '<option value="' . $item['id'] . '">' . $item['name'] . '</option>';

        // echo '<option value="0">---- SMJHL TEAMS ----</option>';

        // foreach ($smjhlTeams as $item)
        //     echo '<option value="' . $item['id'] . '">' . $item['name'] . '</option>';

        echo '</select>';
    }

    function getTeamDropDownRosterForum($shlTeams)
    {
        echo '<select name="massrosterforum" id="massrosterforum">';
        echo '<option value="0">Select a team...</option>';
        // echo '<option value="0">---- SHL TEAMS ----</option>';

        foreach ($shlTeams as $item)
            echo '<option value="' . $item['id'] . '">' . $item['name'] . '</option>';

        // echo '<option value="0">---- SMJHL TEAMS ----</option>';

        // foreach ($smjhlTeams as $item)
        //     echo '<option value="' . $item['id'] . '">' . $item['name'] . '</option>';

        echo '</select>';
    }

    $xQuery = $db->simple_select("teams", "*", "id > 1", array("order_by" => 'name', "order_dir" => 'ASC'));

    while ($xRow = $db->fetch_array($xQuery)) {
        if ($xRow['league'] == "SHL") {
            $shlTeams[] = ["id" => $xRow['id'], "name" => $xRow['name']];
        } 
        // else {
        //     $smjhlTeams[] = ["id" => $xRow['id'], "name" => $xRow['name']];
        // }
    }

    // If a submit button was pressed
    if (isset($mybb->input["bojopostkey"])) {
        verify_post_check($mybb->input["bojopostkey"]);

        // keeps names in text box
        $namelist = trim($mybb->input["namelist"]);

        // Submitted list of names to search for
        // Split by commas if present. otherwise split by new lines
        if (isset($mybb->input["submitnames"])) {
            logAction($db, "ACTION", "$myuid attempts to submit a list of names for team assignments");
            $charToSplit = (strpos($namelist, ',') !== false) ? "," : "\n";
            $namesArray = array_map('trim', explode($charToSplit, $namelist));

            for ($x = 0; $x < count($namesArray); $x++)
                $namesArray[$x] = "'" . getSafeString($db, $namesArray[$x]) . "'";

            $names = implode(",", $namesArray);

            // Gets list of users from db
            $xQueryNames = $db->simple_select("users", "*", "username in (" . $names . ")", array("order_by" => 'username', "order_dir" => 'ASC'));

            $nameCount = mysqli_num_rows($xQueryNames);
            $nameEnteredCount = count($namesArray);
        }

        // Submitted add team from roster forum
        else if (isset($mybb->input["submitforumroster"])) {
            logAction($db, "ACTION", "$myuid attempts to get a list of names for team assignments");

            $teamid = getSafeNumber($db, $mybb->input["massrosterforum"]);

            if ($teamid > 0) {
                $xQuery = $db->simple_select("teams", "*", "id=$teamid", array("limit" => 1));
                if ($xRow = $db->fetch_array($xQuery)) {
                    $teamname = $xRow['name'];
                    $teamforumid = intval($xRow['rosterforumid']);

                    // removes all users assigned to the team
                    $db->update_query("users", array("teamid" => null), "teamid=$teamid");

                    // Updates users in team subforum
                    $threadquery = $db->simple_select("threads", "*", "fid=$teamforumid", array());
                    while ($row = $db->fetch_array($threadquery)) {
                        $userid = $row['uid'];
                        $usernames[] = $row['username'];
                        $db->update_query("users", array("teamid" => $teamid), "uid=$userid", 1);
                    }

                    // Updates users in team prospects subforum
                    $threadquery = $db->simple_select("forums", "*", "pid=$teamforumid", array());
                    while ($subrow = $db->fetch_array($threadquery)) {
                        $prospectforumid = intval($subrow['fid']);
                        $threadquery = $db->simple_select("threads", "*", "fid=$prospectforumid", array());
                        while ($row = $db->fetch_array($threadquery)) {
                            $userid = $row['uid'];
                            $usernames[] = $row['username'];
                            $db->update_query("users", array("teamid" => $teamid), "uid=$userid", 1);
                        }
                    }

                    echo '<div class="successSection">
                        <h4>Success: Players added from Roster Forum</h4>
                        Team: ' . $teamname .
                        '<ul>';

                    foreach ($usernames as $item)
                        echo '<li>' . $item . '</li>';

                    echo '</ul>
                      </div>';
                }
            }
        }

        // Submitted player names to teams
        else if (isset($mybb->input["submitmassseparate"])) {
            logAction($db, "ACTION", "$myuid attempts to assign a list of users to a team");
            $isValid = true;

            $x = 0;
            while (isset($mybb->input["massid_" . $x])) {
                $currId = getSafeNumber($db, $mybb->input["massid_$x"]);
                $teamid = getSafeNumber($db, $mybb->input["massteamid_$x"]);

                // Invalid options
                if ($teamid == 0) {
                    $x++;
                    continue;
                }

                // Unassigning a player
                if ($teamid == 999)
                    $teamid = null;

                $massupdate[] = [
                    "id" => $currId,
                    "teamid" => $teamid,
                ];

                $x++;
            }

            if ($isValid) {
                // Assign the users to a team
                foreach ($massupdate as $item) {
                    $teamid = $item['teamid'];
                    $userid = $item['id'];

                    // IDK how to use update_query to set something null. Research mybb's functions. Also date now()
                    if ($teamid == null) {
                        $updateTeamQuery = "UPDATE mybb_users SET teamid=NULL WHERE mybb_users.uid=$userid LIMIT 1";
                        $db->write_query($updateTeamQuery);
                    } else {
                        $db->update_query("users", array("teamid" => $teamid), "uid=$userid", 1);
                    }
                }

                displaySuccessMsg("Change teams for users");
            } else {
                echo '<div class="errorSection"><h4>Error: There was something wrong.</h4></div>';
            }
        }
    }
    ?>

    <div class="bojoSection navigation">
        <h2>Add Users to Team from Roster Forum</h2>
        <form method="post">
            <?php getTeamDropDownRosterForum($shlTeams); ?>
            <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code; ?>" />
            <input type="submit" name="submitforumroster" value="Update Users from Roster Forum" />
        </form>
    </div>

    <div class="bojoSection navigation">
        <h2>Add Users to Team Manually</h2>
        <p>Assign Users to a specific team.</p>
        <p>First enter a list of user names. Then select a team for each person. Click "Fill the rest" to have the rest of the users copy the first user's team.</p>
        <small>submit a list of usernames separated by either commas or new lines.</small>
        <form method="post">
            <textarea name="namelist" rows="8"><?php echo $namelist ?></textarea><br />
            <input type="submit" name="submitnames" value="Get Users" />
            <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code; ?>" />
        </form>

        <?php
        if ($nameEnteredCount > 0) {
            echo '<hr />';
            if ($nameCount > 0) {
                if ($nameCount != $nameEnteredCount)
                    echo '<div class="nameCompare warning">';
                else
                    echo '<div class="nameCompare success">';

                echo count($namesArray) . ' names entered<br/>' . $nameCount . ' names found';
                echo '</div>
                      <form onsubmit="return validateForms()" method="post">
                      <table class="namesTable">
                          <tr><th>username</th><th>team</th></tr>';

                $massIndex = 0;
                while ($xRow = $db->fetch_array($xQueryNames)) {
                    echo "<tr><td>" . $xRow['username'] . "</td>";

                    echo '<td>';
                    getTeamDropDown($shlTeams, $massIndex);
                    echo "</td>";

                    echo '<input type="hidden" name="massid_' . $massIndex . '" value="' . $xRow['uid'] . '" />
                          <input type="hidden" name="massname_' . $massIndex . '" value="' . $xRow['username'] . '" />';

                    if ($massIndex === 0)
                        echo '<td><input type="button" onclick="fillInUsers()" value="Fill the rest" /></td>';

                    echo "</tr>";
                    $massIndex++;
                }

                echo '<tr><td style="height: 8px"></td></tr>
                      <tr><td colspan="1"></td><td><input type="submit" name="submitmassseparate" value="Add Users to Teams" /></td></tr>
                      </table>
                      <input type="hidden" name="namelist" value="' . $namelist . '" />
                      <input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />
                      </form>';
            }
        }
        ?>
    </div>

    <script>
        function fillInUsers() {
            var i = 0;
            var firstSelectedIndex = 0;
            while (true) {
                var idTeam = "massteamid_" + i;
                if (document.getElementById(idTeam) !== null) {
                    console.log("not null");
                    if (i == 0) {
                        firstSelectedIndex = document.getElementById(idTeam).selectedIndex;
                    } else {
                        document.getElementById(idTeam).selectedIndex = firstSelectedIndex;
                    }
                } else {
                    break;
                }
                i++;
            }
        }

        function validateForms() {
            var i = 0;
            while (true) {
                var idTeam = "massteamid_" + i;
                if (document.getElementById(idTeam) !== null) {
                    var teamid = document.getElementById(idTeam).selectedIndex;
                    if (teamid <= 1) {
                        // alert ("A team was not set");
                        // return false;
                    }
                } else {
                    break;
                }
                i++;
            }

            if (i == 0) {
                alert("Where are the people?");
                return false;
            }

            return true;
        }
    </script>

    <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html>