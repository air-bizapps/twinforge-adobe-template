#!/usr/bin/env bash
# Monta o ambiente local de verificação do Bunge com os serviços nativos da máquina (sem Docker):
# um clone do Bunge-Magento2 na branch base dos agentes, vendor em hardlink, env.php derivado do
# checkout principal e uma CÓPIA do banco num banco de verificação. O checkout principal e o banco
# dele só são lidos.
#
# Uso:  integrations/local-verify/setup.sh [--refresh-db]
# Variáveis (opcionais): BUNGE_DIR, VERIFY_DIR, VERIFY_DB, VERIFY_PORT, BASE_REF
#   --refresh-db  apaga e recria o banco de verificação a partir do banco do checkout principal
set -euo pipefail

BUNGE_DIR=${BUNGE_DIR:-$HOME/workspace/bunge}
VERIFY_DIR=${VERIFY_DIR:-$HOME/workspace/.verify/bunge}
VERIFY_DB=${VERIFY_DB:-magento2}
VERIFY_PORT=${VERIFY_PORT:-8099}
BASE_REF=${BASE_REF:-origin/twinforge}
REFRESH=0
[ "${1:-}" = "--refresh-db" ] && REFRESH=1

say() { printf '== %s\n' "$*"; }
test -f "$BUNGE_DIR/app/etc/env.php" || { echo "Nao achei $BUNGE_DIR/app/etc/env.php"; exit 2; }

# credenciais do MySQL só num arquivo temporário 0600, apagado na saída
CNF=$(mktemp); chmod 600 "$CNF"; trap 'rm -f "$CNF"' EXIT
BUNGE_DIR="$BUNGE_DIR" php -r '
$c = (include getenv("BUNGE_DIR") . "/app/etc/env.php")["db"]["connection"]["default"];
echo "[client]\nuser=", $c["username"], "\npassword=\"", addcslashes($c["password"], "\"\\"), "\"\nhost=", $c["host"], "\n";' > "$CNF"
SOURCE_DB=$(BUNGE_DIR="$BUNGE_DIR" php -r 'echo (include getenv("BUNGE_DIR") . "/app/etc/env.php")["db"]["connection"]["default"]["dbname"];')
[ "$SOURCE_DB" != "$VERIFY_DB" ] || { echo "O banco de verificacao nao pode ser o banco do checkout principal"; exit 2; }

say "clone em $VERIFY_DIR ($BASE_REF)"
mkdir -p "$(dirname "$VERIFY_DIR")"
[ -d "$VERIFY_DIR/.git" ] || git clone -q --shared --no-checkout "$BUNGE_DIR" "$VERIFY_DIR"
git -C "$VERIFY_DIR" checkout -q --detach "$(git -C "$BUNGE_DIR" rev-parse "$BASE_REF")"

say "vendor e artefatos do magento2-base em hardlink (sem custo de disco)"
[ -e "$VERIFY_DIR/vendor" ] || cp -al "$BUNGE_DIR/vendor" "$VERIFY_DIR/vendor"
( cd "$BUNGE_DIR" && git ls-files --others --ignored --exclude-standard --directory \
    | grep -vE '^(vendor/|var/|generated/|pub/static/|pub/media/|node_modules/|\.idea/|\.claude/|\.docker/|app/etc/env.php)' \
    | while read -r entry; do p="${entry%/}"; [ -e "$VERIFY_DIR/$p" ] || cp -al --parents "$p" "$VERIFY_DIR/"; done )

say "env.php do clone (banco $VERIFY_DB, http://127.0.0.1:$VERIFY_PORT, prefixo OpenSearch twinforge_verify)"
BUNGE_DIR="$BUNGE_DIR" VERIFY_DIR="$VERIFY_DIR" VERIFY_DB="$VERIFY_DB" VERIFY_PORT="$VERIFY_PORT" php -r '
$e = include getenv("BUNGE_DIR") . "/app/etc/env.php";
$e["db"]["connection"]["default"]["dbname"] = getenv("VERIFY_DB");
unset($e["db"]["connection"]["default"]["initStatements"], $e["remote_storage"]);
$base = "http://127.0.0.1:" . getenv("VERIFY_PORT") . "/";
foreach (["unsecure", "secure"] as $k) {
    $e["system"]["default"]["web"][$k]["base_url"] = $base;
    $e["system"]["default"]["web"][$k]["base_link_url"] = $base;
}
$e["system"]["default"]["web"]["secure"]["use_in_frontend"] = "0";
$e["system"]["default"]["web"]["secure"]["use_in_adminhtml"] = "0";
$e["system"]["default"]["web"]["cookie"]["cookie_domain"] = "";
$e["system"]["default"]["catalog"]["search"]["opensearch_index_prefix"] = "twinforge_verify";
$e["system"]["default"]["admin"]["security"]["use_form_key"] = "0";
$e["system"]["default"]["admin"]["captcha"]["enable"] = "0";
$e["system"]["default"]["admin"]["url"]["use_custom_path"] = "0";
$e["MAGE_MODE"] = "developer";
$e["cache"] = ["frontend" => ["default" => ["id_prefix" => "tfv_"], "page_cache" => ["id_prefix" => "tfv_"]]];
$e["session"] = ["save" => "files"];
file_put_contents(getenv("VERIFY_DIR") . "/app/etc/env.php", "<?php\nreturn " . var_export($e, true) . ";\n");'

TABLES=$(mysql --defaults-extra-file="$CNF" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$VERIFY_DB'")
if [ "$TABLES" -gt 0 ] && [ "$REFRESH" = 1 ]; then
    say "apagando o banco de verificacao $VERIFY_DB ($TABLES tabelas) para recriar"
    mysql --defaults-extra-file="$CNF" -e "DROP DATABASE \`$VERIFY_DB\`"
    TABLES=0
fi
if [ "$TABLES" -eq 0 ]; then
    say "copiando $SOURCE_DB para $VERIFY_DB (leitura numa transacao consistente)"
    mysql --defaults-extra-file="$CNF" -e "CREATE DATABASE IF NOT EXISTS \`$VERIFY_DB\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
    # DEFINER exige SUPER: a cópia sai sem ele (views e triggers ficam com o usuário atual)
    mysqldump --defaults-extra-file="$CNF" --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF \
        --triggers "$SOURCE_DB" | sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g; s/SQL SECURITY DEFINER/SQL SECURITY INVOKER/g' \
        | mysql --defaults-extra-file="$CNF" "$VERIFY_DB"
else
    say "banco $VERIFY_DB ja tem $TABLES tabelas: mantido (use --refresh-db para recriar)"
fi

say "setup:upgrade no clone"
( cd "$VERIFY_DIR" && php -d memory_limit=4G bin/magento setup:upgrade -q )

cat <<EOF

Pronto. Próximos passos (no clone $VERIFY_DIR):
  servidor:  php -d memory_limit=2G -d opcache.enable_cli=0 -S 127.0.0.1:$VERIFY_PORT -t pub phpserver/router.php
  admin:     php bin/magento admin:user:create  (só no banco $VERIFY_DB)
  módulo:    rsync -a --delete <<repo-de-configuracao>>/skills/adobe-magento-toolbox/reference/Acme/ app/code/Acme/
             php bin/magento module:enable Acme_Reference && php bin/magento setup:upgrade
  remover:   DROP DATABASE $VERIFY_DB; e apagar $VERIFY_DIR
EOF
