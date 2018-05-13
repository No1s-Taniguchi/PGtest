<?php

include_once "simple_html_dom.php";
$html = file_get_html('https://no1s.biz/');

$elements = $html->find('a');
$results = array();
foreach($elements as $element){
if($element->href && !$element->title) {
	if($element->plaintext) {
		$results[] = $element->href . " ";
		$results[] = $element->plaintext . "\n";
	} else {
		$results[] = $element->href . "\n";
	}
}elseif($element->href && $element->title){
	$results[] = $element->href . " ";
	$results[] = $element->title . "\n";
}
}

$results = array_unique($results);
foreach($results as $result) {
	echo $result;
}