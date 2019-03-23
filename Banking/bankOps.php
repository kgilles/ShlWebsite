<?php
function getSafeInput($db, $mybb, $safeInputItem) {
    $safeInput = trim($mybb->input[$safeInputItem]);
    $safeEscape = $db->escape_string($safeInput);
    return $safeEscape;
}

function getAlpNum($safeEscape) {
    return trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $safeEscape));
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
    $balancequery = "SELECT sum(amount) AS sumamt FROM mybb_banktransactions WHERE uid=$userId AND isaccepted=1";
    $banksumquery = $db->query($balancequery);
    $banksumresult = $db->fetch_array($banksumquery);
    if ($banksumresult != NULL) { $bankbalanceresult = intval($banksumresult['sumamt']); }
    else { $bankbalanceresult = 0; }
    $db->update_query("users", array("bankbalance" => $bankbalanceresult, "isaccepted" => 1), "uid=$userId", 1);
    return $bankbalanceresult;
}

function addBankTransaction($db, $userId, $addAmount, $addTitle, $addDescription, $addcreatorId, $isaccepted) {
    $addArray = [
        "uid" => $userId,
        "amount" => $addAmount,
        "title" => $addTitle,
        "createdbyuserid" => $addcreatorId,
        "isaccepted" => $isaccepted
    ];

    if($addDescription != null) {
        $addArray["description"] = $addDescription;
    }

    $db->insert_query("banktransactions", $addArray);
}

function addBankTransferRequest($db, $requestId, $reqtargetId, $reqamount, $reqtitle, $reqdescription) {
    if ($reqamount != 0 && strlen($reqtitle))
    {
        $addArray = [
            "userrequestid" => $requestId,
            "usertargetid" => $reqtargetId,
            "amount" => $reqamount,
            "title" => $reqtitle,
        ];
    
        if($reqdescription != null) {
            $addArray["description"] = $reqdescription;
        }
    
        $db->insert_query("banktransferrequests", $addArray);
        return true;
    }
    return false;
}

function displaySuccessTransaction($disName, $disAmount, $disTitle, $disDescription, $displayMessage) {
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
    if($disDescription != null)
    {
        echo '<tr><th>Description</th><td>'.$disDescription.'</td></tr>';
    }
    echo "</table>";
    echo '</div>';
}

function displayErrorTransaction() {
    echo '<div class="errorSection">';
    echo "<h4>Error</h4>";
    echo '<p>Invalid arguments for the transaction</p>';
    echo '</div>';
}

function doTransaction($db, $transAmount, $transTitle, $description, $userid, $creatorid, $username, $displayMessage) {
    if ($transAmount != 0 && strlen($transTitle))
    {
        addBankTransaction($db, $userid, $transAmount, $transTitle, $description, $creatorid, 1);
        $newbankbalance = updateBankBalance($db, $userid);
        displaySuccessTransaction($username, $transAmount, $transTitle, $description, $displayMessage);
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

function acceptTransferRequest($db, $uid, $bankerid, $approveid, $approverequester, $approvetarget, $approveamount, $approvetitle, $approvedescription) {
    $setapprovequery = "UPDATE mybb_banktransferrequests SET bankerapproverid=$bankerid, approvaldate=now() WHERE mybb_banktransferrequests.id=$approveid";
    $db->query($setapprovequery);

    $namequery = $db->simple_select("users", "username", "uid=$approvetarget", array("limit" => 1));
    $nameresult = $db->fetch_array($namequery);
    $targetname = $nameresult['username'];

    $namequery = $db->simple_select("users", "username", "uid=$approverequester", array("limit" => 1));
    $nameresult = $db->fetch_array($namequery);
    $requestname = $nameresult['username'];

    $targetbalance = doTransaction($db, $approveamount, $approvetitle, $approvedescription, $approvetarget, $approverequester, $targetname, "Banker Approved Transfer - Target");
    $requestbalance = doTransaction($db, -$approveamount, $approvetitle, $approvedescription, $approverequester, $approverequester, $requestname, "Banker Approved Transfer - Requester");

    if($uid == $approverequester) {
        return $requestbalance;
    }
    else if($uid == $approvetarget) {
        return  $targetbalance;
    }
    return 0;
}
?>