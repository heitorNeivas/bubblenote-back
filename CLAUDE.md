# Contexto do Projeto: API "Obsidian Clone" (Backend)

Você é um Engenheiro de Software Sênior auxiliando no desenvolvimento de uma API RESTful.
Responda sempre de forma direta, com código limpo, seguro e comentários explicativos em português.

## Stack Tecnológica

- **Framework:** Laravel 12 (modo API — estrutura enxuta `bootstrap/app.php`, `install:api`).
  Laravel 11 foi descartado: a linha 11.x saiu do suporte de segurança e o audit do Composer bloqueia a instalação.
- **Linguagem:** PHP 8.4
- **Banco de Dados:** PostgreSQL 16 — banco `Granito` (local: `127.0.0.1:5432`, user `postgres`).
  Busca em texto (quando chegar): `tsvector` + índice `GIN` / `websearch_to_tsquery` — **não** FTS5.
  Os testes rodam em SQLite `:memory:` (`phpunit.xml`); manter as migrations compatíveis com ambos.
- **Autenticação:** Laravel Sanctum **híbrido**.
  - **SPA (Next.js):** stateful — `GET /sanctum/csrf-cookie`, header `X-XSRF-TOKEN`,
    requisições com `credentials: 'include'` e header `Origin` de `SANCTUM_STATEFUL_DOMAINS`.
    Login por cookie de sessão; `register`/`login` retornam `"token": null`.
  - **Cliente de API (Insomnia/Postman/mobile):** sem sessão — `register`/`login`
    retornam um `token` pessoal para usar em `Authorization: Bearer <token>`.
  Sem JWT.
- **E-mail:** SMTP do Gmail com App Password (`MAIL_*` no `.env`). Verificação de e-mail **obrigatória**.
- **Front-end:** Next.js. As rotas são testadas via Thunder Client localmente antes da integração.

## Regras de Arquitetura e Código

1. **MVC restrito.** Lógica de negócio fora dos controllers. Eloquent Models para
   relacionamentos, **Form Requests** para validação, **API Resources** para formatar o JSON.
   Controllers finos (autorizar → transação → delegar).
2. **Segurança e isolamento.** Toda rota fora de `register` / `login` /
   verificação de e-mail passa por `auth:sanctum`. Recursos do produto também exigem
   `verified` (e-mail confirmado). Toda query parte do usuário autenticado
   (`$request->user()->notes()...`); o route binding de `{note}` resolve só nas notas do dono
   (id alheio ⇒ 404, sem vazar existência). Autorização fina em Policies.
3. **Padrão de resposta.** Status HTTP corretos para o Next.js
   (`200, 201, 202, 401, 403, 404, 409, 422, 429, 500`). Erros em `/api/*` saem
   sempre como JSON padronizado `{ "message": ..., "errors"?: {...} }`
   (envelope central em `bootstrap/app.php` → `withExceptions`).
4. **Rate limiting.** `throttle` de rota nas rotas públicas de auth; no `/login`,
   `LoginRequest` ainda limita por e-mail + IP (5 tentativas).
5. **Hashing.** bcrypt com salt por senha (custo 13); re-hash automático no login.
   `argon2id` disponível como upgrade de produção (`HASH_DRIVER`).
6. **Testes.** `php artisan test` (PHPUnit, `tests/Feature`) além do Thunder Client manual.

## Comandos úteis

```bash
php artisan migrate:fresh      # recria o schema no Postgres
php artisan test               # suíte de feature (SQLite :memory:)
php artisan route:list         # conferir rotas e middlewares
php artisan serve              # http://localhost:8000
```

-> LEMBRANDO, toda implementação, lembre-se que o código está open source, não deixe informações sensíveis minhas expostas  