FROM mysql:8.4

COPY init-users.sh /docker-entrypoint-initdb.d/20-create-production-users.sh

RUN chmod 0550 /docker-entrypoint-initdb.d/20-create-production-users.sh
