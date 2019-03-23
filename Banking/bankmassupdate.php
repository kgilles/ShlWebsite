<html>

<head>
    <title>SHL Hockey -> Bank Mass Update</title>
    {$headerinclude}

    <style>
        .bojoSection {
            margin-bottom: 20px; 
            border: 1px solid black; 
            border-radius: 2px;
            padding: 10px; 
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
            color: black;
        }

        .errorSection {
            background: #f0cfcf;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid black;
            color: black;

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
    include 'bankerOps.php';

    function displaySuccessGroup($groupName, $mybb, $groupid, $message) {
        echo '<div class="successSection">';
        echo '<h4>Success: ' . $message . '</h4>';
        echo '<table>';
        echo '<tr><th>Group Name</th><td colspan="3"><a href="http://simulationhockey.com/bankgrouptransaction.php?id=' . $groupid . '">' . $groupName . '</td></tr>';
        echo '<tr><th>User</th><th>Amount</th></tr>';
        $x = 0;
        while (isset($mybb->input["massid_" . $x]))
        {
            $currName = $mybb->input["massname_" . $x];
            $currId = $mybb->input["massid_" . $x];
            $currAmount = $mybb->input["massamount_" . $x];

            echo '<td><a href="http://simulationhockey.com/playerupdater.php?uid=' . $currId . '">'.$currName.'</a></td>';
            echo '<td>$'.$currAmount.'</td></tr>';

            $x++;
        }
        echo '</table>';
        echo '</div>';
    }

    // Gets current user logged in
    $myuid = $mybb->user['uid'];

    // if not logged in, go away why are you even here
    if ($myuid <= 0) { echo 'You are not logged in'; exit; }

    $isBanker = checkIfBanker($mybb);
    // $isBanker = false; // TODO: remove for testing

    // If a submit button was pressed
    if (isset($mybb->input["bojopostkey"])) 
    {
        verify_post_check($mybb->input["bojopostkey"]);

        // keeps names in text box
        $namelist = trim($mybb->input["namelist"]);

        // Submitted list of names to search for
        if (isset($mybb->input["submitnames"]))
        {
            // Split by commas if present. otherwise split by new lines
            $charToSplit = (strpos($namelist, ',') !== false) ? "," : "\n";
            $namesArray = array_map('trim', explode($charToSplit, $namelist));

            for ($x = 0; $x < count($namesArray); $x++) 
                $namesArray[$x] = "'" . getEscapeString($db, $namesArray[$x]) . "'";

            $names = implode(",", $namesArray);

            // Gets list of users from db
            $nameRows = $db->simple_select("users", "*", "username in (" . $names . ")", array(
                "order_by" => 'username',
                "order_dir" => 'ASC'
            ));
        }

        // Submitted Mass Transactions
        else if (isset($mybb->input["submitmassseparate"]))
        {
            // Validate data
            // Create the group
            // If Banker, create the transactions directly
            // If Not Banker, create the requests

            $isValid = true;

            $groupName = getSafeInputAlpNum($db, $mybb, "massgroupname");
            if(strlen($groupName) <= 0)
            {
                $isValid = false;
            }

            $x = 0;
            $massinsert = array();
            while (isset($mybb->input["massid_" . $x]))
            {
                $currId = getSafeInputNum($db, $mybb, "massid_$x");
                $currAmount = getSafeInputNum($db, $mybb, "massamount_$x");
                $currTitle = getSafeInputAlpNum($db, $mybb, "masstitle_$x");
                $currDescription = getSafeInputAlpNum($db, $mybb, "massdescription_$x");

                if (strlen($currTitle) <= 0 || $currAmount == 0) { $isValid = false; break; }
                if (strlen($currDescription) == 0) { $currDescription = null; }

                if ($isBanker) 
                {
                    $massinsert[] = [
                        "uid" => $currId,
                        "amount" => $currAmount,
                        "title" => $currTitle,
                        "description" => $currDescription,
                        "createdbyuserid" => $myuid,
                        "bankerapproverid" => $myuid,
                    ];
                }
                else
                {
                    $massinsert[] = [
                        "uid" => $currId,
                        "amount" => $currAmount,
                        "title" => $currTitle,
                        "description" => $currDescription,
                    ];
                }

                $x++;
            }

            if ($isValid)
            {
                if ($isBanker)
                {
                    $groupArray = [
                        "creatorid" => $myuid,
                        "groupname" => $groupName,
                        "bankerid" => $myuid,
                        "isapproved" => 1
                    ];
                }
                else
                {
                    $groupArray = [
                        "creatorid" => $myuid,
                        "groupname" => $groupName
                    ];
                }

                $db->insert_query("banktransactiongroups", $groupArray);
    
                $grouprows = $db->simple_select("banktransactiongroups", "*", "groupname='$groupName'", array("order_by" => 'requestdate', "order_dir" => 'DESC', "limit" => 1));
                $groupresult = $db->fetch_array($grouprows);
                $groupid = intval($groupresult['id']);
                $groupName = $groupresult['groupname'];

                $db->update_query("banktransactiongroups", array("decisiondate" => $groupresult['requestdate']), "id=$groupid", 1);

                if ($isBanker)
                {
                    for ($x = 0; $x < count($massinsert); $x++)
                    { $massinsert[$x]["groupid"] = $groupid; }

                    // Adds rows to bank transactions
                    $db->insert_query_multiple("banktransactions", $massinsert);

                    $x = 0;
                    while (isset($mybb->input["massid_" . $x]))
                    {
                        // Updates user balances
                        $currId = getSafeInputNum($db, $mybb, "massid_" . $x++);
                        updateBankBalance($db, $currId);
                    }

                    displaySuccessGroup($groupName, $mybb, $groupid, "Group Banker Transactions Made");
                }
                else
                {
                    for ($x = 0; $x < count($massinsert); $x++)
                    { $massinsert[$x]["groupid"] = $groupid; }
                    
                    // Adds rows to bank transaction request table
                    $db->insert_query_multiple("banktransactionrequests", $massinsert);

                    displaySuccessGroup($groupName, $mybb, $groupid, "Group Request Made");
                }
            }
            else
            {
                echo '<div class="errorSection">';
                echo '<h4>Error: There was invalid arguments for at least one of the transactions.</h4>';
                echo '</div>';
            }
        }
    }
    ?>

    <div class="bojoSection navigation">
        <?php if ($isBanker)
        {
            echo '<h2>Group Transactions</h2>
            <p>Submit a group transaction. <strong>As a banker no approvals are necessary</strong></p>';
        }
        else
        {
            echo "<h2>Group Transactions Request</h2>
            <p>Submit a group transaction. <strong style='color: red;'>Will require a banker's approval before the transactions can be completed.</strong></p>";
        }
        ?>
        <p>First enter a list of user names for the transaction. Then enter amounts for each person. Positive if deposits otherwise negative. Enter Titles and descriptions. Click "Fill the rest" to have the rest of the users copy the first user's information. The group name should summarize the group's transactions, but can usually just be same as the titles for each person.</p>
    </div>

    <div class="bojoSection navigation">
    <small>submit a list of usernames separated by either commas or new lines.</small>
    <form method="post">
    <textarea name="namelist" rows="8"><?php echo $namelist ?></textarea><br />
    <input type="submit" name="submitnames" value="Get Users" />
    <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code; ?>" />
    </form>
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
            echo '<form onsubmit="return validateForms()" method="post">';
            echo '<table class="namesTable">';
            echo '<tr><th>username</th><th>amount</th><th>title</th><th>description</th></tr>';

            $massIndex = 0;
            while ($namerow = $db->fetch_array($nameRows))
            {
                echo "<tr><td>" . $namerow['username'] . "</td>";
                // echo "<td>" . $namerow['uid'] . "</td>";
                echo '<td><input type="number" id="massamount_' . $massIndex . '" name="massamount_' . $massIndex . '" value="0" /></td>';
                echo '<td><input type="text" id="masstitle_' . $massIndex . '" name="masstitle_' . $massIndex . '" /></td>';
                echo '<td><input type="text" id="massdescription_' . $massIndex . '" name="massdescription_' . $massIndex . '" /></td>';
                echo '<input type="hidden" name="massid_' . $massIndex . '" value="' . $namerow['uid'] . '" />';
                echo '<input type="hidden" name="massname_' . $massIndex . '" value="' . $namerow['username'] . '" />';
                if($massIndex === 0)
                {
                    echo '<td><input type="button" onclick="fillInUsers()" value="Fill the rest" /></td>';
                }
                echo "</tr>";
                $massIndex++;
            }
            echo '<tr><td style="height: 20px"></td></td>';
            echo '<tr><th>transaction group name:</th><td colspan="2"><input type="text" id="massgroupname" name="massgroupname"" /></td></tr>';
            echo '<tr><td style="height: 8px"></td></tr>';
            echo '<tr><td colspan="3"></td><td><input type="submit" name="submitmassseparate" value="Submit Transactions" /></td></tr>';
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
        var firstAmount = 0;
        var firstTitle = "";
        var firstDescription = "";
        while(true) {
            var idAmount = "massamount_" + i;
            var idTitle = "masstitle_" + i;
            var idDescription = "massdescription_" + i;
            if (document.getElementById(idAmount) !== null) {
                if (i == 0) {
                    firstAmount = document.getElementById(idAmount).value;
                    firstTitle = document.getElementById(idTitle).value;
                    firstDescription = document.getElementById(idDescription).value;
                }
                else {
                    document.getElementById(idAmount).value = firstAmount;
                    document.getElementById(idTitle).value = firstTitle;
                    document.getElementById(idDescription).value = firstDescription;
                }
            }
            else {
                break;
            }
            i++;
        }
        document.getElementById("massgroupname").value = firstTitle;
    }

    function validateForms() {
        var i = 0;
        while(true) {
            var idAmount = "massamount_" + i;
            var idTitle = "masstitle_" + i;
            if (document.getElementById(idAmount) !== null) {
                i++;
                var amount = document.getElementById(idAmount).value;
                var title = document.getElementById(idTitle).value;
                if (amount == 0)
                {
                    alert("A field has an invalid amount");
                    return false;
                }
                if (title.length <= 0)
                {
                    alert("A title is invalid");
                    return false;
                }
            }
            else { break; }

            if (i == 0)
            {
                alert("Where are the people?");
                return false;
            }
        }

        var massgroupname = document.getElementById("massgroupname").value;
        if (massgroupname.length <= 0)
        {
            alert("group name is invalid");
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