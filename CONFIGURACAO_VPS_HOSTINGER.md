# Configuracao SMTP na VPS Hostinger

Este guia configura o envio de e-mails do formulario de contato via Amazon SES. Execute os comandos na VPS por SSH. Nunca salve credenciais SMTP no GitHub ou em arquivos publicos.

## Dados SMTP

```text
CONTACT_RECIPIENT=contato@fluxo.com.br
CONTACT_FROM=contato@fluxo.com.br
SMTP_HOST=email-smtp.us-east-2.amazonaws.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=<USUARIO_SMTP_GERADO_NO_SES>
SMTP_PASSWORD=<SENHA_SMTP_GERADA_NO_SES>
```

A porta `587` do Amazon SES usa STARTTLS. Por isso, `SMTP_ENCRYPTION` deve ser `tls`, e nao `ssl`.

## Identificar o servidor web

Conecte-se a VPS e execute:

```bash
sudo systemctl status nginx
sudo systemctl status apache2
sudo systemctl list-units --type=service | grep php
```

Siga somente a secao correspondente ao servidor em uso.

## Nginx com PHP-FPM

1. Localize a versao do PHP-FPM, por exemplo `php8.3-fpm`.
2. Edite o arquivo do pool, ajustando a versao quando necessario:

```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

3. Adicione ao final do arquivo:

```ini
env[CONTACT_RECIPIENT] = contato@fluxo.com.br
env[CONTACT_FROM] = contato@fluxo.com.br
env[SMTP_HOST] = email-smtp.us-east-2.amazonaws.com
env[SMTP_PORT] = 587
env[SMTP_ENCRYPTION] = tls
env[SMTP_USERNAME] = COLE_O_USUARIO_SMTP_DO_SES
env[SMTP_PASSWORD] = COLE_A_SENHA_SMTP_DO_SES
```

4. Reinicie o PHP-FPM e recarregue o Nginx:

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

## Apache com mod_php

1. Localize o VirtualHost do dominio, por exemplo:

```bash
sudo nano /etc/apache2/sites-available/fluxocursos.conf
```

2. Dentro do bloco `<VirtualHost>`, adicione:

```apache
SetEnv CONTACT_RECIPIENT "contato@fluxo.com.br"
SetEnv CONTACT_FROM "contato@fluxo.com.br"
SetEnv SMTP_HOST "email-smtp.us-east-2.amazonaws.com"
SetEnv SMTP_PORT "587"
SetEnv SMTP_ENCRYPTION "tls"
SetEnv SMTP_USERNAME "COLE_O_USUARIO_SMTP_DO_SES"
SetEnv SMTP_PASSWORD "COLE_A_SENHA_SMTP_DO_SES"
```

3. Valide e recarregue o Apache:

```bash
sudo apachectl configtest
sudo systemctl reload apache2
```

## Requisitos do Amazon SES

1. Gere as credenciais SMTP na regiao `us-east-2` em Amazon SES > SMTP settings.
2. Use o usuario e a senha SMTP gerados pela AWS. O e-mail `contato@fluxo.com.br` nao e uma credencial SMTP.
3. Verifique a identidade `fluxo.com.br` ou o e-mail `contato@fluxo.com.br` no Amazon SES.
4. Se a conta SES estiver no sandbox, verifique cada destinatario ou solicite acesso de producao.
5. Mantenha `CONTACT_FROM` como endereco ou dominio verificado no SES.

## Teste e logs

Depois de recarregar os servicos, envie uma mensagem pela pagina de contato.

Para investigar falhas, use:

```bash
sudo journalctl -u php8.3-fpm -n 100
sudo tail -n 100 /var/log/nginx/error.log
sudo tail -n 100 /var/log/apache2/error.log
```

Use somente o log referente ao servidor web instalado.
