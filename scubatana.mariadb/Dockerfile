FROM mariadb:11.4

COPY init/ /docker-entrypoint-initdb.d/
COPY conf/mariadb.cnf /etc/mysql/conf.d/scubatana.cnf
