<html>

<head>
    <title>SHL Hockey -> Player Updater</title>
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

        .errorSection {
            background: #f0cfcf;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid black;
        }

        .successSection th,
        .successSection td {
            padding: 0px 10px;
            text-align: right;
        }

        .successSection th:nth-child(1),
        .successSection td:nth-child(1) {
            text-align: left;
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
        $myuid = $mybb->user['uid'];
        $uid = 0;

        if ($myuid <= 0) {
            exit;
        }

        $isBanker = false;
        // Gets profile of user. If no id provided, gets logged in user by default.
        if (isset($_GET["uid"]) && is_numeric($_GET["uid"])) {
            $uid = intval($_GET["uid"]);
        }
        else {
            header('Location: http://simulationhockey.com/playerupdater.php?uid='.$myuid);
            // $uid = $myuid;
        }

        if ($uid > 0) 
        {
            // Gets user from DB.
            $userquery = $db->simple_select("users", "*", "uid=$uid", array(
                "limit" => 1
            ));

            // Checks to make sure person visiting page is a banker.
            // Banker id is 13 as of me writing this.
            $groupstring = $mybb->user['usergroup'] . ',' . $mybb->user['additionalgroups'];
            $groups = explode(",", $groupstring);
            if (in_array("13", $groups)) {
                $isBanker = true;
            }
            
            // TODO: remove for testing
            // $isBanker = true;

            $curruser = $db->fetch_array($userquery);
            $bankbalance = $curruser["bankbalance"];
            $currname = $curruser["username"];

            // If a submit button was pressed
            if (isset($mybb->input["bojopostkey"])) 
            {
                verify_post_check($mybb->input["bojopostkey"]);

                // If banker undid a transaction.
                if ($isBanker && isset($mybb->input["undotransaction"], $mybb->input["undoid"]) && is_numeric($mybb->input["undoid"]))
                {
                    $transid = intval($mybb->input["undoid"]);
                    $undoquery = $db->simple_select("banktransactions", "*", "id=$transid", array("limit" => 1));
                    $undoresult = $db->fetch_array($undoquery);
                    $undoamount = intval($undoresult["amount"]);

                    // Removes transaction row
                    $db->delete_query("banktransactions", "id=$transid");

                    $balancequery = "SELECT sum(amount) AS sumamt FROM mybb_banktransactions WHERE uid=$uid";
                    $banksumquery = $db->query($balancequery);
                    $banksumresult = $db->fetch_array($banksumquery);
                    if ($banksumresult != NULL) { $bankbalance = intval($banksumresult['sumamt']); }
                    else { $bankbalance = 0; }
                    $db->update_query("users", array("bankbalance" => $bankbalance), "uid=$uid", 1);

                    echo '<div class="successSection">';
                    echo "<h4>Success: Undo transaction</h4>";
                    echo "<table>";
                    if($undoamount < 0) {
                        echo '<tr><th>Amount</th><td>-$'.abs($undoamount).'</td></tr>';
                    }
                    else {
                        echo '<tr><th>Amount</th><td>$'.$undoamount.'</td></tr>';
                    }
                    echo '<tr><th>Title</th><td>'.$undoresult['title'].'</td></tr>';
                    echo "</table>";
                    echo '</div>';
                }

                // If banker submitted a transaction.
                else if ($isBanker && isset($mybb->input["submittransaction"], $mybb->input["transactionamount"]))
                {
                    $transAmount = intval($mybb->input["transactionamount"]);
                    $transTitle = trim(preg_replace('/[^A-Za-z0-9\-]/', '', $mybb->input["transactiontitle"]));
                    if ($transAmount != 0 && strlen($transTitle) && is_numeric($mybb->input["transactionamount"]))
                    {
                        $db->insert_query("banktransactions", array(
                            "uid" => $uid,
                            "amount" => $transAmount,
                            "title" => $transTitle,
                            "bankerid" => $myuid));
                        
                        $balancequery = "SELECT sum(amount) AS sumamt FROM mybb_banktransactions WHERE uid=$uid";
                        $banksumquery = $db->query($balancequery);
                        $banksumresult = $db->fetch_array($banksumquery);
                        if ($banksumresult != NULL) { $bankbalance = intval($banksumresult['sumamt']); }
                        else { $bankbalance = 0; }
                        $db->update_query("users", array("bankbalance" => $bankbalance), "uid=$uid", 1);

                        echo '<div class="successSection">';
                        echo "<h4>Success: Banker Transaction</h4>";
                        echo "<table>";
                        echo '<tr><th>User</th><td>'.$currname.'</td></tr>';
                        if($transAmount < 0) {
                            echo '<tr><th>Amount</th><td>-$'.abs($transAmount).'</td></tr>';
                        }
                        else {
                            echo '<tr><th>Amount</th><td>$'.$transAmount.'</td></tr>';
                        }
                        echo '<tr><th>Title</th><td>'.$transTitle.'</td></tr>';
                        echo "</table>";
                        echo '</div>';
                    }
                    else {
                        echo '<div class="errorSection">';
                        echo "<h4>Error</h4>";
                        echo '<p>Invalid arguments for the transaction</p>';
                        echo '</div>';
                    }
                }

                // If a banker submitted a balance.
                else if ($isBanker && isset($mybb->input["submitbalance"], $mybb->input["balanceamount"]))
                {
                    $transAmount = intval($mybb->input["balanceamount"]) - $bankbalance;
                    $transTitle = "BALANCE AUDIT";

                    if($bankbalance != $transAmount && is_numeric($mybb->input["balanceamount"]))
                    {
                        $db->insert_query("banktransactions", array(
                            "uid" => $uid,
                            "amount" => $transAmount,
                            "title" => $transTitle,
                            "bankerid" => $myuid));                        

                        $balancequery = "SELECT sum(amount) AS sumamt FROM mybb_banktransactions WHERE uid=$uid";
                        $banksumquery = $db->query($balancequery);
                        $banksumresult = $db->fetch_array($banksumquery);
                        if ($banksumresult != NULL) { $bankbalance = intval($banksumresult['sumamt']); }
                        else { $bankbalance = 0; }
                        $db->update_query("users", array("bankbalance" => $bankbalance), "uid=$uid", 1);

                        echo '<div class="successSection">';
                        echo "<h4>Success: Balance Audit</h4>";
                        echo "<table>";
                        echo '<tr><th>User</th><td>'.$currname.'</td></tr>';
                        if($transAmount < 0) {
                            echo '<tr><th>Amount</th><td>-$'.abs($transAmount).'</td></tr>';
                        }
                        else {
                            echo '<tr><th>Amount</th><td>$'.$transAmount.'</td></tr>';
                        }
                        echo '<tr><th>Title</th><td>'.$transTitle.'</td></tr>';
                        echo "</table>";
                        echo '</div>';
                    }
                    else {
                        echo '<div class="errorSection">';
                        echo "<h4>Error</h4>";
                        echo '<p>Invalid arguments for the transaction</p>';
                        echo '</div>';
                    }
                }

                // If the user submitted a transaction himself.
                else if (isset($mybb->input["submitpurchase"], $mybb->input["purchaseamount"]))
                {
                    $transAmount = -abs(intval($mybb->input["purchaseamount"]));
                    $transTitle = trim(preg_replace('/[^A-Za-z0-9\-]/', '', $mybb->input["purchasetitle"]));
                    if ($transAmount != 0 && strlen($transTitle) && is_numeric($mybb->input["purchaseamount"]))
                    {
                        $db->insert_query("banktransactions", array(
                            "uid" => $uid,
                            "amount" => $transAmount,
                            "title" => $transTitle,
                            "bankerid" => $myuid));
                        
                        $balancequery = "SELECT sum(amount) AS sumamt FROM mybb_banktransactions WHERE uid=$uid";
                        $banksumquery = $db->query($balancequery);
                        $banksumresult = $db->fetch_array($banksumquery);
                        if ($banksumresult != NULL) { $bankbalance = intval($banksumresult['sumamt']); }
                        else { $bankbalance = 0; }
                        $db->update_query("users", array("bankbalance" => $bankbalance), "uid=$uid", 1);
                        
                        echo '<div class="successSection">';
                        echo "<h4>Success: Purchase Results</h4>";
                        echo "<table>";
                        echo '<tr><th>User</th><td>'.$currname.'</td></tr>';
                        if($transAmount < 0) {
                            echo '<tr><th>Amount</th><td>-$'.abs($transAmount).'</td></tr>';
                        }
                        else {
                            echo '<tr><th>Amount</th><td>$'.$transAmount.'</td></tr>';
                        }
                        echo '<tr><th>Title</th><td>'.$transTitle.'</td></tr>';
                        echo "</table>";
                        echo '</div>';
                    }
                    else {
                        echo '<div class="errorSection">';
                        echo "<h4>Error</h4>";
                        echo '<p>Invalid arguments for the transaction</p>';
                        echo '</div>';
                    }
                }
            }

            // User Information
            echo '<div class="bojoSection">';
            echo '<h2>' . $currname . '</h2>';
            echo '<table>';
            echo "<tr><th>Balance</th><td>";
            if ($bankbalance < 0) { echo '-'; }
            echo "$" . number_format(abs($bankbalance), 0) . "</td></tr>";
            echo "</table>";

            echo '<hr />';

            // Bank Transactions
            $transactionQuery = 
            "SELECT bt.*, banker.username AS 'owner'
                FROM mybb_banktransactions bt
                JOIN mybb_users banker ON bt.bankerid=banker.uid
                WHERE bt.uid=$uid
                ORDER BY bt.date DESC
                LIMIT 50";
            $bankRows = $db->query($transactionQuery);
            echo '<h4>Bank Transactions</h4>';
            echo '<table>';
            echo '<tr>';
            echo '<th>Title</th>';
            echo '<th>Amount</th>';
            echo '<th>Date</th>';
            echo '<th>Made By</th>';
            echo '</tr>';
            while ($row = $db->fetch_array($bankRows))
            {
                $date = new DateTime($row['date']);

                echo '<tr>';
                echo '<td><a href="http://simulationhockey.com/banktransaction.php?id='.$row['id'].'">' . $row['title'] . '</a></td>';
                if ($row['amount'] < 0) {
                    echo "<td class='negative'>" . '<a href="http://simulationhockey.com/banktransaction.php?id='.$row['id'].'">-$' . number_format(abs($row['amount']), 0) . "</a></td>";
                }
                else {
                    echo "<td class='positive'>" . '<a href="http://simulationhockey.com/banktransaction.php?id='.$row['id'].'">$' . number_format($row['amount'], 0) . "</a></td>";
                }
                echo '</a>';
                echo "<td>" . $date->format('m/d/y') . "</td>";
                echo '<td><a href="http://simulationhockey.com/playerupdater.php?uid="'.$row['bankerid'].'">' . $row['owner'] . "</a></td>";
                if($isBanker)
                {
                    echo '<form method="post"><td><input type="submit" name="undotransaction" value="Undo" /></td>';
                    echo '<form method="post"><input type="hidden" name="undoid" value="'. $row['id'] .'" />';
                    echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" /></form>';
                }
                echo "</tr></form>";
            }
            echo "</table>";
            echo "</div>";

            // New Purchase: Only available to the actual user
            if ($uid == $myuid)
            {
                echo '<div class="bojoSection">';
                echo '<h2>New Purchase</h2>';
                echo '<form method="post">';
                echo '<table>';
                echo '<tr><th>Amount</th><td><input type="number" name="purchaseamount" placeholder="Enter amount..." /></td></tr>';
                echo '<tr><th>Title</th><td><input type="text" name="purchasetitle" placeholder="Enter title..." /></td></tr>';
                echo '<tr><th></th><td><input type="submit" name="submitpurchase" value="Make Purchase" /></td></tr>';
                echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />';
                echo '</table>';
                echo '</form>';
                echo '<p style="margin-bottom: 0px"><em>Write a postive number for a purchase transaction. Contact a banker if there\'s a mistake.</em></p>';
                echo '</div>';
            }

            // Add Banker Transaction
            if($isBanker)
            {
                echo '<div class="bojoSection">';
                echo '<h2>Banker Controls</h2>';
                echo '<h4>Add Transaction</h4>';
                echo '<form method="post">';
                echo '<table>';
                echo '<tr><th>Amount</th><td><input type="number" name="transactionamount" placeholder="Enter amount..." /></td></tr>';
                echo '<tr><th>Title</th><td><input type="text" name="transactiontitle" placeholder="Enter title..." /></td></tr>';
                echo '<tr><th></th><td><input type="submit" name="submittransaction" value="Add Transaction" /></td></tr>';
                echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />';
                echo '</table>';
                echo '<p style="margin-bottom: 0px"><em>Adds a transaction. If removing money, make sure to add the negative sign.</em></p>';
                echo '</form>';
        
                echo '<hr />';
        
                // Audit Balance
                echo '<h4>Fix Balance</h4>';
                echo '<form method="post">';
                echo '<table>';
                echo '<tr><th>Amount</th><td><input type="number" name="balanceamount" placeholder="Enter balance..." /></td></tr>';
                echo '<tr><th></th><td><input type="submit" name="submitbalance" value="Set Balance" /></td></tr>';
                echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />';
                echo '</table>';
                echo '<p style="margin-bottom: 0px"><em>Sets the balance to the value, and adds a transaction to get it there.</em></p>';
                echo '</form>';
                echo "</div>";
            }
        }
        ?>

        <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html>