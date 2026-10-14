FROM mariadb:11.4

COPY init/ /docker-entrypoint-initdb.d/
COPY conf/mariadb.cnf /etc/mysql/conf.d/scubatana.cnf

HEALTHCHECK --interval=10s --timeout=3s --start-period=30s --retries=5 \
	CMD mariadb-admin ping -h 127.0.0.1 --silent
