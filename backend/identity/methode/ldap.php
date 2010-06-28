<?php
/* This module automatically inserts valid LDAP users into OCS operators table.
 *
 * The userlevel is defined according to conditions defined in the following configuration fields:
 * 
 * - CONEX_LDAP_CHECK_FIELD1_NAME
 * - CONEX_LDAP_CHECK_FIELD1_VALUE
 * - CONEX_LDAP_CHECK_FIELD1_ROLE
 * - CONEX_LDAP_CHECK_FIELD2_NAME
 * - CONEX_LDAP_CHECK_FIELD2_VALUE
 * - CONEX_LDAP_CHECK_FIELD2_ROLE
 * 
 * If any of these attributes are defined (and found on the LDAP query), they're used to determine the correct
 * user level and role.
 * 
 * in case of success, an array is returned with the access data in the following format: 
 * array('accesslvl'=>%%,'tag_show'=>array(%,%,%,%,%...))
 * 
 * else, an error code is returned.
 * 
 * CONEX_LDAP_CHECK_FIELD1_NAME="thisGuyIsAdmin"
 * CONEX_LDAP_CHECK_FIELD1_VALUE="0"
 * CONEX_LDAP_CHECK_FIELD1_ROLE="user"
 * CONEX_LDAP_CHECK_FIELD2_NAME="thisGuyIsAdmin"
 * CONEX_LDAP_CHECK_FIELD2_VALUE="1"
 * CONEX_LDAP_CHECK_FIELD2_ROLE="sadmin"
 * In logical terms:
 * if thisGuyIsAdmin=0 then
 *    role=user
 * else if thisGuyIsAdmin=1 then
 *    role=sadmin
 *    
 *    Note: the default user levels in OCS currently are "admin", "ladmin" and "sadmin". The above is just an example.
 * 
 */

require_once ('require/function_files.php');
// page name
$name="ldap.php";
connexion_local();

// select the main database
mysql_select_db($db_ocs,$link_ocs);


// retrieve LDAP-related config values into an array
$sql="select substr(NAME,7) as NAME,TVALUE from config where NAME like '%CONEX%'";
$res=mysql_query($sql, $link_ocs) or die(mysql_error($link_ocs));
while($item = mysql_fetch_object($res)){
    $config[$item->NAME]=$item->TVALUE;
    define ($item->NAME,$item->TVALUE);
}

// checks if the user already exists 
$reqOp="SELECT new_accesslvl as accesslvl FROM operators WHERE id='".$_SESSION['OCS']["loggeduser"]."'";
$resOp=mysql_query($reqOp, $link_ocs) or die(mysql_error($link_ocs));

// defines the user level according to specific LDAP attributes
// default: normal user
$defaultRole='admin'; 
$defaultLevel='2';

// Checks if the custom fields are valid
$f1_name=$config['LDAP_CHECK_FIELD1_NAME'];
$f2_name=$config['LDAP_CHECK_FIELD2_NAME'];
$f1_value=$_SESSION['OCS']['details'][$f1_name];
$f2_value=$_SESSION['OCS']['details'][$f2_name];

if ($f1_value != '') 
{
    if ($f1_value == $config['LDAP_CHECK_FIELD1_VALUE'])
    {
        $defaultRole=$config['LDAP_CHECK_FIELD1_ROLE'];
      //  $defaultLevel=$config['LDAP_CHECK_FIELD1_USERLEVEL'];
    }
}

if ($f2_value != '') 
{
    if ($f2_value == $config['LDAP_CHECK_FIELD2_VALUE'])
    {
        $defaultRole=$config['LDAP_CHECK_FIELD2_ROLE'];
     //   $defaultLevel=$config['LDAP_CHECK_FIELD2_USERLEVEL'];
    }
}

// uncomment this section for DEBUG
// note: cannot use the global DEBUG variable because this happens before the toggle is available.
/*
    echo ("field1: ".$f1_name." value=".$f1_value." condition: ".$config['LDAP_CHECK_FIELD1_VALUE']." role=".$config['LDAP_CHECK_FIELD1_ROLE']." level=".$config['LDAP_CHECK_FIELD1_USERLEVEL']."<br>");
    echo ("field2: ".$item['CONEX_LDAP_CHECK_FIELD2_NAME']." value=".$f2_value." condition: ".$config['LDAP_CHECK_FIELD2_VALUE']." role=".$config['LDAP_CHECK_FIELD2_ROLE']." level=".$config['LDAP_CHECK_FIELD2_USERLEVEL']."<br>");
    echo ("user: ".$_SESSION['OCS']["loggeduser"]." will have level=".$defaultLevel." and role=".$defaultRole."<br>");
*/

// if it doesn't exist, create the user record
if (!mysql_fetch_object($resOp)) {


    $reqInsert="INSERT INTO ocsweb.operators (
        ID,
        FIRSTNAME,
        LASTNAME,
        PASSWD,
        COMMENTS,
        NEW_ACCESSLVL,
        EMAIL,
        USER_GROUP
            )
            VALUES (
                    '".$_SESSION['OCS']["loggeduser"]."', '".$_SESSION['OCS']['details']['sn']."', '', NULL, 'LDAP', '".$defaultRole."', '".$_SESSION['OCS']['details']['mail']."', NULL )";

}
else
{

    // else update it
    $reqInsert="UPDATE ocsweb.operators SET 
        FIRSTNAME='".$_SESSION['OCS']['details']['sn']."',
        LASTNAME='',
        PASSWD=NULL,
        COMMENTS='LDAP',
        NEW_ACCESSLVL='".$defaultRole."',
        EMAIL='".$_SESSION['OCS']['details']['mail']."',
        USER_GROUP=NULL
            WHERE ID='".$_SESSION['OCS']["loggeduser"]."';";
};

// Execute the query to insert/update the user record
mysql_query($reqInsert) or die ("error inserting user: ".mysql_error($link_ocs));

// repeat the query and define the needed OCS variables
// note: original OCS code below
$resOp=mysql_query($reqOp, $link_ocs) or die(mysql_error($link_ocs));
$rowOp=mysql_fetch_object($resOp);

if (isset($rowOp -> accesslvl)){
    $lvluser=$rowOp -> accesslvl;
    $ms_cfg_file=$_SESSION['OCS']['main_sections_dir'].$lvluser."_config.txt";
    $search=array('RESTRICTION'=>'MULTI');
    $res=read_configuration($ms_cfg_file,$search);
    $restriction=$res['RESTRICTION']['GUI'];
    //Si l'utilisateur a des droits limit�s
    //on va rechercher les tags sur lesquels il a des droits
    if ($restriction == 'YES'){
        $sql="select tag from tags where login='".$_SESSION['OCS']["loggeduser"]."'";
        $res=mysql_query($sql, $link_ocs) or die(mysql_error($link_ocs));
        while ($row=mysql_fetch_object($res)){    
            $list_tag[$row->tag]=$row->tag;
        }
        if (!isset($list_tag))
            $ERROR=$l->g(893);
    }elseif (($restriction != 'NO')) 
    $ERROR=$restriction;
}else
$ERROR=$l->g(894);

?>