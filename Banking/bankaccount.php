<html>

<head>
    <title>SHL Hockey -> Bank Account</title>
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

    // Gets id of user from URL
    if (isset($_GET["uid"]) && is_numeric($_GET["uid"]))
        $currentUserId = getSafeNumber($db, $_GET["uid"]);
    else {
        header('Location: http://simulationhockey.com/bankaccount.php?uid=' . $myuid);
        exit;
    }


    $curruser = getUser($db, $currentUserId);
    if ($curruser == null) {
        header('Location: http://simulationhockey.com/bankaccount.php?uid=' . $myuid);
        exit;
    }

    $isMyAccount = ($myuid == $currentUserId);
    $currbankbalance = $curruser["bankbalance"];
    $currname = $curruser["username"];
    $currteamid = $curruser["teamid"];

    if ($currteamid !== null) {
        $xQuery = $db->simple_select("teams", "name", "id=$currteamid", array("limit" => 1));
        if ($xRow = $db->fetch_array($xQuery))
            $teamName = $xRow['name'];
        else
            $teamName = "Unassigned";
    } else {
        $teamName = "Unassigned";
    }

    // If a submit button was pressed
    if (isset($mybb->input["bojopostkey"])) {
        verify_post_check($mybb->input["bojopostkey"]);

        // If banker undid a transaction.
        if (isset($mybb->input["undotransaction"], $mybb->input["undoid"]) && is_numeric($mybb->input["undoid"])) {
            if ($isBanker) {
                $transid = getSafeNumber($db, $mybb->input["undoid"]);
                $xQuery = $db->simple_select("banktransactions", "*", "id=$transid", array("limit" => 1));
                if ($xRow = $db->fetch_array($xQuery)) {
                    $transAmount = intval($xRow["amount"]);
                    $transTitle = $xRow["title"];
                    $transDescription = $xRow["description"];
                    $transUserId = $xRow["uid"];

                    // Removes the transaction
                    $db->delete_query("banktransactions", "id=$transid");

                    // Updates user's balance
                    $currbankbalance = updateBankBalance($db, $currentUserId);

                    logAction($db, "UNDO", "$myuid undid a transaction for $transUserId titled $transTitle. The amount was $transAmount. Description: $transDescription");
                    displaySuccessTransaction($currname, $transAmount, $transTitle, $transDescription, "Undo Transaction");
                } else {
                    echo "transaction not found...";
                    exit;
                }
            } else {
                echo "You're not a banker. shoo.";
                exit;
            }
        }

        // If banker approved a transfer.
        else if (isset($mybb->input["approvetransfer"], $mybb->input["approveid"])) {
            if ($isBanker) {
                $transferRequestId = getSafeNumber($db, $mybb->input["approveid"]);
                $xQuery = $db->simple_select("banktransferrequests", "*", "id=$transferRequestId", array("limit" => 1));
                if ($xRow = $db->fetch_array($xQuery)) {
                    $requestAmount = intval($xRow["amount"]);
                    $requestTitle = $xRow["title"];
                    $requestDescription = $xRow["description"];
                    $requesterId = intval($xRow["userrequestid"]);
                    $requestTargetId = intval($xRow["usertargetid"]);

                    // Accepts and updates both users' balances.
                    $currbankbalance = acceptTransferRequest($db, $currentUserId, $myuid, $transferRequestId, $requesterId, $requestTargetId, $requestAmount, $requestTitle, $requestDescription);
                    logAction($db, "UNDO", "$myuid approved transfer ($transferRequestId) from $requesterId titled $requestTitle. The amount was $requestAmount to $requestTargetId. Description: $requestDescription");

                    // TODO: Success Message.
                } else {
                    echo "transfer request not found...";
                    exit;
                }
            } else {
                echo "You're not a banker. shoo.";
                exit;
            }
        }

        // If banker declined a transfer.
        else if (isset($mybb->input["declinetransfer"], $mybb->input["declineid"])) {
            if ($isBanker) {
                $declineid = getSafeNumber($db, $mybb->input["declineid"]);
                $xQuery = $db->simple_select("banktransferrequests", "*", "id=$declineid", array("limit" => 1));
                if ($xRow = $db->fetch_array($xQuery)) {
                    $requestAmount = intval($xRow["amount"]);
                    $requestTitle = $xRow["title"];
                    $requestDescription = $xRow["description"];
                    $requestRequesterId = intval($xRow["userrequestid"]);
                    $requestTargetId = intval($xRow["usertargetid"]);

                    // Deletes Transfer Request
                    // TODO: Set to not approved instead
                    $db->delete_query("banktransferrequests", "id=$declineid");
                    logAction($db, "UNDO", "$myuid declined transfer for $requestTargetId titled $requestTitle. The amount was $requestAmount to $requestTargetId. Description: $requestDescription");

                    echo '<div class="successSection">';
                    echo '<h4>Successfully declined transaction</h4>';
                    echo '</div>';
                } else {
                    echo "transfer request not found...";
                    exit;
                }
            } else {
                echo "You're not a banker. shoo.";
                exit;
            }
        }

        // If banker submitted a transaction.
        else if (isset($mybb->input["submittransaction"], $mybb->input["transactionamount"])) {
            if ($isBanker) {
                $transAmount = getSafeNumber($db, $mybb->input["transactionamount"]);
                $transTitle = getSafeAlpNum($db, $mybb->input["transactiontitle"]);
                $transDescription = getSafeAlpNum($db, $mybb->input["transactiondescription"]);
                if (strlen($transDescription) == 0) $transDescription = null;

                // Adds a transaction via banker
                $currbankbalance = doTransaction($db, $transAmount, $transTitle, $transDescription, $currentUserId, $myuid, $currname, "Banker Transaction");
            } else {
                echo "You're not a banker. shoo.";
                exit;
            }
        }

        // If the user submitted a transaction himself.
        else if (isset($mybb->input["submitpurchase"], $mybb->input["purchaseamount"])) {
            if ($isMyAccount) {
                $transAmount = -abs(getSafeNumber($db, $mybb->input["purchaseamount"]));
                $transTitle = getSafeAlpNum($db, $mybb->input["purchasetitle"]);
                $transDescription = getSafeAlpNum($db, $mybb->input["purchasedescription"]);
                if (strlen($transDescription) == 0) $transDescription = null;

                if ($transAmount != 0 && strlen($transTitle)) {
                    $currbankbalance = addBankTransaction($db, $currentUserId, $transAmount, $transTitle, $transDescription, $currentUserId);
                    displaySuccessTransaction($currname, $transAmount, $transTitle, $transDescription, "User Purchase");
                } else
                    displayErrorTransaction();
            } else {
                echo "You're not this user. shoo";
                exit;
            }
        }

        // If the user submitted a training +5
        else if (isset($mybb->input["submittraining"], $mybb->input["trainingvalue"]) && is_numeric($mybb->input["trainingvalue"])) {
            if ($isMyAccount) {
                $trainvalue = getSafeNumber($db, $mybb->input["trainingvalue"]);
                switch ($trainvalue) {
                    case 5:
                        $transAmount = -1000000;
                        break;
                    case 3:
                        $transAmount = -500000;
                        break;
                    case 2:
                        $transAmount = -250000;
                        break;
                    case 1:
                        $transAmount = -100000;
                        break;
                }

                if ($transAmount < -10) {
                    $transTitle = "Training +$trainvalue";
                    $transDescription = 'Purchased training for player.';
                    $currbankbalance = addBankTransaction($db, $currentUserId, $transAmount, $transTitle, $transDescription, $currentUserId);
                    displaySuccessTransaction($currname, $transAmount, $transTitle, $transDescription, "User Training");
                } else {
                    echo "there was an error with the buttons somehow";
                    exit;
                }
            } else {
                echo "You're not this user. shoo";
                exit;
            }
        }

        // If user submitted a transfer request for another user.
        else if (isset($mybb->input["submitrequest"], $mybb->input["requestamount"])) {
            if (!$isMyAccount) {
                $transAmount = abs(getSafeNumber($db, $mybb->input["requestamount"]));
                $transTitle = getSafeAlpNum($db, $mybb->input["requesttitle"]);
                $transDescription = getSafeAlpNum($db, $mybb->input["requestdescription"]);
                if (strlen($transDescription) == 0) $transDescription = null;

                if ($transAmount != 0 && strlen($transTitle)) {
                    addBankTransferRequest($db, $myuid, $currentUserId, $transAmount, $transTitle, $transDescription);
                    displaySuccessTransaction($currname, $transAmount, $transTitle, $transDescription, "Transfer Request");
                } else {
                    displayErrorTransaction();
                }
            } else {
                echo "you can't transfer money to yourself. dummy.";
                exit;
            }
        }
    }
    ?>


    <!-- User Information -->
    <div class="bojoSection navigation">
        <h2>{$currname}</h2>
        <table>
            <tr>
                <th>Balance</th>
                <td>
                    <?php 
                    if ($currbankbalance < 0) {
                        $balanceclass = "red negative";
                        $negativesign = '-';
                    } else {
                        $balanceclass = "positive";
                        $negativesign = '';
                    }
                    $bankbalancedisplay = number_format(abs($currbankbalance), 0);
                    echo "<span class=\"$balanceclass\">$negativesign" . "$" . $bankbalancedisplay . "</span>";
                    ?>
                </td>
            </tr>
            <tr>
                <th>SHL Team</th>
                <td><a href="http://simulationhockey.com/bankteam.php?id=<?php echo $curruser['teamid']; ?>"><?php echo $teamName; ?></a></td>
            </tr>
        </table>

        <a href="http://simulationhockey.com/bankexportaccount.php?uid=<?php echo $currentUserId; ?>">Export Data</a>

        <hr />

        <h4>Bank Transactions</h4>
        <table style="margin-bottom: 20px;">
            <tr>
                <th>Title</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Made By</th>
                <if $isBanker then>
                    <th></th>
                </if>
            </tr>

            <?php 
            // Bank Transactions
            $transactionQuery =
                "SELECT bt.*, creator.username AS 'creator'
                FROM mybb_banktransactions bt
                LEFT JOIN mybb_users creator ON bt.createdbyuserid=creator.uid
                WHERE bt.uid=$currentUserId
                ORDER BY bt.date DESC
                LIMIT 50";

            $bankRows = $db->query($transactionQuery);
            while ($row = $db->fetch_array($bankRows)) {
                $date = new DateTime($row['date']);
                $amountClass = ($row['amount'] < 0) ? 'negative' : 'positive';
                $transactionLink = '<a class="' . $amountClass . '" href="' . getBankTransactionLink($row['id']) . '">';
                $creatorLink = '<a class="' . $amountClass . '" href="' . getBankAccountLink($row['createdbyuserid']) . '">';
                $negativeSign = ($row['amount'] < 0) ? '-' : '';
                $dateformat = $date->format('m/d/y');
                $numberformat = number_format(abs($row['amount']), 0);

                echo '<tr>';
                echo "<td>$transactionLink" . $row['title'] . '</a></td>';
                echo "<td>$transactionLink<span class=\"$amountClass\">" . $negativeSign . '$' . $numberformat . "</span></a></td>";
                echo "<td>$dateformat</td>";
                echo "<td>$creatorLink" . $row['creator'] . "</a></td>";

                if ($isBanker) {
                    echo '<form method="post"><td><input type="submit" name="undotransaction" value="Undo" /></td>';
                    echo '<input type="hidden" name="undoid" value="' . $row['id'] . '" />';
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
                WHERE bt.uid=$currentUserId
                ORDER BY groups.requestdate DESC
                LIMIT 50";

        $bankRows = $db->query($transactionQuery);
        $bankRowCount = mysqli_num_rows($bankRows);

        if ($bankRowCount <= 0) {
            echo '<p>No active requests for this user</p>';
        } else {
            echo
                '<table>
                <tr>
                <th>Title</th>
                <th>Amount</th>
                <th>Date Requested</th>
                <th>Description</th>
                </tr>';

            while ($row = $db->fetch_array($bankRows)) {
                $requestdate = new DateTime($row['requestdate']);
                $requestdate = $requestdate->format('m/d/y');

                $amountClass = ($row['amount'] < 0) ? 'negative' : 'positive';
                $grouplink = '<a href="http://simulationhockey.com/bankrequest.php?id=' . $row['gid'] . '">';
                $negativeSign = ($row['amount'] < 0) ? '-' : '';
                $description = $row['description'];
                $title = $row['title'];
                $amountformat = number_format(abs($row['amount']), 0);

                echo '<tr>';
                echo "<td>$grouplink" . $title . "</a></td>";
                echo "<td>$grouplink<span class=\"$amountClass\">$negativeSign" . '$' . "$amountformat</span></a></td>";
                echo "<td>$requestdate</td>";
                echo "<td>$description</td>";
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
                WHERE (bt.userrequestid=$currentUserId OR bt.usertargetid=$currentUserId) AND bankerapproverid IS NULL
                ORDER BY bt.requestdate DESC
                LIMIT 50";

        $bankRows = $db->query($transactionQuery);
        $bankRowCount = mysqli_num_rows($bankRows);

        if ($bankRowCount <= 0) {
            echo '<p>No active transfer requests for this user</p>';
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
                $requestdate = new DateTime($row['requestdate']);
                $requestdate = $requestdate->format('m/d/y');

                if ($row['approvaldate'] === null) {
                    $approvedate = '';
                } else {
                    $approvedate = new DateTime($row['approvaldate']);
                    $approvedate = $approvedate->format('m/d/y');
                }

                $urequesterLink = '<a href="' . getBankAccountLink($row['userrequestid']) . '">';
                $utargetLink = '<a href="' . getBankAccountLink($row['usertargetid']) . '">';
                $amountClass = ($row['amount'] < 0) ? 'negative' : 'positive';
                $amountformat = number_format(abs($row['amount']), 0);
                $title = $row['title'];

                echo "<tr>";
                echo "<td>$title</td>";
                echo "<td>$urequesterLink" . $row['urequester'] . "</a></td>";
                echo "<td>$utargetLink" . $row['utarget'] . "</a></td>";
                echo '<td class="' . $amountClass . '">$' . $amountformat . "</td>";
                echo "<td>$requestdate</td>";

                if ($isBanker) {
                    if ($row['bankerapproverid'] == null) {
                        echo '<form method="post"><td><input type="submit" name="approvetransfer" value="Accept" /></td>';
                        echo '<input type="hidden" name="approveid" value="' . $row['id'] . '" />';
                        echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" /></form>';

                        echo '<form method="post"><td><input type="submit" name="declinetransfer" value="Decline" /></td>';
                        echo '<input type="hidden" name="declineid" value="' . $row['id'] . '" />';
                        echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" /></form>';
                    } else {
                        echo '<td></td><td></td>';
                    }
                }
                echo '<td>' . $row['description'] . "</a></td>";
                echo "</tr>";
            }
            echo '</table>';
        }

        ?>
    </div>

    <!-- New Purchase: Only available to the actual user -->
    <if ($currentUserId==$myuid) then>
        <div class="bojoSection navigation">
            <h2>New Purchase <span class="expandclick" onclick="toggleArea(this, 'purchasearea')">(expand)</span></h2>
            <div id="purchasearea" class="hideme">
                <h4>Weekly Training</h4>
                <table>
                    <tr>
                        <th>Points</th>
                        <th>Cost</th>
                    </tr>
                    <if $currteamid==null then>
                        <tr>
                            <td>+3</td>
                            <td>$500,000</td>
                            <form onsubmit="return areYouSure();" method="post">
                                <td><input type="submit" name="submittraining" value="Purchase Training" /></td>
                                <input type="hidden" name="trainingvalue" value="3" />
                                <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code ?>" />
                            </form>
                        </tr>
                        <tr>
                            <td>+2</td>
                            <td>$250,000</td>
                            <form onsubmit="return areYouSure();" method="post">
                                <td><input type="submit" name="submittraining" value="Purchase Training" /></td>
                                <input type="hidden" name="trainingvalue" value="2" />
                                <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code ?>" />
                            </form>
                        </tr>
                        <tr>
                            <td>+1</td>
                            <td>$100,000</td>
                            <form onsubmit="return areYouSure();" method="post">
                                <td><input type="submit" name="submittraining" value="Purchase Training" /></td>
                                <input type="hidden" name="trainingvalue" value="1" />
                                <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code ?>" />
                            </form>
                        </tr>
                        <else>
                            <tr>
                                <td>+5</td>
                                <td>$1,000,000</td>
                                <form onsubmit="return areYouSure();" method="post">
                                    <td><input type="submit" name="submittraining" value="Purchase Training" /></td>
                                    <input type="hidden" name="trainingvalue" value="5" />
                                    <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code ?>" />
                                </form>
                            </tr>
                            <tr>
                                <td>+3</td>
                                <td>$500,000</td>
                                <form onsubmit="return areYouSure();" method="post">
                                    <td><input type="submit" name="submittraining" value="Purchase Training" /></td>
                                    <input type="hidden" name="trainingvalue" value="3" />
                                    <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code ?>" />
                                </form>
                            </tr>
                            <tr>
                                <td>+1</td>
                                <td>$100,000</td>
                                <form onsubmit="return areYouSure();" method="post">
                                    <td><input type="submit" name="submittraining" value="Purchase Training" /></td>
                                    <input type="hidden" name="trainingvalue" value="1" />
                                    <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code ?>" />
                                </form>
                            </tr>
                    </if>
                </table>
                <p><em>+5 Training only available when you've been drafted to an SHL team</em></p>
                <form onsubmit="return areYouSure();" method="post">
                    <h4 style="margin-top: 10px;">Other Purchases</h4>
                    <table>
                        <tr>
                            <th>Amount</th>
                            <td><input type="number" name="purchaseamount" placeholder="Enter amount..." /></td>
                        </tr>
                        <tr>
                            <th>Title</th>
                            <td><input type="text" name="purchasetitle" placeholder="Enter title..." /></td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td><input type="text" name="purchasedescription" placeholder="Enter description..." /></td>
                        </tr>
                        <tr>
                            <th></th>
                            <td><input type="submit" name="submitpurchase" value="Make Purchase" /></td>
                        </tr>
                        <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code ?>" />
                    </table>
                </form>
                <p style="margin-bottom: 0px"><em>Write a postive number for a purchase transaction. No approvals necessary.</em></p>
            </div>
        </div>
    </if>

    <!-- New Transfer Request: Only available when on another user's page -->
    <if ($currentUserId !==$myuid) then>
        <div class="bojoSection navigation">
            <h2>New Transfer Request <span class="expandclick" onclick="toggleArea(this, 'transferarea')">(expand)</span></h2>
            <div id="transferarea" class="hideme">
                <form onsubmit="return areYouSure();" method="post">
                    <table>
                        <tr>
                            <th>Amount</th>
                            <td><input type="number" name="requestamount" placeholder="Enter amount..." /></td>
                        </tr>
                        <tr>
                            <th>Title</th>
                            <td><input type="text" name="requesttitle" placeholder="Enter title..." /></td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td><input type="text" name="requestdescription" placeholder="Enter description..." /></td>
                        </tr>
                        <tr>
                            <th></th>
                            <td><input type="submit" name="submitrequest" value="Request" /></td>
                        </tr>
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
                <form onsubmit="return areYouSure();" method="post">
                    <table>
                        <tr>
                            <th>Amount</th>
                            <td><input type="number" name="transactionamount" placeholder="Enter amount..." /></td>
                        </tr>
                        <tr>
                            <th>Title</th>
                            <td><input type="text" name="transactiontitle" placeholder="Enter title..." /></td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td><input type="text" name="transactiondescription" placeholder="Enter description..." /></td>
                        </tr>
                        <tr>
                            <th></th>
                            <td><input type="submit" name="submittransaction" value="Add Transaction" /></td>
                        </tr>
                        <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code; ?>" />
                    </table>
                    <p style="margin-bottom: 0px"><em>Adds a transaction. If removing money, make sure to add the negative sign.</em></p>
                </form>
            </div>
        </div>
    </if>

    <script>
        function toggleArea(spanlink, idToHide) {
            if (document.getElementById(idToHide).className != 'hideme') {
                document.getElementById(idToHide).className = 'hideme';
                spanlink.innerHTML = "(expand)";
            } else {
                document.getElementById(idToHide).className = '';
                spanlink.innerHTML = "(hide)";
            }
        }

        function areYouSure() {
            return confirm("Are you sure you want to make this transaction?");
        }
    </script>

    <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
    </body>

</html> 