<?php require_once('Connections/hms.php'); ?>
<?php session_start();
if (!isset($_SESSION['UserID']) or ($_SESSION['roleId'] != 4)){
header("Location:index.php");} else{
 
}
?>
<?php
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

$editFormAction = $_SERVER['PHP_SELF'];
if (isset($_SERVER['QUERY_STRING'])) {
  $editFormAction .= "?" . htmlentities($_SERVER['QUERY_STRING']);
}

if ((isset($_POST["Submit"])) && ($_POST["Submit"] == "Save")) {
	

	//$insetTogetMrn = "INSERT INTO tbl_getMrn(no)values(NULL)";
//	mysqli_select_db($hms,$database_hms);
// 	$Result_1 = mysqli_query($insetTogetMrn, $hms) or die(mysqli_error($hms));
//	$MRN = mysql_insert_id();
//	$getDate = date("y/m/");
	$MRN = $_POST['mrn'];
	
  $insertSQL = sprintf("INSERT INTO tbl_personalinfo (patientid,sfxname, Fname, Mname, Lname, MaidenName, Mothersname, gender, bloodGroup, MStatus, DOB, Address, Address2, City, `State`, countryOrigin, StateOfOrigin, Tribe, EducationLevel, Occupation, Religion, MobilePhone, EmailAddress,DateOfReg) VALUES (%s,%s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,NOW())",
                       GetSQLValueString($hms,$MRN, "text"),
					   GetSQLValueString($hms,$_POST['sfxname'], "text"),
                       GetSQLValueString($hms,$_POST['Fname'], "text"),
                       GetSQLValueString($hms,$_POST['Mname'], "text"),
                       GetSQLValueString($hms,$_POST['Lname'], "text"),
                       GetSQLValueString($hms,$_POST['MaidenName'], "text"),
                       GetSQLValueString($hms,$_POST['Mothersname'], "text"),
                       GetSQLValueString($hms,$_POST['gender'], "text"),
                       GetSQLValueString($hms,$_POST['bloodGroup'], "text"),
                       GetSQLValueString($hms,$_POST['MStatus'], "text"),
                       GetSQLValueString($hms,$_POST['DOB'], "date"),
                       GetSQLValueString($hms,$_POST['Address'], "text"),
                       GetSQLValueString($hms,$_POST['Address2'], "text"),
                       GetSQLValueString($hms,$_POST['City'], "text"),
                       GetSQLValueString($hms,$_POST['State'], "text"),
                       GetSQLValueString($hms,$_POST['countryOrigin'], "text"),
                       GetSQLValueString($hms,$_POST['StateOfOrigin'], "text"),
                       GetSQLValueString($hms,$_POST['Tribe'], "text"),
                       GetSQLValueString($hms,$_POST['EducationLevel'], "text"),
                       GetSQLValueString($hms,$_POST['Occupation'], "text"),
                       GetSQLValueString($hms,$_POST['Religion'], "text"),
                       GetSQLValueString($hms,$_POST['MobilePhone'], "text"),
                       GetSQLValueString($hms,$_POST['EmailAddress'], "text"));

  mysqli_select_db($hms,$database_hms);
  $Result1 = mysqli_query($hms,$insertSQL) or die(mysqli_error($hms));
  

$insertSQL_pastor = sprintf("INSERT INTO tbl_pastor (patientId, NamePastor, PhonePastor, Name_Address_Church) VALUES (%s, %s, %s, %s)",
					   GetSQLValueString($hms,$MRN, "text"),
					   GetSQLValueString($hms,$_POST['NamePastor'], "text"),
                       GetSQLValueString($hms,$_POST['PhonePastor'], "text"),
					    GetSQLValueString($hms,$_POST['Name_Address_Church'], "text"));
						mysqli_select_db($hms,$database_hms);
  $Result2 = mysqli_query($hms,$insertSQL_pastor) or die(mysqli_error($hms));
  
 $insertSQL_NOK = sprintf("INSERT INTO tbl_nok (patientId, NOkName, NOKRelationship, NOKPhone, NOKAddress) VALUES (%s, %s, %s, %s,%s)",
                       GetSQLValueString($hms,$MRN, "text"),
					   GetSQLValueString($hms,$_POST['NOkName'], "text"),
                       GetSQLValueString($hms,$_POST['NOKRelationship'], "text"),
					   GetSQLValueString($hms,$_POST['NOKPhone'], "text"),
					    GetSQLValueString($hms,$_POST['NOKAddress'], "text"));
						mysqli_select_db($hms,$database_hms);
  $Result3 = mysqli_query($hms,$insertSQL_NOK) or die(mysqli_error($hms));

$success = "true";

$insertGoTo = "upload.php?success=true";
  if (isset($_SERVER['QUERY_STRING'])) {
    $insertGoTo .= (strpos($insertGoTo, '?')) ? "&" : "?";
    $insertGoTo .= $_SERVER['QUERY_STRING'];
  }
  header(sprintf("Location: %s", $insertGoTo));

}



mysqli_select_db($hms,$database_hms);
$query_country = "SELECT sjx_gen_countries.Country FROM sjx_gen_countries ORDER BY sjx_gen_countries.Country asc";
$country = mysqli_query($hms,$query_country) or die(mysqli_error($hms));
$row_country = mysqli_fetch_assoc($country);
$totalRows_country = mysqli_num_rows($country);

mysqli_select_db($hms,$database_hms);
$query_tribe = "SELECT tribe.tribe FROM tribe";
$tribe = mysqli_query($hms,$query_tribe) or die(mysqli_error($hms));
$row_tribe = mysqli_fetch_assoc($tribe);
$totalRows_tribe = mysqli_num_rows($tribe);

mysqli_select_db($hms,$database_hms);
$query_nokRelationship = "SELECT nok_relationship.relationship FROM nok_relationship";
$nokRelationship = mysqli_query($hms,$query_nokRelationship) or die(mysqli_error($hms));
$row_nokRelationship = mysqli_fetch_assoc($nokRelationship);
$totalRows_nokRelationship = mysqli_num_rows($nokRelationship);

mysqli_select_db($hms,$database_hms);
$query_bloodgroup = "SELECT tbl_bloodgroup.bloodgroup FROM tbl_bloodgroup";
$bloodgroup = mysqli_query($hms,$query_bloodgroup) or die(mysqli_error($hms));
$row_bloodgroup = mysqli_fetch_assoc($bloodgroup);
$totalRows_bloodgroup = mysqli_num_rows($bloodgroup);

mysqli_select_db($hms,$database_hms);
$query_state2 = "SELECT * FROM state_nigeria";
$state2 = mysqli_query($query_state2, $hms) or die(mysqli_error($hms));
$row_state2 = mysqli_fetch_assoc($state2);
$totalRows_state2 = mysqli_num_rows($state2);


?>
<html>
<head>


<title>Hospital Management - Patient Registration DashBoard</title>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon">

<!--Fireworks MX 2004 Dreamweaver MX 2004 target.  Created Sat Dec 04 17:23:24 GMT+0100 2004-->
<link href="personal_files/oouth.css" rel="stylesheet" type="text/css">
<script language="JavaScript" src="personal_files/general.js" type="text/javascript"></script>
<script type="text/javascript" language="javascript">

            function makeRequest(url,divID) {

                //alert("ajax code");

                // alert(divID);

                //alert(url);

                var http_request = false;

                if (window.XMLHttpRequest) { // Mozilla, Safari, ...

                    http_request = new XMLHttpRequest();

                    if (http_request.overrideMimeType) {

                        http_request.overrideMimeType('text/xml');

                        // See note below about this line

                    }

                }

                else

                    if (window.ActiveXObject) { // IE

                        //alert("fdsa");

                        try {

                            http_request = new ActiveXObject("Msxml2.XMLHTTP");

                        } catch (e) {

                            lgErr.error("this is exception1 in his_secpatientreg.jsp"+e);

                            try {

                                http_request = new ActiveXObject("Microsoft.XMLHTTP");

                            } catch (e) {

                                lgErr.error("this is exception2 in his_secpatientreg.jsp"+e);

                            }

                    }

                }

                if (!http_request) {

                    alert('Giving up :( Cannot create an XMLHTTP instance');

                    return false;

                }

                http_request.onreadystatechange = function() {  alertContents(http_request,divID); };

                http_request.open('GET', url, true);

                http_request.send(null);

            }
			function alertContents(http_request,divid) {

                if (http_request.readyState == 4) {

                    //alert(http_request.status);

                    //alert(divid);

                    if (http_request.status == 200) {

                        document.getElementById(divid).innerHTML=http_request.responseText;

                    } else {

                        //document.getElementById(divid).innerHTML=http_request.responseText;

                        alert("There was a problem with the request");

                    }

                }

            }
						
                        
                        function onSelected(oForm) {

                //options[document.form.profile.selectedIndex].value

                //var s=oForm.selectedIndex;

                var s1=oForm.value;

                //  alert("ddddddddddddd"+s+s1);

                var url="state.php?Country="+s1;

                // alert(url);

                makeRequest(url,"state");

            }
			
function jumptoURL(oForm){
	
	window.location="schedule.php?mrn="+oForm.value;
	
	
	}
    function onSelectedSearchMRN(searchM) {

                //options[document.form.profile.selectedIndex].value

                //var s=oForm.selectedIndex;

                //var mrnSearch = document.getElementById("mrn").value;
				//var dob = document.getElementById("SDOb").value;

                 
				

                var url="uploadSearch.php?SearchMRN="+searchM;
				//var url="patSearch.php?SearchMRN="+searchM;
				//alert(searchM);
                //alert(mrn+lastname+Firstname+phoneno+dob);

                makeRequest(url,"UploadSearchResult");

            }                    
function onSelectedSearch() {

                //options[document.form.profile.selectedIndex].value

                //var s=oForm.selectedIndex;

                var mrn = document.getElementById("SearchMRN").value;
				var dob = document.getElementById("SDOb").value;

                 
				

                var url="patSearch.php?SearchMRN="+mrn;

                //alert(mrn+lastname+Firstname+phoneno+dob);

                makeRequest(url,"patSearchResult");

            }

function reset(){
				 document.getElementById("SearchMRN").value = "";
				 document.getElementById("SLastName").value= "";
				  document.getElementById("SFirstName").value= "";
				document.getElementById("SphoneNo").value= "";
				document.getElementById("SDOb").value= "";

	}
function clearbox(){
	document.getElementById("SearchMRN").focus();
	document.getElementById("SearchMRN").value = "";
	 }
function Ipopcases1(oForm) {

                //options[document.form.profile.selectedIndex].value

                var s=oForm.selectedIndex;

                var s1=oForm.options[oForm.selectedIndex].value;

                // alert("ddddddddddddd"+s+s1);

                var url="config/patientype.php?apptype="+s1;

                // alert(url);

                makeRequest(url,"patcategory");



            }


function Expand90(itemm){

                //alert(itemm.value);

            

                    if(itemm.value=="NC"){

                       // alert("in new");

                        //document.getElementById('patocpdetailsiframe').style.display="none";

                        //document.getElementById("modeofpay").style.display="none";

                        document.getElementById("patnc").style.display="block";

                        //document.getElementById("patnp").style.display="block";

                        document.getElementById("patoc").style.display="none";
						document.getElementById("patSearchResult").style.display="none";

                        //document.getElementById("patop").style.display="none";

                        //document.getElementById("patdisplay").style.display="none";

                        //document.getElementById("patocpdetails").style.display="none";



                        //document.getElementById("patientolddetails").style.display="none";

                        //document.getElementById("patocpdetails").width="0";

                        //document.getElementById("patocpdetails").height="0";



                        //document.getElementById("Temporrarayappointment").style.display="none";

                        //document.getElementById("paylater").style.display="none";

                        //document.getElementById('fname').value="";

                        //document.getElementById('lname').value="";











                    }else{

                        //alert("in old");

                        //document.getElementById("modeofpay").style.display="block";

                        document.getElementById("patnc").style.display="none";

                        document.getElementById("patoc").style.display="block";
						document.getElementById("patSearchResult").style.display="block"
                        //document.getElementById("patnp").style.display="none";

                        //document.getElementById("patop").style.display="block";

                        //document.getElementById("patocpdetails").style.display="none";

                        //document.getElementById("patientolddetails").style.display="none";

                        //document.getElementById("patocpdetails").width="0";

                        //document.getElementById("patocpdetails").height="0";

                        //document.getElementById("Temporrarayappointment").style.display="none";

                        //document.getElementById("companynamesss").style.display="none";

                        //document.getElementById("paylater").style.display="none";

                        //document.getElementById('patfirstname').value="";

                        //document.getElementById('patlastname').value="";

                    }
}



function ischecked(oFormEle,msg)

                                {

                                var s=oFormEle.value

                                if (s=="na"){

                                alert(msg);

                                oFormEle.focus()

                                return false;

                                }

                                return true;

                                }
function UserFeedback(oFormEle)

        {

        oFormEle.focus();

		}
function isSpace(s,message)
                {
					

                ss=s.value;

                var length=ss.length;

                var c = ss.charAt(0);

                var d=ss.charAt(length-1);

                //    var regexpr =/[A-Za-z0-9]/;

                //     result= regexpr.test(c)

                //	if (!result)
				
				
                if(c == " " || d == " ")

                {

                UserFeedback(s);

                s.value = ss.trim();

                alert(message);

               return false;

                }

                return true;

                }

//function cansubmit(){ can (cansubmit=isSpace(document.eduEntry.Fname,"Space not allowed"));}
//cansubmit=isSpace(document.eduEntry.Fname.value,"Space not allowed");
function sameasabove(){
	if (document.eduEntry.same.checked){
	document.eduEntry.NOKAddress.value = document.eduEntry.Address.value +" "+document.eduEntry.Address2.value+" "+document.eduEntry.City.value+" "+document.eduEntry.State.value;
		}else{ document.eduEntry.NOKAddress.value = "";}
}

function validate(){
//var cansubmit=false
if(document.eduEntry.Submit.value == "Save"){
   if ( document.eduEntry.sfxname.value == "na" )
   {
     alert( "Please provide your Title!" );
     document.eduEntry.sfxname.focus() ;
     return false;
   }
   
   cansubmit=isSpace(document.eduEntry.Fname,"Space not allowed")
   if(document.eduEntry.Fname.value == "" )
   {
     alert( "Please provide your First Name!" );
     document.eduEntry.Fname.focus() ;
     return false;
   }
   cansubmit=isSpace(document.eduEntry.Lname,"Space not allowed");
   if( document.eduEntry.Lname.value == "" )
   {
     alert( "Please provide your Last Name!" );
     document.eduEntry.Lname.focus() ;
     return false;
   }
    if( document.eduEntry.patCategory.value == "na" )
   {
     alert( "Please provide Patient Category!" );
     document.eduEntry.patCategory.focus() ;
     return false;
   }
  // if( document.eduEntry.DOB.value == "" )
//   {
//     alert( "Please provide Patient Date of Birth!" );
//     document.eduEntry.DOB.focus() ;
//     return false;
//   }
   cansubmit=isSpace(document.eduEntry.Address,"Space not allowed")
   if( document.eduEntry.Address.value == "" )
   {
     alert( "Please provide Patient House No!" );
     document.eduEntry.Address.focus() ;
     return false;
   }
      cansubmit=isSpace(document.eduEntry.City,"Space not allowed")
   if( document.eduEntry.City.value == "" )
   {
     alert( "Please provide Patient City Address!" );
     document.eduEntry.City.focus() ;
     return false;
   }
   cansubmit=isSpace(document.eduEntry.State,"Space not allowed")
   if( document.eduEntry.State.value == "" )
   {
     alert( "Please provide State!" );
     document.eduEntry.State.focus() ;
     return false;
   }
   cansubmit=isSpace(document.eduEntry.MobilePhone,"Space not allowed")
   if( document.eduEntry.MobilePhone.value == "" )
   {
     alert( "Please provide Mobile Phone No!" );
     document.eduEntry.MobilePhone.focus() ;
     return false;
   }
   cansubmit=isSpace(document.eduEntry.NOkName,"Space not allowed")
   if( document.eduEntry.NOkName.value == "" )
   {
     alert( "Please provide Next of Kin Name!" );
     document.eduEntry.NOkName.focus() ;
     return false;
   }
   if( document.eduEntry.NOKRelationship.value == "na" )
   {
     alert( "Please provide Next of Kin Relationship!" );
     document.eduEntry.NOKRelationship.focus() ;
     return false;
   }
   cansubmit=isSpace(document.eduEntry.NOKPhone,"Space not allowed")
   if( document.eduEntry.NOKPhone.value == "" )
   {
     alert( "Please provide Next of Kin Phone No!" );
     document.eduEntry.NOKPhone.focus() ;
     return false;
   }
   cansubmit=isSpace(document.eduEntry.NOKAddress,"Space not allowed")
   if( document.eduEntry.NOKAddress.value == "" )
   {
     alert( "Please provide Next of Kin Address!" );
     document.eduEntry.NOKAddress.focus() ;
     return false;
   }
return( true );
}
}


</script>
                        




<script type="text/javascript" src="personal_files/popcalendar.js"></script>
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
<body><div onClick="bShow=true" id="calendar" style="z-index: 999; position: absolute; visibility: hidden;"><table style="border: 1px solid rgb(160, 160, 160); font-size: 11px; font-family: arial;" width="220" bgcolor="#ffffff"><tbody><tr bgcolor="#0000aa"><td><table width="218"><tbody><tr><td style="padding: 2px; font-family: arial; font-size: 11px;"><font color="#ffffff"><b><span id="caption"><span id="spanLeft" style="border: 1px solid rgb(51, 102, 255); cursor: pointer;" onmouseover='swapImage("changeLeft","left2.gif");this.style.borderColor="#88AAFF";window.status="Click to scroll to previous month. Hold mouse button to scroll automatically."' onClick="javascript:decMonth()" onmouseout='clearInterval(intervalID1);swapImage("changeLeft","left1.gif");this.style.borderColor="#3366FF";window.status=""' onmousedown='clearTimeout(timeoutID1);timeoutID1=setTimeout("StartDecMonth()",500)' onMouseUp="clearTimeout(timeoutID1);clearInterval(intervalID1)">&nbsp;<img id="changeLeft" src="personal_files/left1.gif" width="10" border="0" height="11">&nbsp;</span>&nbsp;<span id="spanRight" style="border: 1px solid rgb(51, 102, 255); cursor: pointer;" onmouseover='swapImage("changeRight","right2.gif");this.style.borderColor="#88AAFF";window.status="Click to scroll to next month. Hold mouse button to scroll automatically."' onmouseout='clearInterval(intervalID1);swapImage("changeRight","right1.gif");this.style.borderColor="#3366FF";window.status=""' onClick="incMonth()" onmousedown='clearTimeout(timeoutID1);timeoutID1=setTimeout("StartIncMonth()",500)' onMouseUp="clearTimeout(timeoutID1);clearInterval(intervalID1)">&nbsp;<img id="changeRight" src="personal_files/right1.gif" width="10" border="0" height="11">&nbsp;</span>&nbsp;<span id="spanMonth" style="border: 1px solid rgb(51, 102, 255); cursor: pointer;" onmouseover='swapImage("changeMonth","drop2.gif");this.style.borderColor="#88AAFF";window.status="Click to select a month."' onmouseout='swapImage("changeMonth","drop1.gif");this.style.borderColor="#3366FF";window.status=""' onClick="popUpMonth()"></span>&nbsp;<span id="spanYear" style="border: 1px solid rgb(51, 102, 255); cursor: pointer;" onmouseover='swapImage("changeYear","drop2.gif");this.style.borderColor="#88AAFF";window.status="Click to select a year."' onmouseout='swapImage("changeYear","drop1.gif");this.style.borderColor="#3366FF";window.status=""' onClick="popUpYear()"></span>&nbsp;</span></b></font></td><td align="right"><a href="javascript:hideCalendar()"><img src="personal_files/close.gif" alt="Close the Calendar" width="15" border="0" height="13"></a></td></tr></tbody></table></td></tr><tr><td style="padding: 5px;" bgcolor="#ffffff"><span id="content"></span></td></tr><tr bgcolor="#f0f0f0"><td style="padding: 5px;" align="center"><span id="lblToday">Today is <a onmousemove='window.status="Go To Current Month"' onmouseout='window.status=""' title="Go To Current Month" style="text-decoration: none; color: black;" href="javascript:monthSelected=monthNow;yearSelected=yearNow;constructCalendar();">Wed, 8 Jun	2011</a></span></td></tr></tbody></table></div><div id="selectMonth" style="z-index: 999; position: absolute; visibility: hidden;"></div><div id="selectYear" style="z-index: 999; position: absolute; visibility: hidden;"></div>



<table width="100%" border="0" cellpadding="0" cellspacing="0" height="100%">
<!-- fwtable fwsrc="MTN4U.png" fwbase="index.jpg" fwstyle="Dreamweaver" fwdocid = "1226677029" fwnested="0" -->
<tbody>
<tr>
  <td><img src="personal_files/spacer.gif" alt="" width="750" border="0" height="1"></td>
</tr>
<tr>
  <td class="centerAligned" valign="top" height="100"><div align="center"></div>
    <table width="750" border="0" cellpadding="0" cellspacing="0">
      <!-- fwtable fwsrc="Untitled" fwbase="top.gif" fwstyle="Dreamweaver" fwdocid = "2000728079" fwnested="0" -->
      <tbody>
        <tr>
          <td><img src="personal_files/spacer.gif" alt="" width="7" border="0" height="1"></td>
          <td><img src="personal_files/spacer.gif" alt="" width="78" border="0" height="1"></td>
          <td><img src="personal_files/spacer.gif" alt="" width="491" border="0" height="1"></td>
          <td><img src="personal_files/spacer.gif" alt="" width="153" border="0" height="1"></td>
          <td><img src="personal_files/spacer.gif" alt="" width="21" border="0" height="1"></td>
          <td><img src="personal_files/spacer.gif" alt="" width="1" border="0" height="1"></td>
        </tr>
        <tr>
          <td colspan="5"><img name="top_r1_c1" src="personal_files/spacer.gif" alt="" width="1" border="0" height="1"></td>
          <td><img src="personal_files/spacer.gif" alt="" width="1" border="0" height="11"></td>
        </tr>
        <tr>
          <td rowspan="4"><img name="top_r2_c1" src="personal_files/spacer.gif" alt="" width="1" border="0" height="1"></td>
          <td colspan="3" rowspan="4"><img src="images/oouthlogo.jpg" width="499" height="95"><img name="top_r4_c4" src="personal_files/spacer.gif" alt="" width="1" border="0" height="1"></td>
          <td>&nbsp;</td>
          <td><img src="personal_files/spacer.gif" alt="" width="1" border="0" height="17"></td>
        </tr>
        <tr>
          <td rowspan="3"><img name="top_r3_c5" src="personal_files/spacer.gif" alt="" width="1" border="0" height="1"></td>
          <td><img src="personal_files/spacer.gif" alt="" width="1" border="0" height="37"></td>
        </tr>
        <tr>
          <td><img src="personal_files/spacer.gif" alt="" width="1" border="0" height="25"></td>
        </tr>
        <tr>
          <td><img src="personal_files/spacer.gif" alt="" width="1" border="0" height="11"></td>
        </tr>
      </tbody>
    </table></td>
</tr>
<tr>
  <td class="mainNav" valign="top" height="21"><table width="750" border="0" cellpadding="0" cellspacing="0" height="21">
    <tbody>
      <tr>
        <td class="rightAligned" width="10">&nbsp;</td>
        <td class="mainNavTxt" valign="bottom"><table width="100%" border="0" cellpadding="0" cellspacing="0">
          <!-- fwtable fwsrc="Untitled" fwbase="nav.gif" fwstyle="Dreamweaver" fwdocid = "1284367442" fwnested="0" -->
          <tbody>
            <tr>
              <td><a href="http://careers.mtnonline.com/index.asp"></a></td>
              <td><img src="personal_files/spacer.gif" alt="" width="8" border="0" height="8"></td>
              <td><a href="http://careers.mtnonline.com/departments.asp"></a></td>
              <td><img src="personal_files/spacer.gif" alt="" width="8" border="0" height="8"></td>
              <td><a href="http://careers.mtnonline.com/vacancies.asp"></a></td>
              <td><img src="personal_files/spacer.gif" alt="" width="8" border="0" height="8"></td>
              <td><a href="http://careers.mtnonline.com/lifeatmtn.asp"></a></td>
              <td><img src="personal_files/spacer.gif" alt="" width="8" border="0" height="8"></td>
              <td><a href="http://careers.mtnonline.com/mycv.asp"></a></td>
              <td><img src="personal_files/spacer.gif" alt="" width="8" border="0" height="8"></td>
              <td><a href="http://careers.mtnonline.com/logout.asp"></a></td>
            </tr>
          </tbody>
        </table></td>
        <td class="leftAligned" width="12">&nbsp;</td>
      </tr>
    </tbody>
  </table></td>
</tr>
<tr>
  <td class="dividerCenterAligned" valign="top" height="1"><img name="index_r3_c1" src="personal_files/index_r3_c1.jpg" alt="" width="750" border="0" height="1"></td>
</tr>
<tr>
  <td class="globalNav" valign="top" height="25"><table width="750" border="0" cellpadding="0" cellspacing="0" height="21">
    <tbody>
      <tr>
        <td class="rightAligned" width="10"><img src="personal_files/spacer.gif" width="1" height="1"></td>
        <td><img src="personal_files/spacer.gif" width="6"></td>
        <td class="leftAligned" width="12"><img src="personal_files/spacer.gif" width="1" height="1"></td>
      </tr>
    </tbody>
  </table></td>
</tr>
<tr>
  <td class="dividerCenterAligned" valign="top" height="1"><img name="index_r5_c1" src="personal_files/index_r5_c1.jpg" alt="" width="750" border="0" height="1"></td>
</tr>
<tr>

<td class="innerPg" valign="top">
<table width="750" border="0" cellpadding="0" cellspacing="0">
  <tbody>
  <tr>
    <td rowspan="2" width="8"><img src="personal_files/spacer.gif" width="1" height="1"></td>
    <td colspan="2" class="breadcrumbs" valign="bottom" height="20">&nbsp;</td>
    <td rowspan="2" width="12"><img src="personal_files/spacer.gif" width="1" height="1"></td>
  </tr>
  <tr>
  
  <td class="Content" valign="top" width="180"><p>&nbsp;</p>
    <br>
    <table class="innerWhiteBox" width="96%" border="0" cellpadding="4" cellspacing="0">
      <tbody>
        <tr>
          <td class="sidenavtxt" align=""><p><em><font size="1" face="Verdana, Arial, Helvetica, sans-serif">Welcome,</font></em> <font size="1" face="Verdana, Arial, Helvetica, sans-serif"><span><?php echo ($_SESSION['FirstName']); ?></p>
            <p><a href="dashboard.php">DashBoard</a><br>
            </p>
            </tr>
      </tbody>
    </table>
    <br>
    <table class="innerWhiteBox" width="96%" border="0" cellpadding="4" cellspacing="0">
      <tbody>
        
      </tbody>
    </table>
    <br>
    <br>
    <table class="innerWhiteBox" width="96%" border="0" cellpadding="4" cellspacing="0">
      <tbody>
        
      </tbody>
    </table>
    <br>
    <script language="JavaScript1.2" src="personal_files/misc.htm"></script></td>
  <td rowspan="2" valign="top" class="error">
  <img src="personal_files/mycv.gif" width="350" height="30">
  <hr size="1" width="500" align="left" color="#cccccc">
  <table width="500" border="0" cellpadding="0" cellspacing="0">
    <tbody>
    <tr>
    
    <td class="toplinks2" valign="top">
    <div align="justify">
      <table class="Content" width="100%" border="0" cellpadding="4" cellspacing="0">
        <tbody>
        <tr>
          <td valign="top">
          <span class="homeContentSmaller">
              <?php if ((isset($_POST['dtp'])) && (($_POST['dtp'])== ($_SESSION['UserID']))){ echo "<table class=\"errorBox\" width=\"500\" border=\"0\" cellpadding=\"2\" cellspacing=\"0\">
  <tbody><tr>
    <td>Your update was successful</td>
  </tr>
</tbody></table>" ;} ?>
              <br><?php if ((isset($_POST["Submit"])) && ($_POST["Submit"] == "Update")) { echo "Medical Record No = ". $MRN ;}?>
            </span>
          <?php if ((isset($_GET['success'])) and ($_GET['success'] == "true")){?>
				<script type="text/javascript" language="javascript">
				alert("Record's Saved Successfully");
				</script>
				
				<?php } ?>
				<form action="<?php echo $editFormAction; ?>" method="POST" name="eduEntry" onSubmit="return(validate()); ">
				  <p>
            <fieldset>
              <legend class="contentHeader1">Patient Type </legend>
              <table width="96%" align="center" cellpadding="4" cellspacing="0">
                <tbody>
                  <tr valign="top" align="left">
                    <td colspan="2" height="1"><img src="personal_files/spacer.gif" width="1" height="1"></td>
                  </tr>
                  <tr>
                    <td class="greyBgd" width="43%" align="right" height="35"> Patient Data:
                    <td class="greyBgd" width="43%" align="left" height="35"><select name="patstatus1" class="innerBox" onChange="javascript:Expand90(this)">
<option value="NC" >New Patient</option>
                      <option value="OC">Existing Patient</option>
                    </select>
                    </tr>
                  <tr>
                    <td class="greyBgd" width="43%" align="right" height="35"> Patient Type:
                    <td class="greyBgd" width="43%" align="left" height="35"><select name="patType" class="innerBox" id="patType" onChange="javascript:Ipopcases1(this)">
                      <option value="na" <?php if (!(strcmp("na", "Out Patient"))) {echo "selected=\"selected\"";} ?>>-Select-</option>
                      <option value="IP" <?php if (!(strcmp("IP", "Out Patient"))) {echo "selected=\"selected\"";} ?>>In Patient</option>
                      <option value="OP" value="OP"selected <?php if (!(strcmp("OP", "Out Patient"))) {echo "selected=\"selected\"";} ?>>
                      
                                                                                                    Out Patient
                                                                                                    
                      </option>
                    </select>
                    </tr>
                  <tr>
                    <td class="greyBgd" width="43%" align="right" height="35"> Patient Category:
                    <td class="greyBgd" width="43%" align="left" height="35"><div id="patcategory">
                      <select name="patCategory" class="innerBox" id="patCategory" >
                        <option>Select</option>
                        <option value="Appointment">Appointment</option>
                        <option value="Referral">Referral</option>
                        <option value="Walk In">Walk In</option>
                      </select>
                    </div>
                    </tr>
                </table>
            </fieldset>
            <div id="patnc" name="patnc" style="display: block; margin-left: 0em;">
              <fieldset>
                <legend class="contentHeader1">Personal Information </legend>
                <table width="96%" align="center" cellpadding="4" cellspacing="0">
                  <tbody>
                    <tr valign="top" align="left">
                      <td colspan="2" height="1"><img src="personal_files/spacer.gif" width="1" height="1"></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" align="right" height="35">Medical Record No.:<font color="red">*</font></td>
                      <td class="greyBgd" align="left"><p>
                        <input name="mrn" type="text" class="innerBox" id="mrn" onBlur="Javascript:onSelectedSearchMRN(this.value)">
                      </p>
                        <div id="UploadSearchResult"></div></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" align="right" height="35">Title:<font color="red">*</font></td>
                      <td class="greyBgd" align="left"><select name="sfxname" style="width:145px" class="innerBox" >
                        <option value="na">-Select-</option>
                        <option value="Mr">Mr</option>
                        <option value="Miss">Miss</option>
                        <option value="Mrs">Mrs</option>
                        <option value="Dr">Dr</option>
                        <option value="Baby">Baby</option>
                        <option value="Master">Master</option>
                      </select></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" width="43%" align="right" height="35">First Name: <font color="red">*</font></td>
                      <td class="greyBgd" width="57%" align="left"><input name="Fname" type="text" class="innerBox" id="Fname">
                        *</td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" width="43%" align="right" height="35">Middle Name: </td>
                      <td class="greyBgd" width="57%" align="left"><input name="Mname" class="innerBox" id="Mname" type="text"></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" width="43%" align="right" height="35">Last Name:<font color="red">*</font></td>
                      <td class="greyBgd" width="57%" align="left"><input name="Lname" class="innerBox" id="Lname" type="text">
                        *</td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" align="right" height="35">Maiden Name</td>
                      <td class="greyBgd" align="left"><input name="MaidenName" class="innerBox" id="MaidenName" type="text"></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" align="right" height="35">Mother's Name:</td>
                      <td class="greyBgd" align="left"><input name="Mothersname" class="innerBox" id="Mothersname" type="text"></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" width="43%" align="right" height="35">Gender:<font color="red">*</font></td>
                      <td class="greyBgd" width="57%" align="left"><p>
                        <label>
                          <input  name="gender" value="Male" checked="checked" type="radio">
                          Male</label>
                        <label>
                          <input  name="gender" value="Female" type="radio">
                          Female</label>
                        <br>
                      </p></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" align="right" height="35">Blood Group:</td>
                      <td class="greyBgd" align="left"><select name="bloodGroup" class="innerBox" id="bloodGroup">
                        <option selected="selected" value="">Select ...</option>
                        <?php
do {  
?>
                        <option value="<?php echo $row_bloodgroup['bloodgroup']?>"><?php echo $row_bloodgroup['bloodgroup']?></option>
                        <?php
} while ($row_bloodgroup = mysqli_fetch_assoc($bloodgroup));
  $rows = mysqli_num_rows($bloodgroup);
  if($rows > 0) {
      mysql_data_seek($bloodgroup, 0);
	  $row_bloodgroup = mysqli_fetch_assoc($bloodgroup);
  }
?>
                      </select></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" width="43%" align="right" height="35">Marital Status: </td>
                      <td class="greyBgd" width="57%" align="left"><select name="MStatus" class="innerBox" id="MStatus">
                        <option value="na" selected="SELECTED">Select ...</option>
                        <option value="Single" selected="selected"> Single</option>
                        <option value="Married"> Married</option>
                        <option value=" Divorced"> Divorced</option>
                        <option value="Widow">Widow</option>
                        <option value="Widower">Widower</option>
                      </select></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" width="43%" align="right" height="35">Date of Birth [mm/dd/yyyy]:<font color="red">*</font></td>
                      <td class="greyBgd" width="57%" align="left"><input name="DOB" type="text" class="innerBox" id="DOB" readonly>
                        <input src="personal_files/ew_calendar.gif" alt="Pick a Date" onClick="popUpCalendar(this, this.form.DOB,'yyyy-mm-dd');return false;" type="image">
                        * </td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" width="43%" align="right" height="35">House No.:<font color="red">*</font></td>
                      <td class="greyBgd" width="57%" align="left"><input name="Address" class="innerBox" id="Address" type="text"></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" width="43%" align="right" height="35">Address 2: </td>
                      <td class="greyBgd" width="57%" align="left"><input name="Address2" class="innerBox" id="Address2" type="text"></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" width="43%" align="right" height="35">City:<font color="red">*</font></td>
                      <td class="greyBgd" width="57%" align="left"><input name="City" class="innerBox" id="City" type="text"></td>
                    </tr>
                    <tr valign="middle" align="left">
                      <td class="greyBgd" width="43%" align="right" height="35">State:<font color="red">*</font></td>
                      <td class="greyBgd" width="57%" align="left"><select name="State" class="innerBox" id="State">
                       <option value="" selected="selected" <?php if (!(strcmp("", "Ogun State"))) {echo "selected=\"selected\"";} ?>>Select State ...</option>
                        <?php
do {  
?>
                        <option value="<?php echo $row_state2['State']?>"><?php echo $row_state2['State']?></option>
                        <?php
} while ($row_state2 = mysqli_fetch_assoc($state2));
  $rows = mysqli_num_rows($state2);
  if($rows > 0) {
      mysql_data_seek($state2, 0);
	  $row_state2 = mysqli_fetch_assoc($state2);
  }
?>
                      </select>
                      
                    </td>
                    </tr>
                    
                  <tr valign="middle" align="left">
                    <td class="greyBgd" width="43%" align="right" height="35">Country of Origin: </td>
                    <td class="greyBgd" width="57%" align="left"><select name="countryOrigin" size="1" class="innerBox" id="countryOrigin" onChange="javascript:onSelected(this)">
                      <option value="" selected="selected" <?php if (!(strcmp("", "Nigeria"))) {echo "selected=\"selected\"";} ?>>Select Country ...</option>
                      <?php
do {  
?>
                      <option value="<?php echo $row_country['Country']?>"<?php if (!(strcmp($row_country['Country'], "Nigeria"))) {echo "selected=\"selected\"";} ?>><?php echo $row_country['Country']?></option>
                      <?php
} while ($row_country = mysqli_fetch_assoc($country));
  $rows = mysqli_num_rows($country);
  if($rows > 0) {
      mysql_data_seek($country, 0);
	  $row_country = mysqli_fetch_assoc($country);
  }
?>
                    </select></td>
                  </tr>
                  <tr>
                    <td class="greyBgd" align="right" height="35">State of Origin:</td>
                    <td class="greyBgd"><div id="state"  name="state" style="display: block; margin-left: 0em;">
                      <select name="StateOfOrigin" class="innerBox" id="StateOfOrigin">
                        <option value="N/A" selected="selected" <?php if (!(strcmp("N/A", "Ogun"))) {echo "selected=\"selected\"";} ?>>N/A</option>
                        <option value="Ogun" <?php if (!(strcmp("Ogun", "Ogun"))) {echo "selected=\"selected\"";} ?>>Ogun</option>
                      </select>
                    </div>
                      * </td>
                  </tr>
                  <tr>
                    <td class="greyBgd" align="right" height="35">Tribe:</td>
                    <td class="greyBgd"><select name="Tribe" class="innerBox" id="Tribe">
                      <option selected="selected" value="" <?php if (!(strcmp("", "Yoruba"))) {echo "selected=\"selected\"";} ?>>Select ...</option>
                      <option value="Yoruba" <?php if (!(strcmp("Yoruba", "Yoruba"))) {echo "selected=\"selected\"";} ?>>Yoruba</option>
                      <?php
do {  
?>
                      <option value="<?php echo $row_tribe['tribe']?>"<?php if (!(strcmp($row_tribe['tribe'], "Yoruba"))) {echo "selected=\"selected\"";} ?>><?php echo $row_tribe['tribe']?></option>
                      <?php
} while ($row_tribe = mysqli_fetch_assoc($tribe));
  $rows = mysqli_num_rows($tribe);
  if($rows > 0) {
      mysql_data_seek($tribe, 0);
	  $row_tribe = mysqli_fetch_assoc($tribe);
  }
?>
                    </select></td>
                  </tr>
                  <tr>
                    <td class="greyBgd" align="right" height="35">Education Level:</td>
                    <td class="greyBgd"><select name="EducationLevel" size="1" id="EducationLevel">
                      <option>Select</option>
                      <option value="1st School Leaving Cert">1st School Leaving Cert </option>
                      <option value="Advanced Diploma">Advanced Diploma </option>
                      <option value="Associate Degree">Associate Degree </option>
                      <option value="Associate of Science">Associate of Science </option>
                      <option value="Bachelor">Bachelor </option>
                      <option value="Bachelor of Arts">Bachelor of Arts </option>
                      <option value="Bachelor of Business">Bachelor of Business </option>
                      <option value="Bachelor of Education">Bachelor of Education </option>
                      <option value="Bachelor of Engineer">Bachelor of Engineer </option>
                      <option value="Bachelor of Engineering">Bachelor of Engineering </option>
                      <option value="Bachelor of Law">Bachelor of Law </option>
                      <option value="Bachelor of Science">Bachelor of Science </option>
                      <option value="Bachelor of Technology">Bachelor of Technology </option>
                      <option value="Call To Bar">Call To Bar </option>
                      <option value="Certificate">Certificate </option>
                      <option value="City &amp; Guilds Cert.">City &amp; Guilds Cert. </option>
                      <option value="Diploma">Diploma </option>
                      <option value="Doctorate Degree">Doctorate Degree </option>
                      <option value="Executive Masters">Executive Masters </option>
                      <option value="Fellow">Fellow </option>
                      <option value="Full Technological C">Full Technological C </option>
                      <option value="General Cert. of Education">General Cert. of Education </option>
                      <option value="Grade II Certificate">Grade II Certificate </option>
                      <option value="Higher Diploma">Higher Diploma </option>
                      <option value="Higher National Diploma">Higher National Diploma </option>
                      <option value="Higher School Certificate">Higher School Certificate </option>
                      <option value="Institute of Charter">Institute of Charter </option>
                      <option value="Mast. Ener &amp; Pet. Eco">Mast. Ener &amp; Pet. Eco </option>
                      <option value="Master in Public Admin">Master in Public Admin </option>
                      <option value="Master in Technology">Master in Technology </option>
                      <option value="Master of Arts">Master of Arts </option>
                      <option value="Masters">Masters </option>
                      <option value="Masters in Bus Admin">Masters in Bus Admin </option>
                      <option value="Masters in Engineering">Masters in Engineering </option>
                      <option value="Masters in Law">Masters in Law </option>
                      <option value="Masters in Philosophy">Masters in Philosophy </option>
                      <option value="Masters of Science">Masters of Science </option>
                      <option value="MBBS">MBBS </option>
                      <option value="Member Ints. of Pers">Member Ints. of Pers </option>
                      <option value="Modern II Certificate">Modern II Certificate </option>
                      <option value="Modern III Certificate">Modern III Certificate </option>
                      <option value="Modern Sch. Leaving Cert">Modern Sch. Leaving Cert </option>
                      <option value="Nat. Postgraduate">Nat. Postgraduate </option>
                      <option value="National Cert of Education">National Cert of Education </option>
                      <option value="National Diploma">National Diploma </option>
                      <option value="NSE Graduate">NSE Graduate </option>
                      <option value="Ordinary National Diploma">Ordinary National Diploma </option>
                      <option value="Post Graduate Certificate">Post Graduate Certificate </option>
                      <option value="Post Graduate Diploma">Post Graduate Diploma </option>
                      <option value="Professional Diploma">Professional Diploma </option>
                      <option value="Reg. Midwife">Reg. Midwife </option>
                      <option value="Reg. Nurse">Reg. Nurse </option>
                      <option value="Reg. Surveyor">Reg. Surveyor </option>
                      <option value="Secretarial">Secretarial </option>
                      <option value="Senior School Cert. Exam.">Senior School Cert. Exam. </option>
                      <option value="Tech. Teachers Cert.">Tech. Teachers Cert. </option>
                      <option value="Trade Test Certificate">Trade Test Certificate </option>
                      <option value="WASC">WASC </option>
                      <option value="West African Postgrd Med">West African Postgrd Med </option>
                    </select></td>
                  </tr>
                  <tr valign="top" align="left">
                    <td class="greyBgd" valign="middle" width="43%" align="right" height="35">Occupation : </td>
                    <td class="greyBgd" valign="middle" width="57%" align="left"><input name="Occupation" class="innerBox" id="Occupation" type="text">
                      * </td>
                  </tr>
                  <tr valign="middle" align="left">
                    <td class="greyBgd" align="right" height="35">Religion:</td>
                    <td class="greyBgd" align="left"><select name="Religion" size="1" class="innerBox" id="Religion">
                      <option value="" <?php if (!(strcmp("", "Christianity"))) {echo "selected=\"selected\"";} ?>>N/A</option>
                      <option value="Christianity" <?php if (!(strcmp("Christianity", "Christianity"))) {echo "selected=\"selected\"";} ?>>Christianity</option>
                      <option value="Islam" <?php if (!(strcmp("Islam", "Christianity"))) {echo "selected=\"selected\"";} ?>>Islam</option>
                      <option value="Traditional" <?php if (!(strcmp("Traditional", "Christianity"))) {echo "selected=\"selected\"";} ?>>Tradiontional</option>
                    </select></td>
                  </tr>
                  <tr valign="middle" align="left">
                    <td class="greyBgd" width="43%" align="right" height="35">Mobile Phone:<font color="red">*</font></td>
                    <td class="greyBgd" width="57%" align="left"><input name="MobilePhone" class="innerBox" id="MobilePhone" type="text"></td>
                  </tr>
                  <tr valign="middle" align="left">
                    <td class="greyBgd" width="43%" align="right" height="35">E-mail Address: </td>
                    <td class="greyBgd" width="57%" align="left"><input name="EmailAddress" class="innerBox" id="EmailAddress" type="text"></td>
                  </tr>
                  <tr valign="top" align="left">
                    <td colspan="2" valign="middle" align="center" height="10"><p>
                      <fieldset>
                        <legend class="contentHeader1">Pastor/Imam-in-Charge</legend>
                        <script language="JavaScript" type="text/JavaScript">
<!--
function GP_popupConfirmMsg(msg) { //v1.0
  document.MM_returnValue = confirm(msg);
}
//-->
  </script>
                        <table width="96%" align="center" cellpadding="4" cellspacing="0">
                          <tbody>
                            <tr valign="middle" align="left">
                              <td width="36%" height="35" align="right" class="greyBgd">Name of Pastor/Imam-in-charge: </td>
                              <td width="64%" align="left" class="greyBgd"><input name="NamePastor" class="innerBox" id="txtDayPhone4" type="text"></td>
                            </tr>
                            <tr valign="middle" align="left">
                              <td class="greyBgd" align="right" height="35"><p>Telephone No of Pastor/Imam: </p></td>
                              <td class="greyBgd" align="left"><input name="PhonePastor" class="innerBox" id="txtDayPhone3" type="text"></td>
                            </tr>
                            <tr valign="middle" align="left">
                              <td class="greyBgd" align="right" height="35">Name/Address of Church/Mosque: </td>
                              <td class="greyBgd" align="left"><input name="Name_Address_Church" class="innerBox" id="Name_Address_Church" type="text"></td>
                            </tr>
                            <tr valign="top" align="left">
                              <td colspan="2" class="Content" align="right" height="3"><img src="workhistory_files/spacer.gif" width="1" height="1"></td>
                            </tr>
                          </tbody>
                        </table>
                        <br>
                      </fieldset>
                      <p>  
                      <fieldset>
                        <legend class="contentHeader1">Next of Kin
                          <script language="JavaScript" type="text/JavaScript">
<!--
function GP_popupConfirmMsg(msg) { //v1.0
  document.MM_returnValue = confirm(msg);
}
//-->
  </script>
                          </legend>
                        <table width="96%" align="center" cellpadding="4" cellspacing="0">
                          <tbody>
                            <tr valign="top">
                              <td width="36%" height="35" align="right" valign="middle" class="greyBgd"> Next of Kin:<font color="red">*</font></td>
                              <td class="greyBgd" valign="middle" width="64%"><input name="NOkName" type="text" class="innerBox" id="NOkName"></td>
                            </tr>
                            <tr valign="top">
                              <td height="35" align="right" valign="middle" class="greyBgd"> Relationship to Patient:<font color="red">*</font></td>
                              <td class="greyBgd" valign="middle"><select name="NOKRelationship" class="innerBox" id="NOKRelationship">
                                <option selected="selected" value="" <?php if (!(strcmp("", "Parent"))) {echo "selected=\"selected\"";} ?>>Select ...</option>
                                <?php
do {  
?>
                                <option value="<?php echo $row_nokRelationship['relationship']?>"<?php if (!(strcmp($row_nokRelationship['relationship'], "Parent"))) {echo "selected=\"selected\"";} ?>><?php echo $row_nokRelationship['relationship']?></option>
                                <?php
} while ($row_nokRelationship = mysqli_fetch_assoc($nokRelationship));
  $rows = mysqli_num_rows($nokRelationship);
  if($rows > 0) {
      mysql_data_seek($nokRelationship, 0);
	  $row_nokRelationship = mysqli_fetch_assoc($nokRelationship);
  }
?>
                              </select></td>
                            </tr>
                            <tr valign="top">
                              <td height="35" align="right" valign="middle" class="greyBgd"> Next of Kin Phone No:<font color="red">*</font></td>
                              <td class="greyBgd" valign="middle"><input name="NOKPhone" type="text" class="innerBox" id="NOKPhone"></td>
                            </tr>
                            <tr valign="top">
                              <td height="35" align="right" valign="middle" class="greyBgd"> Next of Kin Address:<font color="red">*</font></td>
                              <td class="greyBgd" valign="middle"><input name="NOKAddress" type="text" class="innerBox" id="NOKAddress">
                                <input name="same" type="checkbox" id="same" onChange="javascript:sameasabove()" value="checked">
                                <label for="same"> Same as above</label></td>
                            </tr>
                            <tr valign="top" align="right">
                              <td colspan="2" class="Content" height="3"><img src="workhistory_files/spacer.gif" width="1" height="1"></td>
                            </tr>
                          </tbody>
                        </table>
                        <br>
                      </fieldset>
                      </p>
                      <p>
                        <input name="Submit" class="formbutton" value="Save" type="submit">&nbsp;&nbsp;<input name="Reset" class="formbutton" value="Reset" type="reset">
                      </p></td>
                  </tr>
                  <tr valign="top" align="left">
                    <td colspan="2" height="3"><img src="personal_files/spacer.gif" width="1" height="1"></td>
                  </tr>
                  </tbody>
                  </table>
              </fieldset>
            </div>
            <br>
            <p>
            <div id="patoc"  name="patoc" style="display: none; margin-left: 0em;">
              <fieldset>
                <legend class="contentHeader1">Search Existing Patient</legend>
                <table width="96%" align="center" cellpadding="4" cellspacing="0">
                  <tbody>
                    <tr valign="top">
                      <td width="36%" height="35" align="right" valign="middle" class="greyBgd"> Enter Search Criteria e.g. First Name,Last Name, Telephone No, MRN: </td>
                      <td class="greyBgd" valign="middle" width="64%"><input name="SearchMRN" type="text" class="innerBox" id="SearchMRN">
                        <span class="errorBox"><a onClick="javascript:clearbox()" href="#">X</a></span></td>
                    </tr>
                    <tr valign="top">
                      <td height="35" align="right" valign="middle" class="greyBgd"> Date of Birth : </td>
                      <td class="greyBgd" valign="middle"><input name="SDOb" type="text" class="innerBox" id="SDOb" readonly>
                        <input src="personal_files/ew_calendar.gif" alt="Pick a Date" onClick="popUpCalendar(this, this.form.SearchMRN,'yyyy-mm-dd');return false;" type="image">
                        * </td>
                    </tr>
                    <tr valign="top" align="right">
                      <td height="3" colspan="2" align="center" class="Content"><img src="workhistory_files/spacer.gif"width="1" height="1"> <input type="hidden" name="MM_insert" value="eduEntry">
<input name="ButtonSearch" type="button" class="formbutton" id="ButtonSearch" value="Search" onClick="javascript:onSelectedSearch()"> &nbsp;&nbsp;<input name="ButtonSearch2" type="button" class="formbutton" id="ButtonSearch2" value="Reset" onClick="javascript:reset()"></td>
                    </tr>
                  </tbody>
                </table>
              </fieldset>
            </div>
        </form>
          </p>
         
<div id="patSearchResult"></div>
          <p><br>
        </p>
        </td>
        </tr>
        
        </tbody>
        </table>
    </div>
    </td>
    
    </tr>
    
    </tbody>
    </table>
  <br>
  <br>
  <br>
  </td>
  
  </tr>
  
  <tr>
    <td class="Content" valign="top">&nbsp;</td>
  </tr>
  </tbody>
</table>
</td>

</tr>

<tr>
  <td class="innerPg" valign="top" height="1"><img name="index_r7_c1" src="personal_files/index_r7_c1.jpg" alt="" width="750" border="0" height="1"></td>
</tr>
<tr>
  <td class="innerPg" valign="top" height="21"><table class="contentHeader1" width="750" border="0" cellpadding="0" cellspacing="0" height="21">
    <tbody>
      <tr>
        <td class="rightAligned" width="10">&nbsp;</td>
        <td class="baseNavTxt">&nbsp;</td>
        <td class="leftAligned" width="12">&nbsp;</td>
      </tr>
    </tbody>
  </table></td>
</tr>
<tr>
  <td class="innerPg" valign="top" height="1"><img name="index_r9_c1"mysql_free_result($country);9_c1.jpg" alt="" width="750" border="0" height="1"></td>
</tr>
<tr>
  <td class=

mysql_free_result($SearchResult);

mysql_free_result($SearchResult);"innerPg" valign=

mysql_free_result($state2);"top">&nbsp;</td>
</tr>
</tbody>
</table>
</body></html>
<?php
mysql_free_result($country);

mysql_free_result($tribe);

mysql_free_result($nokRelationship);

mysql_free_result($bloodgroup);
?>
