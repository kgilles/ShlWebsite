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

    // Gets id of team from URL
    if (isset($_GET["teamid"]) && is_numeric($_GET["teamid"])) {
        $currentTeamId = getSafeNumber($db, $_GET["teamid"]);
        $xQuery = $db->simple_select("teams", "*", "id=$currentTeamId", array("limit" => 1));
        $currteam = $db->fetch_array($xQuery);
    }

    if ($currteam == null && $currentTeamId !== null)
        header('Location: http://simulationhockey.com/banksummary.php');

    if ($currentTeamId !== null) {
        $xQuery = $db->simple_select("users", "*", "teamid=$currentTeamId", array());

        while ($teamuser = $db->fetch_array($xQuery)) {
            $namesArray[] = $teamuser['username'];
        }

        for ($x = 0; $x < count($namesArray); $x++)
            $namesArray[$x] = "'" . getSafeString($db, $namesArray[$x]) . "'";

        $names = implode(",", $namesArray);
        $xQueryNames = $db->simple_select("users", "*", "username in (" . $names . ")", array("order_by" => 'username', "order_dir" => 'ASC'));
        $nameCount = mysqli_num_rows($xQueryNames);
        $nameEnteredCount = count($namesArray);
    }

    // If a submit button was pressed
    if (isset($mybb->input["bojopostkey"])) {
        verify_post_check($mybb->input["bojopostkey"]);

        // keeps names in text box
        $namelist = trim($mybb->input["namelist"]);

        // Submitted list of names to search for
        // Split by commas if present. otherwise split by new lines
        if (isset($mybb->input["submitnames"])) {
            logAction($db, "ACTION", "$myuid attempts to submit a list of names for a transaction request");

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
            logAction($db, "ACTION", "$myuid attempts to submit a series of transactions for certain users");

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
                $currDescription = getSafeString($db, $mybb->input["massdescription_$x"]);
                if (strlen($currDescription) == 0) $currDescription = null;

                if ($currAmount == 0) {
                    echo 'no amount';
                    $isValid = false;
                    break;
                }

                // Adds values for table insertion
                if ($isBanker) {
                    $massinsert[] = [
                        "uid" => $currId,
                        "amount" => $currAmount,
                        "title" => $groupName,
                        "description" => $currDescription,
                        "createdbyuserid" => $myuid,
                        "bankerapproverid" => $myuid,
                    ];
                } else {
                    $massinsert[] = [
                        "uid" => $currId,
                        "amount" => $currAmount,
                        "title" => $groupName,
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
                            $currId = getSafeNumber($db, $mybb->input["massid_" . $x++]);
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
        <h2>Submitting a Request</h2>
        <if $isBanker then>
            <p><strong>As a banker no approvals are necessary</strong></p>
            <ul>
                <li><a href="http://simulationhockey.com/bank.php">Main Page</a></li>
                <li><a href="http://simulationhockey.com/bankaccount.php">Your Account</a></li>
            </ul>
        <else>
            <h2>Submit Transaction Request</h2>
            <p><strong style='color: red;'>Will require a banker's approval before the transactions can be completed.</strong></p>
        </if>
        <hr />
        <h3>Example Request Scenarios</h3>
        <ul>
            <li><strong>Grouped Deposits</strong> (Contracts, Media Pay, Job Pay)</li>
            <li><strong>Money Transfers</strong> (Bets, Sig Payments)</li>
            <li><strong>Overdraft Requests</strong> (Current balance will increase with unprocessed deposits)</li>
        </ul>
        <hr />
        <h3>Instructions</h3>
        <ol>
            <li>Enter list of user names separated by commas or new lines.</li>
            <li>Click "Get Users". Checks if the users exist.</li>
            <li>Enter a short title for the transaction.</li>
            <li>Enter amounts for deposits or withdrawls. Remember to use the negative sign if withdrawing.</li>
            <li>Add a description for each user. Required for withdrawls.</li>
            <li>Click "Submit Transactions"</li>
        </ol>
        <p>The "Copy from First" button will populate all the users with the same info provided for the first user.</p>
    </div>

    <div class="bojoSection navigation">
        <h2>Submit Transactions</h2>
        <p>Read First: <a href="showthread.php?tid=105079">Guide to Transfers Between Users</a></p>
        <if $currentTeamId==null then>
            <p><i>Enter list of usernames separated by commas or new lines.</i></p>
            <form method="post">
                <input type="button" onclick="document.getElementById('namelist').value='<?php echo $mybb->user['username']; ?>'" value="Add Just Myself" /><br />
                <textarea id="namelist" name="namelist" rows="8"><?php echo $namelist ?></textarea><br />
                <input type="submit" name="submitnames" value="Get Users" />
                <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code; ?>" />
            </form>
        </if>
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
                    <table class="namesTable" style="width: 100%; max-width: 500px;">';

                echo '<tr><th>transaction name:</th><td colspan="2"><input type="text" id="massgroupname" name="massgroupname"" /></td></tr>';
                echo '<tr><td style="height: 10px;"></td></td>';
                if ($nameCount > 1) {
                    echo '<tr><td colspan="2"></td><td><input type="button" onclick="fillInUsers()" value="Copy from first" /></td></tr>';
                }
                echo '<tr><th>username</th><th>amount</th><th>description</th></tr>';
                $massIndex = 0;
                while ($xRow = $db->fetch_array($xQueryNames)) {
                    echo '<tr>
                        <td>' . $xRow['username'] . '</td>
                        <input type="hidden" id="massamount_' . $massIndex . '" name="massamount_' . $massIndex . '" value="0" />
                        <td><input type="text" class="dollaramount" value="0" data-id="massamount_' . $massIndex . '" /></td>';
                    if ($nameCount > 1) {
                        echo '<td><input type="text" id="massdescription_' . $massIndex . '" name="massdescription_' . $massIndex . '" /></td>';
                    }
                    else {
                        echo '<td><textarea id="massdescription_' . $massIndex . '" name="massdescription_' . $massIndex . '"></textarea></td>';
                    }
                    echo '<input type="hidden" name="massid_' . $massIndex . '" value="' . $xRow['uid'] . '" />
                        <input type="hidden" name="massname_' . $massIndex . '" value="' . $xRow['username'] . '" />
                        </tr>';
                    $massIndex++;
                }

                echo '<tr><td style="height: 8px"></td></tr>
                  <tr><td colspan="2"></td><td><input type="submit" name="submitmassseparate" value="Submit Transactions" /></td></tr>
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
        function disallowNonNumbers(event) {
            var eventCode = event.code;
            if (eventCode) {
                if (!eventCode.match(/Digit/)) {
                    event.preventDefault();
                }
            } else if (!event.char.match(/\d/)) { // IE 11
                event.preventDefault();
            }
        }

        function addCommasToAmount(event) {
            var inputField = event.target;
            var mappedHiddenInputId = inputField.dataset.id;
            var currentAmount = inputField.value.replace(/,/g, "");
            var amountWithCommas = currentAmount.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            inputField.value = amountWithCommas;
            document.getElementById(mappedHiddenInputId).value = currentAmount;
        }

        var amountInputs = document.getElementsByClassName("dollaramount");
        for (var i = 0; i < amountInputs.length; i++) {
            amountInputs[i].addEventListener('keydown', disallowNonNumbers);
            amountInputs[i].addEventListener('input', addCommasToAmount);
        }

        $(document).on("keydown", ":input:not(textarea)", function(event) {
            return event.key != "Enter";
        });

        function fillInUsers() {
            var i = 0;
            var firstAmount = 0;
            var firstDescription = "";
            while (true) {
                var idAmount = "massamount_" + i;
                var idDescription = "massdescription_" + i;
                if (document.getElementById(idAmount) !== null) {
                    if (i == 0) {
                        firstAmount = document.getElementById(idAmount).value;
                        firstDescription = document.getElementById(idDescription).value;
                    } else {
                        document.getElementById(idAmount).value = firstAmount;
                        document.getElementById(idDescription).value = firstDescription;
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
                var idAmount = "massamount_" + i;
                if (document.getElementById(idAmount) !== null) {
                    i++;
                    var amount = document.getElementById(idAmount).value;
                    if (amount == 0) {
                        alert("A field has an invalid amount");
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