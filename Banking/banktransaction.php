<html>

<head>
    <title>SHL Hockey -> Bank Transaction</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <?php 

    include 'bankerOps.php';

    if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
        $currentTransactionId = intval($_GET["id"]);
    } else {
        echo 'request not found';
        exit;
    }

    if ($currentTransactionId > 0) {
        $querytext =
            "SELECT bt.*, usr.username AS 'username', creator.username AS 'owner', banker.username AS 'bankername', gr.groupname AS 'groupname'
                FROM mybb_banktransactions bt
                LEFT JOIN mybb_users usr ON bt.uid=usr.uid
                LEFT JOIN mybb_users creator ON bt.createdbyuserid=creator.uid
                LEFT JOIN mybb_users banker ON bt.bankerapproverid=banker.uid
                LEFT JOIN mybb_banktransactiongroups gr ON bt.groupid=gr.id
                WHERE bt.id=$currentTransactionId LIMIT 1";

        $xQuery = $db->query($querytext);
        if ($xRow = $db->fetch_array($xQuery)) {
            if ($xRow['uid'] > 0) {
                echo '<div class="bojoSection navigation">
                        <h2>' . $xRow['title'] . '</h2>
                        <p>' . $xRow['description'] . '</p>
                        <table>';

                $transUserId = $xRow['uid'];
                $transUserName = $xRow['username'];

                if ($xRow['amount'] < 0)
                    echo "<tr><th>Amount</th><td class='negative'>-$" . number_format(abs($xRow['amount']), 0) . "</td></tr>";
                else
                    echo "<tr><th>Amount</th><td class='positive'>$" . number_format($xRow['amount'], 0) . "</td></tr>";

                $ba1Link = getBankAccountLink($xRow['uid']);
                $ba2Link = getBankAccountLink($xRow['createdbyuserid']);
                $ba3Link = getBankAccountLink($xRow['bankerapproverid']);
                $requestLink = getBankRequestLink($xRow['groupid']);
                $date = new DateTime($xRow['date']);
                $date->setTimeZone(new DateTimeZone('America/New_York'));

                echo '<tr><th>User</th><td><a href="' . $ba1Link . '">' . $xRow['username'] . '</a></td></tr>
                      <tr><th>Made by</th><td><a href="' . $ba2Link . '">' . $xRow['owner'] . '</a></td></tr>
                      <tr><th>Date</th><td>' . $date->format('m/d/y') . '</td></tr>
                      <tr><th>Time</th><td>' . $date->format('g:i A') . ' ET</td></tr>';
                if ($xRow['groupid'] != null) {
                    echo '<tr><th>Group</th><td><a href="' . $requestLink . '">' . $xRow['groupname'] . '</a></td></tr>';
                }
                if ($xRow['bankerapproverid'] != null) {
                    echo '<tr><th>Banker</th><td><a href="' . $ba3Link . '">' . $xRow['bankername'] . '</a></td></tr>';
                }
                echo '</table></div>';
            } else {
                echo '<div class="bojoSection"><p>No transaction found</p></div>';
            }
        } else {
            echo '<div class="bojoSection"><p>No transaction found</p></div>';
        }
    } else {
        echo '<div class="bojoSection"><p>No transaction found</p></div>';
    }
    ?>

    <div>
    </div>

    <div class="bojoSection navigation">
        <h4>Link to this page in your updates when claiming training, equipment, or any other bank related TPE claim.</h3>
        <h3>Bank Links</h3>

        <ul>
            <li><a href="http://simulationhockey.com/bank.php">Main Page</a></li>
            <?php 
            if ($currentTransactionId > 0) {
                echo '<li><a href="http://simulationhockey.com/bankaccount.php?uid=' . $transUserId . '">' . $transUserName . '\'s Account</a></li>';
            }
            ?>
            <li><a href="http://simulationhockey.com/bankaccount.php">Your Account</a></li>
        </ul>
    </div>

    <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html> 