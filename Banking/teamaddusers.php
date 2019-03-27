<html>

<head>
    <title>SHL Hockey -> Change User Teams</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php 
    include 'bankerOps.php';

    function displaySuccessMsg($message)
    {
        echo '<div class="successSection">';
        echo '<h4>Success: ' . $message . '</h4>';
        // TODO: team change summary
        echo '</div>';
    }

    function getTeamDropDown($teams, $rowIndex)
    {
        echo '<select name="massteamid_' . $rowIndex . '" id="massteamid_' . $rowIndex . '">';
        echo '<option value="999">Unassigned</option>';
        for ($x = 0; $x < count($teams); $x++) {
            $teams[$x]['id'];
            echo '<option value="' . $teams[$x]['id'] . '">' . $teams[$x]['name'] . '</option>';
        }
        echo '</select>';
    }

    function getTeamDropDownRosterForum($teams)
    {
        echo '<select name="massteamforum" id="massteamforum">';
        for ($x = 0; $x < count($teams); $x++) {
            $teams[$x]['id'];
            echo '<option value="' . $teams[$x]['id'] . '">' . $teams[$x]['name'] . '</option>';
        }
        echo '</select>';
    }

    $myuid = getUserId($mybb);

    // if not logged in, go away why are you even here
    if ($myuid <= 0) {
        echo 'You are not logged in';
        exit;
    }

    $isBanker = checkIfBanker($mybb);

    $teamRows = $db->simple_select("teams", "*", "id > 1", array(
        "order_by" => 'name',
        "order_dir" => 'ASC'
    ));

    while ($teamrow = $db->fetch_array($teamRows)) {
        $teams[] = [
            "id" => $teamrow['id'],
            "name" => $teamrow['name'],
        ];
    }

    // If a submit button was pressed
    if (isset($mybb->input["bojopostkey"])) {
        verify_post_check($mybb->input["bojopostkey"]);

        // keeps names in text box
        $namelist = trim($mybb->input["namelist"]);

        // Submitted list of names to search for
        if (isset($mybb->input["submitnames"])) {
            // Split by commas if present. otherwise split by new lines
            $charToSplit = (strpos($namelist, ',') !== false) ? "," : "\n";
            $namesArray = array_map('trim', explode($charToSplit, $namelist));

            for ($x = 0; $x < count($namesArray); $x++)
                $namesArray[$x] = "'" . getSafeString($db, $namesArray[$x]) . "'";

            $names = implode(",", $namesArray);

            // Gets list of users from db
            $nameRows = $db->simple_select("users", "*", "username in (" . $names . ")", array(
                "order_by" => 'username',
                "order_dir" => 'ASC'
            ));
        }

        // Submitted add team from roster forum
        else if (isset($mybb->input["submitforumroster"])) {
            $teamid = getSafeNumber($db, $mybb->input["massteamforum"]);

            $teamquery = $db->simple_select("teams", "*", "id=$teamid", array("limit" => 1));
            $teamrow = $db->fetch_array($teamquery);
            $teamname = $teamrow['name'];
            $teamforumid = intval($teamrow['rosterforumid']);

            $threadquery = $db->simple_select("threads", "*", "fid=$teamforumid", array());
            while ($row = $db->fetch_array($threadquery)) {
                $userid = $row['uid'];
                $usernames[] = $row['username'];
                $db->update_query("users", array("teamid" => $teamid), "uid=$userid", 1);
            }

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

            echo '<div class="successSection">';
            echo '<h4>Success: Players added from Roster Forum';
            echo 'Team: ' . $teamname;
            echo '<ul>';
            for ($x = 0; $x < count($usernames); $x++) {
                echo '<li>' . $usernames[$x] . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        // Submitted player names to teams
        else if (isset($mybb->input["submitmassseparate"])) {
            $isValid = true;

            $x = 0;
            $massinsert = array();
            while (isset($mybb->input["massid_" . $x])) {
                $currId = getSafeNumber($db, $mybb->input["massid_$x"]);
                $teamid = getSafeNumber($db, $mybb->input["massteamid_$x"]);

                if ($teamid == 999) {
                    $teamid = null;
                }

                $massupdate[] = [
                    "id" => $currId,
                    "teamid" => $teamid,
                ];

                $x++;
            }

            if ($isValid) {
                for ($x = 0; $x < count($massupdate); $x++) {
                    $teamid = $massupdate[$x]['teamid'];
                    $userid = $massupdate[$x]['id'];

                    if ($teamid == null) {
                        // IDK how to use update_query to set something null. 
                        $updateTeamQuery = "UPDATE mybb_users SET teamid=NULL WHERE mybb_users.uid=$userid LIMIT 1";
                        $db->write_query($updateTeamQuery);
                    } else {
                        $db->update_query("users", array("teamid" => $teamid), "uid=$userid", 1);
                    }
                }

                displaySuccessMsg("Change teams for users");
            } else {
                echo '<div class="errorSection">';
                echo '<h4>Error: There was something wrong.</h4>';
                echo '</div>';
            }
        }
    }
    ?>

    <div class="bojoSection navigation">
        <h2>Add Users to Team from Roster Forum</h2>
        <form method="post">
            <?php getTeamDropDownRosterForum($teams); ?>
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
        if ($nameRows != null) {
            $nameCount = mysqli_num_rows($nameRows);
            $enteredCount = count($namesArray);
            if ($nameCount > 0) {
                echo '<hr />';
                if ($nameCount != $enteredCount) {
                    echo '<div class="nameCompare warning">';
                } else {
                    echo '<div class="nameCompare success">';
                }
                echo count($namesArray) . ' names entered<br/>' . $nameCount . ' names found';
                echo '</div>';
                echo '<form onsubmit="return validateForms()" method="post">';
                echo '<table class="namesTable">';
                echo '<tr><th>username</th><th>team</th></tr>';

                $massIndex = 0;
                while ($namerow = $db->fetch_array($nameRows)) {
                    echo "<tr><td>" . $namerow['username'] . "</td>";
                    echo '<td>';
                    getTeamDropDown($teams, $massIndex);
                    echo "</td>";
                    echo '<input type="hidden" name="massid_' . $massIndex . '" value="' . $namerow['uid'] . '" />';
                    echo '<input type="hidden" name="massname_' . $massIndex . '" value="' . $namerow['username'] . '" />';
                    if ($massIndex === 0) {
                        echo '<td><input type="button" onclick="fillInUsers()" value="Fill the rest" /></td>';
                    }
                    echo "</tr>";
                    $massIndex++;
                }
                echo '<tr><td style="height: 8px"></td></tr>';
                echo '<tr><td colspan="1"></td><td><input type="submit" name="submitmassseparate" value="Add Users to Teams" /></td></tr>';
                echo '</table>';
                echo '<input type="hidden" name="namelist" value="' . $namelist . '" />';
                echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />';
                echo '</form>';
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