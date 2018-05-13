<?php
$directory_path = dirname(__FILE__) . '\picture';

if(file_exists($directory_path)) {} 
else {
	mkdir($directory_path ,0777, TRUE);
}

?>