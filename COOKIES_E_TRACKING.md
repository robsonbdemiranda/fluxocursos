# Consentimento de Cookies e Tags

O site respeita as escolhas de cookies do visitante. Tags de marketing e analytics (GTM, Hotjar) só são carregadas apos o consentimento explicito.

## Como funciona

1. Cada pagina HTML inclui um placeholder (`#fluxo-consent-placeholder`) e o loader `js/consent-loader.js`.
2. O loader busca `md/partials/consent-snippet.html`, que contem somente o HTML do banner, e carrega `css/cookies.css`, `tracking.js` e `cookies.js` por elementos DOM executaveis.
3. `js/tracking.js` expoe:
   - `window.activateAnalytics()`: dispara GTM e Hotjar.
   - `window.activateMarketing()`: reservado para integracoes futuras.
   - `window.fluxoConsent.{read,save,clear}`: persistencia versionada em `localStorage` (`fluxo:consent`).
   - `window.fluxoFormContext`: retorna o consentimento e as UTMs atuais para os formularios.
4. `js/cookies.js` le a preferencia do usuario e envia o sinal para `tracking.js`.

Apos o aceite, o `dataLayer` recebe `event: fluxo_consent_update` para que tags condicionais no GTM respeitem o consentimento.

## Armazenamento

- `localStorage.fluxo:consent`: `{ version, analytics, marketing, updatedAt }`. Bump de `version` reinicializa o banner para todos.
- `sessionStorage.fluxo:utm`: UTMs da sessao, usadas por `materiais.js` para preencher campos ocultos antes do envio.

## UTMs em formularios

Os formularios de download e lista de interesse propagam UTMs para o backend por meio de `window.fluxoFormContext.utm()`. Os endpoints `material_download.php` e `lista_interesse.php` recebem `utm_source`, `utm_medium`, `utm_campaign`, `utm_term` e `utm_content` via `POST` e os encaminham ao Mautic como atributos do contato.

## Como validar

1. Abra o DevTools no navegador.
2. Limpe `localStorage` e `sessionStorage`.
3. Recarregue a pagina. Confirme que o banner aparece e que nenhum script de `googletagmanager` foi carregado.
4. Clique em `Salvar preferencias` com `Analise de uso` e `Marketing` marcados. Confirme que `localStorage.fluxo:consent` foi salvo e que `dataLayer` tem `event: fluxo_consent_update`.
5. Recarregue. Confirme que o GTM foi carregado e o banner nao aparece mais.

## Mudancas recentes

- Removido o loader da RD Station em todas as paginas.
- Removido o GTM inline (`GTM-KW5DHT9`) e o `<noscript>` correspondente.
- Banner de cookies padronizado em `md/partials/consent-snippet.html`.
- Botao "Apenas necessarios" grava `analytics: false, marketing: false`.
