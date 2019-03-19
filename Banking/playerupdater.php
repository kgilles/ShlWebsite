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

        $isBanker = false;
        // Gets profile of user. If no id provided, gets logged in user by default.
        if (isset($_GET["uid"]) && is_numeric($_GET["uid"])) {
            $uid = intval($_GET["uid"]);
        }
        else {
            $uid = $myuid;
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
            
            $curruser = $db->fetch_array($userquery);
            $bankbalance = $curruser["bankbalance"];
            $currname = $curruser["username"];

            // If a submit button was pressed
            if (isset($mybb->input["bojopostkey"])) 
            {
                verify_post_check($mybb->input["bojopostkey"]);

                // If banker submitted a transaction.
                if ($isBanker && isset($mybb->input["submittransaction"], $mybb->input["transactionamount"]) && is_numeric($mybb->input["transactionamount"]))
                {
                    $transAmount = intval($mybb->input["transactionamount"]);
                    $transTitle = $mybb->input["transactiontitle"];
                    if ($transAmount != 0)
                    {
                        $db->insert_query("banktransactions", array(
                            "uid" => $uid,
                            "amount" => $transAmount,
                            "title" => $transTitle,
                            "bankerid" => $myuid));
                        
                        $bankbalance += $transAmount;
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
                }

                // If a banker submitted a balance.
                else if ($isBanker && isset($mybb->input["submitbalance"], $mybb->input["balanceamount"]) && is_numeric($mybb->input["balanceamount"]))
                {
                    $transAmount = intval($mybb->input["balanceamount"]) - $bankbalance;
                    $transTitle = "BALANCE AUDIT";
                    if ($transAmount != 0)
                    {
                        $db->insert_query("banktransactions", array(
                            "uid" => $uid,
                            "amount" => $transAmount,
                            "title" => $transTitle,
                            "bankerid" => $myuid));
                        
                        $bankbalance += $transAmount;
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
                }

                // If the user submitted a transaction himself.
                else if (isset($mybb->input["submitpurchase"], $mybb->input["purchaseamount"]) && is_numeric($mybb->input["purchaseamount"]))
                {
                    $transAmount = -abs(intval($mybb->input["purchaseamount"]));
                    $transTitle = $mybb->input["purchasetitle"];
                    if ($transAmount != 0)
                    {
                        $db->insert_query("banktransactions", array(
                            "uid" => $uid,
                            "amount" => $transAmount,
                            "title" => $transTitle,
                            "bankerid" => $myuid));
                        
                        $bankbalance += $transAmount;
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
                }
            }

            // User Information
            echo '<div class="bojoSection">';
            echo '<h2>User Summary</h2>';
            echo '<h4>User Information</h4>';
            echo '<table>';
            echo "<tr><th>UserId</th><td>" . $uid . "</td></tr>";
            echo "<tr><th>User</th><td>" . $currname . "</td></tr>";
            echo "<tr><th>Balance</th><td>$" . number_format($bankbalance, 0) . "</td></tr>";
            echo "</table>";

            // New Purchase: Only available to the actual user
            if ($uid == $myuid)
            {
                echo '<hr />';

                echo '<h4>New Purchase</h4>';
                echo '<form method="post">';
                echo '<table>';
                echo '<tr><th>Amount</th><td><input type="number" name="purchaseamount" placeholder="Enter amount..." /></td></tr>';
                echo '<tr><th>Title</th><td><input type="text" name="purchasetitle" placeholder="Enter title..." /></td></tr>';
                echo '<tr><th></th><td><input type="submit" name="submitpurchase" value="Make Purchase" /></td></tr>';
                echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />';
                echo '</table>';
                echo '</form>';
                echo '<p style="margin-bottom: 0px"><em>Write a postive number for a purchase transaction. Contact a banker if there\'s a mistake.</em></p>';
            }

            echo '<hr />';

            // Bank Transactions
            $bankRows = $db->simple_select("banktransactions", "*", "uid='" . $uid . "'", array(
                "order_by" => 'date',
                "order_dir" => 'DESC'
            ));
            echo '<h4>Bank Transactions</h4>';
            echo '<table>';
            echo '<tr>';
            echo '<th>Title</th>';
            echo '<th>Amount</th>';
            echo '<th>Date</th>';
            echo '</tr>';
            while ($row = $db->fetch_array($bankRows))
            {
                $date = new DateTime($row['date']);

                echo "<tr>";
                echo "<td>" . $row['title'] . "</td>";
                if ($row['amount'] < 0) {
                    echo "<td class='negative'>-$" . number_format(abs($row['amount']), 0) . "</td>";
                }
                else {
                    echo "<td class='positive'>$" . number_format($row['amount'], 0) . "</td>";
                }
                echo "<td>" . $date->format('m/d/y') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";

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
                echo '<p style="margin-bottom: 0px"><em>This will add a transaction to set to the new value. Just in case a technical errors happens.</em></p>';
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