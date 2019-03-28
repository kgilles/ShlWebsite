<html>

<head>
    <title>SHL Hockey -> Bank Summary</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php include 'bankerOps.php';

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

        // If banker approved a transfer.
        if (isset($mybb->input["approvetransfer"], $mybb->input["approveid"]) && is_numeric($mybb->input["approveid"])) {
            if ($isBanker) {
                $requestid = getSafeNumber($db, $mybb->input["approveid"]);
                $xQuery = $db->simple_select("banktransferrequests", "*", "id=$requestid", array("limit" => 1));
                if ($xRow = $db->fetch_array($xQuery)) {
                    $requestAmount = intval($xRow["amount"]);
                    $requestTitle = $xRow["title"];
                    $requestDescription = $xRow["description"];
                    $requesterId = intval($xRow["userrequestid"]);
                    $requestTargetId = intval($xRow["usertargetid"]);
                    acceptTransferRequest($db, $uid, $myuid, $requestid, $requesterId, $requestTargetId, $requestAmount, $requestTitle, $requestDescription);
                } else {
                    echo "request not found...";
                    exit;
                }
            } else {
                echo "You're not a banker. shoo.";
                exit;
            }
        }

        // If banker declined a transfer.
        else if (isset($mybb->input["declinetransfer"], $mybb->input["declineid"]) && is_numeric($mybb->input["declineid"])) {
            if ($isBanker) {
                $declineid = getSafeNumber($db, $mybb->input["declineid"]);

                // TODO: replace delete with a approval column
                $db->delete_query("banktransferrequests", "id=$declineid");

                echo '<div class="successSection">';
                echo '<h4>Successfully declined transaction</h4>';
                echo '</div>';
            } else {
                echo "You're not a banker. shoo.";
                exit;
            }
        }
    }
    ?>

    <div class="bojoSection navigation">
        <h2>Banker Portal</h2>
        <p>At a glance view of active requests requiring banker decisions.</p>
        <p>Links:
            <ul>
            <li><a href="http://simulationhockey.com/bankaccount.php">Your Bank Account</a></li>
            <li><a href="http://simulationhockey.com/banksubmitrequest.php">Submit Request</a></li>
            <li><a href="http://simulationhockey.com/bankexportbalances.php">Download Bank Balances</a></li>
                <?php if ($isBanker) {
                    echo '<li><a href="http://simulationhockey.com/teamaddusers.php">Assign Users to Team</a></li>';
                } ?>
            </ul>
        </p>
    </div>

    <?php 
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
    ?>
    <div class="bojoSection navigation">
        <h2>User Accounts by Team</h2>
        <p>
            <ul>
                <?php 
                foreach ($teams as $item)
                    echo '<li><a href="http://simulationhockey.com/bankteam.php?id=' . $item["id"] . '">' . $item["name"] . '</a></li>';
                ?>
            </ul>
        </p>
    </div>

    <div class="bojoSection navigation">
        <h2>Group Requests</h2>
        <h3>Pending Approval</h3>
        <?php 
        // Transfer Requests
        $transactionQuery =
            "SELECT bt.*, urequester.username AS 'urequester'
            FROM mybb_banktransactiongroups bt
            LEFT JOIN mybb_users urequester ON bt.creatorid=urequester.uid
            WHERE bt.isapproved IS NULL
            ORDER BY bt.requestdate DESC";

        $bankRows = $db->query($transactionQuery);
        $bankRowCount = mysqli_num_rows($bankRows);

        if ($bankRowCount <= 0) {
            echo '<p>No active transfers</p>';
        } else {
            echo
                '<table>
            <tr>
            <th>Group Name</th>
            <th>Requester</th>
            <th>Date Requested</th>
            </tr>';

            while ($row = $db->fetch_array($bankRows)) {
                $requestdate = new DateTime($row['datrequestdatee']);
                $requestdate = $requestdate->format('m/d/y');

                $ugroupLink =  getBankRequestLink($row['id']);
                $urequesterLink = getBankAccountLink($row['creatorid']);

                echo '<tr>';
                echo '<td><a href="' . $ugroupLink . '">' . $row['groupname'] . '</a></td>';
                echo '<td><a href="' . $urequesterLink . '">' . $row['urequester'] . '</a></td>';
                echo "<td>" . $requestdate . "</td>";
                echo '<td>' . $row['description'] . "</a></td>";
                echo "</tr>";
            }
            echo '</table>';
        }

        ?>

        <hr />

        <h3>Review History (Last 25)</h3>
        <?php 
        // Transfer Requests
        $transactionQuery =
            "SELECT bt.*, urequester.username AS 'urequester', ubanker.username AS 'bankername'
            FROM mybb_banktransactiongroups bt
            LEFT JOIN mybb_users urequester ON bt.creatorid=urequester.uid
            LEFT JOIN mybb_users ubanker ON bt.bankerid=ubanker.uid
            WHERE bt.isapproved IS NOT NULL
            ORDER BY bt.requestdate DESC
            LIMIT 25";

        $bankRows = $db->query($transactionQuery);
        $bankRowCount = mysqli_num_rows($bankRows);

        if ($bankRowCount <= 0) {
            echo '<p>No transfers</p>';
        } else {
            echo
                '<table>
            <tr>
            <th>Group Name</th>
            <th>Requester</th>
            <th>Date Requested</th>
            <th>Approved?</th>
            <th>Banker</th>
            <th>Date Decided</th>
            </tr>';

            while ($row = $db->fetch_array($bankRows)) {
                $decisiondate = new DateTime($row['decisiondate']);
                $decisiondate = $decisiondate->format('m/d/y');

                $requestdate = new DateTime($row['requestdate']);
                $requestdate = $requestdate->format('m/d/y');
                $requestApproval = intval($row['isapproved']) ? "Yes" : "No";

                $ugroupLink =  getBankRequestLink($row['id']);
                $urequesterLink = getBankAccountLink($row['creatorid']);

                echo '<tr>';
                echo '<td><a href="' . $ugroupLink . '">' . $row['groupname'] . '</a></td>';
                echo '<td><a href="' . $urequesterLink . '">' . $row['urequester'] . '</a></td>';
                echo "<td>" . $requestdate . "</td>";
                echo '<td>' . $requestApproval . "</td>";
                echo '<td>' . $row['bankername'] . "</td>";
                echo '<td>' . $decisiondate . "</td>";
                echo "</tr>";
            }
            echo '</table>';
        }

        ?>
    </div>

    <div class="bojoSection navigation">
        <h2>Active Transfer Requests</h2>
        <?php 
        // Transfer Requests
        $transactionQuery =
            "SELECT bt.*, utarget.username AS 'utarget', urequester.username AS 'urequester'
            FROM mybb_banktransferrequests bt
            LEFT JOIN mybb_users urequester ON bt.userrequestid=urequester.uid
            LEFT JOIN mybb_users utarget ON bt.usertargetid=utarget.uid
            WHERE bt.bankerapproverid IS NULL
            ORDER BY bt.requestdate DESC
            LIMIT 50";

        $bankRows = $db->query($transactionQuery);
        $bankRowCount = mysqli_num_rows($bankRows);

        if ($bankRowCount <= 0) {
            echo '<p>No active transfers</p>';
        } else {
            echo
                '<table>
            <tr>
            <th>Title</th>
            <th>Requester</th>
            <th>Target</th>
            <th>Amount</th>
            <th>Date Requested</th>';
            if ($isBanker) {
                echo '<th></th><th></th>';
            }
            echo '<th>Description</th>
            </tr>';

            while ($row = $db->fetch_array($bankRows)) {
                $requestdate = new DateTime($row['datrequestdatee']);
                $requestdate = $requestdate->format('m/d/y');

                if ($row['approvaldate'] === null) {
                    $approvedate = '';
                } else {
                    $approvedate = new DateTime($row['approvaldate']);
                    $approvedate = $approvedate->format('m/d/y');
                }

                $urequesterLink = getBankAccountLink($row['userrequestid']);
                $utargetLink = getBankAccountLink($row['usertargetid']);
                $amountClass = ($row['amount'] < 0) ? 'negative' : 'positive';
                $negativeSign = ($row['amount'] < 0) ? '-' : '';

                echo '<tr>';
                echo '<td>' . $row['title'] . '</a></td>';
                echo '<td><a href="' . $urequesterLink . '">' . $row['urequester'] . '</a></td>';
                echo '<td><a href="' . $utargetLink . '">' . $row['utarget'] . '</a></td>';
                echo '<td class="' . $amountClass . '">' . $transactionLink . $negativeSign . '$' . number_format(abs($row['amount']), 0) . "</a></td>";
                echo "<td>" . $requestdate . "</td>";
                if ($isBanker) {
                    if ($row['bankerapproverid'] == null) {
                        echo '<form method="post"><td><input type="submit" name="approvetransfer" value="Accept" /></td>';
                        echo '<input type="hidden" name="approveid" value="' . $row['id'] . '" />';
                        echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" /></form>';

                        echo '<form method="post"><td><input type="submit" name="declinetransfer" value="Decline" /></td>';
                        echo '<input type="hidden" name="declineid" value="' . $row['id'] . '" />';
                        echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" /></form>';
                    } else {
                        echo '<td></td>';
                    }
                }
                echo '<td>' . $row['description'] . "</a></td>";
                echo "</tr>";
            }
            echo '</table>';
        }

        ?>
    </div>

    <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html> 