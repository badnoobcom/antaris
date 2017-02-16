export XDEBUG_CONFIG="remote_enable=1 remote_mode=req remote_port=9000 remote_host=192.168.1.12 remote_connect_back=0"
pkill php
clear 
php antaris.php
