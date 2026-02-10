# Condominium Events Manager API

API multi-tenant (SaaS B2B) para gestao de condominios, incluindo reservas de espacos comuns, governanca, controle de pessoas e comunicacao. Construida com **Laravel**, **PostgreSQL** (pgvector), **Redis** e arquitetura **DDD + Clean Architecture**.

## Stack

| Componente | Tecnologia |
|---|---|
| Framework | Laravel 12 / PHP 8.4 |
| Banco de dados | PostgreSQL 17 + pgvector |
| Cache / Filas / Sessao | Redis 7 |
| E-mail (dev) | Mailpit |
| Testes | Pest v4 |
| Containerizacao | Docker (PHP-FPM + Nginx) |

## Requisitos

- Docker e Docker Compose V2
- Git

## Instalacao

```bash
git clone git@github.com:DaniDrummondDev/condominium-events-manager-api.git
cd condominium-events-manager-api
./setup.sh
```

O script `setup.sh` executa automaticamente:

1. Build e start dos containers Docker
2. Instalacao do Laravel e dependencias (producao + dev)
3. Configuracao do `.env`
4. Geracao de chaves, migrations e publish de configs

Apos a execucao:

| Servico | URL |
|---|---|
| API | http://localhost:8000 |
| Mailpit | http://localhost:8025 |
| PostgreSQL | localhost:5432 |
| Redis | localhost:6379 |

## Comandos Uteis

```bash
docker compose exec app php artisan tinker       # Tinker
docker compose exec app ./vendor/bin/pest         # Testes (Pest)
docker compose exec app php artisan migrate       # Migrations
docker compose exec app php artisan horizon       # Filas (Horizon)
docker compose logs -f app                        # Logs
```

## Testes com Insomnia

Importe a collection em **File > Import** no Insomnia:

```
docs/insomnia/insomnia-collection.json
```

Contém ~120 requests cobrindo todos os endpoints da Platform API e Tenant API, com variáveis de ambiente pré-configuradas.

## Documentacao

### Dominio

| Documento | Descricao |
|---|---|
| [Bounded Contexts](docs/domain/bounded-contexts.md) | Contextos delimitados e suas fronteiras |
| [Linguagem Ubiqua](docs/domain/ubiquitous-language.md) | Glossario de termos do dominio |
| [Modelo de Dominio](docs/domain/domain-model.md) | Entidades, Value Objects, Aggregates |
| [Casos de Uso](docs/domain/use-cases.md) | Fluxos detalhados de cada operacao |

### Arquitetura

| Documento | Descricao |
|---|---|
| [Arquitetura Tecnica](docs/architecture/technical-architecture.md) | Visao geral da arquitetura |
| [Multi-Tenancy](docs/architecture/multi-tenancy-implementation.md) | Estrategia de isolamento por tenant |
| [Estrutura do Projeto](docs/architecture/project-structure.md) | Organizacao de pastas e camadas |
| [Banco de Dados](docs/architecture/database-architecture.md) | Schema completo (42 tabelas) |

### Seguranca

| Documento | Descricao |
|---|---|
| [Fluxos de Autenticacao](docs/security/auth-flows.md) | OAuth 2.1, JWT RS256, MFA TOTP |
| [Matriz de Autorizacao](docs/security/authorization-matrix.md) | Permissoes por role e recurso |

### API

| Documento | Descricao |
|---|---|
| [Guidelines de API](docs/api/api-design-guidelines.md) | Padroes e convencoes da API |
| [Platform API](docs/api/platform-api.md) | Endpoints da plataforma (admin) |
| [Tenant API](docs/api/tenant-api.md) | Endpoints do tenant (condominio) |

### Front-end

| Documento | Descricao |
|---|---|
| [Integracao Front-end](docs/front-end/frontend-auth-integration.md) | Spec de autenticacao para o front |

### Roadmap

| Documento | Descricao |
|---|---|
| [Roadmap de Implementacao](docs/roadmap/roadmap-implementacao.md) | Fases e entregas planejadas |
| [Roadmap Tecnico (Skills)](docs/roadmap/roadmap-tecnico-skills.md) | 44 skills em 9 fases |
| [Features Futuras](docs/roadmap/future-features.md) | Funcionalidades planejadas |

### Outros

| Documento | Descricao |
|---|---|
| [Visao Geral do Projeto](docs/project-overview.md) | Resumo executivo |
| [Estrutura de Skills](docs/estrutura-pastas-skills.md) | Organizacao das skills do Claude |
| [Insomnia Collection](docs/insomnia/insomnia-collection.json) | Collection importavel para testes |
