<?php
include "conexao.php";
garantir_estrutura($con);

/*
 * Recuperação de senha de PACIENTES e PROFISSIONAIS.
 * 1. Procura o e-mail na tabela certa (conforme o tipo de conta).
 * 2. Gera um token aleatório válido por 1 hora e grava no cadastro.
 * 3. Envia o link por e-mail pelo Resend (variável RESEND_API_KEY, a mesma conta ligada ao Supabase).
 *    Se o Resend ainda não estiver configurado, mostra o link na tela (modo de demonstração do TCC).
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

$emailConfigurado = $resend_api_key !== '';
$linkDemonstracao = null; // só usado quando o Resend não está configurado
$falhaEnvio = false;

$verifica = $con->query("SELECT {$t['id']}, {$t['nome']}, {$t['email']} FROM {$t['tabela']} WHERE LOWER({$t['email']}) = LOWER(?)", [$email]);
if ($email !== '' && $verifica && $verifica->num_rows > 0) {
    $usuario = $verifica->fetch_assoc();
    $token = bin2hex(random_bytes(16));
    $expiracao = date("Y-m-d H:i:s", strtotime('+1 hour'));
    $con->query("UPDATE {$t['tabela']} SET Token_Senha = ?, Expiracao_Token = ? WHERE {$t['id']} = ?", [$token, $expiracao, (int)$usuario[$t['id']]]);

    $linkRecuperacao = url_base_site() . "/redefinirSenha.php?token=" . $token;

    if ($emailConfigurado) {
        $nome = htmlspecialchars(explode(' ', trim($usuario[$t['nome']]))[0]);
        $linkHtml = htmlspecialchars($linkRecuperacao);
        $html = <<<HTML
<div style="background:#f8fafc;padding:32px 16px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;">
    <h1 style="margin:0 0 8px;font-size:22px;color:#0f259e;">Acesso+</h1>
    <h2 style="margin:0 0 20px;font-size:18px;color:#0f172a;">Redefinição de senha</h2>
    <p style="margin:0 0 12px;line-height:1.5;">Olá, {$nome}!</p>
    <p style="margin:0 0 24px;line-height:1.5;">Recebemos um pedido para redefinir a senha da sua conta no Acesso+. Clique no botão abaixo para criar uma nova senha:</p>
    <p style="text-align:center;margin:0 0 24px;">
      <a href="{$linkHtml}" style="display:inline-block;background:#2668D7;color:#ffffff;text-decoration:none;font-weight:bold;padding:14px 28px;border-radius:10px;">Criar nova senha</a>
    </p>
    <p style="margin:0 0 8px;font-size:13px;color:#64748b;line-height:1.5;">Se o botão não funcionar, copie e cole este endereço no navegador:<br><a href="{$linkHtml}" style="color:#2668D7;word-break:break-all;">{$linkHtml}</a></p>
    <p style="margin:16px 0 0;font-size:13px;color:#64748b;line-height:1.5;">O link expira em 1 hora. Se você não fez este pedido, ignore este e-mail: sua senha continua a mesma.</p>
  </div>
</div>
HTML;
        $texto = "Olá, " . $usuario[$t['nome']] . "!\n\n"
            . "Você solicitou a redefinição da sua senha no Acesso+.\n"
            . "Acesse o link abaixo para criar uma nova senha:\n" . $linkRecuperacao . "\n\n"
            . "Se você não solicitou isso, ignore este e-mail. O link expira em 1 hora.";
        $falhaEnvio = !enviar_email($usuario[$t['email']], "Redefinição de senha - Acesso+", $html, $texto);
    } else {
        $linkDemonstracao = $linkRecuperacao;
    }
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
                    <?php if ($linkDemonstracao): ?>
                        <p style="text-align: center; color: #475569; margin-bottom: 16px;">
                            O envio de e-mails ainda não foi configurado no servidor (variável RESEND_API_KEY).
                            Use o botão abaixo para criar a nova senha (com o Resend configurado, este link chega no e-mail cadastrado).
                        </p>
                        <a href="<?php echo htmlspecialchars($linkDemonstracao); ?>" class="esqueci-senha" style="display:block; text-align:center; font-weight:600;">Criar nova senha &rarr;</a>
                    <?php elseif ($falhaEnvio): ?>
                        <p style="text-align: center; color: #ef4444;">
                            Não foi possível enviar o e-mail agora. Tente novamente em alguns minutos.
                        </p>
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
