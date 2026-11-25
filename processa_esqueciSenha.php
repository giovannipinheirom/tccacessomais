<?php
session_start();
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

// Evita vários e-mails seguidos para o mesmo endereço (no máximo 1 a cada 60 segundos)
$chaveEnvio = $tipo . '|' . strtolower($email);
$ultimoEnvio = $_SESSION['envios_senha'][$chaveEnvio] ?? 0;
$aguardar = $emailConfigurado && (time() - $ultimoEnvio) < 60;

$verifica = $aguardar ? false : $con->query("SELECT {$t['id']}, {$t['nome']}, {$t['email']} FROM {$t['tabela']} WHERE LOWER({$t['email']}) = LOWER(?)", [$email]);
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
        if (!$falhaEnvio) $_SESSION['envios_senha'][$chaveEnvio] = time();
    } else {
        $linkDemonstracao = $linkRecuperacao;
    }
}
$voltarLogin = $t['login'];
$icone = [
    'email' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>',
    'erro'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>',
    'chave' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha | Acesso+</title>
    <link rel="stylesheet" href="css/senha.css">
</head>
<body class="pagina-senha">
    <main class="senha-card">
        <?php include 'inc/ladoSenha.php'; ?>

        <section class="senha-conteudo">
            <div class="senha-topo">
                <a href="index.html"><img src="img/logo.png" alt="Acesso+ - página inicial"></a>
                <a href="<?php echo $voltarLogin; ?>" class="senha-voltar">&larr; Voltar para o login</a>
            </div>

            <div class="senha-corpo" role="status">
                <?php if ($linkDemonstracao): ?>
                    <div class="senha-icone" aria-hidden="true"><?php echo $icone['chave']; ?></div>
                    <h1>Link de recuperação</h1>
                    <p class="senha-sub">
                        O envio de e-mails ainda não foi configurado no servidor (variável <strong>RESEND_API_KEY</strong>).
                        Para demonstração, use o botão abaixo para criar a nova senha.
                    </p>
                    <div class="acoes-senha">
                        <a href="<?php echo htmlspecialchars($linkDemonstracao); ?>" class="btn-senha">Criar nova senha</a>
                    </div>
                <?php elseif ($falhaEnvio): ?>
                    <div class="senha-icone erro" aria-hidden="true"><?php echo $icone['erro']; ?></div>
                    <h1>Não conseguimos enviar</h1>
                    <p class="senha-sub">Houve um problema ao enviar o e-mail agora. Aguarde alguns minutos e tente de novo.</p>
                    <div class="acoes-senha">
                        <a href="esqueciSenha.html<?php echo $tipo === 'profissional' ? '?tipo=profissional' : ''; ?>" class="btn-senha">Tentar novamente</a>
                    </div>
                <?php else: ?>
                    <div class="senha-icone" aria-hidden="true"><?php echo $icone['email']; ?></div>
                    <h1>Verifique seu e-mail</h1>
                    <p class="senha-sub">
                        Se existir uma conta com <strong><?php echo htmlspecialchars($email); ?></strong>,
                        você vai receber um link para criar uma nova senha.
                    </p>
                    <ol class="passos-email">
                        <li>Abra o e-mail “Redefinição de senha - Acesso+”</li>
                        <li><span>Clique em <strong>Criar nova senha</strong></span></li>
                        <li>Escolha a nova senha e entre normalmente</li>
                    </ol>
                    <div class="acoes-senha">
                        <a href="<?php echo $voltarLogin; ?>" class="btn-senha">Voltar para o login</a>
                        <form action="processa_esqueciSenha.php" method="post">
                            <input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            <button type="submit" class="btn-secundario">Não recebi, enviar de novo</button>
                        </form>
                    </div>
                    <p class="texto-pequeno">
                        O link vale por 1 hora. Confira também a caixa de spam ou lixo eletrônico.
                        <?php if ($aguardar): ?><br>Aguarde 1 minuto entre um envio e outro.<?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
