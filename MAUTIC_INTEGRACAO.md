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
MAUTIC_PUBLIC_CLIENT_ID=<Client ID OAuth2>
MAUTIC_PUBLIC_CLIENT_SECRET=<Client Secret OAuth2>
```

## Como obter as credenciais

1. Habilite a API: Settings → Configuration → API Settings → API enabled.
2. Crie uma credencial OAuth2: Settings → Integrations → API Credentials → New.
3. Use **Redirect URI**: `https://fluxocursos.com.br/oauth/callback`.
4. Copie Client ID e Client Secret imediatamente. Eles só aparecem uma vez.
5. Esta integração usa **Client Credentials Grant**, sem necessidade de usuário e senha do Mautic.

## Tags geradas automaticamente

| Origem | Tag |
|---|---|
| Formulário de contato | `lead_contato_site`, `site-fluxocursos` |
| Lista de interesse em curso | `lista_espera`, `lista_espera_curso`, `site-fluxocursos`, `lista_espera_<slug>` |
| Lista de interesse em material | `lista_espera_material`, `site-fluxocursos`, `lista_espera_<slug>` |
| Pré-lançamento de livro | `lista_espera_livro_doppler_arterial`, `site-fluxocursos` e `pre_lancamento_doppler_arterial` (somente com opt-in) |

## Endpoints

- `enviar_email.php` recebe o formulário de contato e sincroniza com o Mautic.
- `lista_interesse.php` recebe capturas de cursos, materiais futuros e pré-lançamento do livro Doppler Arterial; exige aceite da Política de Privacidade e sincroniza com o Mautic.

## QA

1. Acesse `https://fluxocursos.com.br/?utm_source=teste` em janela anônima.
2. Verifique no Mautic, em **Contacts**, se um novo contato apareceu com a tag `site-fluxocursos`.
3. Em `cursos.html`, `materiais.html` ou `livros.html`, abra um modal "EM BREVE" e submeta o formulário.
4. Confirme se o contato recebeu `lista_espera_<slug>`, a tag de tipo (`lista_espera_curso`, `lista_espera_material` ou `lista_espera_livro_doppler_arterial`) e `site-fluxocursos`.
5. Confirme que `consentimento_marketing` aparece somente quando o opt-in foi marcado.
6. Em `livros.html`, a tag `pre_lancamento_doppler_arterial` aparece apenas se o opt-in foi marcado no pré-lançamento.

## Boas práticas

- Não versionar credenciais no GitHub.
- Não usar a conta admin do Mautic como `MAUTIC_PUBLIC_USER`.
- Revogar credenciais em caso de troca de equipe ou suspeita de vazamento.
- Revisar mensalmente os contatos com tag `site-fluxocursos` no Mautic.
- Manter o repositório público somente enquanto não houver dados pessoais reais; ao tornar privado, valide o convite dos colaboradores.
