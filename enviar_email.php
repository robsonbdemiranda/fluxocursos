<?php
$nome = $_POST['nome'];
$telefone = $_POST['telefone'];
$email = $_POST['email'];
$mensagem = $_POST['mensagem'];


$subject = 'Contato do Site - Fluxo Cursos';
$message = 'Nome: '. $nome . "\n".'Telefone: '. $telefone . "\n" . 
'Email: '. $email . "\n". 
'Mensagem: '. $mensagem;
$headers = 'From: cursoduplexweb@gmail.com' . "\r\n" .
    'Reply-To: cursoduplexweb@gmail.com' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();
echo mail("cursoduplexweb@gmail.com", $subject, $message, $headers);
?> 