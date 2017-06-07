<?php
// basic sequence with LDAP is connect, bind, search, interpret search
// result, close connection

echo "<h3>LDAP query test</h3>";
echo "Connecting ...";
$ds=ldap_connect("ldap.service.uci.edu");  // must be a valid LDAP server!
echo "connect result is " . $ds . "<br />";

if ($ds) { 
   echo "Binding ..."; 
   $r=ldap_bind($ds);    // this is an "anonymous" bind, typically
                           // read-only access
   echo "Bind result is " . $r . "<br />";

   echo "Searching for (campusid=000000137118) ..."; // this is a test campusid
   // Search surname entry
   $sr=ldap_search($ds, "ou=University of California Irvine,o=University of California, c=US", "campusid=000000137118");  
   echo "Search result is " . $sr . "<br />";

   echo "Number of entires returned is " . ldap_count_entries($ds, $sr) . "<br />";

   echo "Getting entries ...<p>";
   $info = ldap_get_entries($ds, $sr);
   echo "Data for " . $info["count"] . " items returned:<p>";

   for ($i=0; $i<$info["count"]; $i++) {
       echo "dn is: " . $info[$i]["dn"] . "<br />";
       echo "first cn entry is: " . $info[$i]["cn"][0] . "<br />";
       echo "first type is: " . $info[$i]["type"][0] . "<br /><hr />";
   }

   echo "Closing connection";
   ldap_close($ds);

} else {
   echo "<h4>Unable to connect to LDAP server</h4>";
}
?>
