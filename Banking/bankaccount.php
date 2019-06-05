<html>

<head>
    <title>SHL Hockey -> Bank Account</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php include 'bankerOps.php';

    // Testing out some time related stuff. Might not use though.
    // $xQuery = $db->write_query("SELECT now() as serverTime");
    // if ($xRow = $db->fetch_array($xQuery)) {
    //     $serverDate = new DateTime($xRow['serverTime']);
    //     $startDate = new DateTime('last friday');
    //     $startDate->setTime(4, 0);
    //     $endDate = new DateTime('last friday');
    //     $endDate->setTime(12, 0);

    //     $startDate->setTimeZone(new DateTimeZone('America/New_York'));
    //     $endDate->setTimeZone(new DateTimeZone('America/New_York'));
    //     $serverDate->setTimeZone(new DateTimeZone('America/New_York'));

    //     echo '<table>';
    //     echo '<tr>';
    //     echo '<th>Start of week</th> ';
    //     echo '<td>' . $startDate->format('m/d/y g:i A') . ' ET</td>';
    //     echo '</tr>';
    //     echo '<tr>';
    //     echo '<th>End of week deadline</th> ';
    //     echo '<td>' . $endDate->format('m/d/y g:i A') . ' ET</td>';
    //     echo '</tr>';
    //     echo '<tr>';
    //     echo '<th>Current server time</th> ';
    //     echo '<td>' . $serverDate->format('m/d/y g:i A') . ' ET</td>';
    //     echo '</tr>';
    //     echo '</table>';
    // }

    $myuid = getUserId($mybb);

    // if not logged in, go away
    if ($myuid <= 0) {
        echo 'You are not logged in';
        exit;
    }

    $isBanker = checkIfBanker($mybb);
    $flippedClaim = false;

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

    function displayNotEnoughtMoney()
    {
        echo '<div class="errorSection">';
        echo "<h4>Error</h4>";
        echo '<p>Not enough money for transaction. Your balance cannot go below $1,500,000 without a banker.</p>';
        echo '</div>';
    }

    $isMyAccount = ($myuid == $currentUserId);
    $currbankbalance = $curruser["bankbalance"];
    $currname = $curruser["username"];
    $currteamid = $curruser["teamid"];

    if ($currteamid !== null) {
        $xQuery = $db->simple_select("teams", "*", "id=$currteamid", array("limit" => 1));
        if ($xRow = $db->fetch_array($xQuery)) {
            $teamName = $xRow['name'];
            $teamLeague = $xRow['league'];
        } else {
            $teamName = "Unassigned";
            $teamLeague = "Unassigned";
        }
    } else {
        $teamName = "Unassigned";
        $teamLeague = "Unassigned";
    }

    // If a submit button was pressed
    if (isset($mybb->input["bojopostkey"])) {
        verify_post_check($mybb->input["bojopostkey"]);

        // If banker undid a transaction.
        if (isset($mybb->input["undotransaction"], $mybb->input["undoid"]) && is_numeric($mybb->input["undoid"])) {
            logAction($db, "ACTION", "$myuid attempts to undo a transaction");
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
            logAction($db, "ACTION", "$myuid attempts to approve a transfer");
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
            logAction($db, "ACTION", "$myuid attempts to decline a transfer");
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
            logAction($db, "ACTION", "$myuid attempts to submit a transaction as a banker");
            if ($isBanker) {
                $transAmount = getSafeNumber($db, $mybb->input["transactionamount"]);
                $transTitle = getSafeAlpNum($db, $mybb->input["transactiontitle"]);
                $transDescription = getSafeString($db, $mybb->input["transactiondescription"]);
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
            logAction($db, "ACTION", "$myuid attempts to make a transaction");
            if ($isMyAccount) {
                $transAmount = -abs(getSafeNumber($db, $mybb->input["purchaseamount"]));
                $transTitle = getSafeAlpNum($db, $mybb->input["purchasetitle"]);
                $transDescription = getSafeString($db, $mybb->input["purchasedescription"]);
                if (strlen($transDescription) == 0) $transDescription = null;

                if ($transAmount != 0 && strlen($transTitle)) {
                    if ($currbankbalance + $transAmount >= -1500000) {
                        $currbankbalance = addBankTransaction($db, $currentUserId, $transAmount, $transTitle, $transDescription, $currentUserId);
                        displaySuccessTransaction($currname, $transAmount, $transTitle, $transDescription, "User Purchase");
                    } else {
                        displayNotEnoughtMoney();
                    }
                } else
                    displayErrorTransaction();
            } else {
                echo "You're not this user. shoo";
                exit;
            }
        }

        // If the user submitted a training
        else if (isset($mybb->input["submittraining"], $mybb->input["trainingvalue"]) && is_numeric($mybb->input["trainingvalue"])) {
            logAction($db, "ACTION", "$myuid attempts to do a training");
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
                    if ($currbankbalance + $transAmount >= -1500000) {
                        $currbankbalance = addBankTransaction($db, $currentUserId, $transAmount, $transTitle, $transDescription, $currentUserId);
                        displaySuccessTransaction($currname, $transAmount, $transTitle, $transDescription, "User Training");
                    } else {
                        displayNotEnoughtMoney();
                    }
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
            logAction($db, "ACTION", "$myuid attempts to submit a transfer request");
            if (!$isMyAccount) {
                $transAmount = abs(getSafeNumber($db, $mybb->input["requestamount"]));
                $transTitle = getSafeAlpNum($db, $mybb->input["requesttitle"]);
                $transDescription = getSafeString($db, $mybb->input["requestdescription"]);
                if (strlen($transDescription) == 0) $transDescription = null;
                alert($transDescription);
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

        // If the banker flipped the free500k eligibility.
        else if (isset($mybb->input["flipClaim"])) {
            logAction($db, "ACTION", "$myuid as a banker attempts to flip the status of $currentUserId free500 eligibility");
            $flippedClaim = true;
            $claimQuery = $db->simple_select("users", "claimedFreeTraining", "uid=$currentUserId", array("limit" => 1));
            if ($xRow = $db->fetch_array($claimQuery)) { $claimValue = $xRow['claimedFreeTraining']; }
            else { $claimValue = 0; }
            if ($claimValue == 1) $claimValue = 0;
            else $claimValue = 1;
            $db->update_query("users", array("claimedFreeTraining" => $claimValue), "uid=$currentUserId");
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
            <tr>
                <td><a href="http://simulationhockey.com/bankexportaccount.php?uid=<?php echo $currentUserId; ?>">Export Data</a></td>
            </tr>
        </table>

        <br />
        <a href="http://simulationhockey.com/bank.php">Link to main Bank page</a>

        <hr />

        <h4>Bank Transactions</h4>
        <table style="margin-bottom: 20px;">
            <tr>
                <th>Title</th>
                <th>Amount</th>
                <th class="hideSmall">Date</th>
                <th class="hideSmall">Made By</th>
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
                echo "<td class='hideSmall'>$dateformat</td>";
                echo "<td class='hideSmall'>$creatorLink" . $row['creator'] . "</a></td>";

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
        // Transaction Requests
        $transactionQuery =
            "SELECT bt.*, groups.id as 'gid', groups.groupname, groups.requestdate
                FROM mybb_banktransactionrequests bt
                JOIN mybb_banktransactiongroups groups ON bt.groupid=groups.id && groups.isapproved IS NULL
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
    </div>

    <!-- New Purchase: Only available to the actual user -->
    <if ($currentUserId==$myuid) then>
        <div class="bojoSection navigation">
            <h2>New Purchase</h2>
            <div id="purchasearea">
                <h4>Weekly Training</h4>
                <table>
                    <tr>
                        <th>Points</th>
                        <th>Cost</th>
                    </tr>
                    <if $teamLeague != "SHL" then>
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
                <hr />
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
                <hr />
                <?php
                if ($flippedClaim == false) {
                    $claimQuery = $db->simple_select("users", "claimedFreeTraining", "uid=$currentUserId", array("limit" => 1));
                    if ($xRow = $db->fetch_array($claimQuery)) { $claimValue = $xRow['claimedFreeTraining']; }
                }
                ?>
                <h4>Set Free 500k claim eligibility</h4>
                <form method="post">
                    <p>Can this user claim the free 500k? <b><?php echo $claimValue == 1 ? "No" : "Yes"; ?></b></p>
                    <td><input type="submit" name="flipClaim" value="Flip Eligibility" /></td>
                    <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code; ?>" />
                    <p><i>Note: Don't refresh after clicking. A popup should warn you that it might resubmit, and it would go back to what is was before!</i></p>
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