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
            "SELECT bt.*, usr.username AS 'username', creator.username AS 'owner'
                FROM mybb_banktransactions bt
                JOIN mybb_users usr ON bt.uid=usr.uid
                JOIN mybb_users creator ON bt.createdbyuserid=creator.uid
                WHERE id=$id
                LIMIT 1";

            $query = $db->query($transactionQuery);
            $transaction = $db->fetch_array($query);

            if ($transaction['uid'] > 0)
            {
                echo '<div class="bojoSection navigation">';
                echo '<h4>Bank Transaction</h4>';
                echo '<table>';
                echo '<tr><th>Title</th><td>' . $transaction['title'] . '</td></tr>';

                if ($transaction['amount'] < 0) {
                    echo "<tr><th>Amount</th><td class='negative'>-$" . number_format(abs($transaction['amount']), 0) . "</td></tr>";
                }
                else {
                    echo "<tr><th>Amount</th><td class='positive'>$" . number_format($transaction['amount'], 0) . "</td></tr>";
                }

                echo '<tr><th>User</th><td>' . $transaction['username'] . '</td></tr>';
                echo '<tr><th>Made by</th><td>' . $transaction['owner'] . '</td></tr>';

                $date = new DateTime($transaction['date']);
                echo '<tr><th>Date</th><td>' . $date->format('m/d/y') . '</td></tr>';

                echo '</table>';
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