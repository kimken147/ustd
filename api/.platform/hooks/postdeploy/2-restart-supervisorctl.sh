#!/bin/bash

# Restart Supervisor workers

# During deployment, some of the workers might have
# SPAWN ERR errors, so it's better to restart them.

# At this point in time, the whole app
# has already been deployed.

cat <<EOF > /etc/supervisord.d/laravel.ini

[unix_http_server]
file=/tmp/supervisor.sock
chmod=0777

[supervisord]
logfile=/var/log/supervisor/supervisord.log
logfile_maxbytes=0
logfile_backups=0
loglevel=warn
pidfile=/var/run/supervisord.pid
nodaemon=false
nocleanup=true
user=webapp

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

[supervisorctl]
serverurl=unix:///tmp/supervisor.sock

[program:laravel_queue_low]
process_name=%(program_name)s_%(process_num)02d
command=php artisan queue:work --tries=3 --timeout=5 --queue=${LOW_PRIORITY_QUEUE},default
directory=/var/app/current
stdout_logfile=/var/log/supervisor/laravel-queue.log
logfile_maxbytes=0
logfile_backups=0
redirect_stderr=true
autostart=true
autorestart=true
startretries=86400
numprocs=2
user=webapp

[program:laravel_queue_medium]
process_name=%(program_name)s_%(process_num)02d
command=php artisan queue:work --tries=3 --sleep=1 --timeout=15 --queue=${MEDIUM_PRIORITY_QUEUE}
directory=/var/app/current
stdout_logfile=/var/log/supervisor/laravel-queue.log
logfile_maxbytes=0
logfile_backups=0
redirect_stderr=true
autostart=true
autorestart=true
startretries=86400
numprocs=5
user=webapp

[program:laravel_queue_high]
process_name=%(program_name)s_%(process_num)02d
command=php artisan queue:work --tries=3 --sleep=1 --timeout=15 --queue=${HIGH_PRIORITY_QUEUE}
directory=/var/app/current
stdout_logfile=/var/log/supervisor/laravel-queue.log
logfile_maxbytes=0
logfile_backups=0
redirect_stderr=true
autostart=true
autorestart=true
startretries=86400
numprocs=10
user=webapp

[program:laravel_short_schedule]
process_name=%(program_name)s_%(process_num)02d
command=php artisan short-schedule:run
directory=/var/app/current
stdout_logfile=/var/log/supervisor/laravel-short-schedule.log
logfile_maxbytes=0
logfile_backups=0
redirect_stderr=true
autostart=true
autorestart=true
startretries=86400
numprocs=1
user=webapp

EOF


sudo supervisorctl reread

sudo supervisorctl update

sudo supervisorctl restart all

sudo chmod 777 -R /var/log/supervisor