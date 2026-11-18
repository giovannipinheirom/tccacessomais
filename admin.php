<?php
/*
 * Painel do Administrador do Acesso+.
 * Login: usuário e senha definidos nas variáveis de ambiente ADMIN_USUARIO e ADMIN_SENHA (aba "Variables" do Railway).
 * Funções: resumo do sistema, pacientes (situação do pagamento), profissionais, planos (preços) e mensagens do Fale Conosco.
 */
session_start();
include 'conexao.php';
garantir_estrutura($con);

$adminUsuario = getenv('ADMIN_USUARIO') ?: '';
$adminSenha   = getenv('ADMIN_SENHA') ?: '';
$adminConfigurado = $adminUsuario !== '' && $adminSenha !== '';

if (isset($_GET['sair'])) {
    unset($_SESSION['admin'], $_SESSION['csrf_admin']);
    header('Location: admin.php');
    exit();
}

$erroLogin = '';
if (!isset($_SESSION['admin']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_admin'])) {
    if ($adminConfigurado
        && hash_equals($adminUsuario, (string)($_POST['usuario'] ?? ''))
        && hash_equals($adminSenha, (string)($_POST['senha'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        header('Location: admin.php');
        exit();
    }
    $erroLogin = $adminConfigurado ? 'Usuário ou senha inválidos.' : 'O acesso do administrador ainda não foi configurado no servidor.';
}

$logado = !empty($_SESSION['admin']);
$aviso = '';

// ----- Ações do painel (protegidas contra envio de outro site com token CSRF) -----
if ($logado && $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf_admin'] ?? '', $_POST['csrf'] ?? '')) {
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($acao === 'pagamento' && $id > 0) {
        $status = ($_POST['status'] ?? '') === 'Ativo' ? 'Ativo' : 'Pendente';
        $con->query("UPDATE Cadastro_Users SET Status_Pagamento = ? WHERE Id_User = ?", [$status, $id]);
        $aviso = 'Situação do pagamento atualizada.';
    } elseif ($acao === 'excluir_profissional' && $id > 0) {
        $con->query("DELETE FROM Cadastro_Profissionais WHERE Id_Profissional = ?", [$id]);
        $aviso = 'Profissional removido da rede credenciada.';
    } elseif ($acao === 'preco_plano' && $id > 0) {
        $preco = (float)str_replace(',', '.', $_POST['preco'] ?? '0');
        if ($preco > 0) {
            $con->query("UPDATE Planos SET Preco = ? WHERE Id_Plano = ?", [number_format($preco, 2, '.', ''), $id]);
            $aviso = 'Preço do plano atualizado.';
        }
    } elseif ($acao === 'mensagem_lida' && $id > 0) {
        $con->query("UPDATE Mensagens_Contato SET Lida = TRUE WHERE Id_Mensagem = ?", [$id]);
        $aviso = 'Mensagem marcada como lida.';
    }
    $_SESSION['aviso_admin'] = $aviso;
    header('Location: admin.php?aba=' . urlencode($_POST['aba'] ?? 'resumo'));
    exit();
}
if (isset($_SESSION['aviso_admin'])) { $aviso = $_SESSION['aviso_admin']; unset($_SESSION['aviso_admin']); }

function linhas($r) { $out = []; while ($r && ($l = $r->fetch_assoc())) $out[] = $l; return $out; }
function h($t) { return htmlspecialchars((string)$t); }

$aba = $_GET['aba'] ?? 'resumo';
$csrf = $_SESSION['csrf_admin'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrador | Acesso+</title>
    <link rel="stylesheet" href="css/edit.css">
    <link rel="stylesheet" href="css/paginas.css">
    <style>
        .abas { display: flex; gap: 8px; flex-wrap: wrap; margin: 20px 0; }
        .abas a { padding: 8px 16px; border-radius: 999px; background: #eef2ff; color: #0f259e; text-decoration: none; font-weight: 600; }
        .abas a.ativa { background: #2668D7; color: #fff; }
        .numeros { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; }
        .numero { background: #f1f5ff; border: 1px solid #dbe4ff; border-radius: 12px; padding: 16px; }
        .numero strong { display: block; font-size: 2rem; color: #0f259e; }
        table.lista { width: 100%; border-collapse: collapse; font-size: .95rem; }
        table.lista th, table.lista td { border-bottom: 1px solid #e2e8f0; padding: 10px 8px; text-align: left; vertical-align: top; }
        table.lista th { background: #f8fafc; color: #334155; }
        .tabela-rolagem { overflow-x: auto; }
        .tag { padding: 2px 10px; border-radius: 999px; font-size: .85rem; font-weight: 600; }
        .tag.ativo { background: #dcfce7; color: #166534; } .tag.pendente { background: #fef9c3; color: #854d0e; }
        .inline { display: inline-flex; gap: 6px; align-items: center; }
        .inline input { width: 110px; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .btn-peq { background: #2668D7; color: #fff; border: 0; border-radius: 8px; padding: 6px 12px; cursor: pointer; font-weight: 600; }
        .btn-peq.perigo { background: #b91c1c; }
    </style>
</head>
<body>
    <nav>
        <div class="img-logo"><a href="index.html"><img src="img/logo.png" alt="Logo Acesso+ — voltar ao início" class="logo-img"></a></div>
        <div class="links-nav"><a href="admin.php" class="link-ativo">Painel do Administrador</a></div>
        <?php if ($logado): ?><a class="btn-nav" href="admin.php?sair=1" style="text-decoration:none">Sair</a><?php endif; ?>
    </nav>

    <main class="pagina" style="max-width: 1100px">
    <?php if (!$logado): ?>
        <h1>Acesso do Administrador</h1>
        <p class="subtitulo">Área restrita à gestão interna do sistema.</p>
        <?php if ($erroLogin): ?><div class="aviso erro" role="alert"><?php echo h($erroLogin); ?></div><?php endif; ?>
        <?php if (!$adminConfigurado): ?>
            <div class="aviso erro">Configure as variáveis ADMIN_USUARIO e ADMIN_SENHA no servidor para habilitar este acesso.</div>
        <?php endif; ?>
        <form class="form-contato" method="post" action="admin.php" style="max-width: 420px">
            <div><label for="usuario">Usuário</label><input id="usuario" name="usuario" required autocomplete="username"></div>
            <div><label for="senha">Senha</label><input id="senha" name="senha" type="password" required autocomplete="current-password"></div>
            <button type="submit" name="login_admin" value="1">Entrar</button>
        </form>
    <?php else: ?>
        <h1>Painel do Administrador</h1>
        <div class="abas">
            <?php foreach (['resumo' => 'Resumo', 'pacientes' => 'Pacientes', 'profissionais' => 'Profissionais', 'planos' => 'Planos', 'mensagens' => 'Mensagens'] as $k => $rotulo): ?>
                <a href="admin.php?aba=<?php echo $k; ?>" class="<?php echo $aba === $k ? 'ativa' : ''; ?>"><?php echo $rotulo; ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($aviso): ?><div class="aviso ok" role="status"><?php echo h($aviso); ?></div><?php endif; ?>

        <?php if ($aba === 'resumo'):
            $c = fn($sql) => (int)($con->query($sql)->fetch_assoc()['total'] ?? 0);
            $numeros = [
                'Pacientes' => $c("SELECT COUNT(*) AS total FROM Cadastro_Users"),
                'Pagamentos ativos' => $c("SELECT COUNT(*) AS total FROM Cadastro_Users WHERE Status_Pagamento = 'Ativo'"),
                'Profissionais' => $c("SELECT COUNT(*) AS total FROM Cadastro_Profissionais"),
                'Consultas agendadas' => $c("SELECT COUNT(*) AS total FROM Consultas WHERE Status_Consulta IN ('Pendente', 'Confirmado') AND Id_User IS NOT NULL"),
                'Consultas realizadas' => $c("SELECT COUNT(*) AS total FROM Consultas WHERE Status_Consulta = 'Realizada'"),
                'Mensagens não lidas' => $c("SELECT COUNT(*) AS total FROM Mensagens_Contato WHERE Lida = FALSE"),
            ];
            $porPlano = linhas($con->query("SELECT p.Nome_Plano, COUNT(u.Id_User) AS total FROM Planos p LEFT JOIN Cadastro_Users u ON u.Id_Plano = p.Id_Plano GROUP BY p.Id_Plano, p.Nome_Plano ORDER BY p.Id_Plano"));
        ?>
            <div class="numeros">
                <?php foreach ($numeros as $rotulo => $valor): ?><div class="numero"><strong><?php echo $valor; ?></strong><?php echo $rotulo; ?></div><?php endforeach; ?>
            </div>
            <h2>Pacientes por plano</h2>
            <ul><?php foreach ($porPlano as $p): ?><li><?php echo h($p['Nome_Plano']); ?>: <?php echo (int)$p['total']; ?></li><?php endforeach; ?></ul>

        <?php elseif ($aba === 'pacientes'):
            $pacientes = linhas($con->query("SELECT u.Id_User, u.Nome_User, u.CPF_User, u.Email_User, u.Cidade_User, u.Status_Pagamento, p.Nome_Plano FROM Cadastro_Users u LEFT JOIN Planos p ON p.Id_Plano = u.Id_Plano ORDER BY u.Nome_User"));
        ?>
            <div class="tabela-rolagem"><table class="lista">
                <tr><th>Nome</th><th>CPF</th><th>E-mail</th><th>Cidade</th><th>Plano</th><th>Pagamento</th><th>Ação</th></tr>
                <?php foreach ($pacientes as $u): $ativo = $u['Status_Pagamento'] === 'Ativo'; ?>
                <tr>
                    <td><?php echo h($u['Nome_User']); ?></td><td><?php echo h($u['CPF_User']); ?></td><td><?php echo h($u['Email_User']); ?></td>
                    <td><?php echo h($u['Cidade_User']); ?></td><td><?php echo h($u['Nome_Plano'] ?? '-'); ?></td>
                    <td><span class="tag <?php echo $ativo ? 'ativo' : 'pendente'; ?>"><?php echo h($u['Status_Pagamento']); ?></span></td>
                    <td><form method="post" class="inline">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="aba" value="pacientes">
                        <input type="hidden" name="acao" value="pagamento"><input type="hidden" name="id" value="<?php echo (int)$u['Id_User']; ?>">
                        <input type="hidden" name="status" value="<?php echo $ativo ? 'Pendente' : 'Ativo'; ?>">
                        <button class="btn-peq"><?php echo $ativo ? 'Marcar pendente' : 'Confirmar pagamento'; ?></button>
                    </form></td>
                </tr>
                <?php endforeach; if (!$pacientes): ?><tr><td colspan="7">Nenhum paciente cadastrado.</td></tr><?php endif; ?>
            </table></div>

        <?php elseif ($aba === 'profissionais'):
            $profs = linhas($con->query("SELECT Id_Profissional, Nome_Profissional, Especialidade_Profissional, Registro_Profissional, UF_Registro_Profissional, Cidade_Profissional, Email_Profissional FROM Cadastro_Profissionais ORDER BY Nome_Profissional"));
        ?>
            <div class="tabela-rolagem"><table class="lista">
                <tr><th>Nome</th><th>Especialidade</th><th>Registro</th><th>Cidade</th><th>E-mail</th><th>Ação</th></tr>
                <?php foreach ($profs as $p): ?>
                <tr>
                    <td><?php echo h($p['Nome_Profissional']); ?></td><td><?php echo h($p['Especialidade_Profissional']); ?></td>
                    <td><?php echo h($p['Registro_Profissional'] . '/' . $p['UF_Registro_Profissional']); ?></td>
                    <td><?php echo h($p['Cidade_Profissional']); ?></td><td><?php echo h($p['Email_Profissional']); ?></td>
                    <td><form method="post" onsubmit="return confirm('Remover este profissional e todas as consultas dele?');">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="aba" value="profissionais">
                        <input type="hidden" name="acao" value="excluir_profissional"><input type="hidden" name="id" value="<?php echo (int)$p['Id_Profissional']; ?>">
                        <button class="btn-peq perigo">Remover</button>
                    </form></td>
                </tr>
                <?php endforeach; if (!$profs): ?><tr><td colspan="6">Nenhum profissional cadastrado.</td></tr><?php endif; ?>
            </table></div>

        <?php elseif ($aba === 'planos'):
            $planos = linhas($con->query("SELECT Id_Plano, Nome_Plano, Preco FROM Planos ORDER BY Id_Plano"));
        ?>
            <table class="lista">
                <tr><th>Plano</th><th>Preço mensal (R$)</th></tr>
                <?php foreach ($planos as $p): ?>
                <tr><td><?php echo h($p['Nome_Plano']); ?></td>
                    <td><form method="post" class="inline">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="aba" value="planos">
                        <input type="hidden" name="acao" value="preco_plano"><input type="hidden" name="id" value="<?php echo (int)$p['Id_Plano']; ?>">
                        <input name="preco" value="<?php echo h(number_format((float)$p['Preco'], 2, ',', '')); ?>" aria-label="Preço do plano <?php echo h($p['Nome_Plano']); ?>">
                        <button class="btn-peq">Salvar</button>
                    </form></td></tr>
                <?php endforeach; ?>
            </table>

        <?php elseif ($aba === 'mensagens'):
            $msgs = linhas($con->query("SELECT * FROM Mensagens_Contato ORDER BY Lida ASC, Data_Envio DESC"));
        ?>
            <div class="tabela-rolagem"><table class="lista">
                <tr><th>Data</th><th>Nome / e-mail</th><th>Assunto</th><th>Mensagem</th><th>Ação</th></tr>
                <?php foreach ($msgs as $m): $lida = in_array($m['Lida'], [true, 't', 1, '1'], true); ?>
                <tr style="<?php echo $lida ? 'color:#64748b' : 'font-weight:500'; ?>">
                    <td><?php echo h(date('d/m/Y H:i', strtotime($m['Data_Envio']))); ?></td>
                    <td><?php echo h($m['Nome_Contato']); ?><br><a href="mailto:<?php echo h($m['Email_Contato']); ?>"><?php echo h($m['Email_Contato']); ?></a></td>
                    <td><?php echo h($m['Assunto_Contato']); ?></td><td><?php echo nl2br(h($m['Mensagem_Contato'])); ?></td>
                    <td><?php if (!$lida): ?><form method="post">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="aba" value="mensagens">
                        <input type="hidden" name="acao" value="mensagem_lida"><input type="hidden" name="id" value="<?php echo (int)$m['Id_Mensagem']; ?>">
                        <button class="btn-peq">Marcar como lida</button>
                    </form><?php else: ?>Lida<?php endif; ?></td>
                </tr>
                <?php endforeach; if (!$msgs): ?><tr><td colspan="5">Nenhuma mensagem recebida.</td></tr><?php endif; ?>
            </table></div>
        <?php endif; ?>
    <?php endif; ?>
    </main>
</body>
</html>
