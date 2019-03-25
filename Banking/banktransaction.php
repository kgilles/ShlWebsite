<html>

<head>
    <title>SHL Hockey -> Bank Transaction</title>
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

        if ($id > 0) 
        {
            $transactionQuery = 
            "SELECT bt.*, usr.username AS 'username', creator.username AS 'owner', banker.username AS 'bankername', gr.groupname AS 'groupname'
                FROM mybb_banktransactions bt
                LEFT JOIN mybb_users usr ON bt.uid=usr.uid
                LEFT JOIN mybb_users creator ON bt.createdbyuserid=creator.uid
                LEFT JOIN mybb_users banker ON bt.bankerapproverid=banker.uid
                LEFT JOIN mybb_banktransactiongroups gr ON bt.groupid=gr.id
                WHERE bt.id=$id
                LIMIT 1";

            $query = $db->query($transactionQuery);
            $transaction = $db->fetch_array($query);

            if ($transaction['uid'] > 0)
            {
                echo '<div class="bojoSection navigation">';
                echo '<h4>Bank Transaction</h4>';
                echo '<table>';
                echo '<tr><th>Title</th><td>' . $transaction['title'] . '</td></tr>';
                echo '<tr><th>Description</th><td>' . $transaction['description'] . '</td></tr>';

                if ($transaction['amount'] < 0) {
                    echo "<tr><th>Amount</th><td class='negative'>-$" . number_format(abs($transaction['amount']), 0) . "</td></tr>";
                }
                else {
                    echo "<tr><th>Amount</th><td class='positive'>$" . number_format($transaction['amount'], 0) . "</td></tr>";
                }

                $ba1Link = getBankAccountLink($transaction['uid']);
                $ba2Link = getBankAccountLink($transaction['createdbyuserid']);
                $ba3Link = getBankAccountLink($transaction['bankerapproverid']);
                $requestLink = getBankRequestLink($transaction['groupid']);

                echo '<tr><th>User</th><td><a href="' . $ba1Link . '">' . $transaction['username'] . '</a></td></tr>';
                echo '<tr><th>Made by</th><td><a href="' . $ba2Link . '">' . $transaction['owner'] . '</a></td></tr>';

                $date = new DateTime($transaction['date']);
                echo '<tr><th>Date</th><td>' . $date->format('m/d/y') . '</td></tr>';
                echo '<tr><th>Time</th><td>' . $date->format('H:i:s') . '</td></tr>';

                echo '<tr><th>Group*</th><td><a href="' . $requestLink . '">' . $transaction['groupname'] . '</a></td></tr>';
                echo '<tr><th>Banker**</th><td><a href="' . $ba3Link . '">' . $transaction['bankername'] . '</a></td></tr>';

                echo '</table>';
                echo '<p>* If part of a mass group update.<br />** Only neccessary for transactions that require approval.</p>';
                echo "</div>";
            }
            else
            {
                echo '<div class="bojoSection"';
                echo '<p>No transaction found</p>';
                echo '</div>';
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