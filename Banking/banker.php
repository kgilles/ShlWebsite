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

    // Gets current user logged in
    $myuid = $mybb->user['uid'];

    // if not logged in, go away why are you even here
    if ($myuid <= 0) { echo 'You are not logged in'; exit; }

    $isBanker = checkIfBanker($mybb);
    $isBanker = true; // TODO: remove for testing

    // If a submit button was pressed
    if (isset($mybb->input["bojopostkey"])) 
    {
        verify_post_check($mybb->input["bojopostkey"]);

        // If banker approved a transfer.
        if ($isBanker && isset($mybb->input["approvetransfer"], $mybb->input["approveid"]))
        {
            $approveid = getSafeInputNum($db, $mybb, "approveid");
            $approvequery = $db->simple_select("banktransferrequests", "*", "id=$approveid", array("limit" => 1));
            $approveresult = $db->fetch_array($approvequery);
            $approveamount = intval($approveresult["amount"]);
            $approvetitle = $approveresult["title"];
            $approvedescription = $approveresult["description"];
            $approverequester = intval($approveresult["userrequestid"]);
            $approvetarget = intval($approveresult["usertargetid"]);

            $bankbalance = acceptTransferRequest($db, $uid, $myuid, $approveid, $approverequester, $approvetarget, $approveamount, $approvetitle, $approvedescription);
        }

        // If banker declined a transfer.
        else if ($isBanker && isset($mybb->input["declinetransfer"], $mybb->input["declineid"]))
        {
            $declineid = getSafeInputNum($db, $mybb, "declineid");
            
            $db->delete_query("banktransferrequests", "id=$declineid");

            echo '<div class="successSection">';
            echo '<h4>Successfully declined transaction</h4>';
            echo '</div>';
        }
    }
    ?>

    <div class="bojoSection navigation">
    <h2>Active Group Requests</h2>
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
        }        
        else {
            echo 
            '<table>
            <tr>
            <th>Group Name</th>
            <th>Requester</th>
            <th>Date Requested</th>
            </tr>';

            while ($row = $db->fetch_array($bankRows))
            {
                $requestdate = new DateTime($row['datrequestdatee']);
                $requestdate = $requestdate->format('m/d/y');

                $ugroupLink = '<a href="http://simulationhockey.com/bankgrouptransaction.php?id=' . $row['id'] . '">';
                $urequesterLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['creatorid'] . '">';

                echo '<tr>';
                echo '<td>' . $ugroupLink . $row['groupname'] . '</a></td>';
                echo '<td>' . $urequesterLink . $row['urequester'] . '</a></td>';
                echo "<td>" . $requestdate . "</td>";
                echo '<td>' . $row['description'] . "</a></td>";
                echo "</tr>";
            }
            echo '</table>';
        }

    ?>
    
    <hr />

    <h2>Previous Group Requests</h2>
    <?php 
        // Transfer Requests
        $transactionQuery = 
        "SELECT bt.*, urequester.username AS 'urequester'
            FROM mybb_banktransactiongroups bt
            LEFT JOIN mybb_users urequester ON bt.creatorid=urequester.uid
            WHERE bt.isapproved IS NOT NULL
            ORDER BY bt.requestdate DESC
            LIMIT 50";

        $bankRows = $db->query($transactionQuery);
        $bankRowCount = mysqli_num_rows($bankRows);

        if ($bankRowCount <= 0) {
            echo '<p>No transfers</p>';
        }        
        else {
            echo 
            '<table>
            <tr>
            <th>Group Name</th>
            <th>Requester</th>
            <th>Date Requested</th>
            <th>Approved?</th>
            </tr>';

            while ($row = $db->fetch_array($bankRows))
            {
                $requestdate = new DateTime($row['datrequestdatee']);
                $requestdate = $requestdate->format('m/d/y');
                $requestApproval = intval($row['isapproved']) ? "Yes" : "No";

                $ugroupLink = '<a href="http://simulationhockey.com/bankgrouptransaction.php?id=' . $row['id'] . '">';
                $urequesterLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['creatorid'] . '">';

                echo '<tr>';
                echo '<td>' . $ugroupLink . $row['groupname'] . '</a></td>';
                echo '<td>' . $urequesterLink . $row['urequester'] . '</a></td>';
                echo "<td>" . $requestdate . "</td>";
                echo '<td>' . $requestApproval . "</td>";
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
        }        
        else {
            echo 
            '<table>
            <tr>
            <th>Title</th>
            <th>Requester</th>
            <th>Target</th>
            <th>Amount</th>
            <th>Date Requested</th>';
            if ($isBanker) { echo '<th></th><th></th>'; }
            echo '<th>Description</th>
            </tr>';

            while ($row = $db->fetch_array($bankRows))
            {
                $requestdate = new DateTime($row['datrequestdatee']);
                $requestdate = $requestdate->format('m/d/y');

                if($row['approvaldate'] === null) {
                    $approvedate = '';    
                } else {
                    $approvedate = new DateTime($row['approvaldate']);
                    $approvedate = $approvedate->format('m/d/y');
                }

                $urequesterLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['userrequestid'] . '">';
                $utargetLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['usertargetid'] . '">';
                $amountClass = ($row['amount'] < 0) ? 'negative' : 'positive';
                $negativeSign = ($row['amount'] < 0) ? '-' : '';

                echo '<tr>';
                echo '<td>' . $row['title'] . '</a></td>';
                echo '<td>' . $urequesterLink . $row['urequester'] . '</a></td>';
                echo '<td>' . $utargetLink . $row['utarget'] . '</a></td>';
                echo '<td class="' . $amountClass . '">' . $transactionLink . $negativeSign . '$' . number_format(abs($row['amount']), 0) . "</a></td>";
                echo "<td>" . $requestdate . "</td>";
                if($isBanker)
                {
                    if($row['bankerapproverid'] == null)
                    {
                        echo '<form method="post"><td><input type="submit" name="approvetransfer" value="Accept" /></td>';
                        echo '<input type="hidden" name="approveid" value="'. $row['id'] .'" />';
                        echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" /></form>';

                        echo '<form method="post"><td><input type="submit" name="declinetransfer" value="Decline" /></td>';
                        echo '<input type="hidden" name="declineid" value="'. $row['id'] .'" />';
                        echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" /></form>';
                    }
                    else { echo '<td></td>'; }
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