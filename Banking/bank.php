<html>

<head>
    <title>SHL Hockey -> Bank</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php include 'bankerOps.php';

    $myuid = getUserId($mybb);

    $page = 1;
    if(isset($_GET["page"])) {
        if(is_numeric($_GET["page"])) {
            $page = getSafeNumber($db, $_GET["page"]);
        }
        else
        {
            echo 'incorrect parameter';
            exit;
        }
    }
    $rowsPerPage = 25;
    $offset = ($page - 1) * $rowsPerPage;   

    // if not logged in, go away
    if ($myuid <= 0) {
        echo 'You are not logged in';
        exit;
    }

    $isBanker = checkIfBanker($mybb);

    $hideFree500 = $mybb->user['claimedFreeTraining'];

    // If a submit button was pressed
    if (isset($mybb->input["bojopostkey"])) {
        verify_post_check($mybb->input["bojopostkey"]);

        // If banker approved a transfer.
        if (isset($mybb->input["approvetransfer"], $mybb->input["approveid"]) && is_numeric($mybb->input["approveid"])) {
            logAction($db, "ACTION", "$myuid attempts to approve a transfer request");
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
            logAction($db, "ACTION", "$myuid attempts to decline a transfer request");
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

        // If the user claims a free 500k.
        else if (isset($mybb->input["free500"])) {
            logAction($db, "ACTION", "$myuid attempts to claim their free $500,000");
            $transAmount = 500000;
            $transTitle = "Free 500k";
            $transDescription = "Free transaction for newcomers";
            $currbankbalance = addBankTransaction($db, $myuid, $transAmount, $transTitle, $transDescription, $myuid);
            $db->update_query("users", array("claimedFreeTraining" => 1), "uid=$myuid");
            header('Location: http://simulationhockey.com/bankaccount.php');
        }
    }
    ?>

    <div class="bojoSection navigation">
        <h2>Bank Portal</h2>
        <p>
            <ul>
                <li><a href="http://simulationhockey.com/bankaccount.php">Your Bank Account</a></li>
                <li><a href="http://simulationhockey.com/banksubmitrequest.php">Submit Request</a></li>
                <li><a href="http://simulationhockey.com/showthread.php?tid=19878">Report an Issue</a></li>
                <li><a href="http://simulationhockey.com/bankexportbalances.php">Download Bank Balances</a></li>
                <?php if ($isBanker) {
                    echo '<li><a href="http://simulationhockey.com/teamaddusers.php">Assign Users to Team</a></li>';
                } ?>
            </ul>
        </p>
    </div>

    <if !$hideFree500 then>
    <div class="bojoSection navigation">
        <h2>Free $500k!</h2>
        <p>Welcome! You are eligible for a free $500,000 for joining the site. Click the button below and it will be added to your account.</p>
        <p>We hope you enjoy the site!</p>
        <form method="post">
            <td><input type="submit" name="free500" value="Claim $500k" /></td>
            <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code ?>" />
        </form>
    </div>
    </if>

    <?php
    $teamRows = $db->simple_select("teams", "*", "id > 1", array(
        "order_by" => 'name',
        "order_dir" => 'ASC'
    ));

    while ($teamrow = $db->fetch_array($teamRows)) {
        if ($teamrow['league'] == "SHL") {
            $teamsShl[] = [ "id" => $teamrow['id'], "name" => $teamrow['name']];
        }
        // else {
        //     $teamsSmjhl[] = [ "id" => $teamrow['id'], "name" => $teamrow['name']];
        // }
    }
    ?>
    <div class="bojoSection navigation">
        <h2>User Accounts by Team</h2>
        <p>
            <!-- <h4>SHL</h4> -->
            <ul>
                <?php
                foreach ($teamsShl as $item) {
                    echo '<li><a href="http://simulationhockey.com/bankteam.php?id=' . $item["id"] . '">' . $item["name"] . '</a></li>';
                }
                ?>
            </ul>
            <?php echo '<a href="http://simulationhockey.com/bankteam.php">Non-SHL Players</a>'; ?>
            <!-- TODO: Add unassigned players -->
            <!-- <h4>SMJHL</h4>
            <ul>
                <?php
                // foreach ($teamsSmjhl as $item)
                //     echo '<li><a href="http://simulationhockey.com/bankteam.php?id=' . $item["id"] . '">' . $item["name"] . '</a></li>';
                ?>
            </ul> -->
        </p>
    </div>

    <div class="bojoSection navigation">
        <h2>Requests</h2>
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
            <th class="hideSmall">Date Requested</th>
            </tr>';

            while ($row = $db->fetch_array($bankRows)) {
                $requestdate = new DateTime($row['datrequestdatee']);
                $requestdate = $requestdate->format('m/d/y');

                $ugroupLink =  getBankRequestLink($row['id']);
                $urequesterLink = getBankAccountLink($row['creatorid']);

                echo '<tr>';
                echo '<td><a href="' . $ugroupLink . '">' . $row['groupname'] . '</a></td>';
                echo '<td><a href="' . $urequesterLink . '">' . $row['urequester'] . '</a></td>';
                echo '<td class="hideSmall">' . $requestdate . "</td>";
                echo "</tr>";
            }
            echo '</table>';
        }

        ?>

        <hr />

        <h3 id="history">Review History</h3>
        <p>
        <?php
        if ($page > 1) {
            echo '<a href="bank.php?page=' . ($page - 1) . '#history">PREV</a>';
        }
        ?>
        <span> - Page <?php echo $page; ?> - </span>
        <?php echo '<a href="bank.php?page=' . ($page + 1) . '#history">NEXT</a>'; ?>
        </p>
        <?php
        // Transfer Requests
        $transactionQuery =
            "SELECT bt.*, urequester.username AS 'urequester', ubanker.username AS 'bankername'
            FROM mybb_banktransactiongroups bt
            LEFT JOIN mybb_users urequester ON bt.creatorid=urequester.uid
            LEFT JOIN mybb_users ubanker ON bt.bankerid=ubanker.uid
            WHERE bt.isapproved IS NOT NULL
            ORDER BY bt.decisiondate DESC
            LIMIT " . $rowsPerPage . " OFFSET " . $offset;

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
            <th class="hideSmall">Date Requested</th>
            <th class="hideSmall">Approved?</th>
            <th class="hideSmall">Banker</th>
            <th class="hideSmall">Date Decided</th>
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
                echo '<td class="hideSmall">' . $requestdate . "</td>";
                echo '<td class="hideSmall">' . $requestApproval . "</td>";
                echo '<td class="hideSmall">' . $row['bankername'] . "</td>";
                echo '<td class="hideSmall">' . $decisiondate . "</td>";
                echo "</tr>";
            }
            echo '</table>';
        }

        ?>
        <p><i>Ordered by Date Decision</i></p>
    </div>

    <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html>