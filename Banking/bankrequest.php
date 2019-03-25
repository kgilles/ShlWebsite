<html>

<head>
    <title>SHL Hockey -> Bank Group Transaction</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php 
        include 'bankerOps.php';

        $id = 0;
        if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
            $id = intval($_GET["id"]);
        }

        $myuid = getUserId($mybb);

        $isBanker = checkIfBanker($mybb);
        // $isBanker = true; // TODO: testing

        if ($id > 0) 
        {
            // If a submit button was pressed
            if (isset($mybb->input["bojopostkey"])) 
            {
                verify_post_check($mybb->input["bojopostkey"]);

                if ($isBanker && isset($mybb->input["submitApprove"]))
                {
                    $setapprovequery = "UPDATE mybb_banktransactiongroups SET bankerid=$myuid, isapproved=1, decisiondate=now() WHERE mybb_banktransactiongroups.id=$id";
                    $db->write_query($setapprovequery);

                    $grouprows = $db->simple_select("banktransactiongroups", "*", "id='$id'", array("limit" => 1));
                    $groupresult = $db->fetch_array($grouprows);
                    
                    $bankerid = intval($groupresult['bankerid']);
                    $requesterid = intval($groupresult['creatorid']);
                    $groupname = $groupresult['groupname'];
                    $decisiondate = $groupresult['decisiondate'];

                    $requestrows = $db->simple_select("banktransactionrequests", "*", "groupid='$id'", array());
                    while ($requestresult = $db->fetch_array($requestrows))
                    {
                        $requestdata[] = [
                            "uid" => $requestresult['uid'],
                            "amount" => $requestresult['amount'],
                            "title" => $requestresult['title'],
                            "description" => $requestresult['description'],
                            "createdbyuserid" => $requesterid,
                            "bankerapproverid" => $bankerid,
                            "date" => $decisiondate,
                            "groupid" => $id
                        ];
                    }

                    if (count($requestdata) > 0)
                    {
                        $db->insert_query_multiple("banktransactions", $requestdata);
                    }

                    for ($x = 0; $x < count($requestdata); $x++)
                    {
                        updateBankBalance($db, intval($requestdata[$x]["uid"]));
                    }

                    echo '<div class="successSection">';
                    echo '<h4>Successfully Accepted: Transactions added</h4>';
                    echo '</div>';
                }

                else if ($isBanker && isset($mybb->input["submitDecline"]))
                {
                    $setapprovequery = "UPDATE mybb_banktransactiongroups SET bankerid=$myuid, isapproved=0, decisiondate=now() WHERE mybb_banktransactiongroups.id=$id";
                    $db->query($setapprovequery);

                    echo '<div class="successSection">';
                    echo '<h4>Successfully Declined: No Transactions added</h4>';
                    echo '</div>';
                }
            }
        
            $groupQuery = 
            "SELECT bt.*, usr.username AS 'username', banker.username AS 'bankerusername'
                FROM mybb_banktransactiongroups bt
                LEFT JOIN mybb_users usr ON bt.creatorid=usr.uid
                LEFT JOIN mybb_users banker ON bt.bankerid=banker.uid
                WHERE id=$id
                LIMIT 1";

            $grouprows = $db->query($groupQuery);
            $groupresult = $db->fetch_array($grouprows);

            $groupid = intval($groupresult['id']);
            $groupName = $groupresult['groupname'];
            $groupUser = $groupresult['username'];
            $groupBanker = $groupresult['bankerusername'];
            $isapproved = $groupresult['isapproved'];

            if ($isapproved == 1)
            {
                $transactionQuery = 
                "SELECT bt.*, usr.username AS 'username'
                    FROM mybb_banktransactions bt
                    INNER JOIN mybb_users usr ON bt.uid=usr.uid
                    WHERE groupid=$groupid";
            }
            else
            {
                $transactionQuery = 
                "SELECT bt.*, usr.username AS 'username'
                    FROM mybb_banktransactionrequests bt
                    INNER JOIN mybb_users usr ON bt.uid=usr.uid
                    WHERE groupid=$groupid";
            }

            $query = $db->query($transactionQuery);

            if($isapproved == NULL)
                $approveText = "No Decision";
            else if($isapproved == 1)
                $approveText = "Approved";
            else if ($isapproved == 0)
                $approveText = "Declined";

            $date = new DateTime($groupresult['requestdate']);

            echo '<div class="bojoSection navigation">';
            echo '<h4>Bank Group Transaction</h4>';
            echo '<table>';
            echo '<tr><th>Group Name</th><td>' . $groupName . '</td></tr>';
            echo '<tr><th>Submitted By</th><td>' . $groupUser . '</td></tr>';
            echo '<tr><th>Submitted Date</th><td>' . $date->format('m/d/y H:i:s') . '</td></tr>';
            echo '<tr><th>Status</th><td>' . $approveText . '</td></tr>';

            if ($isapproved != NULL)
            {
                echo '<tr><th>Decided By</th><td>' . $groupBanker . '</td></tr>';
                $date = new DateTime($groupresult['decisiondate']);
                echo '<tr><th>Decision Date</th><td>' . $date->format('m/d/y H:i:s') . '</td></tr>';
            }

            echo '<tr><td style="height: 15px;"></td></tr>';
            echo "<tr><th>User</th><th>Amount</th><th>Title</th><th>Description</th></tr>";

            while($transaction = $db->fetch_array($query))
            {
                $userlink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $transaction['uid'] . '">';
                echo '<td>' . $userlink . $transaction['username'] . '</a></td>';
                
                if ($transaction['amount'] < 0) {
                    echo "<td class='negative'>-$" . number_format(abs($transaction['amount']), 0) . "</td>";
                }
                else {
                    echo "<td class='positive'>$" . number_format($transaction['amount'], 0) . "</td>";
                }
                
                echo '<td>' . $transaction['title'] . '</td>';
                echo '<td>' . $transaction['description'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';

            if($isBanker && $isapproved == NULL)
            {
                echo '<p><form method="post">';
                echo '<input type="submit" name="submitApprove" value="Approve" /> ';
                echo '<input type="submit" name="submitDecline" value="Decline" />';
                echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />';
                echo '</form></p>';
                echo "</div>";
            }
        }
        else
        {
            echo '<div class="bojoSection"';
            echo '<p>No transaction found</p>';
            echo '</div>';
        }
        ?>

        <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html>