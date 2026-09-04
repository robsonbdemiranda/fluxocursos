# Mautic - Tags e Segmentos (Semana 2)

Lista completa de tags e segmentos para configurar no Mautic após a implantacao da Semana 2.

## Tags automaticas

| Origem | Tag | Quando aplicar |
|---|---|---|
| Formulario de contato | `lead_contato_site` | Ao receber mensagem em `enviar_email.php` |
| Formulario de contato | `site-fluxocursos` | Em todos os leads vindos do site |
| Lista de interesse | `lista_espera` | Captura de cursos futuros; mantida para compatibilidade com campanhas existentes |
| Lista de interesse | `lista_espera_curso` | Captura de cursos futuros |
| Lista de interesse | `lista_espera_material` | Captura de materiais futuros |
| Lista de interesse | `lista_espera_<slug>` | Captura em `lista_interesse.php` (slug do curso) |
| Lista de interesse | `site-fluxocursos` | Todas as capturas de cursos e materiais futuros |
| Download de material | `download_material` | Captura em `material_download.php` |
| Download de material | `download_<slug>` | Captura em `material_download.php` (slug do material) |
| Consentimento de marketing | `consentimento_marketing` | Aplicada no download ou lista de interesse somente quando o contato autoriza comunicações |
| Pop-up exit-intent | `exit_intent_fluxo` | Quando o JS `exit-intent.js` capturar o lead (futuro) |

## Tags para criar manualmente em Settings -> Tags

```text
lead_contato_site
site-fluxocursos
lista_espera
download_material
consentimento_marketing
exit_intent_fluxo
```

As tags filhas (`lista_espera_<slug>` e `download_<slug>`) sao criadas automaticamente pelo PHP quando o lead captura um item pela primeira vez.

Os materiais futuros usam `lista_espera_material` com `lista_espera_cases-clinicos-comentados` ou `lista_espera_aula-gravada-doppler`. Eles não recebem `lista_espera`, evitando sua entrada nas campanhas de abertura de turmas.

## Segmentos para criar em Segments -> New

### leads_site_fluxo
- Regra: tag is `site-fluxocursos`
- Uso: monitorar volume total de leads vindos do site.

### leads_qualificados
- Regra: tag is `lista_espera` OR tag is `lista_espera_material` OR tag is `download_material`
- Uso: leads que demonstraram interesse alem do contato simples.

### leads_lista_espera_cursos
- Regra: tag is `lista_espera_curso`
- Uso: acionar fluxo de aviso de turma aberta.

### leads_lista_espera_materiais
- Regra: tag is `lista_espera_material`
- Uso: avisar quando cases clínicos ou aulas gratuitas forem publicados.

### leads_download_tabela_cim
- Regra: tag is `download_tabela-cim-aric` OR tag is `download_tabela-cim-caps` OR tag is `download_tabela-cim-elsa` OR tag is `download_tabela-cim-mesa`
- Uso: identificar leads com interesse em normas de referencia para CIM.

### leads_download_tabela_cim_optin
- Regra: tag is `consentimento_marketing` AND (tag is `download_tabela-cim-aric` OR tag is `download_tabela-cim-caps` OR tag is `download_tabela-cim-elsa` OR tag is `download_tabela-cim-mesa`)
- Uso: fonte das campanhas de nutricao para contatos que autorizaram comunicacoes.

### leads_captados_blog
- Regra: utm_source is `blog-organico` (aplicado via GTM/UTM no Mautic)
- Uso: medir eficacia do blog na geracao de leads.

## Como criar tags no Mautic

1. Settings -> Tags
2. New
3. Name: nome exato da tag (sem espacos)
4. Save

## Como criar segmentos no Mautic

1. Segments -> New
2. Name: nome do segmento
3. Description opcional
4. Filters -> Add filter
5. Escolher o campo (ex.: `tags`) e a condicao (ex.: `is`)
6. Salvar

## Validacao apos deploy

1. Abrir `https://fluxocursos.com.br/materiais.html` em janela anonima.
2. Clicar em **Baixar PDF** de qualquer tabela CIM.
3. Preencher o modal e enviar.
4. No Mautic, em **Contacts**, pesquisar o e-mail usado.
5. Confirmar tags:
   - `site-fluxocursos`
   - `download_material`
   - `download_tabela-cim-<slug>`
   - `consentimento_marketing`, somente quando a opcao de receber comunicacoes for marcada
6. Em **Segments**, abrir `leads_site_fluxo` e confirmar que o lead aparece.

## Eventos para campanhas futuras

Quando o Mautic estiver processando os dados, criar campanhas para:

| Trigger | Acao |
|---|---|
| Tag `download_tabela-cim-elsa` | E-mail "Voce baixou Tabela CIM do ELSA-Brasil: quer conferir o Ecovasc?" |
| Tag `lista_espera_<slug>` | E-mail avisando abertura de turma, com CTA para a loja |
| Sem acao por 7 dias apos captura | E-mail de reativacao com case clinico |

Esses eventos serao configurados em uma fase posterior, apos validar volume de leads.
