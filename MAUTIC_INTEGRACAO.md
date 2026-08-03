# Integração Mautic - Fluxo Cursos

Este documento descreve como o site institucional `fluxocursos.com.br` envia leads para o Mautic em `mautic.fluxo.com.br` e como manter a integração saudável.

## Visão geral

- `fluxocursos.com.br` é a vitrine.
- `mautic.fluxo.com.br` é a plataforma de automação de marketing.
- O site envia leads via API REST do Mautic com OAuth2 Password Grant.
- Cada lead recebe tags para segmentação no Mautic.

## Variáveis de ambiente

Configure no Easypanel, em **Ambiente**, sem segredos versionados:

```text
MAUTIC_BASE_URL=https://mautic.fluxo.com.br
MAUTIC_PUBLIC_USER=fluxocursos-site
MAUTIC_PUBLIC_PASS=<senha do usuário de integração>
MAUTIC_PUBLIC_CLIENT_ID=<Client ID OAuth2>
MAUTIC_PUBLIC_CLIENT_SECRET=<Client Secret OAuth2>
```

## Como obter as credenciais

1. Habilite a API: Settings → Configuration → API Settings → API enabled.
2. Crie um usuário dedicado: Settings → Users → New. Use role com permissão de API.
3. Crie uma credencial OAuth2: Settings → Integrations → API Credentials → New.
4. Copie Client ID e Client Secret imediatamente. Eles só aparecem uma vez.

## Tags geradas automaticamente

| Origem | Tag |
|---|---|
| Formulário de contato | `lead_contato_site`, `site-fluxocursos` |
| Lista de interesse | `lista_espera`, `site-fluxocursos`, `lista_espera_<slug>` |

## Endpoints

- `enviar_email.php` recebe o formulário de contato e sincroniza com o Mautic.
- `lista_interesse.php` recebe capturas de cursos em "EM BREVE" e sincroniza com o Mautic.

## QA

1. Acesse `https://fluxocursos.com.br/?utm_source=teste` em janela anônima.
2. Verifique no Mautic, em **Contacts**, se um novo contato apareceu com a tag `site-fluxocursos`.
3. Em `cursos.html`, abra um modal "EM BREVE" e submeta o formulário.
4. Confirme se o contato recebeu a tag `lista_espera_<slug>`.

## Boas práticas

- Não versionar credenciais no GitHub.
- Não usar a conta admin do Mautic como `MAUTIC_PUBLIC_USER`.
- Revogar credenciais em caso de troca de equipe ou suspeita de vazamento.
- Revisar mensalmente os contatos com tag `site-fluxocursos` no Mautic.