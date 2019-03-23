<html>

<head>
    <title>SHL Hockey -> Bank Transaction</title>
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
            color black;
        }

        .errorSection {
            background: #f0cfcf;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid black;
            color black;
        }

        .successSection th,
        .successSection td {
            padding: 0px 10px;
        }

        .successSection th:nth-child(1),
        .successSection td:nth-child(1) {
            text-align: left;
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

                $puLink = "http://simulationhockey.com/playerupdater.php?uid=";
                $groupLink = "http://simulationhockey.com/bankgrouptransaction.php?id=";

                echo '<tr><th>User</th><td><a href="' . $puLink . $transaction['uid'] . '">' . $transaction['username'] . '</a></td></tr>';
                echo '<tr><th>Made by</th><td><a href="' . $puLink . $transaction['createdbyuserid'] . '">' . $transaction['owner'] . '</a></td></tr>';

                $date = new DateTime($transaction['date']);
                echo '<tr><th>Date</th><td>' . $date->format('m/d/y') . '</td></tr>';
                echo '<tr><th>Time</th><td>' . $date->format('H:i:s') . '</td></tr>';

                echo '<tr><th>Group*</th><td><a href="' . $puLink . $transaction['groupid'] . '">' . $transaction['groupname'] . '</a></td></tr>';
                echo '<tr><th>Banker**</th><td><a href="' . $groupLink . $transaction['groupid'] . '">' . $transaction['bankername'] . '</a></td></tr>';

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