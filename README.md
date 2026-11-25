# Site Acesso+ (PHP + Supabase + Railway)

Este é o site do Acesso+. O arquivo `api_app.php` é a API que o app Android usa. **Site e app usam o mesmo banco PostgreSQL no Supabase.**

```
App Android ──HTTPS──> api_app.php ┐
                                   ├─> Railway (PHP 8.3 + Apache) ──> Supabase (PostgreSQL)
Navegador ───HTTPS──> páginas .php ┘
```

## 1. Criar o banco no Supabase

1. Entre em https://supabase.com, clique em **New project** e escolha a região **South America (São Paulo)**. Guarde a senha do banco.
2. Abra **SQL Editor**, cole todo o conteúdo de `banco_de_dados.sql` e clique em **Run**.
3. Clique em **Connect** (no topo do projeto) e escolha **Session pooler**. Copie a string `postgresql://postgres.xxxx:[YOUR-PASSWORD]@aws-0-sa-east-1.pooler.supabase.com:5432/postgres` e troque `[YOUR-PASSWORD]` pela senha do banco.
   - Use o **Session pooler**, não a "Direct connection": a conexão direta só funciona por IPv6, e o Railway não alcança.
   - Se a senha tiver caracteres especiais (`@ # / ? %`), codifique-os na URL (ex.: `@` vira `%40`).

## 2. Publicar no Railway

1. Envie este repositório para o GitHub (com a pasta `site/`).
2. Em https://railway.com clique em **New Project → Deploy from GitHub repo** e escolha o repositório.
3. No serviço criado, abra **Settings**:
   - **Root Directory** = `/site` (o Railway encontra o `Dockerfile` e o `railway.json` sozinho).
   - Em **Networking**, clique em **Generate Domain** para ganhar um endereço `https://xxxx.up.railway.app`.
4. Em **Variables**, adicione:
   - `DATABASE_URL` = a string do Session pooler do passo 1.3
   - `GOOGLE_MAPS_API_KEY` = sua chave do Google Maps (opcional, só para o mapa da Rede Credenciada)
   - `MP_ACCESS_TOKEN` = Access Token do Mercado Pago (Checkout Pro), usado no menu **Financeiro** para pagar a mensalidade. Use a credencial de **teste** (`APP_USR-...` da conta de teste). A tabela `Faturas` é criada sozinha no primeiro acesso ao Financeiro.
   - `ADMIN_USUARIO` e `ADMIN_SENHA` = login do Painel do Administrador (`admin.php`). Sem elas, o painel fica bloqueado.
   - `RESEND_API_KEY` = API Key do Resend (`re_...`), usada para enviar o e-mail de **Esqueci minha senha** (veja a seção abaixo).
   - `RESEND_FROM` = remetente do e-mail, ex.: `Acesso+ <nao-responda@seudominio.com.br>`.
   - `SITE_URL` = endereço público do site (ex.: `https://xxxx.up.railway.app`), usado no link do e-mail.
5. Aguarde o deploy e teste `https://xxxx.up.railway.app/api_app.php?action=ping`. A resposta deve ser `{"ok":true,...}`.

## Planos e pagamento (Mercado Pago)

- **Começar um plano:** depois do cadastro o paciente já entra logado e vai direto ao Checkout Pro do Mercado Pago
  (`pagarPlano.php`) para pagar a primeira mensalidade. O plano fica **Ativo** quando o pagamento é aprovado.
- **Trocar de plano** (menu *Meu plano*, `alterarPlano.php`):
  - mensalidade do mês ainda em aberto → passa a ter o valor do novo plano e o paciente vai ao Mercado Pago;
  - mês já pago e plano novo mais caro → é criada uma fatura só com a **diferença** e o paciente vai ao Mercado Pago;
  - mês já pago e plano novo mais barato → nada a pagar agora; o novo valor vale a partir da próxima fatura.
- As regras ficam em `inc/pagamento.php` (também usadas pelo Financeiro e pela API do app).
- O retorno do Mercado Pago (`retorno_pagamento.php`) confere o pagamento na API oficial e também se o valor pago cobre a fatura.

## E-mail de "Esqueci minha senha" (Resend)

O site não usa o Supabase Auth (os logins ficam nas tabelas `Cadastro_Users` e `Cadastro_Profissionais`), então os e-mails
do Supabase Auth não são usados aqui. O PHP envia o e-mail direto pela **API do Resend** (HTTPS), com a mesma conta Resend
ligada ao Supabase. Usamos a API em vez de SMTP porque o Railway bloqueia SMTP nos planos gratuito/Hobby.

1. Em https://resend.com/api-keys crie uma API Key com permissão **Sending access** (se você ligou o Resend ao Supabase
   pela integração, a chave já existe na conta; crie outra só para o site).
2. Em https://resend.com/domains adicione e verifique o seu domínio (registros DNS). Sem domínio verificado, o Resend só
   aceita o remetente `onboarding@resend.dev` e só entrega para o e-mail dono da conta Resend.
3. No Railway, cadastre `RESEND_API_KEY`, `RESEND_FROM` (com o domínio verificado) e `SITE_URL`.
4. Teste em `esqueciSenha.html`. Erros de envio aparecem nos logs do Railway como `[Acesso+] Resend HTTP ...`.

Enquanto `RESEND_API_KEY` não estiver configurada, o site continua no modo de demonstração e mostra o link na tela.

## 3. Apontar o app para o site

Em `app/src/main/java/com/example/acessomais/ApiClient.java`, troque `BASE_URL` por
`https://xxxx.up.railway.app/api_app.php` e gere o app de novo.

## Testar no computador (opcional)

Copie `.env.example` para `.env`, preencha `DATABASE_URL` e rode `php -S localhost:8000` dentro da pasta `site/`. Precisa ter PHP 8 instalado, com a extensão `pdo_pgsql` ativa.

## Segurança

- As senhas de pacientes e profissionais são gravadas com `password_hash` (bcrypt). Contas antigas, com senha sem criptografia, são convertidas automaticamente no primeiro login.
- Novas tabelas e colunas (mensagens do Fale Conosco, recuperação de senha do profissional) são criadas automaticamente pelo `conexao.php` (`garantir_estrutura`), sem precisar rodar SQL no Supabase.
