# Semana 3 - Integração WooCommerce/LearnDash com Mautic

Esta semana conecta a loja `loja.fluxocursos.com.br` (WordPress + WooCommerce + LearnDash) com o Mautic em `mautic.fluxo.com.br`, passando pela vitrine `fluxocursos.com.br` como proxy autenticado.

## Arquitetura

```
[loja.fluxocursos.com.br]
   | WooCommerce order_completed, learndash_lesson_completed
   v
[Plugin fluxo-woo-mautic] -- POST JSON assinado -->
   |
   v
[https://fluxocursos.com.br/api/webhook-woo.php]
   | valida HMAC, atualiza tags no Mautic via OAuth2 Client Credentials
   v
[mautic.fluxo.com.br]
```

## Arquivos criados

- `api/webhook-woo.php` - endpoint receptor na vitrine.
- `wp-plugin/fluxo-woo-mautic/fluxo-woo-mautic.php` - plugin WordPress.

## Instalação na vitrine

1. Faca commit e push do endpoint:
   ```powershell
   git add api/webhook-woo.php
   git commit -m "Adiciona endpoint de webhook para WooCommerce"
   git push origin main
   ```

2. No Easypanel, reimplantar.

3. Configure a variavel de ambiente na vitrine:
   ```text
   FLUXO_WEBHOOK_SECRET=<escolha uma string longa e aleatória>
   ```
   Use o mesmo valor depois no plugin WordPress.

4. Gere um segredo forte, por exemplo:
   ```bash
   openssl rand -hex 32
   ```

5. Acesse `https://fluxocursos.com.br/api/webhook-woo.php` para confirmar que retorna 405 (so aceita POST).

## Instalação na loja WordPress

1. Empacote o plugin:
   ```powershell
   Compress-Archive -Path wp-plugin/fluxo-woo-mautic -DestinationPath fluxo-woo-mautic.zip
   ```

2. No painel WordPress da loja, va em **Plugins > Adicionar novo > Enviar plugin**.
3. Faca upload de `fluxo-woo-mautic.zip` e clique em **Instalar agora**.
4. Clique em **Ativar**.
5. Va em **Configuracoes > Fluxo - Mautic**.
6. Preencha:
   - **Endpoint do webhook**: `https://fluxocursos.com.br/api/webhook-woo.php`
   - **Segredo compartilhado**: o mesmo `FLUXO_WEBHOOK_SECRET` definido na vitrine.
7. Selecione os eventos que deseja enviar.
8. Clique em **Salvar configurações**.

## Eventos enviados

| Evento WordPress | Evento Mautic | Tags aplicadas |
|---|---|---|
| `woocommerce_order_status_completed` | `pedido_pago` | `cliente_pago`, `pedido_<id>`, `produto_<slug>` por item |
| `woocommerce_order_status_processing` | `pedido_pago` | idem |
| `woocommerce_order_status_cancelled` | `pedido_cancelado` | `pedido_cancelado` |
| `woocommerce_order_status_refunded` | `reembolso` | `reembolso` |
| `learndash_lesson_completed` (primeira) | `matricula_criada` | `aluno_ativo`, `aluno_<slug>` |
| `learndash_course_completed` | `matricula_concluida` | `aluno_concluinte`, `concluiu_<slug>` |

## Configuração no Mautic

Crie os segmentos abaixo em Segments > New:

### clientes_pagos
- Regra: tag is `cliente_pago`
- Acao: ativar fluxo de boas-vindas.

### alunos_ativos
- Regra: tag is `aluno_ativo`
- Acao: disparar fluxo de onboarding do LMS.

### reembolsos
- Regra: tag is `reembolso`
- Acao: fluxo de retenção e pesquisa de motivo.

## Validacao

1. Na loja, crie um pedido de teste e marque como Concluido.
2. No log do plugin (ative **Depuração** nas configurações), verifique `Webhook pedido_pago enviado (status 200)`.
3. No Mautic, procure o e-mail do cliente.
4. Confirme as tags `cliente_pago`, `pedido_<id>`, `produto_<slug>`.
5. Crie uma matrícula LearnDash e conclua uma aula. Verifique tag `aluno_ativo` ou `aluno_concluinte`.

## Seguranca

- Webhook protegido por HMAC SHA-256 com segredo compartilhado.
- Endpoint aceita somente POST.
- Validação server-side em PHP com `hash_equals` (tempo constante).
- Tags geradas com `sanitizeTag` para evitar caracteres invalidos.

## Variaveis de ambiente necessárias na vitrine

- `MAUTIC_BASE_URL`
- `MAUTIC_PUBLIC_CLIENT_ID`
- `MAUTIC_PUBLIC_CLIENT_SECRET`
- `CONTACT_RECIPIENT`
- `CONTACT_FROM`
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_ENCRYPTION`
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `FLUXO_WEBHOOK_SECRET`