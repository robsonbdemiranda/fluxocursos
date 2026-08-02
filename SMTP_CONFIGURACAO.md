# Configuracao SMTP - Amazon SES

O formulario de contato usa SMTP autenticado do Amazon SES na regiao `us-east-2`.

## Variaveis do servidor

Configure estas variaveis no ambiente de producao. Nao salve credenciais em arquivos versionados.

```text
CONTACT_RECIPIENT=contato@fluxo.com.br
CONTACT_FROM=contato@fluxo.com.br
SMTP_HOST=email-smtp.us-east-2.amazonaws.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=<credencial SMTP do SES>
SMTP_PASSWORD=<senha SMTP do SES>
```

Use `tls` com a porta `587` porque o SES utiliza STARTTLS nessa porta. `ssl` e usado somente com a porta `465`.

## Credenciais AWS

1. No console AWS, abra Amazon SES na regiao `us-east-2`.
2. Em `SMTP settings`, crie credenciais SMTP.
3. Use o nome de usuario e a senha SMTP gerados pela AWS em `SMTP_USERNAME` e `SMTP_PASSWORD`.
4. Nao use o e-mail `contato@fluxo.com.br` como usuario SMTP, pois ele nao e uma credencial SMTP do SES.

## Requisitos de envio

1. Verifique a identidade `fluxo.com.br` ou o endereco `contato@fluxo.com.br` no Amazon SES.
2. Se a conta estiver no sandbox do SES, verifique tambem cada destinatario ou solicite acesso de producao.
3. Mantenha `CONTACT_FROM` como endereco ou dominio verificado no SES.
4. Apos configurar as variaveis no servidor, envie uma mensagem de teste pela pagina de contato.
