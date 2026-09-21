# Manual de Lançamento - Livro Doppler Arterial

**Cliente:** Fluxo Cursos
**Data:** setembro de 2026
**Autor:** Equipe técnica Fluxo Cursos
**Versão:** 1.0

Este documento descreve o fluxo completo de captação, nutrição e lançamento do livro **Doppler Arterial - Carótidas, Abdome e Membros - Do Diagnóstico à Intervenção**, programado para 08 de outubro de 2026 pela editora DiLivros.

---

## 1. Resumo executivo

- O objetivo é construir uma audiência engajada antes do lançamento e converter parte dela em compradores do livro ou alunos dos cursos da Fluxo Cursos.
- O fluxo combina uma página dedicada (`livros.html`), tags e segmentos organizados no Mautic, três e-mails triggered com layout padronizado e três campanhas coordenadas por data.
- Toda a infraestrutura é rastreável por UTMs e respeita o consentimento de marketing (LGPD).

## 2. Cronograma

| Marco | Data | Ação |
|---|---|---|
| Publicação da página `livros.html` | 19/09/2026 | Cards com capas reais dos livros e botão "Quero ser avisado" para o pré-lançamento |
| Captação ativa | 19/09 a 07/10/2026 | Formulário do site coleta leads com tag automática |
| Campanha de confirmação | imediata após captura | Confirmação do pré-lançamento |
| Campanha de nutrição | a cada novo lead | Sequência de 3 e-mails (imediato + 2 dias + 5 dias) |
| Campanha de lançamento | 08/10/2026 às 10h | E-mail de disponibilidade com CTA de compra |
| Pós-lançamento | 09/10 em diante | Onboarding do livro nas campanhas já existentes |

## 3. Página `livros.html`

- Três livros em destaque: **Doppler de Carótidas e Vertebrais** (2018), **Doppler Venoso - Do Diagnóstico à Intervenção** (2024) e o pré-lançamento do **Doppler Arterial**.
- Apenas o terceiro livro exibe o botão **Quero ser avisado**.
- O formulário é o mesmo do fluxo de materiais (modal único com `js/lista-interesse.js`).
- Aceita a Política de Privacidade e opt-in de marketing.

## 4. Captura de leads

- Endpoint: `lista_interesse.php`.
- Campos obrigatórios: nome, e-mail, aceite de privacidade.
- Campos opcionais: telefone, opt-in de marketing.
- Honeypot: `website`.
- Origem fixa: `livros`.
- Rate limit: 60 segundos por IP + curso.
- Sincronização com o Mautic via OAuth2 Client Credentials.
- UTMs da sessão são propagados e adicionados como atributos do contato no Mautic via endpoint `/api/contacts/{id}/utm/add`.

## 5. Tags no Mautic

| Tag | Origem | Quando aplicar |
|---|---|---|
| `lista_espera_livro_doppler_arterial` | automática | Captura no formulário |
| `pre_lancamento_doppler_arterial` | automática | Apenas com opt-in de marketing |
| `livro_carotidas_publicado` | manual | Quando o livro for lançado para futuras campanhas |
| `livro_venoso_publicado` | manual | Idem para o livro Doppler Venoso |
| `livro_doppler_arterial_publicado` | manual | No dia 08/10/2026 para disparar o lançamento |

## 6. Segmentos no Mautic

### Categoria: Leads de Cursos

- `leads_interesse_cases_clinicos` - filtro: tag inclui `lista_espera_cases-clinicos-comentados`.
- `leads_interesse_aula_gravada` - filtro: tag inclui `lista_espera_aula-gravada-doppler`.
- `leads_lista_espera_materiais` - filtro: tag inclui `lista_espera_material`.
- `leads_lista_espera_cursos` - filtro: tag inclui `lista_espera_curso`.
- `leads_download_tabela_cim` - filtro: tag inclui `download_tabela-cim-*`.
- `leads_download_tabela_cim_optin` - tag inclui `consentimento_marketing` e `download_tabela-cim-*`.

### Categoria: Leads de Livros

- `leads_pre_lancamento_doppler_arterial` - filtro: tag inclui `lista_espera_livro_doppler_arterial`.
- `leads_pre_lancamento_doppler_arterial_optin` - filtro: tag inclui `lista_espera_livro_doppler_arterial` E `pre_lancamento_doppler_arterial`.
- `leads_livro_carotidas_publicado` - filtro: tag inclui `livro_carotidas_publicado`.
- `leads_livro_venoso_publicado` - filtro: tag inclui `livro_venoso_publicado`.
- `leads_livro_doppler_arterial_publicado` - filtro: tag inclui `livro_doppler_arterial_publicado`.

## 7. E-mails triggered

Todos usam o layout base `md/emails/confirmacao-interesse-material.html`, com logotipo, CTA verde e link de descadastro.

| E-mail | Origem | Função |
|---|---|---|
| `Confirmação - Pré-lançamento Doppler Arterial` | `md/emails/confirmacao-interesse-material.html` | Confirmar o interesse do lead |
| `Nutrição - Conteúdo técnico` | `md/emails/nutricao-conteudo-tecnico.html` | Apresentar materiais técnicos e PDFs |
| `Nutrição - Conheça os cursos` | a ser criado | Indicar cursos da Fluxo Cursos |
| `Lançamento - Doppler Arterial` | `md/emails/lancamento-doppler-arterial.html` | Notificar a publicação do livro |

## 8. Campanhas no Mautic

### Confirmação imediata

- **Categoria**: Leads de Livros.
- **Contact source**: `leads_pre_lancamento_doppler_arterial`.
- **Builder**: Segmento + ação **Send Email** para o e-mail de confirmação.
- **Execução**: imediata.
- **Published**: Não até validação.

### Nutrição com opt-in

- **Categoria**: Leads de Livros.
- **Contact source**: `leads_pre_lancamento_doppler_arterial_optin`.
- **Builder**: Segmento + três ações **Send Email** em sequência (imediato, 2 dias, 5 dias).
- **Execução**: imediata após captura.
- **Published**: Sim, durante todo o pré-lançamento.

### Lançamento

- **Categoria**: Leads de Livros.
- **Contact source**: `leads_pre_lancamento_doppler_arterial`.
- **Builder**: Segmento + **Condition by Tags** (tag `livro_doppler_arterial_publicado`) + **Send Email** com o e-mail de lançamento.
- **Execução**: `at a specific date/time` em **08/10/2026 às 10h**.
- **Published**: Não até o dia do lançamento. Aplicar manualmente a tag aos contatos do segmento `leads_pre_lancamento_doppler_arterial` antes de publicar.

## 9. Operação no dia 08/10/2026

1. Aplicar manualmente a tag `livro_doppler_arterial_publicado` aos contatos do segmento **Leads pré-lançamento Doppler Arterial**.
2. Publicar a campanha **Lançamento - Doppler Arterial**.
3. Acompanhar os eventos da campanha para confirmar o envio do e-mail de lançamento.
4. Atualizar o card do livro em `livros.html` removendo o badge **Pré-lançamento** e adicionando o CTA **Comprar na DiLivros**.

## 10. Pontos de atenção

- O site `dr.-robson-vascular` é um submódulo sem `.gitmodules`. Mudanças internas lá não são versionadas neste repositório principal.
- O repositório está atualmente público. Ao tornar privado, confirme os colaboradores e revise os templates de e-mail.
- O layout de e-mail depende do logotipo `https://fluxocursos.com.br/image/01-FLUXO-LOGO.png`. Não renomeie essa imagem.
- O endpoint `lista_interesse.php` requer `MAUTIC_BASE_URL`, `MAUTIC_PUBLIC_CLIENT_ID` e `MAUTIC_PUBLIC_CLIENT_SECRET` configurados no servidor. O SMTP também deve estar operacional.

## 11. Recursos úteis

- Repositório: https://github.com/robsonbdemiranda/fluxocursos
- Documentos internos: `MAUTIC_INTEGRACAO.md`, `MAUTIC_TAGS_E_SEGMENTOS.md`, `COOKIES_E_TRACKING.md`, `SEMANA_4_COMUNIDADE.md`.
- Templates de e-mail: `md/emails/*.html`.
- Layout da página de livros: `livros.html` e `css/livros.css`.

---

**Contato técnico:** Equipe Fluxo Cursos
**Agência parceira:** GRID Estratégia
