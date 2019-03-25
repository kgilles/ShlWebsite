<html>

<head>
    <title>SHL Hockey -> Player Updater</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php 

        include 'bankerOps.php';

        $myuid = getUserId($mybb);

        // if not logged in, go away why are you even here
        if ($myuid <= 0) { echo 'You are not logged in'; exit; }

        // Gets id of user from URL
        $uid = 0;
        if (isset($_GET["uid"]) && is_numeric($_GET["uid"])) {
            $uid = getSafeNumber($db, $_GET["uid"]);
        }
        else {
            // ... or redirects to your own page
            header('Location: http://simulationhockey.com/playerupdater.php?uid='.$myuid);
        }

        $isBanker = checkIfBanker($mybb);
        $isBanker = true; // TODO: remove for testing

        $curruser = getUser($db, $uid);
        $bankbalance = $curruser["bankbalance"];
        $currname = $curruser["username"];

        // If a submit button was pressed
        if (isset($mybb->input["bojopostkey"])) 
        {
            verify_post_check($mybb->input["bojopostkey"]);

            // If banker undid a transaction.
            if ($isBanker && isset($mybb->input["undotransaction"], $mybb->input["undoid"]) && is_numeric($mybb->input["undoid"]))
            {
                // Removes transaction row
                $transid = getSafeNumber($db, $mybb->input["undoid"]);
                $undoquery = $db->simple_select("banktransactions", "*", "id=$transid", array("limit" => 1));
                $undoresult = $db->fetch_array($undoquery);
                $undoamount = intval($undoresult["amount"]);
                $undotitle = $undoresult["title"];
                $undodescription = $undoresult["description"];
                $undouid = $undoresult["uid"];
                $db->delete_query("banktransactions", "id=$transid");

                $bankbalance = updateBankBalance($db, $uid);

                logAction($db, "UNDO", "$myuid undid a transaction for $undouid titled $undotitle. The amount was $undoamount. Description: $undodescription");
                displaySuccessTransaction($currname, $undoamount, $undoresult['title'], $undodescription, "Undo Transaction");
            }

            // If banker approved a transfer.
            else if ($isBanker && isset($mybb->input["approvetransfer"], $mybb->input["approveid"]))
            {
                $approveid = getSafeNumber($db, $mybb->input["approveid"]);
                $approvequery = $db->simple_select("banktransferrequests", "*", "id=$approveid", array("limit" => 1));
                $approveresult = $db->fetch_array($approvequery);
                $approveamount = intval($approveresult["amount"]);
                $approvetitle = $approveresult["title"];
                $approvedescription = $approveresult["description"];
                $approverequester = intval($approveresult["userrequestid"]);
                $approvetarget = intval($approveresult["usertargetid"]);

                $bankbalance = acceptTransferRequest($db, $uid, $myuid, $approveid, $approverequester, $approvetarget, $approveamount, $approvetitle, $approvedescription);
                logAction($db, "UNDO", "$myuid approved transfer ($approveid) for $approvetarget titled $approvetitle. The amount was $approveamount to $approvetarget. Description: $approvedescription");
            }

            // If banker declined a transfer.
            else if ($isBanker && isset($mybb->input["declinetransfer"], $mybb->input["declineid"]))
            {
                $declineid = getSafeNumber($db, $mybb->input["declineid"]);
                $approvequery = $db->simple_select("banktransferrequests", "*", "id=$declineid", array("limit" => 1));
                $approveresult = $db->fetch_array($approvequery);
                $approveamount = intval($approveresult["amount"]);
                $approvetitle = $approveresult["title"];
                $approvedescription = $approveresult["description"];
                $approverequester = intval($approveresult["userrequestid"]);
                $approvetarget = intval($approveresult["usertargetid"]);

                $db->delete_query("banktransferrequests", "id=$declineid");
                logAction($db, "UNDO", "$myuid declined transfer for $approvetarget titled $approvetitle. The amount was $approveamount to $approvetarget. Description: $approvedescription");
    
                echo '<div class="successSection">';
                echo '<h4>Successfully declined transaction</h4>';
                echo '</div>';
            }

            // If banker submitted a transaction.
            else if ($isBanker && isset($mybb->input["submittransaction"], $mybb->input["transactionamount"]))
            {
                $transAmount = getSafeNumber($db, $mybb->input["transactionamount"]);
                $transTitle = getSafeAlpNum($db, $mybb->input["transactiontitle"]);
                $transDescription = getSafeAlpNum($db, $mybb->input["transactiondescription"]);
                if(strlen($transDescription) == 0) $transDescription = null;

                $bankbalance = doTransaction($db, $transAmount, $transTitle, $transDescription, $uid, $myuid, $currname, "Banker Transaction");
            }

            // If the user submitted a transaction himself.
            else if (isset($mybb->input["submitpurchase"], $mybb->input["purchaseamount"]))
            {
                $transAmount = -abs(getSafeNumber($db, $mybb->input["purchaseamount"]));
                $transTitle = getSafeAlpNum($db, $mybb->input["purchasetitle"]);
                $transDescription = getSafeAlpNum($db, $mybb->input["purchasedescription"]);
                if(strlen($transDescription) == 0) { $transDescription = null; }

                if ($transAmount != 0 && strlen($transTitle))
                {
                    $bankbalance = addBankTransaction($db, $userid, $transAmount, $transTitle, $description, $creatorid);
                    displaySuccessTransaction($currname, $transAmount, $transTitle, $description, "User Purchase");
                }
                else
                {
                    displayErrorTransaction();
                }
            }

            // If user submitted a transfer request for another user.
            else if (isset($mybb->input["submitrequest"], $mybb->input["requestamount"]))
            {
                $transAmount = -abs(getSafeNumber($db, $mybb->input["requestamount"]));
                $transTitle = getSafeAlpNum($db, $mybb->input["requesttitle"]);
                $transDescription = getSafeAlpNum($db, $mybb->input["requestdescription"]);
                if(strlen($transDescription) == 0) { $transDescription = null; }

                if ($transAmount != 0 && strlen($transTitle))
                {
                    addBankTransferRequest($db, $myuid, $uid, $transAmount, $transTitle, $transDescription);
                    displaySuccessTransaction($currname, $transAmount, $transTitle, $transDescription, "Transfer Request");
                }
                else {
                    displayErrorTransaction();
                }
            }
        }
        ?>
        
        
        <!-- User Information -->
        <div class="bojoSection navigation">
        <h2>{$currname}</h2>
        <table>
        <tr><th>Balance</th><td>
        <?php 
            if ($bankbalance < 0) { echo '-'; }
            echo "$" . number_format(abs($bankbalance), 0) . "</td></tr>";
        ?>
        </table>

        <hr />

        <h4>Bank Transactions</h4>
        <table style="margin-bottom: 20px;">
        <tr>
        <th>Title</th>
        <th>Amount</th>
        <th>Date</th>
        <th>Made By</th>
        <if $isBanker then><th></th></if>
        </tr>

        <?php 
            // Bank Transactions
            $transactionQuery = 
            "SELECT bt.*, creator.username AS 'creator'
                FROM mybb_banktransactions bt
                LEFT JOIN mybb_users creator ON bt.createdbyuserid=creator.uid
                WHERE bt.uid=$uid
                ORDER BY bt.date DESC
                LIMIT 50";

            $bankRows = $db->query($transactionQuery);
            while ($row = $db->fetch_array($bankRows))
            {
                $date = new DateTime($row['date']);
                $transactionLink = '<a href="http://simulationhockey.com/banktransaction.php?id=' . $row['id'] . '">';
                $creatorLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['createdbyuserid'] . '">';
                $bankerLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['bankerapprovalid'] . '">';
                $amountClass = ($row['amount'] < 0) ? 'negative' : 'positive';
                $negativeSign = ($row['amount'] < 0) ? '-' : '';

                echo '<tr>';
                echo '<td>' . $transactionLink . $row['title'] . '</a></td>';
                echo '<td class="' . $amountClass . '">' . $transactionLink . $negativeSign . '$' . number_format(abs($row['amount']), 0) . "</a></td>";
                echo "<td>" . $date->format('m/d/y') . "</td>";
                echo '<td>' . $creatorLink . $row['creator'] . "</a></td>";
          
                if($isBanker)
                {
                    echo '<form method="post"><td><input type="submit" name="undotransaction" value="Undo" /></td>';
                    echo '<form method="post"><input type="hidden" name="undoid" value="'. $row['id'] .'" />';
                    echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" /></form>';
                }

                echo "</tr>";
            }
        ?>
        </table>

        <hr />
        <h4>Requests Pending Approval</h4>

        <?php 
            // Transfer Requests
            $transactionQuery = 
            "SELECT bt.*, groups.id as 'gid', groups.groupname, groups.requestdate
                FROM mybb_banktransactionrequests bt
                LEFT JOIN mybb_banktransactiongroups groups ON bt.groupid=groups.id && groups.isapproved IS NULL
                WHERE bt.uid=$uid
                ORDER BY groups.requestdate DESC
                LIMIT 50";

            $bankRows = $db->query($transactionQuery);
            $bankRowCount = mysqli_num_rows($bankRows);

            if ($bankRowCount <= 0) {
                echo '<p>No active requests for this user</p>';
            }
            else {
                echo 
                '<table>
                <tr>
                <th>Title</th>
                <th>Amount</th>
                <th>Date Requested</th>
                <th>Description</th>
                </tr>';

                while ($row = $db->fetch_array($bankRows))
                {
                    $requestdate = new DateTime($row['requestdate']);
                    $requestdate = $requestdate->format('m/d/y');

                    $grouplink = '<a href="http://simulationhockey.com/bankgrouptransaction.php?id=' . $row['gid'] . '">';
                    $amountClass = ($row['amount'] < 0) ? 'negative' : 'positive';
                    $negativeSign = ($row['amount'] < 0) ? '-' : '';

                    echo '<tr>';
                    echo '<td>' . $grouplink . $row['title'] . '</a></td>';
                    echo '<td class="' . $amountClass . '">' . $grouplink . $negativeSign . '$' . number_format(abs($row['amount']), 0) . "</a></td>";
                    echo "<td>" . $requestdate . "</td>";
                    echo '<td>' . $row['description'] . "</a></td>";
                    echo "</tr>";
                }
                echo '</table>';
            }

        ?>

        <hr />
        <h4>Transfers Pending Approval</h4>

        <?php 
            // Transfer Requests
            $transactionQuery = 
            "SELECT bt.*, utarget.username AS 'utarget', ubanker.username AS 'ubanker', urequester.username AS 'urequester'
                FROM mybb_banktransferrequests bt
                LEFT JOIN mybb_users urequester ON bt.userrequestid=urequester.uid
                LEFT JOIN mybb_users utarget ON bt.usertargetid=utarget.uid
                LEFT JOIN mybb_users ubanker ON bt.bankerapproverid=ubanker.uid
                WHERE (bt.userrequestid=$uid OR bt.usertargetid=$uid) AND bankerapproverid IS NULL
                ORDER BY bt.requestdate DESC
                LIMIT 50";

            $bankRows = $db->query($transactionQuery);
            $bankRowCount = mysqli_num_rows($bankRows);

            if ($bankRowCount <= 0) {
                echo '<p>No active transfer requests for this user</p>';
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
                    $requestdate = new DateTime($row['requestdate']);
                    $requestdate = $requestdate->format('m/d/y');

                    if($row['approvaldate'] === null) {
                        $approvedate = '';    
                    } else {
                        $approvedate = new DateTime($row['approvaldate']);
                        $approvedate = $approvedate->format('m/d/y');
                    }

                    $urequesterLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['userrequestid'] . '">';
                    $utargetLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['usertargetid'] . '">';
                    $ubankerLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['bankerapprovalid'] . '">';
                    $amountClass = ($row['amount'] < 0) ? 'negative' : 'positive';
                    $negativeSign = ($row['amount'] < 0) ? '-' : '';

                    echo '<tr>';
                    echo '<td>' . $row['title'] . '</a></td>';
                    echo '<td>' . $urequesterLink . $row['urequester'] . '</a></td>';
                    echo '<td>' . $utargetLink . $row['utarget'] . '</a></td>';
                    echo '<td class="' . $amountClass . '">' . $transactionLink . $negativeSign . '$' . number_format(abs($row['amount']), 0) . "</a></td>";
                    echo "<td>" . $requestdate . "</td>";
                    // echo '<td>' . $ubankerLink . $row['ubanker'] . '</a></td>';
                    // echo "<td>" . $approvedate . "</td>";
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
                        else { echo '<td></td><td></td>'; }
                    }
                    echo '<td>' . $row['description'] . "</a></td>";
                    echo "</tr>";
                }
                echo '</table>';
            }

        ?>
        </div>

        <!-- New Purchase: Only available to the actual user -->
        <if ($uid == $myuid) then>
            <div class="bojoSection navigation">
                <h2>New Purchase <span class="expandclick" onclick="toggleArea(this, 'purchasearea')">(expand)</span></h2>
                <div id="purchasearea" class="hideme">
                <form method="post">
                <table>
                    <tr><th>Amount</th><td><input type="number" name="purchaseamount" placeholder="Enter amount..." /></td></tr>
                    <tr><th>Title</th><td><input type="text" name="purchasetitle" placeholder="Enter title..." /></td></tr>
                    <tr><th>Description</th><td><input type="text" name="purchasedescription" placeholder="Enter description..." /></td></tr>
                    <tr><th></th><td><input type="submit" name="submitpurchase" value="Make Purchase" /></td></tr>
                    <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code ?>" />
                </table>
                </form>
                <p style="margin-bottom: 0px"><em>Write a postive number for a purchase transaction. No approvals necessary.</em></p>
                </div>
            </div>
        </if>

        <!-- New Transfer Request: Only available when on another user's page -->
        <if ($uid !== $myuid) then>
            <div class="bojoSection navigation">
                <h2>New Transfer Request <span class="expandclick" onclick="toggleArea(this, 'transferarea')">(expand)</span></h2>
                <div id="transferarea" class="hideme">
                <form method="post">
                <table>
                    <tr><th>Amount</th><td><input type="number" name="requestamount" placeholder="Enter amount..." /></td></tr>
                    <tr><th>Title</th><td><input type="text" name="requesttitle" placeholder="Enter title..." /></td></tr>
                    <tr><th>Description</th><td><input type="text" name="requestdescription" placeholder="Enter description..." /></td></tr>
                    <tr><th></th><td><input type="submit" name="submitrequest" value="Request" /></td></tr>
                    <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code ?>" />
                </table>
                </form>
                <p style="margin-bottom: 0px"><em>Write a postive number to send to this user. A request will be sent to the bankers who will need to approve all requests.</em></p>
                </div>
            </div>
        </if>

        <!-- Banker Controls: Bankers only -->
        <if ($isBanker) then>

            <!-- Add a transaction -->
            <div class="bojoSection navigation">
            <h2>Banker Controls <span class="expandclick" onclick="toggleArea(this, 'bankerarea')">(expand)</span></h2>
            <div id="bankerarea" class="hideme">
            <h4>Add Transaction</h4>
            <form method="post">
            <table>
            <tr><th>Amount</th><td><input type="number" name="transactionamount" placeholder="Enter amount..." /></td></tr>
            <tr><th>Title</th><td><input type="text" name="transactiontitle" placeholder="Enter title..." /></td></tr>
            <tr><th>Description</th><td><input type="text" name="transactiondescription" placeholder="Enter description..." /></td></tr>
            <tr><th></th><td><input type="submit" name="submittransaction" value="Add Transaction" /></td></tr>
            <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code; ?>" />
            </table>
            <p style="margin-bottom: 0px"><em>Adds a transaction. If removing money, make sure to add the negative sign.</em></p>
            </form>
            </div>
            </div>
        </if>

        <script>
        function toggleArea(spanlink, idToHide)
        {
            if(document.getElementById(idToHide).className != 'hideme')
            {
                document.getElementById(idToHide).className = 'hideme';
                spanlink.innerHTML = "(expand)";
            }
            else
            {
                document.getElementById(idToHide).className = '';
                spanlink.innerHTML = "(hide)";
            }
        }
        </script>

        <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html>