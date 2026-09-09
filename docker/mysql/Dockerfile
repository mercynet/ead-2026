FROM mysql:8.4

COPY --chmod=0555 init-users.sh /docker-entrypoint-initdb.d/20-create-production-users.sh

RUN chmod 0555 /docker-entrypoint-initdb.d/20-create-production-users.sh
