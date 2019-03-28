<html>

<head>
    <title>SHL Hockey -> Bank Transaction Request</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php include 'bankerOps.php';

    // Gets id of user from URL
    if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
        $currBankRequestId = intval($_GET["id"]);
    } else {
        echo 'request not found';
        exit;
    }

    $myuid = getUserId($mybb);
    $isBanker = checkIfBanker($mybb);

    if ($currBankRequestId > 0) {
        // If a submit button was pressed
        if (isset($mybb->input["bojopostkey"])) {
            verify_post_check($mybb->input["bojopostkey"]);

            if ($isBanker) {
                if (isset($mybb->input["submitApprove"])) {
                    $querytext = "UPDATE mybb_banktransactiongroups SET bankerid=$myuid, isapproved=1, decisiondate=now() WHERE mybb_banktransactiongroups.id=$currBankRequestId";
                    $db->write_query($querytext);

                    $xQuery = $db->simple_select("banktransactiongroups", "*", "id='$currBankRequestId'", array("limit" => 1));
                    if ($xRow = $db->fetch_array($xQuery)) {
                        $bankerid = intval($xRow['bankerid']);
                        $requesterid = intval($xRow['creatorid']);
                        $groupname = $xRow['groupname'];
                        $decisiondate = $xRow['decisiondate'];

                        $requestrows = $db->simple_select("banktransactionrequests", "*", "groupid='$currBankRequestId'", array());
                        while ($requestresult = $db->fetch_array($requestrows)) {
                            $requestdata[] = [
                                "uid" => $requestresult['uid'],
                                "amount" => $requestresult['amount'],
                                "title" => $requestresult['title'],
                                "description" => $requestresult['description'],
                                "createdbyuserid" => $requesterid,
                                "bankerapproverid" => $bankerid,
                                "date" => $decisiondate,
                                "groupid" => $currBankRequestId
                            ];
                        }

                        if (count($requestdata) > 0) {
                            $db->insert_query_multiple("banktransactions", $requestdata);
                        }
                        // TODO: What to do if 0 requests

                        foreach ($requestdata as $data) {
                            updateBankBalance($db, intval($data["uid"]));
                        }

                        // TODO: Add list of transactions added
                        echo '<div class="successSection">';
                        echo '<h4>Successfully Accepted: Transactions added</h4>';
                        echo '</div>';
                    }
                } else if (isset($mybb->input["submitDecline"])) {
                    $querytext = "UPDATE mybb_banktransactiongroups SET bankerid=$myuid, isapproved=0, decisiondate=now() WHERE mybb_banktransactiongroups.id=$currBankRequestId";
                    $db->query($querytext);

                    echo '<div class="successSection">';
                    echo '<h4>Successfully Declined: No Transactions added</h4>';
                    echo '</div>';
                }
            } else {
                echo "You're not a banker. shoo.";
                exit;
            }
        }

        $querytext =
            "SELECT bt.*, usr.username AS 'username', banker.username AS 'bankerusername'
                FROM mybb_banktransactiongroups bt 
                LEFT JOIN mybb_users usr ON bt.creatorid=usr.uid 
                LEFT JOIN mybb_users banker ON bt.bankerid=banker.uid 
                WHERE id=$currBankRequestId LIMIT 1";

        $xQuery = $db->query($querytext);
        if ($xRow = $db->fetch_array($xQuery)) {
            $groupid = intval($xRow['id']);
            $groupName = $xRow['groupname'];
            $groupUser = $xRow['username'];
            $groupBanker = $xRow['bankerusername'];
            $isapproved = $xRow['isapproved'];

            if ($isapproved == 1)
                $approveText = "Approved";
            else if ($isapproved == 0)
                $approveText = "Declined";
            else
                $approveText = "No Decision";

            $requestDate = new DateTime($xRow['requestdate']);

            echo '<div class="bojoSection navigation">';
            echo '<h4>Bank Group Transaction</h4>';
            echo '<table>';
            echo '<tr><th>Group Name</th><td>' . $groupName . '</td></tr>';
            echo '<tr><th>Submitted By</th><td>' . $groupUser . '</td></tr>';
            echo '<tr><th>Submitted Date</th><td>' . $requestDate->format('m/d/y H:i:s') . '</td></tr>';
            echo '<tr><th>Status</th><td>' . $approveText . '</td></tr>';

            if ($isapproved != null) {
                echo '<tr><th>Decided By</th><td>' . $groupBanker . '</td></tr>';
                $requestDate = new DateTime($xRow['decisiondate']);
                echo '<tr><th>Decision Date</th><td>' . $requestDate->format('m/d/y H:i:s') . '</td></tr>';
            }

            echo '<tr><td style="height: 15px;"></td></tr>';
            echo "<tr><th>User</th><th>Amount</th><th>Title</th><th>Description</th></tr>";

            if ($isapproved == 1)
                $transactiontable = "mybb_banktransactions";
            else
                $transactiontable = "mybb_banktransactionrequests";

            $querytext =
                "SELECT bt.*, usr.username AS 'username'
                    FROM $transactiontable bt 
                    INNER JOIN mybb_users usr ON bt.uid=usr.uid 
                    WHERE groupid=$groupid";

            $xQuery = $db->query($querytext);

            while ($xRow = $db->fetch_array($xQuery)) {
                $userlink = '<a href="' . getBankAccountLink($xRow['uid']) . '">';
                echo '<td>' . $userlink . $xRow['username'] . '</a></td>';

                if ($xRow['amount'] < 0)
                    echo "<td class='negative'>-$" . number_format(abs($xRow['amount']), 0) . "</td>";
                else
                    echo "<td class='positive'>$" . number_format($xRow['amount'], 0) . "</td>";

                echo '<td>' . $xRow['title'] . '</td>';
                echo '<td>' . $xRow['description'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';

            if ($isBanker && $isapproved == null) {
                echo '<p><form method="post">';
                echo '<input type="submit" name="submitApprove" value="Approve" /> ';
                echo '<input type="submit" name="submitDecline" value="Decline" />';
                echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />';
                echo '</form></p>';
                echo "</div>";
            }
        }
        else {
            echo '<div class="bojoSection"';
            echo '<p>No transaction found</p>';
            echo '</div>';            
        }
    } else {
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