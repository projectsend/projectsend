<?php
/**
 * Allows to hide, show or delete the files assigend to the
 * selected client.
 * @package ProjectSend
 */
$load_scripts	= array('footable',); 

$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

$active_nav = 'Admin';
$cc_active_page = 'Calendar View';

$page_title = __('Calendar View','cftp_admin');

$current_level = get_current_user_level();

/*
 * Get the total downloads count here. The results are then
 * referenced on the results table.
 */  
include('header.php');
global $dbh;	
if($_POST) {		}
?>

<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
<div id="main">
  <div id="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
			<h1 class="page-title txt-color-blueDark"><i class="fa fa-tasks" aria-hidden="true"></i>&nbsp;<?php echo $page_title; ?></h1>
			<div style="margin-bottom:20px;">
			<div id="datepicker"></div>
			</div>
			<div>
			<div id=d1><p style="color:#ff0000">Available dates are highlighted in the calendar. Click on the dates to view the action</p></div>
			</div></div>	
      </div>
    </div>
  </div>
</div>

<?php include('footer.php'); ?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>

<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
function checkDate(selectedDate) { 

<?php

$q="SELECT distinct date_format( expiry_date, '%d-%m-%Y' ) as date
FROM tbl_files
UNION
SELECT  distinct date_format( future_send_date, '%d-%m-%Y' ) as date
FROM tbl_files";

/*$q="select CONCAT(date_format( expiry_date, '%d-%m-%Y' ),date_format( timestamp, '%d-%m-%Y' ))as date from tbl_files"; */

$str="[ ";
foreach ($dbh->query($q) as $row) {
$str.="\"$row[date]\",";
}
$str=substr($str,0,(strlen($str)-1));
$str.="]";

if(empty($str))
{
	echo "var not_available_dates=hi"; 

}
else {
	echo "var not_available_dates=$str"; 
}

?>	

 var m = selectedDate.getMonth()+1;
 var d = selectedDate.getDate();
 var y = selectedDate.getFullYear();
 m=m.toString();
 d=d.toString();
if(m.length <2){m='0'+m;}
if(d.length <2){d='0'+d;} 
 
 var date_to_check = d+ '-' + m + '-'  + y ;
 
 	if(not_available_dates.length > 0){
		for (var i = 0; i < not_available_dates.length; i++) {
		
			 if ($.inArray(date_to_check, not_available_dates) != -1 ) {
				return [true,'	','Open date T'];
			 }else{
					return [false,'na_dates','Close date F'];
			}
		} 
	}
	else {
		return [false,'na_dates','Close date F'];
	}
}
$(function() {
    $( "#datepicker" ).datepicker({
	dateFormat: 'dd-mm-yy',
	beforeShowDay:checkDate,
	});
	/*
	$("#datepicker").on("change",function(){
        var selectedDate = $(this).val();
        alert(selectedDate);
		var url="display-data.php?selectedDate="+selectedDate;
		$('#d1').load(url);
    }); */
	$("#datepicker").on("change",function(){
		var selectedDate = $(this).val();
		var obj;
		var global_json_data;
        $.ajax
        ({
              type: "Post",
              url: "display-data.php",
              data: {'date': selectedDate},
              success: function(json)
              {
				 var json = jQuery.parseJSON(json);
				 console.log(json);
				 console.log(json.send);
				 var data_obj = [];
				 var htmlText='';
				 var htmlText1='';
				 var htmlText2='';
				 var htmlText3='';
				 if(json.send!='false') {
					 if(json.send.length >0) {
					  htmlText += '<h5>Send Files</h5>';
					  htmlText += '<table class="table_calendar_common">';
					  htmlText += '<tr>';
					  htmlText += '<th>File Name</th><th>File Desc</th><th>Send Date</th><th>Send To</th>';
					  htmlText += '</tr>';
					  for(var i = 0; i < json.send.length; i++)
					   { 
						htmlText += '<tr>';
						htmlText += '<td>'+json.send[i].filename+'</td>';
						htmlText += '<td>'+json.send[i].description+'</td>';
						htmlText += '<td>'+json.send[i].timestamp+'</td>';
						if(json.send[i].clientid != null) {
						    htmlText += '<td>'+json.send[i].clientid+'</td>';
						}else{
						    htmlText += '<td>'+json.send[i].groupid+'</td>';                            
						}
						htmlText += '</tr>';
						};
						 htmlText += '</table>';
					 }
				 }
				 if(json.receive!='false') {
					 if(json.receive.length>0) { 
						htmlText1 += '<h5>Received Files</h5>';
						htmlText1 += '<table class="table_calendar_common">';
						htmlText1 += '<tr>';
						htmlText1 += '<th>File Name</th><th>File Desc</th><th>Received Date</th>';
						htmlText1 += '</tr>';
						for(var i = 0; i < json.receive.length; i++)
						{ 
							htmlText1 += '<tr>';
							htmlText1 += '<td>'+json.receive[i].filename+'</td>';
							htmlText1 += '<td>'+json.receive[i].description+'</td>';
							htmlText1 += '<td>'+json.receive[i].timestamp+'</td>';

							htmlText1 += '</tr>';
						};
						 htmlText1 += '</table>';
					 }
				 }
				 if(json.expiry!='false') {
					 if(json.expiry.length>0) { 
						htmlText2 += '<h5>Expired Files</h5>';
						htmlText2 += '<table class="table_calendar_common">';
						htmlText2 += '<tr>';
						htmlText2 += '<th>File Name</th><th>File Desc</th><th>Expiry Date</th>';
						htmlText2 += '</tr>';
						for(var i = 0; i < json.expiry.length; i++)
						{ 
							htmlText2 += '<tr>';
							htmlText2 += '<td>'+json.expiry[i].filename+'</td>';
							htmlText2 += '<td>'+json.expiry[i].description+'</td>';
							htmlText2 += '<td>'+json.expiry[i].expiry_date+'</td>';

							htmlText2 += '</tr>';
						};
						 htmlText2 += '</table>';
					 }
				 }
					
					if(json.send=='false' && json.receive=='false' && json.expiry=='false') {
						
						htmlText3 += '<table>';
						htmlText3 += '<tr>No data found </tr>';
						htmlText3 += '</table>';
						var fullarry=  htmlText3;
						$('#d1').html(fullarry);
					}
					else
					{
						var fullarry=  htmlText+htmlText1+htmlText2;
						$('#d1').html(fullarry);
					}
				 
			    }  
        }); /* ajax cloase */ 
    }); /* on chage close */
}); /* fucntion close */
}) /* document ready close */
</script>
