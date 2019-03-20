<?php
function getSafeInput($db, $mybb, $safeInputItem) {
    $safeInput = $mybb->input[$safeInputItem];
    $safeEscape = $db->escape_string($safeInput);
    return $safeEscape;
}

function getAlpNum($safeEscape) {
    return trim(preg_replace('/[^A-Za-z0-9\-]/', '', $safeEscape));
}


function getSafeInputAlpNum($db, $mybb, $safeInputItem) {
    $safeEscape = getSafeInput($db, $mybb, $safeInputItem);
    return getAlpNum($safeEscape);
}

function getSafeInputNum($db, $mybb, $safeInputItem) {
    return intval(getSafeInputAlpNum($db, $mybb, $safeInputItem));
}

function getEscapeString($db, $safeInputItem) {
    return $db->escape_string($safeInputItem);
}

function getSafeNumber($db, $xNumber) {
    $safeEscape = $db->escape_string($xNumber);
    return intval(getAlpNum($safeEscape));
}

function updateBankBalance($db, $userId) {
    $balancequery = "SELECT sum(amount) AS sumamt FROM mybb_banktransactions WHERE uid=$userId";
    $banksumquery = $db->query($balancequery);
    $banksumresult = $db->fetch_array($banksumquery);
    if ($banksumresult != NULL) { $bankbalanceresult = intval($banksumresult['sumamt']); }
    else { $bankbalanceresult = 0; }
    $db->update_query("users", array("bankbalance" => $bankbalanceresult), "uid=$userId", 1);
    return $bankbalanceresult;
}

function addBankTransaction($db, $userId, $addAmount, $addTitle, $addBankerId) {
    $db->insert_query("banktransactions", array(
        "uid" => $userId,
        "amount" => $addAmount,
        "title" => $addTitle,
        "bankerid" => $addBankerId));
}

function displaySuccessTransaction($disName, $disAmount, $disTitle, $displayMessage) {
    echo '<div class="successSection">';
    echo "<h4>Success: $displayMessage</h4>";
    echo "<table>";
    echo '<tr><th>User</th><td>'.$disName.'</td></tr>';
    if($disAmount < 0) {
        echo '<tr><th>Amount</th><td>-$'.abs($disAmount).'</td></tr>';
    }
    else {
        echo '<tr><th>Amount</th><td>$'.$disAmount.'</td></tr>';
    }
    echo '<tr><th>Title</th><td>'.$disTitle.'</td></tr>';
    echo "</table>";
    echo '</div>';
}

function displayErrorTransaction() {
    echo '<div class="errorSection">';
    echo "<h4>Error</h4>";
    echo '<p>Invalid arguments for the transaction</p>';
    echo '</div>';
}

function doTransaction($db, $transAmount, $transTitle, $userid, $bankerid, $username, $displayMessage) {
    if ($transAmount != 0 && strlen($transTitle))
    {
        addBankTransaction($db, $userid, $transAmount, $transTitle, $bankerid);
        $newbankbalance = updateBankBalance($db, $userid);
        displaySuccessTransaction($username, $transAmount, $transTitle, $displayMessage);
    }
    else {
        displayErrorTransaction();
        $newbankbalance = null;
    }

    return $newbankbalance;
}

function checkIfBanker($mybb) {
    // Banker id is 13 as of me writing this.
    $groupstring = $mybb->user['usergroup'] . ',' . $mybb->user['additionalgroups'];
    $groups = explode(",", $groupstring);
    return in_array("13", $groups);
}

function getUser($db, $userId) {
    // Gets user from DB.
    $userquery = $db->simple_select("users", "*", "uid=$userId", array("limit" => 1));
    return $db->fetch_array($userquery);            
}
?>