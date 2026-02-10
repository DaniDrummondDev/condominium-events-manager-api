#!/bin/bash
set -e

# ============================================================
# Setup Script — Condominium Events Manager API
# ============================================================
# Este script:
# 1. Builda e sobe os containers Docker
# 2. Instala o Laravel (se nao instalado)
# 3. Instala todos os packages do projeto
# 4. Configura o .env
# 5. Gera chaves e executa migrations
# ============================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info()    { echo -e "${CYAN}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error()   { echo -e "${RED}[ERROR]${NC} $1"; }
log_step()    { echo -e "\n${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"; echo -e "${GREEN}  $1${NC}"; echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"; }

# ============================================================
# Pre-checks
# ============================================================

if ! command -v docker &> /dev/null; then
    log_error "Docker nao encontrado. Instale o Docker primeiro."
    exit 1
fi

if ! docker compose version &> /dev/null; then
    log_error "Docker Compose nao encontrado. Instale o Docker Compose V2."
    exit 1
fi

# Exporta UID do host para que o user dentro do container tenha o mesmo UID
export HOST_UID=$(id -u)
log_info "Usando UID do host: ${HOST_UID} (${USER})"

# ============================================================
# Step 1: Build e start dos containers
# ============================================================

log_step "1/6 — Build e start dos containers"

docker compose up -d --build

log_info "Aguardando containers ficarem prontos..."
sleep 5

# Verificar se containers estao rodando
if ! docker compose ps --status running | grep -q "condominium-app"; then
    log_error "Container 'condominium-app' nao esta rodando."
    docker compose logs app
    exit 1
fi

if ! docker compose ps --status running | grep -q "condominium-postgres"; then
    log_error "Container 'condominium-postgres' nao esta rodando."
    docker compose logs postgres
    exit 1
fi

log_success "Todos os containers estao rodando."

# ============================================================
# Step 2: Instalar Laravel
# ============================================================

log_step "2/6 — Instalando Laravel"

if [ -f "artisan" ]; then
    log_warn "Laravel ja esta instalado. Pulando instalacao."
else
    log_info "Instalando Laravel em diretorio temporario..."
    docker compose exec -T app composer create-project laravel/laravel /tmp/laravel-install --prefer-dist --no-interaction

    log_info "Copiando arquivos do Laravel para o projeto..."
    docker compose exec -T app bash -c 'cp -rn /tmp/laravel-install/. /var/www/ 2>/dev/null; rm -rf /tmp/laravel-install'

    log_success "Laravel instalado."
fi

# ============================================================
# Step 3: Instalar packages de producao
# ============================================================

log_step "3/6 — Instalando packages de producao"

docker compose exec -T app composer require \
    --no-interaction \
    --no-scripts \
    echolabsdev/prism \
    lcobucci/jwt \
    pragmarx/google2fa-laravel \
    bacon/bacon-qr-code \
    predis/predis \
    pgvector/pgvector \
    laravel/horizon \
    spatie/laravel-data \
    spatie/laravel-query-builder

log_success "Packages de producao instalados."

# ============================================================
# Step 4: Instalar packages de desenvolvimento
# ============================================================

log_step "4/6 — Instalando packages de desenvolvimento"

docker compose exec -T app composer require --dev \
    --no-interaction \
    --no-scripts \
    -W \
    pestphp/pest \
    pestphp/pest-plugin-arch \
    pestphp/pest-plugin-laravel \
    pestphp/pest-plugin-drift \
    laravel/pint \
    nunomaduro/larastan \
    phpstan/phpstan

log_info "Inicializando Pest..."
docker compose exec -T app bash -c 'echo "" | ./vendor/bin/pest --init' 2>/dev/null || true

log_success "Packages de desenvolvimento instalados."

# ============================================================
# Step 5: Configurar .env
# ============================================================

log_step "5/6 — Configurando .env"

if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
    fi
fi

if [ -f ".env" ]; then
    # App
    sed -i 's|^APP_NAME=.*|APP_NAME="Condominium Events Manager"|' .env
    sed -i 's|^APP_URL=.*|APP_URL=http://localhost:8000|' .env

    # Database (trata linhas comentadas ou nao)
    sed -i 's|^#\? *DB_CONNECTION=.*|DB_CONNECTION=pgsql|' .env
    sed -i 's|^#\? *DB_HOST=.*|DB_HOST=postgres|' .env
    sed -i 's|^#\? *DB_PORT=.*|DB_PORT=5432|' .env
    sed -i 's|^#\? *DB_DATABASE=.*|DB_DATABASE=condominium_platform|' .env
    sed -i 's|^#\? *DB_USERNAME=.*|DB_USERNAME=condominium|' .env
    sed -i 's|^#\? *DB_PASSWORD=.*|DB_PASSWORD=secret|' .env

    # Redis
    sed -i 's|^REDIS_HOST=.*|REDIS_HOST=redis|' .env
    sed -i 's|^CACHE_STORE=.*|CACHE_STORE=redis|' .env
    sed -i 's|^SESSION_DRIVER=.*|SESSION_DRIVER=redis|' .env
    sed -i 's|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=redis|' .env

    # Mail (Mailpit)
    sed -i 's|^MAIL_MAILER=.*|MAIL_MAILER=smtp|' .env
    sed -i 's|^MAIL_HOST=.*|MAIL_HOST=mailpit|' .env
    sed -i 's|^MAIL_PORT=.*|MAIL_PORT=1025|' .env
    sed -i 's|^MAIL_USERNAME=.*|MAIL_USERNAME=null|' .env
    sed -i 's|^MAIL_PASSWORD=.*|MAIL_PASSWORD=null|' .env
    sed -i 's|^MAIL_ENCRYPTION=.*|MAIL_ENCRYPTION=null|' .env
    sed -i 's|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS="noreply@condominium-events.com"|' .env
    sed -i 's|^MAIL_FROM_NAME=.*|MAIL_FROM_NAME="Condominium Events Manager"|' .env

    log_success ".env configurado."
else
    log_warn ".env nao encontrado. Configure manualmente."
fi

# ============================================================
# Step 6: Finalizar setup
# ============================================================

log_step "6/6 — Finalizando setup"

log_info "Gerando application key..."
docker compose exec -T app php artisan key:generate --force

log_info "Limpando caches..."
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan cache:clear

log_info "Executando migrations..."
docker compose exec -T app php artisan migrate --force

log_info "Publicando configs dos packages..."
docker compose exec -T app php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider" 2>/dev/null || true

# ============================================================
# Summary
# ============================================================

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Setup concluido com sucesso!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "  ${CYAN}API:${NC}       http://localhost:8000"
echo -e "  ${CYAN}Mailpit:${NC}   http://localhost:8025"
echo -e "  ${CYAN}Postgres:${NC}  localhost:5432 (user: condominium, db: condominium_platform)"
echo -e "  ${CYAN}Redis:${NC}     localhost:6379"
echo ""
echo -e "  ${YELLOW}Comandos uteis:${NC}"
echo -e "  docker compose exec app php artisan tinker     # Tinker"
echo -e "  docker compose exec app php artisan test        # Testes"
echo -e "  docker compose exec app ./vendor/bin/pest       # Pest"
echo -e "  docker compose exec app php artisan migrate     # Migrations"
echo -e "  docker compose exec app php artisan horizon     # Queues"
echo -e "  docker compose logs -f app                      # Logs"
echo ""
