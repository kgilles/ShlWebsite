<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->profile}</title>
{$headerinclude}
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/report.js?ver=1808"></script>
</head>
<body id="forums">
{$header}
	
<div class="posts2">
    <div id="one">
		<div class="us">
	
			
			
			  <fieldset>
	<table width="100%" cellspacing="0" cellpadding="0" border="0">
		<tr>
			<td>
				<center>
					<span class="largetext"><strong>{$formattedname}</strong><br />{$usergroup['title']}<br />
				{$post['usertitle']}</span>
				{$avatar} <br />	
				
				<span class="smalltext">
					{$groupimage}<br /><br />
					<!--<a class="button" href="private.php?action=send&amp;uid={$memprofile['uid']}" title="Private message user"> 
				     <i style="font-size: 12px;" class="fa fa-envelope-o fa-fw"></i> 
					</a>  
					<a class="button" href="member.php?action=emailuser&amp;uid={$memprofile['uid']}" title="Email user">  
					 <i style="font-size: 12px;" class="fa fa-envelope fa-fw"></i> 
					</a>   
					<a class="button" href="search.php?action=finduser&amp;uid={$uid}" title="My posts">  
						<i style="font-size: 12px;" class="fa fa-file fa-fw"></i>
					</a>   
					<a class="button" href="search.php?action=finduserthreads&amp;uid={$uid}" title="My threads"> 
						<i style="font-size: 12px;" class="fa fa-file-o fa-fw"></i>
					</a>-->
					<a class="button" href="search.php?action=finduser&amp;uid={$uid}" title="My posts">  
						<i style="font-size: 12px;" class="fa fa-file fa-fw"></i> View All Posts
					</a>   <br />
					<a class="button" href="search.php?action=finduserthreads&amp;uid={$uid}" title="My threads"> 
						<i style="font-size: 12px;" class="fa fa-file-o fa-fw"></i> View All Topics
					</a>
				</span>
			</center>
				<br />
				<center>
					{$awaybit}
					{$bannedbit} 
					
					{$modoptions}
                    {$adminoptions} 
				</center>
			</td>
		</tr>
	</table>
 </fieldset>
{$contact_details}
         </div>
    </div>
    <div id="two">
		
		<div class="float_right" style="text-align: center">{$buddy_options}  {$ignore_options} {$report_options}</div><br />
        <table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
            <tr>
                <td colspan="2" class="thead"><strong>{$online_status}</strong></td>
			</tr>
			<tr>
                <td class="trow1">
                    <div class="float_left"><strong>{$lang->joined}</strong> </div>
				    <div class="float_right">{$memregdate}</div>
                </td>
            </tr>
            <tr>
                <td class="trow2">
                    <div class="float_left"><strong>{$lang->lastvisit}</strong></div>
					<div class="float_right">	{$memlastvisitdate}</div>
                </td>
            </tr>
            <tr>
                <td class="trow1">
                    <div class="float_left"><strong>Online For:</strong></div>
                    <div class="float_right">{$timeonline}</div>
                </td>
            </tr>
            <tr>
                <td class="trow1">
                    <div class="float_left"><strong>Bank Balance:</strong></div>
                    <div class="float_right">
                    <?php
                        // Gets user from DB.
                        $userquery = $db->simple_select("users", "bankbalance", "uid=$uid", array("limit" => 1));
                        $curruser = $db->fetch_array($userquery);
                        $bankbalance = intval($curruser["bankbalance"]);
                        
                        echo '<a href="http://simulationhockey.com/bankaccount.php?uid=' . $uid . '">'; 
                        if($bankbalance < 0) { echo '-'; } 
                        echo '$' . number_format(abs($bankbalance), 0);
                        echo '</a>';
                    ?>
                    </div>
                </td>
            </tr>
            {$referrals}
            {$reputation}
            {$myawards}

            {$warning_level}{$newpoints_profile}
        </table>
        <br />
        {$signature}
        <br />
    </div>
  </div>


{$footer}
</body>
</html>


<style>
.us { 
    overflow:hidden;
	text-align: center:
	float: center:
	margin: auto auto;
}
	
.posts2 { 
	-moz-border-radius: 2px;
	-webkit-border-radius: 2px;
	border-radius: 2px;
    overflow:hidden;
}

.posts2 div {
   padding: 1px;
}
#one {
  float:left; 
  margin-right:10px;
  width:300px;
}
#two { 
  overflow:hidden;
  margin:10px;
  min-height:160px;
}

@media screen and (max-width: 867px) {
   #one { 
    float: none;
    margin-right:0;
    width:auto;
    border:0;
  }
}
</style>