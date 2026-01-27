<?php include_once('mysql/mysql2i.class.php'); ?>
<?php require_once('Connections/hms.php'); ?>
<?php  session_start();
if (!isset($_SESSION['UserID'])){
header("Location:index.php");} else{
 
}


if (!function_exists("GetSQLValueString")) {
function GetSQLValueString($con,$theValue, $theType, $theDefinedValue = "", $theNotDefinedValue = "") 
{
  if (PHP_VERSION < 6) {
    $theValue = get_magic_quotes_gpc() ? stripslashes($theValue) : $theValue;
  }

  $theValue = function_exists("mysqli_real_escape_string") ? mysqli_real_escape_string($con,$theValue) : mysqli_escape_string($con,$theValue);

  switch ($theType) {
    case "text":
      $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
      break;    
    case "long":
    case "int":
      $theValue = ($theValue != "") ? intval($theValue) : "NULL";
      break;
    case "double":
      $theValue = ($theValue != "") ? doubleval($theValue) : "NULL";
      break;
    case "date":
      $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
      break;
    case "defined":
      $theValue = ($theValue != "") ? $theDefinedValue : $theNotDefinedValue;
      break;
  }
  return $theValue;
}
}

$col_Batch = "-1";
if (isset($_SESSION['period'])) {
  $col_Batch = $_SESSION['period'];
}
mysqli_select_db($hms,$database_hms);
$query_Batch = sprintf("SELECT tbl_bank_schedule.loanamount,tbl_bank_schedule.loanid,  tbl_bank_schedule.cheque_no, tbl_bank_schedule.date_on_cheque, tbl_bank_schedule.periodid,
tbl_bank_schedule.memberid, tbl_bank_schedule.`name` FROM tbl_bank_schedule WHERE tbl_bank_schedule.periodid= %s order by loanid asc", GetSQLValueString($hms,$col_Batch, "text"));
$Batch = mysqli_query($hms,$query_Batch) or die(mysqli_error());
$row_Batch = mysqli_fetch_assoc($Batch);
$totalRows_Batch = mysqli_num_rows($Batch);


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Print Schedule</title>
<link href="registration_files/oouth.css" rel="stylesheet" type="text/css" />
<script language="javascript">
function print__(){
document.getElementById('print').hidden = "true";
window.print() ;
}
</script>
<script type="text/javascript" language="javascript" src="numberToWord/jquery.min.js"></script>
<script type="text/javascript" language="javascript" src="numberToWord/jquery.num2words.js"></script>
<script>

                    var isNS4=(navigator.appName=="Netscape")?1:0;

                    function auto_logout(iSessionTimeout,iSessTimeOut,sessiontimeout)

                    {

                             window.setTimeout('', iSessionTimeout);

                              window.setTimeout('winClose()', iSessTimeOut);

                    }

                    function winClose() {

                        //alert("Your Application session is expired.");

                   if(!isNS4)

	           {

		          window.navigate("index.php");

	           }

                  else

	          {

		        window.location="index.php";

	           }

             }

            auto_logout(1440000,1500000,1500)

</script>
</head>

<body>
<table width="100%" border="0">
  <tr>
    <th colspan="5" scope="col"><img src="images/mhwun_logo_schedule.jpg" width="78" height="79"><h1>MEDICAL AND HEALTH WORKERS UNION ON NIGERIA,<BR />OOUTH BRANCH, SAGAMU, OGUN STATE</h1></th>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td align="right"><font size="+1">Date: <?php echo date('d/m/y');?></font></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td><font size="+1"><strong>To: The Manager</strong></font></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td><hr /></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td><hr /></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td><hr />
    </td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td align="center" class="greyBgd"><strong><font size="+1">CONFIRMATION SCHEDULE OF <BR />CHEQUES ISSUED OUT</font></strong></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td colspan="5" align="center"><table width="100%" border="1" class="greyBgd">
      <tr>
        <th width="7%" scope="col">S/N</th>
        <th width="29%" scope="col">DATE ON CHEQUE</th>
        <th width="34%" scope="col">PAYEE</th>
        <th width="15%" scope="col">CHEQUE NO</th>
        <th width="15%" scope="col">AMOUNT</th>
      </tr>
      <?php $i = 1 ; $sum = 0; do { ?>
        <tr>
          <td nowrap="nowrap"><?php echo $i; ?></td>
          <td nowrap="nowrap"><?php echo $row_Batch['date_on_cheque']; ?></td>
          <td nowrap="nowrap"><?php echo $row_Batch['name']; ?></td>
          <td nowrap="nowrap"><?php echo $row_Batch['cheque_no']; ?></td>
          <td nowrap="nowrap"><?php echo number_format($row_Batch['loanamount'],2,'.',','); ?></td>
        </tr><?php $i=$i+1;$sum =$sum+$row_Batch['loanamount'];} while ($row_Batch = mysqli_fetch_assoc($Batch)); ?>
        <tr>
          <td colspan="4" align="right" nowrap="nowrap"><strong>SUM</strong></td>
          <td nowrap="nowrap"><strong><?php echo number_format($sum,2,'.',','); ?></strong></td>
        </tr>
        <tr><script type="text/javascript">
$(document).ready(function() {
   $('#num').focus();
   $('#demo').num2words();
  }); 
</script>
          <td colspan="4" align="right" nowrap="nowrap"><strong>Amount in words:</strong><div id="demo"><strong>
            <input type="text" id="num" value="<?php echo$sum; ?>" size="15" />
            <div></div>
          </strong></div></td>
          <td nowrap="nowrap">&nbsp;</td>
        </tr> 
		
        <tr>
          <td colspan="5" align="center" nowrap="nowrap"><div id="print"><input name="Schedule3" type="image" class="formbutton" id="Schedule3" value="Print" onclick='print__()' style="opacity:100" / src="images/print2.jpg"></div>
</td>
          </tr>
       
    </table></td>
  </tr>
</table>
<p class="death">&nbsp;</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
</body>
</html>
<?php
mysqli_free_result($Batch);
?>
