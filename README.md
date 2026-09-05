# Obsidian Back — API de Gestão de Conhecimento

API REST inspirada no [Obsidian](https://obsidian.md): notas em Markdown,
organização por tags e **links bidirecionais** entre notas (o motor do _graph
view_). Arquitetura MVC voltada para API — Model, Controller e **API Resources
como camada de View**.

---

## Stack

| Item         | Versão / escolha                                       |
| ------------ | ------------------------------------------------------ |
| Framework    | Laravel **12.69**                                      |
| Linguagem    | PHP 8.4                                                |
| Banco        | SQLite (arquivo único)                                 |
| Autenticação | Laravel Sanctum — **SPA por sessão/cookie** (stateful) |
| Testes       | PHPUnit (`tests/Feature`)                              |

> **Por que Laravel 12 e não 11?** Em 2026 toda a linha 11.x saiu da janela de
> suporte de segurança e o audit do Composer bloqueia a instalação. O 12
> mantém a mesma estrutura enxuta (`bootstrap/app.php`, `php artisan install:api`).

---

## Setup local

```bash
composer install
cp .env.example .env
php artisan key:generate

# PostgreSQL: crie o banco "granito" e ajuste DB_* no .env, depois:
php artisan migrate

php artisan serve            # http://localhost:8000
php artisan queue:work       # 2º terminal — envia os e-mails de verificação
```

O `.env` traz `DB_CONNECTION=pgsql` e as chaves de SPA
(`SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `FRONTEND_URL`). O
`php artisan install:api` (Sanctum + `routes/api.php`) já foi executado no repo.

### Cadastro em 2 passos (usuário só entra em `users` após verificar)

O `register` **não cria o usuário** — grava um cadastro pendente
(`pending_registrations`: nome, e-mail, senha-hash, hash do código de 6 dígitos,
expira em 15 min) e dispara o e-mail com o código (enfileirado).

```
POST /api/register          {name,email,password,password_confirmation}
  → 202 { verification_required: true }          (SEM user, SEM token)

POST /api/register/verify    {email, code}       (público, sem auth)
  → valida o código → cria o `users` (email_verified_at preenchido) → apaga o pendente
  → 201 { user, token }                          (token Bearer; ou null se SPA/stateful)

POST /api/register/resend    {email}             (público)
  → gera novo código para o pendente → 202 (resposta genérica)
```

- Cada `register`/`resend` **substitui** o código anterior — use sempre o e-mail mais recente.
- `login` só funciona depois do `verify` (antes disso não existe usuário).
- O código aceita número ou string, e recompõe zero à esquerda perdido.

### Fila / e-mail (leia se o código não chegar)

```bash
php artisan queue:work           # 2º terminal, DEIXE RODANDO. Sem "--once" / "--stop-when-empty".
php artisan queue:restart        # SEMPRE após mudar .env ou classes de job/notification
php artisan queue:failed         # jobs que falharam + o erro (SMTP, etc.)
php artisan queue:retry all      # re-tenta os falhados
```

- Sem worker vivo, o job fica parado em `jobs` e **nenhum e-mail sai** — não é bug.
- O `queue:work` lê o `.env` no boot: trocou `MAIL_MAILER`/`MAIL_*`? reinicie o worker.
- `MAIL_MAILER=log` → o código sai em `storage/logs/laravel.log` (nada vai pro Gmail).
- `MAIL_MAILER=smtp` → envia de verdade; um envio pelo Gmail leva ~5–15 s (por isso é enfileirado).
- Destinatário só recebe se for um e-mail real; use alias `+` do Gmail para testar
  (`voce+teste@gmail.com`).
- Falha de envio depois de 3 tentativas cai em `failed_jobs` e é logada por
  `VerifyEmailCodeQueued::failed()`.

Rodar os testes:

```bash
php artisan test
```

---

## Estrutura de diretórios (frente à arquitetura)

| Caminho                     | Papel                                                                              |
| --------------------------- | ---------------------------------------------------------------------------------- |
| `app/Models/`               | Entidades Eloquent e relacionamentos — o **M**.                                    |
| `app/Http/Controllers/Api/` | Orquestra a requisição (autoriza, transação, delega) — o **C**. Fino de propósito. |
| `app/Http/Requests/`        | `FormRequest`: validação de entrada e rate limiting (`LoginRequest`).              |
| `app/Http/Resources/`       | Serialização Model → JSON — a **View** da API.                                     |
| `app/Policies/`             | Autorização por recurso (`NotePolicy` — dono da nota).                             |
| `app/Providers/`            | Bootstrap de serviços (ex.: `Password::defaults()`).                               |
| `routes/api.php`            | Mapa de endpoints (prefixo `/api`) + route binding com escopo de dono.             |
| `bootstrap/app.php`         | Configuração central: middleware (`statefulApi()`), rotas, handler de exceções.    |
| `database/migrations/`      | Schema versionado (fonte da verdade do banco).                                     |
| `database/factories/`       | Geração de dados para os testes.                                                   |
| `config/`                   | `sanctum.php`, `cors.php`, `database.php`, `session.php`.                          |
| `tests/Feature/`            | Testes de integração HTTP (auth, CRUD).                                            |

---

## Modelagem do banco — processo

### Princípio: isolamento por usuário

O sistema é multiusuário. A garantia de que ninguém enxerga dados de outro é
estrutural: `notes` e `tags` carregam `user_id`, e **toda query parte do
usuário autenticado** (`$request->user()->notes()...`). O route binding de
`{note}` resolve apenas dentro das notas do dono — id alheio retorna `404`.

### Comandos usados

```bash
php artisan make:model Note -m
php artisan make:model Tag  -m
php artisan make:migration create_note_tag_table
php artisan make:migration create_note_links_table
```

As migrations em `database/migrations/` foram então escritas à mão com o schema
abaixo. Para recriar o banco do zero a qualquer momento:

```bash
php artisan migrate:fresh
```

### Tabelas

**`users`** — fundação multiusuário (migration padrão do Laravel).

| Coluna       | Tipo           | Responsabilidade            |
| ------------ | -------------- | --------------------------- |
| `id`         | integer (PK)   | Identificador.              |
| `name`       | string         | Nome de exibição.           |
| `email`      | string (único) | Credencial de login.        |
| `password`   | string         | Hash da senha (`bcrypt`).   |
| `timestamps` | datetime       | `created_at`, `updated_at`. |

**`notes`** — o núcleo do conteúdo.

| Coluna          | Tipo                   | Responsabilidade                                                                                         |
| --------------- | ---------------------- | -------------------------------------------------------------------------------------------------------- |
| `id`            | integer (PK)           | Identificador.                                                                                           |
| `user_id`       | integer (FK → `users`) | **Isolamento**: 1 usuário → N notas. `cascadeOnDelete`.                                                  |
| `title`         | string                 | Título do documento.                                                                                     |
| `body_markdown` | text (nullable)        | Texto cru, com `#`, `**`, `[[ ]]`.                                                                       |
| `properties`    | json (nullable)        | Frontmatter estruturado (`status`, `due`, `aliases`…). No SQLite é `TEXT`, consultável via funções JSON. |
| `timestamps`    | datetime               | Criação e última edição.                                                                                 |

**`tags`** — vocabulário de categorização, **isolado por usuário** (o
auto-complete de um usuário não sugere tags de outro).

| Coluna    | Tipo                   | Responsabilidade                |
| --------- | ---------------------- | ------------------------------- |
| `id`      | integer (PK)           | Identificador.                  |
| `user_id` | integer (FK → `users`) | Dono da tag. `cascadeOnDelete`. |
| `name`    | string                 | Texto da tag (ex.: `laravel`).  |

Restrição: `UNIQUE (user_id, name)` — nome único **por usuário**, não
globalmente. Sem `timestamps` (`public $timestamps = false` no model).

**`note_tag`** — pivô N:N (muitas notas ↔ muitas tags).

| Coluna    | Tipo                   |
| --------- | ---------------------- |
| `note_id` | integer (FK → `notes`) |
| `tag_id`  | integer (FK → `tags`)  |

Chave primária composta `(note_id, tag_id)` — impede a mesma tag repetida na
mesma nota.

**`note_links`** — o motor do _graph view_: pivô **autorreferencial** de
`notes` para `notes`, habilitando _backlinks_.

| Coluna           | Tipo                   | Responsabilidade                                                 |
| ---------------- | ---------------------- | ---------------------------------------------------------------- |
| `source_note_id` | integer (FK → `notes`) | Nota onde o `[[link]]` foi digitado.                             |
| `target_note_id` | integer (FK → `notes`) | Nota mencionada / alvo do link.                                  |
| `created_at`     | datetime               | Quando a conexão foi estabelecida (`DEFAULT CURRENT_TIMESTAMP`). |

Chave primária composta `(source_note_id, target_note_id)` — 1 aresta por par.
Não tem `id` nem `updated_at` (um link existe ou não; não é editável).

### Relacionamentos (Eloquent)

```
User  1 ─── N  Note          User->notes()          Note->user()
User  1 ─── N  Tag           User->tags()           Tag->user()
Note  N ─── N  Tag           Note->tags()  /  Tag->notes()          (pivô note_tag)
Note  N ─── N  Note          Note->linkedNotes()    (outgoing, via note_links)
                             Note->backlinks()      (incoming, via note_links)
```

Todas as FKs usam `cascadeOnDelete`: apagar o usuário apaga suas notas e tags,
e apagar uma nota apaga as arestas de link que a envolvem.

---

## Autenticação (Sanctum SPA — sessão/cookie)

Não há tokens `Bearer`. O frontend (origem confiável) autentica por **cookie de
sessão**; o guard `web` do Sanctum valida cada requisição stateful.

### Configuração aplicada

| Onde                 | O quê                                                                                                                                                                                |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `bootstrap/app.php`  | `$middleware->statefulApi()` — para origens em `SANCTUM_STATEFUL_DOMAINS`, roda `EncryptCookies` + `VerifyCsrfToken` + `StartSession` + `AddQueuedCookiesToResponse` no grupo `api`. |
| `.env`               | `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN=localhost` (cookie compartilhado entre portas), `FRONTEND_URL`.                                                                          |
| `config/cors.php`    | `supports_credentials => true`, `allowed_origins` a partir de `FRONTEND_URL`.                                                                                                        |
| `config/sanctum.php` | `guard => ['web']`.                                                                                                                                                                  |

### Fluxo passo a passo

1. `GET /sanctum/csrf-cookie` → grava os cookies `XSRF-TOKEN` e `laravel-session`.
2. `POST /api/register` **ou** `POST /api/login` — com header `X-XSRF-TOKEN` e
   `credentials: 'include'` no cliente HTTP.
3. Requisições seguintes vão autenticadas pelo cookie de sessão.
4. `POST /api/logout` — invalida a sessão.

### Proteções

- **Rate limiting**
    - `LoginRequest`: 5 tentativas falhas por `e-mail + IP`; a 6ª responde `422`
      com _"Muitas tentativas de login. Tente novamente em N segundos."_
    - Rota: `throttle:6,1` em `/register`, `throttle:10,1` em `/login`.
- **Política de senha** (`AppServiceProvider::boot` → `Password::defaults()`):
  mínimo 8 caracteres; em produção também exige letras + números e barra senhas
  vazadas (checagem HIBP).
- **Sessão**: `regenerate()` após login/registro (anti-fixação);
  `invalidate()` + `regenerateToken()` no logout.

### Exemplo (curl)

```bash
BASE=http://localhost:8000

# 1. CSRF cookie
curl -c cookies.txt -H 'Origin: http://localhost:3000' "$BASE/sanctum/csrf-cookie"
XSRF=$(grep XSRF-TOKEN cookies.txt | awk '{print $7}' | python -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read()))')

# 2. Registro
curl -b cookies.txt -c cookies.txt -H 'Origin: http://localhost:3000' \
  -H "X-XSRF-TOKEN: $XSRF" -H 'Accept: application/json' \
  -d 'name=Ana&email=ana@ex.com&password=senha-forte-1&password_confirmation=senha-forte-1' \
  "$BASE/api/register"

# 3. Requisição autenticada (só com o cookie)
curl -b cookies.txt -H 'Origin: http://localhost:3000' -H 'Accept: application/json' "$BASE/api/user"

# 4. Logout
curl -b cookies.txt -c cookies.txt -X POST -H 'Origin: http://localhost:3000' \
  -H "X-XSRF-TOKEN: $XSRF" -H 'Accept: application/json' "$BASE/api/logout"
```

---

## Endpoints

| Método        | URI                    | Descrição                                                       | Auth   |
| ------------- | ---------------------- | --------------------------------------------------------------- | ------ |
| `GET`         | `/sanctum/csrf-cookie` | Emite o cookie CSRF (pré-requisito do SPA).                     | —      |
| `POST`        | `/api/register`        | Grava cadastro pendente + envia o código. `202`, sem user/token. | —      |
| `POST`        | `/api/register/verify` | `{email, code}` → cria o usuário e devolve `{ user, token }`.   | —      |
| `POST`        | `/api/register/resend` | `{email}` → reenvia um novo código para o cadastro pendente.   | —      |
| `POST`        | `/api/login`           | Autentica; devolve `{ user, token }` (token null se SPA).      | —      |
| `POST`        | `/api/logout`          | Encerra a sessão (SPA) ou revoga o token atual.                 | auth   |
| `GET`         | `/api/user`            | Usuário autenticado.                                            | auth   |
| `GET`         | `/api/notes`           | Lista as notas do usuário (paginado).                           | auth   |
| `POST`        | `/api/notes`           | Cria nota (aceita `tags[]`, `linked_note_ids[]`, `properties`). | auth   |
| `GET`         | `/api/notes/{note}`    | Detalha a nota (com `tags`, `linked_notes`, `backlinks`).       | sessão |
| `PUT`/`PATCH` | `/api/notes/{note}`    | Atualiza (parcial).                                             | sessão |
| `DELETE`      | `/api/notes/{note}`    | Remove.                                                         | sessão |

Erros em `/api/*` sempre saem como JSON `{ "message": ..., "errors"?: { … } }`
com o status apropriado (`401`, `403`, `404`, `422`, `429`), padronizado em
`bootstrap/app.php` (`withExceptions`).
