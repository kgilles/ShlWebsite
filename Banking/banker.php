<html>

<head>
    <title>SHL Hockey -> Banker</title>
    {$headerinclude}

    <style>
        .bojoSection {
            margin-bottom: 20px; 
            border: 1px solid black; 
            border-radius: 2px;
            padding: 10px; 
            background: #f3f3f3; 
        }

        .bojoSection th,
        .bojoSection td {
            padding: 0px 10px;
            text-align: right;
        }

        .bojoSection th:nth-child(1),
        .bojoSection td:nth-child(1) {
            text-align: left;
        }

        .namesTable th,
        .namesTable td {
            padding: 0px 5px;
        }

        .namesTable th:nth-child(2),
        .namesTable td:nth-child(2) {
            width: 90px;
        }

        .namesTable input {
            width: 100%;
        }

        .bojoSection h4,
        .bojoSection h2 {
            margin-top: 0px;
            margin-bottom: 10px;
        }

        .negative {
            font-weight: bold;
        }
        .positive {
            font-weight: bold;
            color: #2ead30;
        }

        hr {
            margin-bottom: 20px;
        }

        .successSection {
            background: #d1f1cf;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid black;
        }

        .successSection h4 {
            margin: 0px;
            margin-bottom: 10px;
        }
        
        .nameCompare {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid black;
        }

        .warning {
            background: yellow;
            font-weight: bold;
        }

        .success {
            background: #d1f1cf;
        }

    </style>
</head>

<body>
    {$header}

    <?php 

        // Gets current user logged in
        $myuid = $mybb->user['uid'];
        if($myuid <= 0) {
            echo "You're not logged in. go away";
            exit;
        }

        // Checks to make sure person visiting page is a banker.
        // Banker id is 13 as of me writing this.
        $groupstring = $mybb->user['usergroup'] . ',' . $mybb->user['additionalgroups'];
        $groups = explode(",", $groupstring);
        if (!in_array("13", $groups)) {
            echo "You're not a banker. Begone!";
            exit;
        }

        // If a submit button was pressed
        if (isset($mybb->input["bojopostkey"])) 
        {
            verify_post_check($mybb->input["bojopostkey"]);
            
            // Submitted list of names to search for
            if (isset($mybb->input["submitnames"]))
            {
                $namelist = $mybb->input["namelist"];

                // Split by commas if present. otherwise split by new lines
                if(strpos($namelist, ',') !== false) {
                    $namesArray = array_map('trim', explode(',', $namelist));
                }
                else {
                    $namesArray = array_map('trim', explode("\n", $namelist));
                }

                for ($x = 0; $x < count($namesArray); $x++) {
                    $namesArray[$x] = "'" . $namesArray[$x] . "'";
                } 
                $names = implode(",", $namesArray);

                // Gets list of users from db
                $nameRows = $db->simple_select("users", "*", "username in (" . $names . ")", array(
                    "order_by" => 'username',
                    "order_dir" => 'ASC'
                ));
            }

            // Submitted Mass Transactions with Separate values
            else if (isset($mybb->input["submitmassseparate"]))
            {
                $namelist = $mybb->input["namelist"];

                $massinsert = array();
                $x = 0;
                while (isset($mybb->input["massid_" . $x]))
                {
                    $currName = $mybb->input["massname_" . $x];
                    $currId = intval($mybb->input["massid_" . $x]);
                    $currTitle = trim(preg_replace('/[^A-Za-z0-9\-]/', '', $mybb->input["masstitle_" . $x]));

                    $currAmount = intval($mybb->input["massamount_" . $x]);
                    $massinsert[] = [
                        "uid" => $currId,
                        "bankerid" => $myuid,
                        "amount" => $currAmount,
                        "title" => $currTitle
                    ];

                    $x++;
                }

                // Adds rows to bank transactions
                $db->insert_query_multiple("banktransactions", $massinsert);

                $x = 0;
                while (isset($mybb->input["massid_" . $x]))
                {
                    $currId = intval($mybb->input["massid_" . $x]);

                    // Updates user's balance
                    $balancequery = "SELECT sum(amount) AS sumamt FROM mybb_banktransactions WHERE uid=$currId";
                    $banksumquery = $db->query($balancequery);
                    $banksumresult = $db->fetch_array($banksumquery);
                    if ($banksumresult != NULL) { $bankbalance = intval($banksumresult['sumamt']); }
                    else { $bankbalance = 0; }
                    $db->update_query("users", array("bankbalance" => $bankbalance), "uid=$currId", 1);

                    $x++;
                }
            }
           
            // Submitted Mass Transactions with the same values
            else if (isset($mybb->input["submitmasstogether"]))
            {
                $namelist = $mybb->input["namelist"];

                $massinsert = array();
                $currTitle = trim(preg_replace('/[^A-Za-z0-9\-]/', '', $mybb->input["masstitleall"]));
                $currAmount = intval($mybb->input["massamountall"]);

                $x = 0;
                while (isset($mybb->input["massid_" . $x]))
                {
                    $currName = $mybb->input["massname_" . $x];
                    $currId = intval($mybb->input["massid_" . $x]);
                    $massinsert[] = [
                        "uid" => $currId,
                        "bankerid" => $myuid,
                        "amount" => $currAmount,
                        "title" => $currTitle
                    ];

                    $x++;
                }
             
                // Adds rows to bank transactions
                $db->insert_query_multiple("banktransactions", $massinsert);

                $x = 0;
                while (isset($mybb->input["massid_" . $x]))
                {
                    $currId = intval($mybb->input["massid_" . $x]);

                    // Updates user's balance
                    $balancequery = "SELECT sum(amount) AS sumamt FROM mybb_banktransactions WHERE uid=$currId";
                    $banksumquery = $db->query($balancequery);
                    $banksumresult = $db->fetch_array($banksumquery);
                    if ($banksumresult != NULL) { $bankbalance = intval($banksumresult['sumamt']); }
                    else { $bankbalance = 0; }
                    $db->update_query("users", array("bankbalance" => $bankbalance), "uid=$currId", 1);

                    $x++;
                }
            }
        }
    ?>

    <?php
        // Display Mass Separate Values submitted
        if (isset($mybb->input["submitmassseparate"]))
        {
            echo '<div class="successSection">';
            echo "<h4>Success: Mass Update Results</h4>";
            $x = 0;
            echo "<table>";
            echo '<tr><th>User</th><th>User Id</th><th>Amount</th><th>Title</th></tr>';
            while (isset($mybb->input["massid_" . $x]))
            {
                $currName = $mybb->input["massname_" . $x];
                $currId = $mybb->input["massid_" . $x];
                $currAmount = $mybb->input["massamount_" . $x];
                $currTitle = $mybb->input["masstitle_" . $x];
                echo '<td><a href="http://simulationhockey.com/playerupdater.php?uid=' . $currId . '">'.$currName.'</a></td>';
                echo '<td>'.$currId.'</td><td>$'.$currAmount.'</td><td>'.$currTitle.'</td></tr>';
                $x++;
            }
            echo "</table>";
            echo '</div>';
        }

        // Display Mass Values submitted
        else if (isset($mybb->input["submitmasstogether"]))
        {
            echo '<div class="successSection">';
            echo "<h4>Success: Mass Update Results</h4>";
            $x = 0;
            echo "<table>";
            echo '<tr><th>User</th><th>User Id</th><th>Amount</th><th>Title</th></tr>';
            while (isset($mybb->input["massid_" . $x]))
            {
                $currName = $mybb->input["massname_" . $x];
                $currId = $mybb->input["massid_" . $x];
                $currAmount = $mybb->input["massamountall"];
                $currTitle = $mybb->input["masstitleall"];
                echo '<td><a href="http://simulationhockey.com/playerupdater.php?uid=' . $currId . '">'.$currName.'</a></td>';
                echo '<td>'.$currId.'</td><td>$'.$currAmount.'</td><td>'.$currTitle.'</td></tr>';
                $x++;
            }
            echo "</table>";
            echo '</div>';
        }
    ?>

    <div class="bojoSection">
    <h2>Banker Controls</h2>
    <h4>Mass Update</h4>
    <small>submit a list of usernames separated by either commas or new lines.</small>
    <form method="post">
    <textarea name="namelist" rows="8"><?php echo $namelist ?></textarea><br />
    <input type="submit" name="submitnames" value="Get Users" />
    <?php
        if($nameRows != NULL)
        {
            $nameCount = mysqli_num_rows($nameRows);
            $enteredCount = count($namesArray);
            if($nameCount > 0)
            {
                echo '<hr />';
                if ($nameCount != $enteredCount) {
                    echo '<div class="nameCompare warning">';
                }
                else { echo '<div class="nameCompare success">'; }
                echo count($namesArray) . ' names entered<br/>' . $nameCount . ' names found';
                echo '</div>';
                echo '<table class="namesTable">';
                echo '<tr><th>username</th><th>amount</th><th>title</th></tr>';

                $massIndex = 0;
                while ($namerow = $db->fetch_array($nameRows))
                {
                    echo "<tr><td>" . $namerow['username'] . "</td>";
                    // echo "<td>" . $namerow['uid'] . "</td>";
                    echo '<td><input type="number" name="massamount_' . $massIndex . '" value="0" /></td>';
                    echo '<td><input type="text" name="masstitle_' . $massIndex . '" /></td>';
                    echo '<input type="hidden" name="massid_' . $massIndex . '" value="' . $namerow['uid'] . '" />';
                    echo '<input type="hidden" name="massname_' . $massIndex . '" value="' . $namerow['username'] . '" />';
                    echo "</tr>";
                    $massIndex++;
                }
                echo '<tr><td colspan="3" style="height: 8px"></td></tr>';
                echo '<tr><td></td><td colspan="2"><input type="submit" name="submitmassseparate" value="Submit Separate" /></td></tr>';
                echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />';
                echo '<tr><td colspan="3" style="height: 22px"></td></tr>';
                echo '<tr><td></td>';
                echo '<td><input type="number" name="massamountall" value="0" /></td>';
                echo '<td><input type="text" name="masstitleall" /></td>';
                echo "</tr>";
                echo '<tr><td colspan="3" style="height: 8px"></td></tr>';
                echo '<tr><td></td><td colspan="2"><input type="submit" name="submitmasstogether" value="Submit Together" /></td></tr>';

                echo '</table>';
                echo '<p>Submit separate to have each user have different values.<br />Submit together to have all the users share the same transaction</p>';
            }
        }
        echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />';
    ?>
    </form>
    </div>

    <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html>