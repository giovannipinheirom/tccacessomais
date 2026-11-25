<?php
/*
 * Criar nova senha (link enviado por e-mail no "Esqueci minha senha").
 * GET  -> confere o token e mostra o formulário.
 * POST -> valida a senha (mesmas regras do cadastro), grava o hash, apaga o token
 *         e lê o registro de volta do banco para confirmar que a senha foi realmente salva.
 */
include "conexao.php";
garantir_estrutura($con);

$contas = [
    'paciente'     => ['tabela' => 'Cadastro_Users',         'id' => 'Id_User',         'email' => 'Email_User',         'senha' => 'Senha_User',         'login' => 'loginUser.html'],
    'profissional' => ['tabela' => 'Cadastro_Profissionais', 'id' => 'Id_Profissional', 'email' => 'Email_Profissional', 'senha' => 'Senha_Profissional', 'login' => 'loginProfissional.html'],
];

/** Procura a conta dona do token (dentro da validade). */
function conta_do_token(Banco $con, array $contas, string $token): ?array
{
    if ($token === '' || !ctype_xdigit($token)) return null;
    foreach ($contas as $tipo => $c) {
        $r = $con->query(
            "SELECT {$c['id']} AS id, {$c['email']} AS email FROM {$c['tabela']} WHERE Token_Senha = ? AND Expiracao_Token >= ? LIMIT 1",
            [$token, date("Y-m-d H:i:s")]
        );
        if ($r && $r->num_rows > 0) return $r->fetch_assoc() + ['tipo' => $tipo];
    }
    return null;
}

/** Mostra só o começo do e-mail (ex.: ma•••@gmail.com). */
function email_mascarado(string $email): string
{
    [$nome, $dominio] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($nome, 0, 2) . str_repeat('•', max(3, mb_strlen($nome) - 2)) . '@' . $dominio;
}

/** Mesmas regras de senha do cadastro (js/loginUser.js). Retorna a lista do que falta. */
function problemas_senha(string $senha): array
{
    $faltando = [];
    if (mb_strlen($senha) < 8) $faltando[] = 'ter pelo menos 8 caracteres';
    if (!preg_match('/[A-Z]/', $senha)) $faltando[] = 'ter uma letra maiúscula';
    if (!preg_match('/[a-z]/', $senha)) $faltando[] = 'ter uma letra minúscula';
    if (!preg_match('/[0-9]/', $senha)) $faltando[] = 'ter um número';
    if (!preg_match('/[^A-Za-z0-9]/', $senha)) $faltando[] = 'ter um símbolo (ex.: ! @ # $ . *)';
    return $faltando;
}

$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$conta = conta_do_token($con, $contas, $token);
$estado = $conta ? 'formulario' : 'invalido';
$erros = [];

if ($conta && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha = (string)($_POST['senha'] ?? '');
    $confirma = (string)($_POST['confirma_senha'] ?? '');
    $erros = problemas_senha($senha);
    if ($senha !== $confirma) $erros[] = 'ser igual nos dois campos';

    if (!$erros) {
        $c = $contas[$conta['tipo']];
        $atualizou = $con->query(
            "UPDATE {$c['tabela']} SET {$c['senha']} = ?, Token_Senha = NULL, Expiracao_Token = NULL
             WHERE {$c['id']} = ? AND Token_Senha = ?",
            [senha_hash($senha), (int)$conta['id'], $token]
        );

        // Confirma no banco: lê a senha gravada e confere com a digitada
        $salva = $con->query("SELECT {$c['senha']} AS senha, Token_Senha FROM {$c['tabela']} WHERE {$c['id']} = ?", [(int)$conta['id']]);
        $linha = $salva ? $salva->fetch_assoc() : null;
        $confirmado = $atualizou && $atualizou->num_rows > 0 && $linha
            && senha_confere($senha, $linha['senha']) && $linha['Token_Senha'] === null;

        if ($confirmado) {
            $estado = 'sucesso';
        } else {
            error_log('[Acesso+] Falha ao gravar nova senha (' . $conta['tipo'] . ' ' . $conta['id'] . '): ' . $con->error);
            $erros = [];
            $estado = 'falha';
        }
    }
}

$paginaLogin = $conta ? $contas[$conta['tipo']]['login'] : 'escolhaLogin.html';
$olho = '<svg class="olho-aberto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>'
      . '<svg class="olho-fechado" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2M6.6 6.6A17.4 17.4 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Senha | Acesso+</title>
    <link rel="stylesheet" href="css/senha.css">
</head>
<body class="pagina-senha">
    <main class="senha-card">
        <?php include 'inc/ladoSenha.php'; ?>

        <section class="senha-conteudo">
            <div class="senha-topo">
                <a href="index.html"><img src="img/logo.png" alt="Acesso+ - página inicial"></a>
                <a href="<?php echo $paginaLogin; ?>" class="senha-voltar">&larr; Voltar para o login</a>
            </div>

            <div class="senha-corpo">
            <?php if ($estado === 'sucesso'): ?>
                <div class="senha-icone sucesso" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                </div>
                <h1 role="status">Senha alterada!</h1>
                <p class="senha-sub">Sua nova senha foi salva com segurança. Use-a a partir de agora para entrar no Acesso+.</p>
                <div class="acoes-senha">
                    <a href="<?php echo $paginaLogin; ?>" class="btn-senha">Ir para o login</a>
                </div>

            <?php elseif ($estado === 'falha'): ?>
                <div class="senha-icone erro" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                </div>
                <h1 role="alert">Não foi possível salvar</h1>
                <p class="senha-sub">A senha não foi alterada por um problema no servidor. Peça um novo link e tente de novo.</p>
                <div class="acoes-senha">
                    <a href="esqueciSenha.html" class="btn-senha">Pedir novo link</a>
                </div>

            <?php elseif ($estado === 'invalido'): ?>
                <div class="senha-icone erro" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </div>
                <h1>Link inválido ou expirado</h1>
                <p class="senha-sub">Este link de recuperação já foi usado ou passou de 1 hora. Peça um novo para continuar.</p>
                <div class="acoes-senha">
                    <a href="esqueciSenha.html" class="btn-senha">Pedir novo link</a>
                    <a href="escolhaLogin.html" class="btn-secundario">Voltar para o login</a>
                </div>

            <?php else: ?>
                <div class="senha-icone" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                </div>
                <h1>Criar nova senha</h1>
                <p class="senha-sub">Conta: <strong><?php echo htmlspecialchars(email_mascarado((string)$conta['email'])); ?></strong>. Escolha uma senha forte que você não use em outros sites.</p>

                <?php if ($erros): ?>
                    <div class="aviso-senha erro" role="alert">
                        A senha precisa:
                        <ul><?php foreach ($erros as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form action="redefinirSenha.php" method="post" class="senha-form" id="formSenha" novalidate>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="text" name="username" value="<?php echo htmlspecialchars((string)$conta['email']); ?>" autocomplete="username" hidden>

                    <div class="campo">
                        <label for="senha">Nova senha</label>
                        <div class="campo-senha">
                            <input type="password" name="senha" id="senha" placeholder="Digite a nova senha" autocomplete="new-password" required aria-describedby="requisitos">
                            <button type="button" class="btn-olho" aria-label="Mostrar senha" aria-pressed="false" data-alvo="senha"><?php echo $olho; ?></button>
                        </div>
                        <div class="forca" id="forca" data-nivel="0">
                            <div class="forca-barras" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                            <span class="forca-texto" id="forcaTexto" aria-live="polite"></span>
                        </div>
                    </div>

                    <ul class="requisitos" id="requisitos" aria-label="Requisitos da senha">
                        <li data-regra="tamanho">8 caracteres ou mais</li>
                        <li data-regra="maiuscula">Uma letra maiúscula</li>
                        <li data-regra="minuscula">Uma letra minúscula</li>
                        <li data-regra="numero">Um número</li>
                        <li data-regra="simbolo" class="largo">Um símbolo (ex.: ! @ # $ . *)</li>
                    </ul>

                    <div class="campo">
                        <label for="confirma_senha">Confirme a nova senha</label>
                        <div class="campo-senha">
                            <input type="password" name="confirma_senha" id="confirma_senha" placeholder="Repita a nova senha" autocomplete="new-password" required aria-describedby="dicaConfirma">
                            <button type="button" class="btn-olho" aria-label="Mostrar senha" aria-pressed="false" data-alvo="confirma_senha"><?php echo $olho; ?></button>
                        </div>
                        <span class="dica-campo" id="dicaConfirma" aria-live="polite"></span>
                    </div>

                    <button type="submit" class="btn-senha" id="btnSalvar" disabled>Salvar nova senha</button>
                </form>
            <?php endif; ?>
            </div>
        </section>
    </main>

    <?php if ($estado === 'formulario'): ?>
    <script>
        const senha = document.getElementById('senha');
        const confirma = document.getElementById('confirma_senha');
        const btnSalvar = document.getElementById('btnSalvar');
        const forca = document.getElementById('forca');
        const forcaTexto = document.getElementById('forcaTexto');
        const dicaConfirma = document.getElementById('dicaConfirma');
        const regras = {
            tamanho: v => v.length >= 8,
            maiuscula: v => /[A-Z]/.test(v),
            minuscula: v => /[a-z]/.test(v),
            numero: v => /[0-9]/.test(v),
            simbolo: v => /[^A-Za-z0-9]/.test(v),
        };
        const nomesForca = ['', 'Fraca', 'Razoável', 'Boa', 'Forte'];

        function validar() {
            const v = senha.value;
            let cumpridas = 0;
            for (const [nome, teste] of Object.entries(regras)) {
                const ok = teste(v);
                if (ok) cumpridas++;
                document.querySelector(`[data-regra="${nome}"]`).classList.toggle('ok', ok);
            }
            // Força: requisitos cumpridos + bônus por tamanho
            let nivel = 0;
            if (v.length > 0) nivel = cumpridas <= 2 ? 1 : cumpridas <= 4 ? 2 : (v.length >= 12 ? 4 : 3);
            forca.dataset.nivel = nivel;
            forcaTexto.textContent = nomesForca[nivel];

            const senhaOk = cumpridas === 5;
            const iguais = confirma.value !== '' && confirma.value === v;
            if (confirma.value === '') {
                dicaConfirma.textContent = '';
                dicaConfirma.className = 'dica-campo';
            } else {
                dicaConfirma.textContent = iguais ? 'As senhas são iguais.' : 'As senhas ainda não são iguais.';
                dicaConfirma.className = 'dica-campo ' + (iguais ? 'ok' : 'erro');
            }
            confirma.classList.toggle('invalido', confirma.value !== '' && !iguais);
            btnSalvar.disabled = !(senhaOk && iguais);
        }

        senha.addEventListener('input', validar);
        confirma.addEventListener('input', validar);

        document.querySelectorAll('.btn-olho').forEach(botao => {
            botao.addEventListener('click', () => {
                const campo = document.getElementById(botao.dataset.alvo);
                const mostrar = campo.type === 'password';
                campo.type = mostrar ? 'text' : 'password';
                botao.setAttribute('aria-pressed', mostrar);
                botao.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
            });
        });

        document.getElementById('formSenha').addEventListener('submit', () => {
            btnSalvar.disabled = true;
            btnSalvar.textContent = 'Salvando...';
        });
        senha.focus();
    </script>
    <?php endif; ?>
</body>
</html>
