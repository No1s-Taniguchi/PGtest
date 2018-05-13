<?php

require_once dirname(__FILE__).'/TwitterOAuth.php';
require_once dirname(__FILE__).'/mkdir.php';

define('CONSUMER_KEY', '');
define('CONSUMER_SECRET', '');
define('ACCESS_TOKEN', '');
define('ACCESS_TOKEN_SECRET', '');
 
function search(array $query)
{
  $toa = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);
  return $toa->get('search/tweets', $query);
}
 
$query = array(
  "q" => "JustinBieber filter:images exclude:retweets",
  "count" => "100",
  "include_entities" => "true",
);
  
$tweets = search($query);   //�c�C�[�g���
$number = 0;
$count = 0;
foreach ((array)$tweets->statuses as $value) {
    $value->created_at;      //�c�C�[�g����
    $value->id;              //�c�C�[�gID
    $value->text;            //�c�C�[�g�R�����g
    foreach((array)$value->extended_entities->media as $value_media){
        if($value_media->type == 'photo' && $value->id != $id && $count < 10){
            $value_media->id;          //�摜ID
            $value_media->media_url;   //�摜URL
            $ext = pathinfo($value_media->media_url,PATHINFO_EXTENSION);   //�摜�g���q
            $save_file = $directory_path.'\save_image' . $number . '.' . $ext;   //�ۑ��摜�t�@�C���� 
            $data = file_get_contents($value_media->media_url);   //�摜�擾
            file_put_contents($save_file,$data);  //�摜�ۑ�
            $number++;
            $count++;
            $id = $value->id;
        }
    }
}