#!/bin/bash
# =====================================================================
# PRINCIPE DE MOINDRE PRIVILÈGE — compte applicatif MySQL
# =====================================================================
# L'image officielle MySQL crée l'utilisateur MYSQL_USER avec
# ALL PRIVILEGES sur la base MYSQL_DATABASE, ce qui inclut les droits
# de structure (CREATE, DROP, ALTER) dont l'application n'a jamais besoin.
#
# Ce script restreint le compte applicatif aux 4 verbes DML strictement
# nécessaires au fonctionnement de l'API :
#   SELECT, INSERT, UPDATE, DELETE
#
# Conséquence concrète : si l'API était compromise (injection SQL,
# exécution de code), l'attaquant ne pourrait ni supprimer une table,
# ni modifier le schéma, ni s'attribuer de nouveaux droits.
#
# Vérification :
#   docker compose exec database mysql -u root -p -e "SHOW GRANTS FOR 'loom'@'%';"
# =====================================================================
set -e

mysql --protocol=socket -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    -- Retire les droits accordés par défaut à la création du compte
    REVOKE ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* FROM '${MYSQL_USER}'@'%';

    -- N'accorde que la manipulation des données, sur la seule base de Loom
    GRANT SELECT, INSERT, UPDATE, DELETE
        ON \`${MYSQL_DATABASE}\`.*
        TO '${MYSQL_USER}'@'%';

    -- Aucun droit de structure (CREATE, DROP, ALTER, INDEX)
    -- Aucun droit d'administration (GRANT OPTION, FILE, SUPER)
    FLUSH PRIVILEGES;
EOSQL

echo "[06-grants] Moindre privilege applique a '${MYSQL_USER}'@'%' sur ${MYSQL_DATABASE}"
