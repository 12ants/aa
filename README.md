# aa
template wp


[https://github.com/12ants/aa/raw/main/aaaa.zip ](https://github.com/12ants/aa/raw/main/aaaa.zip)


[https://github.com/12ants/aa/raw/main/index.php ](https://github.com/12ants/aa/raw/main/index.php)

=====================

##CREATE NEW DB in mariadb
#####

    d1="$(date +%h%d%y_%N)"; echo -e "\n $green $d1 $re";
    mysql -u aaaa -p -e"CREATE DATABASE IF NOT EXISTS $d1;flush privileges;SHOW DATABASES;";
    echo -e "\n $cyan $d1 $re \n\n";


:::::::::::::::::::::::::::
