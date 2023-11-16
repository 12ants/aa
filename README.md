# aa
template wp


[https://github.com/12ants/aa/raw/main/aaaa.zip ](https://github.com/12ants/aa/raw/main/aaaa.zip)


[https://github.com/12ants/aa/raw/main/index.php ](https://github.com/12ants/aa/raw/main/index.php)

=====================

##CREATE NEW DB in mariadb
#####

    d1="$(date +%h%d%y-%S)"; read -ep "$c2 DB User: " -i "$SUDO_USER" "dbu" ;r ead -ep "$c2 New DB: " -i "$d1" "d1"; echo -e "\n $green $d1 $re";
    mysql -u aaaa -p -e"CREATE DATABASE IF NOT EXISTS $d1;flush privileges;SHOW DATABASES;";
    echo -e "\n $cyan $d1 $re \n\n";


:::::::::::::::::::::::::::
