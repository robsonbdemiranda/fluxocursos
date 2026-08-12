# Semana 4 - Integração Comunidade Clube do Doppler + Mautic

Esta semana integra a comunidade em `https://app.clubedodoppler.com.br` (BuddyBoss Platform + WordPress) com o Mautic em `mautic.fluxo.com.br`, passando pela vitrine `fluxocursos.com.br` como proxy autenticado.

## Arquitetura

```
[Comunidade Clube do Doppler - BuddyBoss]
   | hook bp_core_signup_user
   | hook xprofile_updated_profile
   v
[Plugin fluxo-buddyboss-mautic]
   | POST JSON assinado HMAC SHA-256
   v
[fluxocursos.com.br/api/webhook-buddyboss.php]
   | valida assinatura, atualiza tags no Mautic via OAuth2
   v
[mautic.fluxo.com.br]
```

## Arquivos criados

- `api/webhook-buddyboss.php` - endpoint receptor na vitrine.
- `wp-plugin/fluxo-buddyboss-mautic/fluxo-buddyboss-mautic.php` - plugin WordPress.
- `comunidade.html` - página institucional da comunidade no site.
- `css/comunidade.css` - estilo da página.

## Instalação na vitrine

1. Faca commit, push e reimplante:
   ```powershell
   git add api/webhook-buddyboss.php comunidade.html css/comunidade.css
   git commit -m "Adiciona webhook BuddyBoss e pagina /comunidade"
   git push origin main
   ```

2. No Easypanel, reimplante.

3. Confirme que `FLUXO_WEBHOOK_SECRET` já está configurado (mesmo segredo usado pelo WooCommerce).

## Instalação na comunidade BuddyBoss

### Regra de ativação de conta

O plugin **só envia leads ao Mautic quando o cadastro é confirmado**. Signups pendentes (`pending signups`, `user_status = 2`) são ignorados.

Quem ativa o cadastro:

- Confirmação por e-mail.
- Aprovação manual do administrador.

Em ambos os casos, o plugin captura via hook `bp_core_activated_user`.

### Pré-requisito: criar campos xProfile

1. No painel da comunidade, vá em **Usuários > Profile Fields**.
2. Crie os campos com os slugs exatos (em "Name" e "Label"):
   - `Especialidade` - tipo texto curto.
   - `Cidade` - tipo texto curto.
   - `CRM` - tipo texto curto.
   - `Telefone` - tipo telefone.

### Pré-requisito: gerar Application Passwords

1. No painel da comunidade, vá em **Usuários > Seu Perfil**.
2. Role ate a seção **Application Passwords**.
3. Em "New Application Password Name", digite `Fluxo Cursos - Mautic`.
4. Clique em **Add**.
5. Copie a senha gerada (formato `xxxx xxxx xxxx xxxx xxxx xxxx`).

### Upload do plugin

1. Empacote:
   ```powershell
   Compress-Archive -Path wp-plugin\fluxo-buddyboss-mautic -DestinationPath fluxo-buddyboss-mautic.zip
   ```

2. No painel da comunidade, vá em **Plugins > Adicionar novo > Enviar plugin**.
3. Instale e ative **Fluxo Cursos - BuddyBoss Mautic**.

### Configuração

1. Vá em **Configurações > Fluxo - BuddyBoss Mautic**.
2. Preencha:
   - **Endpoint do webhook**: `https://fluxocursos.com.br/api/webhook-buddyboss.php`
   - **Segredo compartilhado**: o mesmo `FLUXO_WEBHOOK_SECRET`.
   - **Slug da comunidade**: `clube-do-doppler`.
3. Marque todos os eventos.
4. Ative a **Depuração** para testar.
5. Clique em **Salvar configurações**.

## Sincronização inicial dos 900 membros existentes

Como o BuddyBoss não tem webhook de saída, os 900 membros históricos precisam ser sincronizados via REST API.

### Passo 1 - Configurar Application Passwords

A senha gerada no pré-requisito será usada aqui.

### Passo 2 - Executar sincronização

Implemente um pequeno script PHP que consome `/wp-json/buddyboss/v1/members` em lotes. Posso implementar este script quando você quiser.

## Tags geradas

| Tag | Quando |
|---|---|
| `site-fluxocursos` | Todos os leads vindos do site |
| `comunidade_clube_doppler` | Membros da comunidade |
| `comunidade_<slug>` | Identifica a comunidade (slug configurável) |
| `comunidade_novo_cadastro` | Marca que o membro é novo |
| `comunidade_perfil_atualizado` | Disparado por `xprofile_updated_profile` |
| `comunidade_especialidade_<slug>` | Baseado no campo xProfile |
| `comunidade_cidade_<slug>` | Baseado no campo xProfile |
| `comunidade_crm_<slug>` | Baseado no campo xProfile |

## Segmentos sugeridos para o Mautic

1. `comunidade_clube_doppler`
   - Regra: tag is `comunidade_clube_doppler`

2. `comunidade_novos_cadastros`
   - Regra: tag is `comunidade_novo_cadastro`

3. `comunidade_cirurgioes_vasculares`
   - Regra: tag is `comunidade_especialidade_cirurgiao-vascular`

## Campanhas sugeridas

### Newsletter semanal da comunidade

- Segmento: `comunidade_clube_doppler`.
- Gatilho: agendado para terça-feira às 9h.
- Conteudo: resumo da semana, eventos, podcast, casos clínicos.

### Boas-vindas ao novo membro

- Gatilho: tag `comunidade_novo_cadastro`.
- Conteudo: boas-vindas, como participar, regras da comunidade.

### Aviso de evento especial

- Gatilho: manual ou tag customizada.
- Conteudo: data, horário, link de inscrição no Clube do Doppler.

## Página /comunidade.html

A nova página `/comunidade.html` da vitrine apresenta:

- Hero com descrição do Clube do Doppler.
- Cards com: eventos ao vivo, podcast "Aumenta o Ganho", newsletter, networking, formação contínua, como participar.
- CTA para `https://app.clubedodoppler.com.br`.
- Cross-link para `cursos.html` no CTA final.

Adicione ao menu superior `COMUNIDADE` em todas as páginas do site.

## Próximos passos

1. Commit e push dos arquivos da Semana 4.
2. Deploy no Easypanel.
3. Configurar BuddyBoss no painel WordPress.
4. Sincronizar os 900 membros.
5. Criar segmentos e campanhas no Mautic.
6. Adicionar link COMUNIDADE em todas as páginas HTML do site.

Posso continuar com a sincronização em massa via REST API quando você confirmar que tem o Application Password pronto.