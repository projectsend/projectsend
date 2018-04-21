<?php
function generate_password(){
	$length=12; 
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVXYZ0123456789!@#$%^&*()_-=+;:,.?";
    echo substr(str_shuffle($chars),0,$length);
}

//here you can do some "routing"
$func = $_POST['func']; //remember to escape it

switch ($func) {
    case 'generate_password':
        generate_password();
        break;
    default:
        //function not found, error or something
        break;
}		
?>