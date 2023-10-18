#!/bin/bash
#CREATE NEW DB in mariadb
#####
d1="$(date +%h%d%y_%N)"; echo -e "\n $green $d1 $re";
mysql -u aaaa -p -e"CREATE DATABASE IF NOT EXISTS $d1;flush privileges;SHOW DATABASES;"
echo -e "\n $cyan $d1 $re \n\n";
####
