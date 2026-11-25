<?php
include "conexao.php";
garantir_estrutura($con);

/*
 * Recuperação de senha de PACIENTES e PROFISSIONAIS.
 * 1. Procura o e-mail na tabela certa (conforme o tipo de conta).
 * 2. Gera um token aleatório válido por 1 hora e grava no cadastro.
 * 3. Tenta enviar o link por e-mail. Se o servidor não tiver envio de e-mail configurado
 *    (caso do Railway), mostra o link na tela para fins de demonstração do TCC.
 */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: esqueciSenha.html");
    exit();
}

$tipo  = (($_POST['tipo'] ?? '') === 'profissional') ? 'profissional' : 'paciente';
$email = trim($_POST['email'] ?? '');

$tabelas = [
    'paciente'     => ['tabela' => 'Cadastro_Users',         'id' => 'Id_User',         'nome' => 'Nome_User',         'email' => 'Email_User',         'login' => 'loginUser.html'],
    'profissional' => ['tabela' => 'Cadastro_Profissionais', 'id' => 'Id_Profissional', 'nome' => 'Nome_Profissional', 'email' => 'Email_Profissional', 'login' => 'loginProfissional.html'],
];
$t = $tabelas[$tipo];

$linkRecuperacao = null;
$emailEnviado = false;

$verifica = $con->query("SELECT {$t['id']}, {$t['nome']} FROM {$t['tabela']} WHERE {$t['email']} = ?", [$email]);
if ($verifica && $verifica->num_rows > 0) {
    $usuario = $verifica->fetch_assoc();
    $token = bin2hex(random_bytes(16));
    $expiracao = date("Y-m-d H:i:s", strtotime('+1 hour'));
    $con->query("UPDATE {$t['tabela']} SET Token_Senha = ?, Expiracao_Token = ? WHERE {$t['id']} = ?", [$token, $expiracao, (int)$usuario[$t['id']]]);

    $protocolo = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || !empty($_SERVER['HTTPS'])) ? 'https' : 'http';
    $linkRecuperacao = $protocolo . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/redefinirSenha.php?token=" . $token;

    $mensagem = "Olá, " . $usuario[$t['nome']] . "!\n\n"
        . "Você solicitou a redefinição da sua senha no Acesso+.\n"
        . "Clique no link abaixo para criar uma nova senha:\n" . $linkRecuperacao . "\n\n"
        . "Se você não solicitou isso, ignore este e-mail. O link expira em 1 hora.";
    $headers = "From: nao-responda@" . $_SERVER['HTTP_HOST'] . "\r\nContent-Type: text/plain; charset=UTF-8";
    $emailEnviado = @mail($email, "Recuperacao de Senha - Acesso+", $mensagem, $headers);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha | Acesso+</title>
    <link rel="stylesheet" href="css/loginUser.css">
</head>
<body>
    <section class="containerPai">
        <a href="<?php echo $t['login']; ?>" class="logo-card"><img src="img/logo.png" alt="Logo Acesso+ e voltar para o login"></a>
        <div class="card loginActive">
            <div class="esquerda">
                <div class="formLogin" style="width: 100%;">
                    <h2>Recuperar Senha</h2>
                    <?php if ($linkRecuperacao && !$emailEnviado): ?>
                        <p style="text-align: center; color: #475569; margin-bottom: 16px;">
                            O servidor de demonstração não envia e-mails. Use o botão abaixo para criar a nova senha
                            (em produção, este link chegaria no e-mail cadastrado).
                        </p>
                        <a href="<?php echo htmlspecialchars($linkRecuperacao); ?>" class="esqueci-senha" style="display:block; text-align:center; font-weight:600;">Criar nova senha &rarr;</a>
                    <?php else: ?>
                        <p style="text-align: center; color: #475569;">
                            Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha. Verifique também a caixa de spam.
                        </p>
                    <?php endif; ?>
                    <a href="<?php echo $t['login']; ?>" class="esqueci-senha" style="display: block; text-align: center; margin-top: 20px;">&larr; Voltar para o Login</a>
                </div>
            </div>
            <div class="direita" style="background: url('img/background2.png') no-repeat center center / cover;"></div>
        </div>
    </section>
</body>
</html>
