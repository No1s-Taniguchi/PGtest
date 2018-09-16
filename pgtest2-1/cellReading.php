<?php
mb_internal_encoding("UTF-8");
define('API_KEY', '');

$id = "11BCnspCt2Mut3nhc4WMY6CYTd0zF9C3eCzsk1AEpKLM";
$range = "sales!A1:E6";
$url = "https://sheets.googleapis.com/v4/spreadsheets/".$id."/values/".$range."?key=".API_KEY;
$jsonData = file_get_contents($url);
preg_match("/[0-9]{3}/", $http_response_header[0], $stcode);
if ((int)$stcode[0] >= 200 && (int)$stcode <= 299) {
    $data = json_decode($jsonData);
    $values = $data->values;
    foreach ((array)$values as $value) {
        for ($i = 0; $i < count($value); $i++) {
            echo "'".$value[$i]."',";
        }
        echo "\n";
    }
} else {
    echo "An error occurred";
}
