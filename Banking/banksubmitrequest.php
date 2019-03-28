<html>

<head>
    <title>SHL Hockey -> Submit Bank Transactions</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php include 'bankerOps.php';

    function displaySuccessGroup($groupName, $mybb, $groupid, $message)
    {
        echo '<div class="successSection">
                <h4>Success: ' . $message . '</h4>
                <table>';

        $requestlink = '<a href="' . getBankRequestLink($groupid) . '">';
        echo '<tr><th>Group Name</th><td colspan="3">' . $requestlink . $groupName . '</a></td></tr>
              <tr><th>User</th><th>Amount</th></tr>';

        $x = 0;
        while (isset($mybb->input["massid_" . $x])) {
            $currName = $mybb->input["massname_" . $x];
            $currId = $mybb->input["massid_" . $x];
            $currAmount = $mybb->input["massamount_" . $x];
            $accountlink = '<a href="' . getBankAccountLink($currId) . '">';

            echo '<td>' . $accountlink . $currName . '</a></td>';
            echo '<td>$' . $currAmount . '</td></tr>';
            $x++;
        }
        echo '</table>
              </div>';
    }

    $myuid = getUserId($mybb);

    // if not logged in, go away
    if ($myuid <= 0) {
        echo 'You are not logged in';
        exit;
    }

    $isBanker = checkIfBanker($mybb);

    // If a submit button was pressed
    if (isset($mybb->input["bojopostkey"])) {
        verify_post_check($mybb->input["bojopostkey"]);

        // keeps names in text box
        $namelist = trim($mybb->input["namelist"]);

        // Submitted list of names to search for
        // Split by commas if present. otherwise split by new lines
        if (isset($mybb->input["submitnames"])) {
            $charToSplit = (strpos($namelist, ',') !== false) ? "," : "\n";
            $namesArray = array_map('trim', explode($charToSplit, $namelist));

            for ($x = 0; $x < count($namesArray); $x++)
                $namesArray[$x] = "'" . getSafeString($db, $namesArray[$x]) . "'";

            $names = implode(",", $namesArray);
            $xQueryNames = $db->simple_select("users", "*", "username in (" . $names . ")", array("order_by" => 'username', "order_dir" => 'ASC'));
            $nameCount = mysqli_num_rows($xQueryNames);
            $nameEnteredCount = count($namesArray);
        }

        // Submitted Mass Transactions
        else if (isset($mybb->input["submitmassseparate"])) {
            // Validate data
            // Create the group
            // If Banker, create the transactions directly
            // If Not Banker, create the requests

            $isValid = true;

            $groupName = getSafeAlpNum($db, $mybb->input["massgroupname"]);
            if (strlen($groupName) <= 0) {
                echo 'no group name';
                $isValid = false;
            }

            $x = 0;
            while (isset($mybb->input["massid_" . $x])) {
                $currId = getSafeNumber($db, $mybb->input["massid_$x"]);
                $currAmount = getSafeNumber($db, $mybb->input["massamount_$x"]);
                $currTitle = getSafeAlpNum($db, $mybb->input["masstitle_$x"]);
                $currDescription = getSafeAlpNum($db, $mybb->input["massdescription_$x"]);
                if (strlen($currDescription) == 0) $currDescription = null;

                if (strlen($currTitle) <= 0 || $currAmount == 0) {
                    echo 'no ammount or title';
                    $isValid = false;
                    break;
                }

                // Adds values for table insertion
                if ($isBanker) {
                    $massinsert[] = [
                        "uid" => $currId,
                        "amount" => $currAmount,
                        "title" => $currTitle,
                        "description" => $currDescription,
                        "createdbyuserid" => $myuid,
                        "bankerapproverid" => $myuid,
                    ];
                } else {
                    $massinsert[] = [
                        "uid" => $currId,
                        "amount" => $currAmount,
                        "title" => $currTitle,
                        "description" => $currDescription,
                    ];
                }

                $x++;
            }
            // End Validation Check

            if ($isValid) {
                if ($isBanker) {
                    $groupArray = [
                        "creatorid" => $myuid,
                        "groupname" => $groupName,
                        "bankerid" => $myuid,
                        "isapproved" => 1
                    ];
                } else {
                    $groupArray = [
                        "creatorid" => $myuid,
                        "groupname" => $groupName
                    ];
                }

                // Adds request group
                $db->insert_query("banktransactiongroups", $groupArray);

                // Get the group you just inserted
                $xQuery = $db->simple_select("banktransactiongroups", "*", "groupname='$groupName'", array("order_by" => 'requestdate', "order_dir" => 'DESC', "limit" => 1));
                if ($xRow = $db->fetch_array($xQuery)) {
                    $groupid = intval($xRow['id']);
                    $groupName = $xRow['groupname'];

                    if ($isBanker) {
                        // Sets date to the date set from the database
                        $db->update_query("banktransactiongroups", array("decisiondate" => $xRow['requestdate']), "id=$groupid", 1);

                        for ($x = 0; $x < count($massinsert); $x++)
                            $massinsert[$x]["groupid"] = $groupid;

                        // Adds rows to bank transactions
                        $db->insert_query_multiple("banktransactions", $massinsert);

                        $x = 0;
                        while (isset($mybb->input["massid_" . $x])) {
                            // Updates user balances
                            $currId = getSafeInputNum($db, $mybb, "massid_" . $x++);
                            updateBankBalance($db, $currId);
                        }

                        displaySuccessGroup($groupName, $mybb, $groupid, "Group Banker Transactions Made");
                    } else {
                        for ($x = 0; $x < count($massinsert); $x++)
                            $massinsert[$x]["groupid"] = $groupid;

                        // Adds rows to bank transaction request table
                        $db->insert_query_multiple("banktransactionrequests", $massinsert);

                        displaySuccessGroup($groupName, $mybb, $groupid, "Group Request Made");
                    }
                } else {
                    echo '<div class="errorSection">';
                    echo '<h4>Error: There was a server error. Please report this.</h4>';
                    echo '</div>';
                }
            } else {
                echo '<div class="errorSection">';
                echo '<h4>Error: There was invalid arguments for at least one of the transactions.</h4>';
                echo '</div>';
            }
        }
    }
    ?>

    <div class="bojoSection navigation">
        <if $isBanker then>
            <h2>Group Transactions</h2>
            <p>Submit a group transaction. <strong>As a banker no approvals are necessary</strong></p>
            <else>
                <h2>Group Transactions Request</h2>
                <p>Submit a group transaction. <strong style='color: red;'>Will require a banker's approval before the transactions can be completed.</strong></p>
        </if>
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
        if ($nameEnteredCount > 0) {
            echo '<hr />';
            if ($nameCount) {
                if ($nameCount != $nameEnteredCount)
                    echo '<div class="nameCompare warning">';
                else
                    echo '<div class="nameCompare success">';

                echo count($namesArray) . ' names entered<br/>' . $nameCount . ' names found';
                echo '</div>
                    <form onsubmit="return validateForms()" method="post">
                    <table class="namesTable">
                        <tr><th>username</th><th>amount</th><th>title</th><th>description</th></tr>';

                $massIndex = 0;
                while ($xRow = $db->fetch_array($xQueryNames)) {
                    echo '<tr>
                        <td>' . $xRow['username'] . '</td>
                        <td><input type="number" id="massamount_' . $massIndex . '" name="massamount_' . $massIndex . '" value="0" /></td>
                        <td><input type="text" id="masstitle_' . $massIndex . '" name="masstitle_' . $massIndex . '" /></td>
                        <td><input type="text" id="massdescription_' . $massIndex . '" name="massdescription_' . $massIndex . '" /></td>
                        <input type="hidden" name="massid_' . $massIndex . '" value="' . $xRow['uid'] . '" />
                        <input type="hidden" name="massname_' . $massIndex . '" value="' . $xRow['username'] . '" />';

                    if ($massIndex === 0)
                        echo '<td><input type="button" onclick="fillInUsers()" value="Fill the rest" /></td>';

                    echo "</tr>";
                    $massIndex++;
                }

                echo '<tr><td style="height: 20px"></td></td></tr>
                  <tr><th>transaction group name:</th><td colspan="2"><input type="text" id="massgroupname" name="massgroupname"" /></td></tr>
                  <tr><td style="height: 8px"></td></tr>
                  <tr><td colspan="3"></td><td><input type="submit" name="submitmassseparate" value="Submit Transactions" /></td></tr>
                  </table>
                  <input type="hidden" name="namelist" value="' . $namelist . '" />
                  <input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />
                  </form>';
            } else {
                echo '<div class="nameCompare warning">No names found. Please try again.</div>';
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
            while (true) {
                var idAmount = "massamount_" + i;
                var idTitle = "masstitle_" + i;
                var idDescription = "massdescription_" + i;
                if (document.getElementById(idAmount) !== null) {
                    if (i == 0) {
                        firstAmount = document.getElementById(idAmount).value;
                        firstTitle = document.getElementById(idTitle).value;
                        firstDescription = document.getElementById(idDescription).value;
                    } else {
                        document.getElementById(idAmount).value = firstAmount;
                        document.getElementById(idTitle).value = firstTitle;
                        document.getElementById(idDescription).value = firstDescription;
                    }
                } else {
                    break;
                }
                i++;
            }
            document.getElementById("massgroupname").value = firstTitle;
        }

        function validateForms() {
            var i = 0;
            while (true) {
                var idAmount = "massamount_" + i;
                var idTitle = "masstitle_" + i;
                if (document.getElementById(idAmount) !== null) {
                    i++;
                    var amount = document.getElementById(idAmount).value;
                    var title = document.getElementById(idTitle).value;
                    if (amount == 0) {
                        alert("A field has an invalid amount");
                        return false;
                    }
                    if (title.length <= 0) {
                        alert("A title is invalid");
                        return false;
                    }
                } else {
                    break;
                }

                if (i == 0) {
                    alert("Where are the people?");
                    return false;
                }
            }

            var massgroupname = document.getElementById("massgroupname").value;
            if (massgroupname.length <= 0) {
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