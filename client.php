<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
$con=socket_connect($socket,'127.0.0.1',6174);
if(!$con){socket_close($socket);exit;}
echo "Link\n";

$i = 0;
while($con && $i++<3){
//        $hear=socket_read($socket,1024);
//        echo $hear;
        $words=fgets(STDIN);
        file_put_contents('log.txt', $words,FILE_APPEND);
        if($words=="exit"){break;}
        socket_write($socket,$words);
}
socket_shutdown($socket);
socket_close($socket);